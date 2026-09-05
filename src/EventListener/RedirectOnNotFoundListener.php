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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Laeuft eine Frontend-Anfrage ins Leere, wird der Pfad als alter Alias nachgeschlagen: 301 auf die Seite.
 *
 * Zwei Wege, weil Contao 5 unbekannte Pfade auf zwei Arten beantwortet:
 *  - Hat der Seitenbaum eine veroeffentlichte 404-Seite, matcht der Route404Provider den Pfad DIREKT auf
 *    deren Catch-all-Route (tl_page.<id>.error_404) — es fliegt keine Exception. Dafuer kernel.request
 *    (Prioritaet 16: nach dem Router bei 32, vor dem Controller).
 *  - Ohne 404-Seite wirft Contao PageNotFoundException/NotFoundHttpException. Dafuer kernel.exception
 *    (Prioritaet 100: vor ExceptionConverter 96 und PrettyErrorScreen).
 * Ein echter Alias gewinnt immer — hier landen nur Anfragen, fuer die Contao KEINE Seite gefunden hat.
 */
#[AsEventListener(event: 'kernel.request', method: 'onRequest', priority: 16)]
#[AsEventListener(event: 'kernel.exception', method: 'onException', priority: 100)]
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

    /** Contao hat den Pfad auf die 404-Seite geroutet (Route404Provider), noch bevor ein Controller laeuft. */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_ends_with((string) $request->attributes->get('_route', ''), '.error_404')) {
            return;
        }
        if (null !== ($ziel = $this->weiterleitung($request))) {
            $event->setResponse($ziel);
        }
    }

    /** Kein 404-Seitentyp im Baum: Contao wirft die Not-Found-Exception. */
    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $e = $event->getThrowable();
        if (!$e instanceof PageNotFoundException && !$e instanceof NotFoundHttpException) {
            return;
        }
        if (null !== ($ziel = $this->weiterleitung($event->getRequest()))) {
            $event->setResponse($ziel);
        }
    }

    /** Pfad als alten Alias nachschlagen; 301 auf die Seite oder null. */
    private function weiterleitung(Request $request): ?RedirectResponse
    {
        if ($this->scope->isBackendRequest($request)) {
            return null;
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
            return null;
        }

        $this->framework->initialize();
        $seite = $this->framework->getAdapter(PageModel::class)->findPublishedById($treffer);
        if (null === $seite) {
            return null;
        }
        // Sprache aus dem Pfadpraefix (/de/alter-alias) mitnehmen: die 404-Route traegt sie nicht, und
        // mehrsprachige URL-Generatoren (z. B. i18nl10n) lesen die Zielsprache aus dem Request.
        if (null !== ($sprache = $this->praefixSprache($request->getPathInfo()))) {
            $request->attributes->set('_locale', $sprache);
        }
        try {
            $ziel = $this->urls->generate($seite, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return null;
        }
        if ('' !== $request->getQueryString()) {
            $ziel .= (str_contains($ziel, '?') ? '&' : '?').$request->getQueryString();
        }
        // Nie auf sich selbst — sonst Schleife.
        if (parse_url($ziel, PHP_URL_PATH) === $request->getPathInfo()) {
            return null;
        }

        return new RedirectResponse($ziel, 301);
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

    /** Sprachpraefix des Pfads (/de/…, /pt-BR/…) oder null. */
    private function praefixSprache(string $pfad): ?string
    {
        $teile = explode('/', trim(rawurldecode($pfad), '/'));

        return \count($teile) > 1 && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $teile[0]) ? $teile[0] : null;
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
