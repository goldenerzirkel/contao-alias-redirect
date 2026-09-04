<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\EventListener;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;

/**
 * Haengt sich an tl_page: alter Alias → in die Weiterleitungsliste, Einmal-Schalter, Liste bereinigen.
 *
 * Reihenfolge ist die Sicherung: Contao verarbeitet die Felder in Palettenreihenfolge, und die Liste
 * steht HINTER dem Alias. Der Alias-Callback legt den alten Alias in das Formularfeld der Liste; die
 * Liste wird danach als Teil DESSELBEN Speichervorgangs geschrieben — eine Aenderung, eine Version.
 */
final class PageAliasListener
{
    public function __construct(
        private readonly AliasRedirects $redirects,
        private readonly Connection $db,
    ) {
    }

    /** Beide Felder hinter dem Alias — in JEDER Palette, die einen Alias hat (regular, forward, root, …). */
    #[AsCallback(table: 'tl_page', target: 'config.onload')]
    public function felderEinhaengen(): void
    {
        foreach ($GLOBALS['TL_DCA']['tl_page']['palettes'] ?? [] as $name => $palette) {
            if ('__selector__' === $name || !\is_string($palette) || !preg_match('/\balias\b/', $palette)) {
                continue;
            }
            PaletteManipulator::create()
                ->addField(['gozi_noRedirect', 'gozi_redirects'], 'alias', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($name, 'tl_page');
        }
    }

    /**
     * Nach Contaos eigener Alias-Pruefung (generateAlias, Prioritaet 0): der alte Alias wandert in die
     * Liste — ueber das Formular, damit er im selben Speichervorgang landet. Der Einmal-Schalter kommt
     * ebenfalls aus dem Formular und gilt fuer DIESES Speichern.
     */
    #[AsCallback(table: 'tl_page', target: 'fields.alias.save', priority: -10)]
    public function beiAliasWechsel(mixed $value, DataContainer $dc): mixed
    {
        $aktuell = $dc->getCurrentRecord();
        $alt = (string) ($aktuell['alias'] ?? '');
        $neu = (string) $value;

        if ('' !== $alt && $alt !== $neu && !Input::post('gozi_noRedirect')) {
            $liste = Input::post(AliasRedirects::FELD) ?? ($aktuell[AliasRedirects::FELD] ?? null);
            Input::setPost(AliasRedirects::FELD, $this->redirects->mitAltemAlias($liste, $alt, $neu));
        }

        return $value;
    }

    /** Was der Redakteur in die Liste tippt, wird bereinigt: Schraegstriche, Leere, Doppelte, der eigene Alias. */
    #[AsCallback(table: 'tl_page', target: 'fields.gozi_redirects.save')]
    public function listeBereinigen(mixed $value, DataContainer $dc): mixed
    {
        $aktuellerAlias = (string) (Input::post('alias') ?? ($dc->getCurrentRecord()['alias'] ?? ''));
        $liste = $this->redirects->bereinige($value, $aktuellerAlias);

        return [] === $liste ? null : serialize($liste);
    }

    /** Der Einmal-Schalter wird nach dem Speichern zurueckgesetzt — er soll nicht still fuer immer gelten. */
    #[AsCallback(table: 'tl_page', target: 'config.onsubmit', priority: -32)]
    public function schalterZuruecksetzen(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->db->update('tl_page', ['gozi_noRedirect' => 0], ['id' => (int) $dc->id]);
        }
    }
}
