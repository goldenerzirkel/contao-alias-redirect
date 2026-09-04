<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Alte Aliase einer Seite als Weiterleitung — gespeichert IM Seiten-Datensatz (tl_page.gozi_redirects).
 *
 * Warum im Datensatz und nicht in einer eigenen Tabelle: Anlegen, Bearbeiten und Loeschen sind damit
 * Contaos eigene Oberflaeche (listWizard), und die Versionierung von tl_page erfasst jede Aenderung
 * von selbst — auch die, die beim Umbenennen automatisch dazukommt (gleicher Speichervorgang, gleiche
 * Version).
 *
 * NICHT IN REIHE: die Eintraege haengen an der SEITE, nicht an einem Alias. Jeder neue Alias ist damit
 * automatisch das Ziel aller alten. Ein Alias, den eine Seite wieder echt traegt, gewinnt von selbst:
 * Contao findet die Seite, die Weiterleitung kommt gar nicht erst zum Zug (nur bei 404).
 *
 * Aliase sind je Wurzel eindeutig, also wird auch hier je Wurzel aufgeloest.
 */
final class AliasRedirects
{
    public const FELD = 'gozi_redirects';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Liste bereinigen: Schraegstriche und Leerzeichen weg, leere raus, doppelte raus, der aktuelle Alias
     * gehoert nicht in seine eigene Weiterleitungsliste.
     *
     * @param list<string>|string|null $liste serialisiert oder als Array
     *
     * @return list<string>
     */
    public function bereinige(array|string|null $liste, string $aktuellerAlias = ''): array
    {
        $aus = [];
        foreach (StringUtil::deserialize($liste, true) as $eintrag) {
            $a = trim((string) $eintrag, "/ \t\n\r");
            if ('' === $a || $a === trim($aktuellerAlias, '/ ') || \in_array($a, $aus, true)) {
                continue;
            }
            $aus[] = $a;
        }

        return $aus;
    }

    /**
     * Beim Umbenennen: alten Alias vorn in die Liste — und den neuen, falls er drinstand, heraus.
     *
     * @param list<string>|string|null $liste
     *
     * @return list<string>
     */
    public function mitAltemAlias(array|string|null $liste, string $alt, string $neu): array
    {
        $alt = trim($alt, '/ ');
        $bereinigt = $this->bereinige($liste, $neu);
        if ('' === $alt || $alt === trim($neu, '/ ')) {
            return $bereinigt;
        }

        return array_values(array_unique([$alt, ...$bereinigt]));
    }

    /**
     * Welche veroeffentlichte Seite fuehrt diesen alten Alias? Optional auf Wurzeln eingegrenzt.
     *
     * @param list<int> $rootIds
     */
    public function finde(string $alias, array $rootIds = []): ?int
    {
        $alias = trim($alias, '/ ');
        if ('' === $alias) {
            return null;
        }
        // Vorauswahl ueber LIKE auf dem serialisierten Feld (s:N:"alias";), dann exakt pruefen.
        $zeilen = $this->db->fetchAllAssociative(
            'SELECT id, '.self::FELD.' AS liste FROM tl_page WHERE published = 1 AND '.self::FELD.' LIKE ?',
            ['%"'.str_replace(['%', '_'], ['\\%', '\\_'], $alias).'"%'],
        );
        $treffer = [];
        foreach ($zeilen as $z) {
            if (\in_array($alias, $this->bereinige($z['liste']), true)) {
                $treffer[] = (int) $z['id'];
            }
        }
        if ([] === $treffer) {
            return null;
        }
        if ([] !== $rootIds) {
            foreach ($treffer as $id) {
                if (\in_array($this->rootId($id), $rootIds, true)) {
                    return $id;
                }
            }

            return null;
        }

        return $treffer[0];
    }

    /** Wurzel einer Seite ueber die pid-Kette. */
    public function rootId(int $pageId): int
    {
        $id = $pageId;
        for ($i = 0; $i < 50 && $id > 0; ++$i) {
            $z = $this->db->fetchAssociative('SELECT id, pid, type FROM tl_page WHERE id = ?', [$id]);
            if (false === $z) {
                return 0;
            }
            if ('root' === $z['type']) {
                return (int) $z['id'];
            }
            $id = (int) $z['pid'];
        }

        return 0;
    }
}
