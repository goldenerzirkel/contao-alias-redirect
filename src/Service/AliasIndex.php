<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * DER INDEX ALTER ALIASE — abgeleitet aus der Liste an der Seite, nie die Wahrheit selbst.
 *
 * Die Liste `gozi_redirects` an der Seite bleibt die Quelle (versioniert mit ihr). Die Suche bei einer
 * 404-Anfrage lief bisher mit LIKE über das serialisierte Feld ALLER Seiten — bei Dutzenden Seiten
 * egal, bei Tausenden und Bot-Traffic eine Volltabellen-Abfrage je Anfrage. Vorbild ist die kompilierte
 * Route von terminal42/contao-url-rewrite: ein Datensatz je altem Alias, mit Index.
 *
 * Gepflegt in denselben Momenten wie die Liste (speichern, löschen, Version wiederherstellen) und mit
 * `gozi:alias-redirect:rebuild` jederzeit aus den Seiten neu aufbaubar.
 *
 * Seiten vom Typ „Entfernt (410)" stehen mit gone=1 drin — mit ihrem aktuellen Alias UND ihrer Liste:
 * jede dieser Adressen antwortet mit 410 Gone statt 404.
 */
final class AliasIndex
{
    public const TABELLE = 'tl_gozi_alias_redirect';
    public const TYP_GONE = 'gozi_gone';

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
                $this->vorhanden = $this->db->createSchemaManager()->tablesExist([self::TABELLE]);
            } catch (\Throwable) {
                $this->vorhanden = false;
            }
        }

        return $this->vorhanden;
    }

    /**
     * Alten Alias nachschlagen — in den Wurzeln des Hosts, wenn welche genannt sind.
     *
     * @param list<int> $rootIds
     *
     * @return array{pid:int, gone:bool}|null
     */
    public function finde(string $alias, array $rootIds = []): ?array
    {
        $alias = trim($alias, '/ ');
        if ('' === $alias || !$this->vorhanden()) {
            return null;
        }
        $zeilen = $this->db->fetchAllAssociative(
            'SELECT pid, root, gone FROM '.self::TABELLE.' WHERE alias = ? ORDER BY gone DESC, id',
            [$alias],
        );
        foreach ($zeilen as $z) {
            if ([] === $rootIds || \in_array((int) $z['root'], $rootIds, true)) {
                return ['pid' => (int) $z['pid'], 'gone' => 1 === (int) $z['gone']];
            }
        }

        return null;
    }

    /** Die Einträge EINER Seite neu schreiben — nach jedem Speichern. */
    public function seiteNeu(int $pageId): int
    {
        if (!$this->vorhanden() || $pageId < 1) {
            return 0;
        }
        $seite = $this->db->fetchAssociative('SELECT id, alias, type, published, '.AliasRedirects::FELD.' AS liste FROM tl_page WHERE id = ?', [$pageId]);
        $this->db->delete(self::TABELLE, ['pid' => $pageId]);
        if (false === $seite || !$seite['published']) {
            return 0;
        }
        $gone = self::TYP_GONE === (string) $seite['type'];
        $aliase = $this->redirects->bereinige($seite['liste'], $gone ? '' : (string) $seite['alias']);
        if ($gone && '' !== trim((string) $seite['alias'])) {
            array_unshift($aliase, trim((string) $seite['alias'], '/'));
            $aliase = array_values(array_unique($aliase));
        }
        if ([] === $aliase) {
            return 0;
        }
        $root = $this->redirects->rootId($pageId);
        $n = 0;
        foreach ($aliase as $alias) {
            $this->db->insert(self::TABELLE, ['tstamp' => time(), 'alias' => $alias, 'root' => $root, 'pid' => $pageId, 'gone' => $gone ? 1 : 0]);
            ++$n;
        }

        return $n;
    }

    public function seiteWeg(int $pageId): void
    {
        if ($this->vorhanden() && $pageId > 0) {
            $this->db->delete(self::TABELLE, ['pid' => $pageId]);
        }
    }

    /** Alles aus den Seiten neu aufbauen. @return array{seiten:int, eintraege:int} */
    public function neuAufbauen(): array
    {
        if (!$this->vorhanden()) {
            return ['seiten' => 0, 'eintraege' => 0];
        }
        $this->db->executeStatement('DELETE FROM '.self::TABELLE);
        $seiten = 0;
        $eintraege = 0;
        $ids = $this->db->fetchFirstColumn(
            'SELECT id FROM tl_page WHERE type = ? OR ('.AliasRedirects::FELD.' IS NOT NULL AND '.AliasRedirects::FELD." <> '' AND ".AliasRedirects::FELD." <> 'a:0:{}')",
            [self::TYP_GONE],
        );
        foreach ($ids as $id) {
            $n = $this->seiteNeu((int) $id);
            if ($n > 0) {
                ++$seiten;
                $eintraege += $n;
            }
        }

        return ['seiten' => $seiten, 'eintraege' => $eintraege];
    }
}
