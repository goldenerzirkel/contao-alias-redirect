<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Protokoll der ins Leere laufenden Anfragen — die Arbeitsliste für Weiterleitungen.
 *
 * Ein Datensatz je Adresse und Host, nicht je Aufruf: derselbe Pfad zählt hoch. Ohne diese Regel
 * schreibt ein einziger Scanner die Tabelle in Stunden voll (gemessen am Livesystem: 1.436 Einträge
 * an einem Tag, ein grosser Teil davon derselbe Bot mit wechselnden Pfaden).
 *
 * Der Abfrageteil (?x=1) wird NICHT gespeichert: er macht aus einer Adresse beliebig viele und ist
 * für eine Weiterleitung ohne Belang — der Ziel-Aufruf bekommt ihn ohnehin wieder angehängt.
 */
final class NotFoundLog
{
    public const TABELLE = 'tl_gozi_404';

    /** Dateiendungen, die im Protokoll nur Rauschen wären (fehlende Bilder, Karten, Schriften). */
    private const RAUSCHEN = ['css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'eot'];

    private ?bool $vorhanden = null;

    public function __construct(private readonly Connection $db)
    {
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

    /** Gehört diese Anfrage ins Protokoll? Nur GET/HEAD aus dem Frontend, keine Assets, kein Contao-Innenleben. */
    public function gehoertHinein(Request $request): bool
    {
        if (!\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }
        $pfad = $request->getPathInfo();
        if ('' === trim($pfad, '/')) {
            return false;
        }
        foreach (['/contao', '/_', '/preview.php', '/api/'] as $anfang) {
            if (str_starts_with($pfad, $anfang)) {
                return false;
            }
        }
        $endung = strtolower((string) pathinfo(parse_url($pfad, PHP_URL_PATH) ?: $pfad, PATHINFO_EXTENSION));

        return !\in_array($endung, self::RAUSCHEN, true);
    }

    /**
     * Adresse festhalten oder hochzählen.
     *
     * @return int id des Eintrags (0 = nicht erfasst)
     */
    public function erfasse(string $pfad, string $host, int $root = 0, string $verweis = ''): int
    {
        if (!$this->vorhanden()) {
            return 0;
        }
        $pfad = $this->normalisiere($pfad);
        $host = mb_substr($host, 0, 255);
        if ('' === $pfad) {
            return 0;
        }
        $jetzt = time();
        $vorhanden = $this->db->fetchAssociative('SELECT id, erledigt FROM '.self::TABELLE.' WHERE pfad = ? AND host = ? LIMIT 1', [$pfad, $host]);
        if (false !== $vorhanden) {
            // Ein erledigter Eintrag, der WIEDER auflaeuft, ist ein Hinweis: die Weiterleitung greift nicht.
            // Er bleibt deshalb erledigt, wird aber mit Zaehler und Zeitpunkt sichtbar aktuell.
            $this->db->executeStatement(
                'UPDATE '.self::TABELLE.' SET zaehler = zaehler + 1, tstamp = ?, verweis = IF(? <> \'\', ?, verweis) WHERE id = ?',
                [$jetzt, $verweis, mb_substr($verweis, 0, 255), (int) $vorhanden['id']],
            );

            return (int) $vorhanden['id'];
        }
        $this->db->insert(self::TABELLE, [
            'tstamp' => $jetzt,
            'erstmals' => $jetzt,
            'pfad' => $pfad,
            'host' => $host,
            'root' => $root,
            'verweis' => mb_substr($verweis, 0, 255),
            'zaehler' => 1,
            'erledigt' => 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Einen Eintrag als erledigt kennzeichnen — aufgerufen, wenn daraus eine Weiterleitung wird. */
    public function erledige(int $id, int $redirectId = 0): void
    {
        if ($this->vorhanden() && $id > 0) {
            $this->db->update(self::TABELLE, ['erledigt' => 1, 'redirect' => $redirectId, 'tstamp' => time()], ['id' => $id]);
        }
    }

    /** Aufraeumen: erledigte und lange nicht mehr aufgerufene Eintraege verfallen. @return int geloeschte Zeilen */
    public function aufraeumen(int $tage = 90): int
    {
        if (!$this->vorhanden() || $tage < 1) {
            return 0;
        }

        return (int) $this->db->executeStatement(
            'DELETE FROM '.self::TABELLE.' WHERE tstamp < ? AND erledigt = 0',
            [time() - $tage * 86400],
        );
    }

    /** Pfad in der Form, in der er verglichen und angezeigt wird: ohne Schraegstriche aussen, ohne .html. */
    public function normalisiere(string $pfad): string
    {
        $p = trim(rawurldecode($pfad), "/ \t\n\r");
        $p = preg_replace('/\.html?$/i', '', $p) ?? $p;

        return mb_substr($p, 0, 255);
    }
}
