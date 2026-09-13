<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Backend;

use Contao\Input;
use Contao\System;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;

/**
 * Vorbelegung beim Anlegen einer Weiterleitung aus einem 404-Eintrag.
 *
 * Aufgerufen aus den „default"-Closures in tl_gozi_redirect: DC_Table::create() wertet sie beim Anlegen
 * aus (DC_Table.php:809-823). Ohne diesen Weg muesste der Redakteur Adresse und Host abtippen — genau die
 * Arbeit, die die Liste ihm abnehmen soll.
 *
 * Die Zeile wird einmal je Anfrage gelesen; `null` heisst „kein Aufruf aus der 404-Liste".
 */
final class Aus404
{
    private static array|null|false $zeile = false;

    /** @return array{id:int, pfad:string, host:string}|null */
    public static function zeile(): ?array
    {
        if (false !== self::$zeile) {
            return self::$zeile;
        }
        self::$zeile = null;
        $id = (int) Input::get('aus404');
        if ($id < 1) {
            return null;
        }
        try {
            $satz = System::getContainer()->get('database_connection')
                ->fetchAssociative('SELECT id, pfad, host FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$id]);
        } catch (\Throwable) {
            return null;
        }
        if (false === $satz) {
            return null;
        }

        return self::$zeile = ['id' => (int) $satz['id'], 'pfad' => (string) $satz['pfad'], 'host' => (string) $satz['host']];
    }

    public static function pfad(): string
    {
        return self::zeile()['pfad'] ?? '';
    }

    public static function host(): string
    {
        return self::zeile()['host'] ?? '';
    }

    public static function id(): int
    {
        return self::zeile()['id'] ?? 0;
    }
}
