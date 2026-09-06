<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CalendarEventsModel;
use Contao\FaqModel;
use Contao\NewsModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
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
#[AsEventListener(event: 'kernel.request', method: 'onEarlyRequest', priority: 33)]
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
        private readonly AliasIndex $index,
    ) {
    }

    /**
     * VOR dem Router (RouterListener hat Prioritaet 32): ein alter Ordner-Alias wie
     * „leistungen/cms-systeme-pflege-contao-5" wird sonst nie 404 — Contao routet ihn auf die Elternseite
     * „leistungen" mit Parameter „/cms-systeme-pflege-contao-5" (200, falsche Seite). Gemessen 06.09.2026.
     * Mit dem Index kostet die Vorpruefung eine indizierte Abfrage je Anfrage; ohne Index bleibt es beim
     * 404-Weg. Eine veroeffentlichte Seite, die den Alias wirklich traegt, gewinnt weiterhin.
     */
    public function onEarlyRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->index->vorhanden()) {
            return;
        }
        $request = $event->getRequest();
        if ($this->scope->isBackendRequest($request) || str_starts_with($request->getPathInfo(), '/_') || str_starts_with($request->getPathInfo(), '/contao')) {
            return;
        }
        $kandidaten = $this->kandidaten($request->getPathInfo());
        if ([] === $kandidaten) {
            return;
        }
        $roots = $this->wurzelnFuer($request->getHost());
        $eintrag = null;
        foreach ($kandidaten as $alias) {
            $eintrag = $this->index->finde($alias, $roots, ['tl_page']);
            if (null !== $eintrag) {
                break;
            }
        }
        if (null === $eintrag) {
            return;
        }
        // Traegt eine veroeffentlichte Seite den Alias selbst? Dann ist es keine alte Adresse.
        foreach ($kandidaten as $alias) {
            $echte = $this->db->fetchOne('SELECT id FROM tl_page WHERE alias = ? AND published = 1 AND type <> ? LIMIT 1', [$alias, AliasIndex::TYP_GONE]);
            if (false !== $echte && null !== $echte) {
                return;
            }
        }
        if (null !== ($ziel = $this->weiterleitung($request))) {
            $event->setResponse($ziel);
        }
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
    private function weiterleitung(Request $request): ?Response
    {
        if ($this->scope->isBackendRequest($request)) {
            return null;
        }

        $roots = $this->wurzelnFuer($request->getHost());
        $treffer = null;
        $gone = false;
        foreach ($this->kandidaten($request->getPathInfo()) as $alias) {
            // Nur die Baeume dieses Hosts: ein Alias, der zu einer anderen Domain gewandert ist, bleibt hier 404.
            // Erst der Index (ein Datensatz je Alias), ohne Index die alte Suche ueber die Seiten.
            if ($this->index->vorhanden()) {
                $eintrag = $this->index->finde($alias, $roots, ['tl_page']);
                if (null !== $eintrag) {
                    $treffer = $eintrag['pid'];
                    $gone = $eintrag['gone'];
                    break;
                }
                continue;
            }
            $treffer = $this->redirects->finde($alias, $roots);
            if (null !== $treffer) {
                break;
            }
        }
        if (null === $treffer) {
            // Kein Seiten-Alias: das letzte Pfadstueck als Alias einer Nachricht, eines Termins oder einer FAQ
            // („/blog/alter-alias" — die Leseseite hat ihn nicht mehr gefunden und 404 geworfen).
            return $this->datensatzWeiterleitung($request, $roots);
        }

        $this->framework->initialize();
        $seite = $this->framework->getAdapter(PageModel::class)->findPublishedById($treffer);
        if (null === $seite) {
            return null;
        }
        // Seite vom Typ „Entfernt": kein Umweg ueber eine Weiterleitung, die Adresse selbst ist weg.
        if ($gone || AliasIndex::TYP_GONE === (string) $seite->type) {
            return $this->gone($seite);
        }
        // Sprache aus dem Pfadpraefix (/de/alter-alias) mitnehmen: die 404-Route traegt sie nicht, und
        // mehrsprachige URL-Generatoren (z. B. i18nl10n) lesen die Zielsprache aus dem Request.
        // Ohne Praefix die Sprache des Zielbaums: vor dem Router kennt der Request noch keine Sprache, und
        // ein mehrsprachiger URL-Generator (i18nl10n) nahm dann die Standardsprache („/en/…", 06.09.2026).
        $sprache = $this->praefixSprache($request->getPathInfo());
        if (null === $sprache) {
            $seite->loadDetails();
            $sprache = '' !== (string) $seite->rootLanguage ? (string) $seite->rootLanguage : null;
        }
        if (null !== $sprache) {
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
     * Alter Alias einer Nachricht oder eines Termins: 301 auf die heutige Adresse des Datensatzes.
     *
     * @param list<int> $roots
     */
    private function datensatzWeiterleitung(Request $request, array $roots): ?Response
    {
        if (!$this->index->vorhanden()) {
            return null;
        }
        $teile = explode('/', trim(rawurldecode($request->getPathInfo()), '/'));
        $letztes = (string) preg_replace('/\.html?$/i', '', (string) end($teile));
        if ('' === $letztes || \count($teile) < 2) {
            return null;
        }
        $eintrag = $this->index->finde($letztes, $roots, ['tl_news', 'tl_calendar_events', 'tl_faq']);
        if (null === $eintrag) {
            return null;
        }
        $this->framework->initialize();
        $modell = match ($eintrag['quelle']) {
            'tl_news' => $this->framework->getAdapter(NewsModel::class)->findById($eintrag['pid']),
            'tl_calendar_events' => $this->framework->getAdapter(CalendarEventsModel::class)->findById($eintrag['pid']),
            default => $this->framework->getAdapter(FaqModel::class)->findById($eintrag['pid']),
        };
        if (null === $modell || !$modell->published) {
            return null;
        }
        if (null !== ($sprache = $this->praefixSprache($request->getPathInfo()))) {
            $request->attributes->set('_locale', $sprache);
        }
        try {
            $ziel = $this->urls->generate($modell, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return null;
        }
        if ('' !== $request->getQueryString()) {
            $ziel .= (str_contains($ziel, '?') ? '&' : '?').$request->getQueryString();
        }
        if (parse_url($ziel, PHP_URL_PATH) === $request->getPathInfo()) {
            return null;
        }

        return new RedirectResponse($ziel, 301);
    }

    /** 410 Gone mit Verweis auf die Seite selbst: sie hat Layout und Text („hier geht es weiter"). */
    private function gone(PageModel $seite): Response
    {
        try {
            $ziel = $this->urls->generate($seite, [], UrlGeneratorInterface::ABSOLUTE_URL);
            $antwort = new Response(sprintf('<!DOCTYPE html><html><head><meta charset="utf-8"><title>410</title><meta http-equiv="refresh" content="0;url=%s"></head><body></body></html>', htmlspecialchars($ziel, \ENT_QUOTES)), Response::HTTP_GONE);
        } catch (\Throwable) {
            $antwort = new Response('Gone', Response::HTTP_GONE);
        }
        $antwort->headers->set('Cache-Control', 'public, max-age=3600');

        return $antwort;
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
