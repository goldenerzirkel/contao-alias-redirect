<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Laeuft eine Frontend-Anfrage ins Leere, wird der Pfad als alter Alias nachgeschlagen: 301 auf die Seite.
 *
 * Vor Contaos eigener 404-Behandlung (ExceptionConverter 96, PrettyErrorScreen dahinter). Ein echter
 * Alias gewinnt immer — hier landen nur Anfragen, fuer die Contao KEINE Seite gefunden hat.
 */
#[AsEventListener(event: 'kernel.exception', priority: 100)]
final class RedirectOnNotFoundListener
{
    public function __construct(
        private readonly AliasRedirects $redirects,
        private readonly ScopeMatcher $scope,
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $urls,
        private readonly Connection $db,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $e = $event->getThrowable();
        if (!$e instanceof PageNotFoundException && !$e instanceof NotFoundHttpException) {
            return;
        }
        $request = $event->getRequest();
        if ($this->scope->isBackendRequest($request)) {
            return;
        }

        $roots = $this->wurzelnFuer($request->getHost());
        $treffer = null;
        foreach ($this->kandidaten($request->getPathInfo()) as $alias) {
            $treffer = $this->redirects->finde($alias, $roots) ?? $this->redirects->finde($alias);
            if (null !== $treffer) {
                break;
            }
        }
        if (null === $treffer) {
            return;
        }

        $this->framework->initialize();
        $seite = $this->framework->getAdapter(PageModel::class)->findPublishedById($treffer);
        if (null === $seite) {
            return;
        }
        try {
            $ziel = $this->urls->generate($seite, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return;
        }
        if ('' !== $request->getQueryString()) {
            $ziel .= (str_contains($ziel, '?') ? '&' : '?').$request->getQueryString();
        }
        // Nie auf sich selbst — sonst Schleife.
        if (parse_url($ziel, PHP_URL_PATH) === $request->getPathInfo()) {
            return;
        }

        $event->setResponse(new RedirectResponse($ziel, 301));
    }

    /**
     * Aus dem Pfad die moeglichen Aliase: ohne Suffix (.html), mit und ohne Sprachpraefix (/de/…).
     *
     * @return list<string>
     */
    private function kandidaten(string $pfad): array
    {
        $p = trim($pfad, '/');
        if ('' === $p) {
            return [];
        }
        $p = rawurldecode($p);
        $ohneSuffix = preg_replace('/\.html?$/i', '', $p) ?? $p;
        $aus = [$ohneSuffix];
        $teile = explode('/', $ohneSuffix);
        if (\count($teile) > 1 && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $teile[0])) {
            $aus[] = implode('/', \array_slice($teile, 1));
        }

        return array_values(array_unique(array_filter($aus)));
    }

    /** @return list<int> Wurzeln, die zu diesem Host gehoeren (oder keinen Host festlegen) */
    private function wurzelnFuer(string $host): array
    {
        $zeilen = $this->db->fetchAllAssociative(
            "SELECT id FROM tl_page WHERE type = 'root' AND published = 1 AND (dns = ? OR dns = '') ORDER BY dns DESC",
            [$host],
        );

        return array_map(static fn ($z) => (int) $z['id'], $zeilen);
    }
}
