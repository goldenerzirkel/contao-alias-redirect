<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * DER INDEX ALTER ALIASE — abgeleitet aus der Liste am Datensatz, nie die Wahrheit selbst.
 *
 * Die Liste `gozi_redirects` an der Seite, der Nachricht oder dem Termin bleibt die Quelle (versioniert
 * mit dem Datensatz). Die Suche bei einer Anfrage lief bisher mit LIKE über das serialisierte Feld ALLER
 * Seiten — bei Dutzenden egal, bei Tausenden und Bot-Traffic eine Volltabellen-Abfrage je Anfrage.
 * Vorbild ist die kompilierte Route von terminal42/contao-url-rewrite: ein Datensatz je altem Alias.
 *
 * Gepflegt in denselben Momenten wie die Liste (speichern, löschen, Version wiederherstellen) und mit
 * `gozi:alias-redirect:rebuild` jederzeit neu aufbaubar.
 *
 * quelle = tl_page | tl_news | tl_calendar_events | tl_faq; pid = id des Datensatzes; root = Wurzel des Baums, in
 * dem die Adresse gilt (bei Nachrichten und Terminen über die Leseseite des Archivs bzw. Kalenders).
 * Seiten vom Typ „Entfernt (410)" stehen mit gone=1 drin — mit ihrem aktuellen Alias UND ihrer Liste.
 */
final class AliasIndex
{
    public const TABELLE = 'tl_gozi_alias_redirect';
    public const TYP_GONE = 'gozi_gone';

    public const UEBERSETZUNG = 'tl_page_i18nl10n';

    /** Quelle → [Archivtabelle, Fremdschlüssel] für die Wurzel-Ermittlung über die Leseseite. */
    public const QUELLEN = [
        'tl_page' => null,
        // Übersetzungen: die Wurzel steht an der übersetzten SEITE (pid), die Sprache am Datensatz.
        self::UEBERSETZUNG => null,
        'tl_news' => ['tl_news_archive', 'pid'],
        'tl_calendar_events' => ['tl_calendar', 'pid'],
        'tl_faq' => ['tl_faq_category', 'pid'],
    ];

    private ?bool $vorhanden = null;

    public function __construct(
        private readonly Connection $db,
        private readonly AliasRedirects $redirects,
    ) {
    }

    public function vorhanden(): bool
    {
        if (null === $this->vorhanden) {
            try {
                $sm = $this->db->createSchemaManager();
                $this->vorhanden = $sm->tablesExist([self::TABELLE])
                    && isset($sm->listTableColumns(self::TABELLE)['quelle']);
            } catch (\Throwable) {
                $this->vorhanden = false;
            }
        }

        return $this->vorhanden;
    }

    /**
     * Alten Alias nachschlagen — in den Wurzeln des Hosts, wenn welche genannt sind; auf Wunsch nur in
     * bestimmten Quellen (vor dem Router nur Seiten: ein Nachrichten-Alias ist kein Seitenpfad).
     *
     * Eine Sprache am Eintrag (Übersetzungen) muss zur gesuchten Sprache passen: derselbe alte Alias
     * kann in zwei Sprachen zu verschiedenen Seiten gehören. Einträge ohne Sprache (tl_page und die
     * Datensatztabellen) gelten wie bisher für jede Anfrage.
     *
     * @param list<int>    $rootIds
     * @param list<string> $quellen
     *
     * @return array{quelle:string, pid:int, gone:bool, sprache:string}|null
     */
    public function finde(string $alias, array $rootIds = [], array $quellen = [], ?string $sprache = null): ?array
    {
        $alias = trim($alias, '/ ');
        if ('' === $alias || !$this->vorhanden()) {
            return null;
        }
        $zeilen = $this->db->fetchAllAssociative(
            'SELECT quelle, pid, root, gone, sprache FROM '.self::TABELLE.' WHERE alias = ? ORDER BY gone DESC, id',
            [$alias],
        );
        foreach ($zeilen as $z) {
            if ([] !== $quellen && !\in_array((string) $z['quelle'], $quellen, true)) {
                continue;
            }
            if ('' !== (string) $z['sprache'] && $z['sprache'] !== $sprache) {
                continue;
            }
            if ([] === $rootIds || \in_array((int) $z['root'], $rootIds, true)) {
                return ['quelle' => (string) $z['quelle'], 'pid' => (int) $z['pid'], 'gone' => 1 === (int) $z['gone'], 'sprache' => (string) $z['sprache']];
            }
        }

        return null;
    }

    /** Die Einträge EINES Datensatzes neu schreiben — nach jedem Speichern. */
    public function neu(string $quelle, int $id): int
    {
        if (!$this->vorhanden() || $id < 1 || !\array_key_exists($quelle, self::QUELLEN)) {
            return 0;
        }
        $this->db->delete(self::TABELLE, ['quelle' => $quelle, 'pid' => $id]);
        $satz = $this->db->fetchAssociative('SELECT * FROM '.$quelle.' WHERE id = ?', [$id]);
        if (false === $satz) {
            return 0;
        }
        // Übersetzungen haben ein eigenes Veröffentlichungsfeld; die Seite dazu muss ebenfalls stehen.
        $veroeffentlicht = self::UEBERSETZUNG === $quelle
            ? ($satz['i18nl10n_published'] ?? 0) && $this->db->fetchOne('SELECT published FROM tl_page WHERE id = ?', [(int) $satz['pid']])
            : ($satz['published'] ?? 0);
        if (!$veroeffentlicht) {
            return 0;
        }
        $sprache = self::UEBERSETZUNG === $quelle ? (string) ($satz['language'] ?? '') : '';
        $gone = 'tl_page' === $quelle && self::TYP_GONE === (string) ($satz['type'] ?? '');
        $aliase = $this->redirects->bereinige($satz[AliasRedirects::FELD] ?? null, $gone ? '' : (string) ($satz['alias'] ?? ''));
        if ($gone && '' !== trim((string) $satz['alias'])) {
            array_unshift($aliase, trim((string) $satz['alias'], '/'));
            $aliase = array_values(array_unique($aliase));
        }
        if ([] === $aliase) {
            return 0;
        }
        $root = $this->wurzel($quelle, $satz);
        $n = 0;
        foreach ($aliase as $alias) {
            $this->db->insert(self::TABELLE, ['tstamp' => time(), 'quelle' => $quelle, 'alias' => $alias, 'root' => $root, 'pid' => $id, 'gone' => $gone ? 1 : 0, 'sprache' => $sprache]);
            ++$n;
        }

        return $n;
    }

    /** @deprecated Seiten-Kurzform, bleibt für vorhandene Aufrufer. */
    public function seiteNeu(int $pageId): int
    {
        return $this->neu('tl_page', $pageId);
    }

    public function weg(string $quelle, int $id): void
    {
        if ($this->vorhanden() && $id > 0) {
            $this->db->delete(self::TABELLE, ['quelle' => $quelle, 'pid' => $id]);
        }
    }

    public function seiteWeg(int $pageId): void
    {
        $this->weg('tl_page', $pageId);
    }

    /** Alles aus Seiten, Nachrichten und Terminen neu aufbauen. @return array{seiten:int, eintraege:int} */
    public function neuAufbauen(): array
    {
        if (!$this->vorhanden()) {
            return ['seiten' => 0, 'eintraege' => 0];
        }
        $this->db->executeStatement('DELETE FROM '.self::TABELLE);
        $saetze = 0;
        $eintraege = 0;
        $sm = $this->db->createSchemaManager();
        foreach (array_keys(self::QUELLEN) as $quelle) {
            if (!$sm->tablesExist([$quelle]) || !isset($sm->listTableColumns($quelle)[strtolower(AliasRedirects::FELD)])) {
                continue;
            }
            $where = AliasRedirects::FELD.' IS NOT NULL AND '.AliasRedirects::FELD." <> '' AND ".AliasRedirects::FELD." <> 'a:0:{}'";
            if ('tl_page' === $quelle) {
                $where = "type = '".self::TYP_GONE."' OR (".$where.')';
            }
            foreach ($this->db->fetchFirstColumn('SELECT id FROM '.$quelle.' WHERE '.$where) as $id) {
                $n = $this->neu($quelle, (int) $id);
                if ($n > 0) {
                    ++$saetze;
                    $eintraege += $n;
                }
            }
        }

        return ['seiten' => $saetze, 'eintraege' => $eintraege];
    }

    /** Wurzel: bei Seiten die pid-Kette, bei Nachrichten/Terminen die Leseseite (jumpTo) des Archivs. */
    private function wurzel(string $quelle, array $satz): int
    {
        if ('tl_page' === $quelle) {
            return $this->redirects->rootId((int) $satz['id']);
        }
        if (self::UEBERSETZUNG === $quelle) {
            return $this->redirects->rootId((int) $satz['pid']);
        }
        [$archiv, $schluessel] = self::QUELLEN[$quelle];
        $leseseite = (int) $this->db->fetchOne('SELECT jumpTo FROM '.$archiv.' WHERE id = ?', [(int) ($satz[$schluessel] ?? 0)]);

        return $leseseite > 0 ? $this->redirects->rootId($leseseite) : 0;
    }
}
