<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Doctrine\DBAL\Connection;

/**
 * Von Hand gepflegte Weiterleitungen — die einzigen, die ueber Wurzelgrenzen hinweg gelten.
 *
 * Abgrenzung zu den beiden anderen Wegen im Bundle:
 *  - `tl_page.gozi_redirects` haengt an EINER Seite und gilt nur in deren Wurzel (Aliase sind je Wurzel
 *    eindeutig). Das deckt den Regelfall ab: Seite umbenannt, alte Adresse bleibt erreichbar.
 *  - Diese Tabelle ist fuer alles andere: eine Adresse, die es nicht mehr gibt, ein Ziel in einem anderen
 *    Baum, eine externe Adresse, oder ein bewusstes 410.
 *  - terminal42/contao-url-rewrite macht MUSTER (regulaere Ausdruecke, Platzhalter) und bleibt dafuer
 *    zustaendig; hier steht je Zeile genau eine Adresse. Siehe docs/vergleich-terminal42-url-rewrite.md.
 *
 * Der Host entscheidet mit: dieselbe Adresse kann auf zwei Marken verschieden enden. Ein leerer Host
 * gilt fuer alle; ein passender Host gewinnt gegen den leeren.
 */
final class ManualRedirects
{
    public const TABELLE = 'tl_gozi_redirect';

    public const ZIEL_SEITE = 'seite';
    public const ZIEL_URL = 'url';
    public const ZIEL_GONE = 'gone';

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

    /**
     * Weiterleitung fuer diese Adresse — passender Host vor leerem Host.
     *
     * @return array{id:int, zielTyp:string, zielSeite:int, zielUrl:string, code:int}|null
     */
    public function finde(string $pfad, string $host): ?array
    {
        if (!$this->vorhanden()) {
            return null;
        }
        $pfad = $this->normalisiere($pfad);
        if ('' === $pfad) {
            return null;
        }
        $zeile = $this->db->fetchAssociative(
            'SELECT id, zielTyp, zielSeite, zielUrl, code FROM '.self::TABELLE."
             WHERE aktiv = 1 AND pfad = ? AND (host = ? OR host = '')
             ORDER BY host DESC LIMIT 1",
            [$pfad, $host],
        );
        if (false === $zeile) {
            return null;
        }

        return [
            'id' => (int) $zeile['id'],
            'zielTyp' => (string) $zeile['zielTyp'],
            'zielSeite' => (int) $zeile['zielSeite'],
            'zielUrl' => (string) $zeile['zielUrl'],
            'code' => (int) $zeile['code'],
        ];
    }

    /** Treffer zaehlen — in der Liste steht damit, welche Weiterleitung wirklich gebraucht wird. */
    public function treffer(int $id): void
    {
        if ($this->vorhanden() && $id > 0) {
            $this->db->executeStatement('UPDATE '.self::TABELLE.' SET zaehler = zaehler + 1, letzter = ? WHERE id = ?', [time(), $id]);
        }
    }

    /** Wie NotFoundLog: ohne Schraegstriche aussen, ohne .html — beide Seiten muessen gleich normalisieren. */
    public function normalisiere(string $pfad): string
    {
        $p = trim(rawurldecode($pfad), "/ \t\n\r");
        $p = preg_replace('/\.html?$/i', '', $p) ?? $p;

        return mb_substr($p, 0, 255);
    }
}
