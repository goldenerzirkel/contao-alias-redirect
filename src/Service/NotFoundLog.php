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

    /** Wert im Ausblend-Panel für „ohne Endung" (die Spalte speichert dafür ''). */
    public const OHNE_ENDUNG = '-';

    private ?bool $vorhanden = null;

    private ?bool $mitEndung = null;

    /** @var list<string> */
    private readonly array $ohneEndungen;

    /**
     * @param list<string> $ohneEndungen Konfiguration gozi_alias_redirect.ohne_endungen: Endungen, die gar
     *                                   nicht erst erfasst werden (Scanner: php, asp, env …)
     */
    public function __construct(private readonly Connection $db, array $ohneEndungen = [])
    {
        $this->ohneEndungen = array_values(array_map(static fn ($e): string => ltrim(strtolower(trim((string) $e)), '.'), $ohneEndungen));
    }

    /**
     * Dateiendung der angefragten Adresse, klein und ohne Punkt; '' ohne Endung.
     *
     * Aus dem ROHEN Pfad: normalisiere() schneidet .html ab — danach wäre „/seite.html" von „/seite"
     * nicht mehr zu unterscheiden. Nur [a-z0-9] bis 16 Zeichen; alles andere („/a.b c", „/x.") gilt
     * als ohne Endung.
     */
    public static function endung(string $pfad): string
    {
        $pfad = (string) (parse_url($pfad, PHP_URL_PATH) ?? $pfad);
        $letztes = (string) strrchr('/'.rtrim(rawurldecode($pfad), '/'), '/');
        $punkt = strrpos($letztes, '.');

        if (false === $punkt) {
            return '';
        }

        $endung = strtolower(substr($letztes, $punkt + 1));

        return preg_match('/^[a-z0-9]{1,16}$/', $endung) ? $endung : '';
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
     * Gibt es die Spalte „endung" schon? Zwischen Deploy und contao:migrate fehlt sie — das Erfassen
     * darf in dieser Lücke nicht scheitern, es läuft bei jedem 404.
     */
    private function mitEndung(): bool
    {
        if (null === $this->mitEndung) {
            try {
                $this->mitEndung = isset($this->db->createSchemaManager()->listTableColumns(self::TABELLE)['endung']);
            } catch (\Throwable) {
                $this->mitEndung = false;
            }
        }

        return $this->mitEndung;
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
        // .well-known/ ist Maschinenverkehr (Zertifikate, Apple- und Android-App-Verknuepfungen,
        // security.txt, Passwort-Aenderungspfade). Ein 404 dort ist normal und nie ein Redaktionsfall.
        foreach (['/contao', '/_', '/preview.php', '/api/', '/.well-known/'] as $anfang) {
            if (str_starts_with($pfad, $anfang)) {
                return false;
            }
        }
        $endung = self::endung($pfad);

        return !\in_array($endung, self::RAUSCHEN, true) && !\in_array($endung, $this->ohneEndungen, true);
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
        $endung = self::endung($pfad);
        $pfad = $this->normalisiere($pfad);
        $host = mb_substr($host, 0, 255);
        if ('' === $pfad) {
            return 0;
        }
        $jetzt = time();
        $mitEndung = $this->mitEndung();
        $vorhanden = $this->db->fetchAssociative('SELECT id, erledigt FROM '.self::TABELLE.' WHERE pfad = ? AND host = ? LIMIT 1', [$pfad, $host]);
        if (false !== $vorhanden) {
            // Ein erledigter Eintrag, der WIEDER auflaeuft, ist ein Hinweis: die Weiterleitung greift nicht.
            // Er bleibt deshalb erledigt, wird aber mit Zaehler und Zeitpunkt sichtbar aktuell.
            // Die Endung nur nachtragen: /seite und /seite.html sind derselbe Eintrag, die erste bekannte bleibt.
            $this->db->executeStatement(
                'UPDATE '.self::TABELLE.' SET zaehler = zaehler + 1, tstamp = ?, verweis = IF(? <> \'\', ?, verweis)'
                .($mitEndung ? ', endung = IF(endung = \'\', ?, endung)' : '').' WHERE id = ?',
                [$jetzt, $verweis, mb_substr($verweis, 0, 255), ...($mitEndung ? [$endung] : []), (int) $vorhanden['id']],
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
        ] + ($mitEndung ? ['endung' => $endung] : []));

        return (int) $this->db->lastInsertId();
    }

    /** Einen Eintrag als erledigt kennzeichnen — aufgerufen, wenn daraus eine Weiterleitung wird. */
    public function erledige(int $id, int $redirectId = 0): void
    {
        if ($this->vorhanden() && $id > 0) {
            // tstamp bleibt unberuehrt: er sagt „zuletzt aufgelaufen", nicht „zuletzt bearbeitet",
            // und danach ist die Liste sortiert.
            $this->db->update(self::TABELLE, ['erledigt' => 1, 'redirect' => $redirectId], ['id' => $id]);
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
