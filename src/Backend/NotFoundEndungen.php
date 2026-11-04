<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Backend;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 404-Liste: Endungen ausblenden — mehrere auf einmal, per Haken im Filterbereich.
 *
 * Contaos Filtermenü kann je Feld nur EINEN Wert zeigen („nur .html"). Gebraucht wird auch das
 * Gegenteil: „alles außer .php, .asp, .env" — die Scanner-Anfragen, die eine Arbeitsliste unlesbar
 * machen. Dafür ein eigenes Panel (panel_callback) und ein fester Filter (list.sorting.filter), den
 * config.onload aus der Backend-Sitzung setzt. Gespeichert wird die Auswahl wie Contaos eigene Filter
 * in der Sitzung, je Benutzer, bis „Zurücksetzen".
 */
final class NotFoundEndungen
{
    private const SITZUNG = 'gozi_404_endungen_aus';

    /** Feld im Panel; „…_gesendet" unterscheidet „alle Haken entfernt" von „Panel nicht abgeschickt". */
    private const FELD = 'gozi_endung_aus';

    public function __construct(
        private readonly Connection $db,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[AsCallback(table: 'tl_gozi_404', target: 'config.onload')]
    public function anwenden(DataContainer|null $dc = null): void
    {
        if ('tl_filters' === Input::post('FORM_SUBMIT')) {
            $neu = Input::post('filter_reset') ? [] : (Input::post(self::FELD.'_gesendet') ? (array) Input::post(self::FELD) : null);

            if (null !== $neu) {
                $this->speichern(self::bereinigen($neu));
            }
        }

        $aus = $this->auswahl();

        if ([] !== $aus) {
            $GLOBALS['TL_DCA']['tl_gozi_404']['list']['sorting']['filter'][] = self::bedingung($aus);
        }
    }

    #[AsCallback(table: 'tl_gozi_404', target: 'list.sorting.panel_callback.endungen')]
    public function panel(DataContainer|null $dc = null): string
    {
        $zeilen = $this->db->fetchAllKeyValue('SELECT endung, COUNT(*) FROM '.NotFoundLog::TABELLE.' WHERE erledigt = 0 GROUP BY endung ORDER BY COUNT(*) DESC');
        $aus = $this->auswahl();

        // Ausgeblendete Endungen ohne offene Einträge trotzdem zeigen, sonst ließe sich der Haken nicht mehr entfernen.
        foreach ($aus as $endung) {
            $zeilen[$endung] ??= 0;
        }

        if ([] === $zeilen) {
            return '';
        }

        $zeilen = self::ohneEndungZuerst($zeilen);
        $lang = $GLOBALS['TL_LANG']['tl_gozi_404'] ?? [];

        // „Alle markieren": Inline-Handler wie Contaos eigene Lösch-Bestätigungen — er läuft in 5.3 (MooTools)
        // und 5.7 (Stimulus) gleich; der check-all-Controller fehlt in 5.3. Danach einzelne Haken entfernen.
        $alle = 'this.closest(\'fieldset\').querySelectorAll(\'input[name="'.self::FELD.'[]"]\').forEach(c=>c.checked=this.checked)';

        $html = '<fieldset class="tl_filter tl_subpanel gozi-endungen"><legend>'.StringUtil::specialchars($lang['endungenAus'] ?? 'Endungen ausblenden').'</legend>'
            .'<input type="hidden" name="'.self::FELD.'_gesendet" value="1">'
            .'<label style="display:block;margin-bottom:.4em;font-weight:600"><input type="checkbox" onclick="'.StringUtil::specialchars($alle).'"'
            .(\count($aus) === \count($zeilen) ? ' checked' : '').'> '.StringUtil::specialchars($lang['alleMarkieren'] ?? 'Alle markieren').'</label>'
            // Bei vielen Endungen (PONS: rund 100) nicht die ganze Seite füllen.
            .'<div style="max-height:12em;overflow-y:auto">';

        foreach ($zeilen as $endung => $anzahl) {
            $endung = (string) $endung;
            $html .= \sprintf(
                ' <label style="margin-right:.8em;white-space:nowrap"><input type="checkbox" name="%s[]" value="%s"%s> %s <span class="tl_gray">(%d)</span></label>',
                self::FELD,
                StringUtil::specialchars('' === $endung ? NotFoundLog::OHNE_ENDUNG : $endung),
                \in_array($endung, $aus, true) ? ' checked' : '',
                StringUtil::specialchars('' === $endung ? ($GLOBALS['TL_LANG']['tl_gozi_404']['ohneEndung'] ?? 'ohne Endung') : '.'.$endung),
                $anzahl,
            );
        }

        return $html.'</div></fieldset>';
    }

    /**
     * „ohne Endung" an den Anfang, sonst nach Zahl — die eigentlichen Seitenadressen stehen damit oben.
     *
     * @param array<string|int, int|string> $zeilen Endung => Anzahl
     *
     * @return array<string, int>
     */
    public static function ohneEndungZuerst(array $zeilen): array
    {
        $sortiert = [];

        if (\array_key_exists('', $zeilen)) {
            $sortiert[''] = (int) $zeilen[''];
        }

        foreach ($zeilen as $endung => $anzahl) {
            if ('' !== (string) $endung) {
                $sortiert[(string) $endung] = (int) $anzahl;
            }
        }

        return $sortiert;
    }

    /**
     * Der feste Filter für die Liste.
     *
     * @param list<string> $aus bereinigte Endungen, '' = ohne Endung
     *
     */
    public static function bedingung(array $aus): string
    {
        // Werte stehen fest im SQL: Contao bindet je Filter nur EINEN Wert, IN (?) mit Liste geht nicht.
        // bereinigen() lässt nur [a-z0-9] durch, die Werte sind damit unbedenklich.
        return 'endung NOT IN ('.implode(',', array_map(static fn (string $e): string => "'".$e."'", $aus)).')';
    }

    /**
     * @param array<mixed> $werte aus dem Formular
     *
     * @return list<string>
     */
    public static function bereinigen(array $werte): array
    {
        $sauber = [];

        foreach ($werte as $wert) {
            $wert = strtolower(trim((string) $wert));
            $wert = NotFoundLog::OHNE_ENDUNG === $wert ? '' : ltrim($wert, '.');

            if ('' === $wert || preg_match('/^[a-z0-9]{1,16}$/', $wert)) {
                $sauber[] = $wert;
            }
        }

        return array_values(array_unique($sauber));
    }

    /** @return list<string> */
    private function auswahl(): array
    {
        $sitzung = $this->requestStack->getCurrentRequest()?->getSession();

        if (null === $sitzung || !$sitzung->has(self::SITZUNG)) {
            return [];
        }

        return self::bereinigen((array) $sitzung->get(self::SITZUNG));
    }

    /** @param list<string> $aus */
    private function speichern(array $aus): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()->set(self::SITZUNG, $aus);
    }
}
