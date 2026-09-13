<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Backend;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Gozi\AliasRedirectBundle\Service\ManualRedirects;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Die 404-Liste im Backend: Knoepfe, Zeilen, Uebersicht.
 *
 * Der Regelweg („An eine Seite haengen") ist die gewoehnliche Bearbeitungsmaske — dort steht das
 * Seitenfeld. Der zweite Knopf fuehrt in die eigene Weiterleitungstabelle, fuer alles, was kein Alias
 * sein kann: anderer Seitenbaum, externe Adresse, 410, oder eine Adresse mit Zeichen, die in keinem
 * Alias vorkommen duerfen.
 */
final class NotFoundOperations
{
    public function __construct(
        private readonly Connection $db,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    /** Knopf „Weiterleitung anlegen" — Wechsel ins Weiterleitungsmodul mit vorbelegter Adresse. */
    #[AsCallback(table: 'tl_gozi_404', target: 'list.operations.weiterleiten.button')]
    public function weiterleitenButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $bestehende = (int) ($row['redirect'] ?? 0);
        if ($bestehende > 0 && false !== $this->db->fetchOne('SELECT id FROM '.ManualRedirects::TABELLE.' WHERE id = ?', [$bestehende])) {
            // Schon eine Weiterleitung dazu: der Knopf fuehrt zu ihr statt eine zweite anzulegen.
            $ziel = $this->backendUrl(['do' => 'gozi_redirect', 'act' => 'edit', 'id' => $bestehende]);
            $title = $GLOBALS['TL_LANG']['tl_gozi_404']['weiterleitungZeigen'] ?? $title;
        } else {
            $ziel = $this->backendUrl(['do' => 'gozi_redirect', 'act' => 'create', 'aus404' => (int) $row['id']]);
        }

        return sprintf(
            '<a href="%s" title="%s"%s>%s</a> ',
            StringUtil::specialcharsUrl($ziel),
            StringUtil::specialchars($title),
            $attributes,
            Image::getHtml($icon ?? 'alias.svg', $label),
        );
    }

    /** Zeile lesbar machen: Zeitpunkte als Datum, der Verweisgeber als Zusatz unter der Adresse. */
    #[AsCallback(table: 'tl_gozi_404', target: 'list.label.label')]
    public function label(array $row, string $label, DataContainer $dc, array $args): array
    {
        $args[3] = Date::parse(Config::get('datimFormat'), (int) $row['tstamp']);
        if ('' !== (string) ($row['verweis'] ?? '')) {
            $args[0] .= '<span style="display:block;color:#999">← '.StringUtil::specialchars($row['verweis']).'</span>';
        }
        $args[4] = $this->zielInWorten($row);

        return $args;
    }

    /**
     * Die Spalte „Ziel": auf einen Blick, ob eine Adresse schon versorgt ist.
     *
     * Leer heisst offen. Sonst steht dort, wohin es geht — der Titel der Seite, die fremde Adresse oder
     * 410. Abgehakt ohne Ziel ist die dritte Moeglichkeit und sagt: bewusst ohne Weiterleitung.
     */
    private function zielInWorten(array $row): string
    {
        $typ = (string) ($row['zielTyp'] ?? '');

        if (ManualRedirects::ZIEL_SEITE === $typ && (int) ($row['zielSeite'] ?? 0) > 0) {
            $titel = (string) $this->db->fetchOne('SELECT title FROM tl_page WHERE id = ?', [(int) $row['zielSeite']]);

            return '→ '.StringUtil::specialchars('' !== $titel ? $titel : '#'.$row['zielSeite']);
        }
        if (ManualRedirects::ZIEL_URL === $typ && '' !== (string) ($row['zielUrl'] ?? '')) {
            $ziel = (string) $row['zielUrl'];
            $kurz = mb_strlen($ziel) > 48 ? mb_substr($ziel, 0, 45).'…' : $ziel;

            return '<span title="'.StringUtil::specialchars($ziel).'">↗ '.StringUtil::specialchars($kurz).'</span>';
        }
        if (ManualRedirects::ZIEL_GONE === $typ) {
            return '410';
        }

        return ($row['erledigt'] ?? false) ? '<span style="color:#999">'.StringUtil::specialchars($this->feldName('erledigt')).'</span>' : '';
    }

    /** Die Zeile in Worten — beim Zuordnen muss sichtbar sein, worum es geht. */
    #[AsCallback(table: 'tl_gozi_404', target: 'fields.uebersicht.input_field')]
    public function uebersichtFeld(DataContainer $dc): string
    {
        $satz = $this->db->fetchAssociative('SELECT * FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [(int) $dc->id]);
        if (false === $satz) {
            return '';
        }
        $zeilen = [
            $this->feldName('pfad') => ('' !== (string) $satz['host'] ? 'https://'.$satz['host'].'/' : '/').$satz['pfad'],
            $this->feldName('zaehler') => (string) $satz['zaehler'],
            $this->feldName('erstmals') => Date::parse(Config::get('datimFormat'), (int) $satz['erstmals']),
            $this->feldName('tstamp') => Date::parse(Config::get('datimFormat'), (int) $satz['tstamp']),
        ];
        if ('' !== (string) $satz['verweis']) {
            $zeilen[$this->feldName('verweis')] = (string) $satz['verweis'];
        }
        $html = '<div class="widget clr"><table class="tl_show" style="width:100%">';
        foreach ($zeilen as $kopf => $wert) {
            $html .= '<tr><td class="tl_label" style="white-space:nowrap">'.StringUtil::specialchars($kopf).'</td><td>'.StringUtil::specialchars($wert).'</td></tr>';
        }

        return $html.'</table></div>';
    }

    private function feldName(string $feld): string
    {
        return $GLOBALS['TL_LANG']['tl_gozi_404'][$feld][0] ?? $feld;
    }

    /**
     * Backend-Adresse mit Rueckverweis und Anfrage-Token.
     *
     * Das Token ist Pflicht: DC_Table.php:144 laesst nur act=edit, show und select ohne durch — „create"
     * legt einen Datensatz an und wird deshalb geprueft.
     *
     * @param array<string, int|string> $parameter
     */
    private function backendUrl(array $parameter): string
    {
        $parameter['rt'] = $this->csrfTokenManager->getDefaultTokenValue();
        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request && null !== ($ref = $request->attributes->get('_contao_referer_id'))) {
            $parameter['ref'] = $ref;
        }

        return $this->router->generate('contao_backend', $parameter);
    }
}
