<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/**
 * Dasselbe wie PageAliasListener, nur an der Uebersetzung: alter Alias → in die Liste dieser Sprache.
 *
 * gozi-i18nl10n erzeugt und prueft den Alias in einem eigenen save_callback (generateAlias,
 * Prioritaet 0). Dieser hier laeuft danach (-10) und sieht deshalb den endgueltigen neuen Wert.
 *
 * Der Einmal-Schalter „keine Weiterleitung anlegen" bleibt an der Seite: eine Umbenennung in einer
 * Sprache ist ein eigener Vorgang, und ein zweiter Schalter je Sprache waere mehr Oberflaeche als Nutzen.
 */
final class TranslationAliasListener
{
    public function __construct(
        private readonly AliasRedirects $redirects,
        private readonly Connection $db,
        private readonly AliasIndex $index,
    ) {
    }

    /** Die Liste hinter den Alias — in jede Palette, die einen Alias hat. */
    #[AsCallback(table: AliasIndex::UEBERSETZUNG, target: 'config.onload')]
    public function feldEinhaengen(?DataContainer $dc = null): void
    {
        foreach ($GLOBALS['TL_DCA'][AliasIndex::UEBERSETZUNG]['palettes'] ?? [] as $name => $palette) {
            if ('__selector__' === $name || !\is_string($palette) || !preg_match('/\balias\b/', $palette)) {
                continue;
            }
            PaletteManipulator::create()
                ->addField('gozi_redirects', 'alias', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($name, AliasIndex::UEBERSETZUNG);
        }
    }

    #[AsCallback(table: AliasIndex::UEBERSETZUNG, target: 'fields.alias.save', priority: -10)]
    public function beiAliasWechsel(mixed $value, DataContainer $dc): mixed
    {
        $aktuell = $dc->getCurrentRecord();
        $alt = (string) ($aktuell['alias'] ?? '');
        $neu = (string) $value;

        if ('' !== $alt && $alt !== $neu) {
            $liste = Input::post(AliasRedirects::FELD) ?? ($aktuell[AliasRedirects::FELD] ?? null);
            Input::setPost(AliasRedirects::FELD, $this->redirects->mitAltemAlias($liste, $alt, $neu));
        }

        return $value;
    }

    /** Was der Redakteur eintippt, wird bereinigt: Schraegstriche, Leere, Doppelte, der eigene Alias. */
    #[AsCallback(table: AliasIndex::UEBERSETZUNG, target: 'fields.gozi_redirects.save')]
    public function listeBereinigen(mixed $value, DataContainer $dc): mixed
    {
        $aktuellerAlias = (string) (Input::post('alias') ?? ($dc->getCurrentRecord()['alias'] ?? ''));
        $liste = $this->redirects->bereinige($value, $aktuellerAlias);

        return [] === $liste ? null : serialize($liste);
    }

    #[AsCallback(table: AliasIndex::UEBERSETZUNG, target: 'config.onsubmit', priority: -32)]
    public function indexNachziehen(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->index->neu(AliasIndex::UEBERSETZUNG, (int) $dc->id);
        }
    }

    #[AsCallback(table: AliasIndex::UEBERSETZUNG, target: 'config.ondelete')]
    public function beiLoeschen(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->index->weg(AliasIndex::UEBERSETZUNG, (int) $dc->id);
        }
    }

    /**
     * Wird die SEITE veroeffentlicht oder zurueckgezogen, aendert sich die Gueltigkeit aller ihrer
     * Uebersetzungen mit — der Index muss dann fuer jede Sprache neu geschrieben werden.
     */
    #[AsCallback(table: 'tl_page', target: 'config.onsubmit', priority: -33)]
    public function uebersetzungenNachziehen(DataContainer $dc): void
    {
        if (!$dc->id || !$this->index->vorhanden()) {
            return;
        }
        try {
            $ids = $this->db->fetchFirstColumn('SELECT id FROM '.AliasIndex::UEBERSETZUNG.' WHERE pid = ?', [(int) $dc->id]);
        } catch (\Throwable) {
            return; // ohne gozi-i18nl10n gibt es die Tabelle nicht
        }
        foreach ($ids as $id) {
            $this->index->neu(AliasIndex::UEBERSETZUNG, (int) $id);
        }
    }
}
