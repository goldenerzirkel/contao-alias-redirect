<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;
use Gozi\AliasRedirectBundle\Service\ManualRedirects;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;

/**
 * Was beim Zuordnen einer aufgelaufenen Adresse wirklich passiert — ohne Oberflaeche.
 *
 * Der Regelweg haengt die Adresse als alten Alias an eine Seite (tl_page.gozi_redirects); die Darstellung
 * im Backend steht in NotFoundOperations. Getrennt, damit dieser Teil ohne Router, Token und
 * DataContainer aufrufbar und damit pruefbar bleibt.
 */
final class NotFoundCallbacks
{
    public function __construct(
        private readonly Connection $db,
        private readonly NotFoundLog $log,
        private readonly ManualRedirects $redirects,
        private readonly AliasRedirects $aliase,
        private readonly AliasIndex $index,
    ) {
    }

    /**
     * Die Adresse als Alias-Weiterleitung an die gewaehlte Seite haengen.
     *
     * Geschrieben wird in tl_page.gozi_redirects — dieselbe Liste, die beim Umbenennen von selbst
     * waechst. Damit steht die Weiterleitung dort, wo der Redakteur sie sucht: an der Seite.
     *
     * Drei Gruende, aus denen das nicht geht, und die deshalb als Fehler am Feld erscheinen:
     *  - die Seite liegt in einem Baum, der zu dieser Domain nicht gehoert (Aliase gelten je Wurzel),
     *  - die Adresse taugt nicht als Alias (Leerzeichen, Klammern, Prozentzeichen — typisch bei Scannern),
     *  - die Adresse ist der echte Alias der Seite (dann ist der 404 ein anderes Problem).
     */
    #[AsCallback(table: 'tl_gozi_404', target: 'fields.zielSeite.save')]
    public function speichereZielSeite(mixed $wert, DataContainer $dc): int
    {
        return $this->hefteAnSeite((int) $dc->id, (int) $wert);
    }

    /**
     * Der Kern des Callbacks, ohne DataContainer — so ist er aus der Testsuite heraus aufrufbar.
     *
     * @throws \InvalidArgumentException mit der Meldung, die im Backend am Feld erscheint
     */
    public function hefteAnSeite(int $logId, int $seitenId): int
    {
        if ($seitenId < 1) {
            return 0;
        }
        $satz = $this->db->fetchAssociative('SELECT * FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$logId]);
        if (false === $satz) {
            return 0;
        }
        $seite = $this->db->fetchAssociative('SELECT id, alias, gozi_redirects FROM tl_page WHERE id = ?', [$seitenId]);
        if (false === $seite) {
            throw new \InvalidArgumentException($this->trans('zielFehltSeite'));
        }

        $wurzel = $this->aliase->rootId($seitenId);
        $host = (string) $satz['host'];
        if ('' !== $host && $wurzel > 0) {
            $dns = (string) $this->db->fetchOne('SELECT dns FROM tl_page WHERE id = ?', [$wurzel]);
            if ('' !== $dns && $dns !== $host) {
                throw new \InvalidArgumentException(sprintf($this->trans('zielFremderHost'), $dns, $host));
            }
        }

        // Fremdsprachige Adresse: sie gehoert an die UEBERSETZUNG, nicht an die Seite. Sonst gaelte der
        // alte Alias in allen Sprachen — und in einer anderen Sprache kann er eine andere Seite meinen.
        if (null !== ($sprache = $this->spracheAus((string) $satz['pfad'], $wurzel))) {
            return $this->hefteAnUebersetzung($satz, $seitenId, $sprache);
        }

        $alias = $this->aliasAus((string) $satz['pfad'], $wurzel);
        if (1 !== preg_match('(^(?!/)[\w/.-]+(?<!/)$)u', $alias)) {
            throw new \InvalidArgumentException(sprintf($this->trans('zielKeinAlias'), $alias));
        }
        if ($alias === trim((string) $seite['alias'], '/')) {
            throw new \InvalidArgumentException($this->trans('zielIstAlias'));
        }

        $liste = $this->aliase->bereinige($seite['gozi_redirects'], (string) $seite['alias']);
        if (!\in_array($alias, $liste, true)) {
            $liste[] = $alias;
        }
        $this->db->update('tl_page', ['gozi_redirects' => serialize($liste), 'tstamp' => time()], ['id' => $seitenId]);
        // Der Index ist abgeleitet und muss im selben Moment nachziehen, sonst greift die Weiterleitung
        // erst nach dem naechsten Speichern der Seite.
        $this->index->neu('tl_page', $seitenId);
        // Wer vorher „beliebige Adresse" gewaehlt hatte, hat einen Eintrag in der eigenen Tabelle
        // erzeugt. Der gilt VOR den Alias-Weiterleitungen und wuerde die neue Zuordnung uebergehen.
        $this->db->delete(ManualRedirects::TABELLE, ['aus404' => (int) $satz['id']]);
        $this->log->erledige((int) $satz['id']);

        return $seitenId;
    }

    /**
     * Die Adresse an die Uebersetzung dieser Sprache haengen.
     *
     * @param array<string,mixed> $satz Zeile aus dem 404-Protokoll
     *
     * @throws \InvalidArgumentException wenn es die Uebersetzung nicht gibt oder die Adresse kein Alias sein kann
     */
    private function hefteAnUebersetzung(array $satz, int $seitenId, string $sprache): int
    {
        $uebersetzung = $this->db->fetchAssociative(
            'SELECT id, alias, '.AliasRedirects::FELD.' AS liste FROM '.AliasIndex::UEBERSETZUNG.' WHERE pid = ? AND language = ?',
            [$seitenId, $sprache],
        );
        if (false === $uebersetzung) {
            throw new \InvalidArgumentException(sprintf($this->trans('zielOhneUebersetzung'), $sprache));
        }

        $alias = $this->ohnePraefix((string) $satz['pfad'], $sprache);
        if (1 !== preg_match('(^(?!/)[\w/.-]+(?<!/)$)u', $alias)) {
            throw new \InvalidArgumentException(sprintf($this->trans('zielKeinAlias'), $alias));
        }
        if ($alias === trim((string) $uebersetzung['alias'], '/')) {
            throw new \InvalidArgumentException($this->trans('zielIstAlias'));
        }

        $liste = $this->aliase->bereinige($uebersetzung['liste'], (string) $uebersetzung['alias']);
        if (!\in_array($alias, $liste, true)) {
            $liste[] = $alias;
        }
        $this->db->update(AliasIndex::UEBERSETZUNG, [AliasRedirects::FELD => serialize($liste), 'tstamp' => time()], ['id' => (int) $uebersetzung['id']]);
        $this->index->neu(AliasIndex::UEBERSETZUNG, (int) $uebersetzung['id']);
        $this->db->delete(ManualRedirects::TABELLE, ['aus404' => (int) $satz['id']]);
        $this->log->erledige((int) $satz['id']);

        return $seitenId;
    }

    /**
     * Traegt der Pfad ein Sprachkuerzel, zu dem es in DIESEM Baum Uebersetzungen gibt?
     *
     * Drei Faelle, die keine Uebersetzung sind:
     *  - kein Sprachkuerzel: nicht jedes zweibuchstabige erste Segment ist eine Sprache
     *    („de-luxe-reisen" waere keine),
     *  - das Praefix der Wurzel selbst (urlPrefix),
     *  - die SPRACHE DER WURZEL. Sie lebt in tl_page, nicht in der Uebersetzungstabelle. Sechs der
     *    sieben Wurzeln dieser Installation sind deutsch, waehrend „de" im PONS-Baum (Wurzelsprache
     *    „en") eine echte Uebersetzung mit 687 Zeilen ist — ohne diese Unterscheidung landete auf
     *    Langenscheidt und Klett jede „/de/…"-Adresse in der Meldung „keine Uebersetzung in de",
     *    obwohl sie schlicht an die Seite gehoert (gemessen 13.09.2026).
     *
     * Die Wurzel entscheidet also mit, nicht nur das Pfadsegment.
     */
    private function spracheAus(string $pfad, int $wurzel): ?string
    {
        $teile = explode('/', trim($pfad, '/'));
        if (\count($teile) < 2 || 1 !== preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $teile[0])) {
            return null;
        }
        if ($wurzel < 1) {
            return null;
        }
        $stamm = $this->db->fetchAssociative('SELECT language, urlPrefix FROM tl_page WHERE id = ?', [$wurzel]);
        if (false === $stamm) {
            return null;
        }
        if ($teile[0] === trim((string) $stamm['urlPrefix'], '/') || $teile[0] === (string) $stamm['language']) {
            return null;
        }
        // Fuehrt die Installation diese Sprache ueberhaupt als Uebersetzung? Ob die GEWAEHLTE SEITE eine
        // hat, entscheidet danach hefteAnUebersetzung — mit einer Meldung, die den Redakteur weiterbringt.
        try {
            $gibtEs = $this->db->fetchOne('SELECT id FROM '.AliasIndex::UEBERSETZUNG.' WHERE language = ? LIMIT 1', [$teile[0]]);
        } catch (\Throwable) {
            return null; // ohne gozi-i18nl10n gibt es die Tabelle nicht
        }

        return false !== $gibtEs && null !== $gibtEs ? $teile[0] : null;
    }

    /** Pfad ohne das fuehrende Sprachkuerzel — so steht der Alias in der Uebersetzung. */
    private function ohnePraefix(string $pfad, string $sprache): string
    {
        $p = trim($pfad, '/');

        return str_starts_with($p.'/', $sprache.'/') ? ltrim(substr($p, \strlen($sprache)), '/') : $p;
    }

    /**
     * Aus dem aufgelaufenen Pfad den Alias: Sprachpraefix der Wurzel weg, fuehrendes Sprachkuerzel weg.
     *
     * „/de/unternehmen/alte-seite" bei urlPrefix „de" wird zu „unternehmen/alte-seite" — der Alias steht
     * in tl_page ohne Praefix, und der Listener setzt es beim Weiterleiten wieder davor.
     */
    private function aliasAus(string $pfad, int $wurzel): string
    {
        $p = trim($pfad, '/');
        if ($wurzel < 1) {
            return $p;
        }
        $stamm = $this->db->fetchAssociative('SELECT language, urlPrefix FROM tl_page WHERE id = ?', [$wurzel]);
        if (false === $stamm) {
            return $p;
        }
        // Zwei moegliche Praefixe: das der Wurzel (urlPrefix) und das der Wurzelsprache, das
        // gozi-i18nl10n auch fuer die Basissprache setzt — „/en/service-center/…" liefert im PONS-Baum
        // 200, obwohl urlPrefix leer ist (gemessen 13.09.2026). Beide gehoeren nicht in den Alias.
        foreach ([trim((string) $stamm['urlPrefix'], '/'), (string) $stamm['language']] as $praefix) {
            if ('' !== $praefix && str_starts_with($p.'/', $praefix.'/')) {
                return ltrim(substr($p, \strlen($praefix)), '/');
            }
        }

        return $p;
    }

    private function trans(string $schluessel): string
    {
        return $GLOBALS['TL_LANG']['tl_gozi_404'][$schluessel] ?? $schluessel;
    }

    /** Adresse normalisieren — beide Seiten muessen gleich schreiben, sonst greift die Weiterleitung nie. */
    #[AsCallback(table: 'tl_gozi_redirect', target: 'fields.pfad.save')]
    public function speicherePfad(mixed $wert): string
    {
        return $this->redirects->normalisiere((string) $wert);
    }

    /** Host: nur die Domain. Wer „https://de.pons.com/" eintraegt, meint „de.pons.com". */
    #[AsCallback(table: 'tl_gozi_redirect', target: 'fields.host.save')]
    public function speichereHost(mixed $wert): string
    {
        $h = trim((string) $wert);
        if ('' === $h) {
            return '';
        }
        if (str_contains($h, '://')) {
            $h = (string) parse_url($h, PHP_URL_HOST);
        }

        return mb_substr(rtrim($h, '/'), 0, 255);
    }

    /** Nach dem Speichern: der 404-Eintrag, aus dem die Weiterleitung entstand, ist erledigt. */
    #[AsCallback(table: 'tl_gozi_redirect', target: 'config.onsubmit')]
    public function markiereErledigt(DataContainer $dc): void
    {
        $id = (int) ($dc->id ?? 0);
        if ($id < 1) {
            return;
        }
        $satz = $this->db->fetchAssociative('SELECT aus404, aktiv FROM '.ManualRedirects::TABELLE.' WHERE id = ?', [$id]);
        if (false === $satz || (int) $satz['aus404'] < 1 || !$satz['aktiv']) {
            return;
        }
        $this->log->erledige((int) $satz['aus404'], $id);
    }

    /** Wird die Weiterleitung geloescht, taucht die Adresse wieder in der Arbeitsliste auf. */
    #[AsCallback(table: 'tl_gozi_redirect', target: 'config.ondelete')]
    public function beiLoeschen(DataContainer $dc): void
    {
        $id = (int) ($dc->id ?? 0);
        if ($id > 0) {
            $this->db->update(NotFoundLog::TABELLE, ['erledigt' => 0, 'redirect' => 0], ['redirect' => $id]);
        }
    }

    /**
     * Ziel „beliebige Adresse" oder „kein Ziel" — beides kann kein Alias sein.
     *
     * Ein Alias zeigt immer auf eine Seite DIESER Installation; eine fremde Adresse und ein bewusstes
     * 410 brauchen deshalb einen Eintrag in der eigenen Tabelle. Der Redakteur merkt davon nichts: er
     * bleibt in der 404-Maske, der Eintrag entsteht hier.
     */
    #[AsCallback(table: 'tl_gozi_404', target: 'config.onsubmit')]
    public function uebernehmeEigenesZiel(DataContainer $dc): void
    {
        $this->schreibeEigeneWeiterleitung((int) ($dc->id ?? 0));
    }

    /**
     * Der Kern davon, ohne DataContainer — aus der Testsuite heraus aufrufbar.
     *
     * @return int id der Weiterleitung (0 = keine angelegt)
     */
    public function schreibeEigeneWeiterleitung(int $logId): int
    {
        if ($logId < 1) {
            return 0;
        }
        $satz = $this->db->fetchAssociative('SELECT * FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$logId]);
        if (false === $satz) {
            return 0;
        }
        $typ = (string) ($satz['zielTyp'] ?? ManualRedirects::ZIEL_SEITE);
        if (ManualRedirects::ZIEL_SEITE === $typ) {
            return 0; // den Alias-Weg erledigt der save_callback am Seitenfeld
        }
        $ziel = trim((string) ($satz['zielUrl'] ?? ''));
        if (ManualRedirects::ZIEL_URL === $typ && '' === $ziel) {
            return 0; // beim Umschalten der Auswahl ist das Feld noch leer
        }

        $daten = [
            'tstamp' => time(),
            'aktiv' => 1,
            'pfad' => (string) $satz['pfad'],
            'host' => (string) $satz['host'],
            'zielTyp' => $typ,
            'zielSeite' => 0,
            'zielUrl' => ManualRedirects::ZIEL_URL === $typ ? $ziel : '',
            'code' => '' !== (string) ($satz['code'] ?? '') ? (string) $satz['code'] : '301',
            'aus404' => $logId,
        ];

        $vorhanden = (int) ($satz['redirect'] ?? 0);
        if ($vorhanden > 0 && false !== $this->db->fetchOne('SELECT id FROM '.ManualRedirects::TABELLE.' WHERE id = ?', [$vorhanden])) {
            $this->db->update(ManualRedirects::TABELLE, $daten, ['id' => $vorhanden]);
        } else {
            $this->db->insert(ManualRedirects::TABELLE, $daten);
            $vorhanden = (int) $this->db->lastInsertId();
        }
        $this->log->erledige($logId, $vorhanden);

        return $vorhanden;
    }
}
