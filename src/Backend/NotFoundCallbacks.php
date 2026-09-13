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
     *  - die Seite liegt in einem Baum, der zu diesem Rechnernamen nicht gehoert (Aliase gelten je Wurzel),
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
        $this->log->erledige((int) $satz['id']);

        return $seitenId;
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
        $praefix = $wurzel > 0 ? trim((string) $this->db->fetchOne('SELECT urlPrefix FROM tl_page WHERE id = ?', [$wurzel]), '/') : '';
        if ('' !== $praefix && (str_starts_with($p.'/', $praefix.'/'))) {
            $p = ltrim(substr($p, \strlen($praefix)), '/');
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

    /** Host: nur der Rechnername. Wer „https://de.pons.com/" eintraegt, meint „de.pons.com". */
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
}
