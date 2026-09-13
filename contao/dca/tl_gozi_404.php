<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Gozi\AliasRedirectBundle\Service\ManualRedirects;

/*
 * Die ins Leere laufenden Adressen — Arbeitsliste.
 *
 * Eintraege entstehen ausschliesslich beim Auflaufen einer Anfrage (RecordNotFoundListener), deshalb
 * nicht anlegbar. Bearbeitet wird nur das Ziel.
 *
 * Drei Ziele, in EINER Maske — der Redakteur soll nicht erst wissen muessen, welches Werkzeug zustaendig ist:
 *  - Seite im Seitenbaum: die Adresse wandert als alter Alias in tl_page.gozi_redirects dieser Seite.
 *    Damit steht an einer Seite, welche alten Adressen auf sie zeigen, versioniert mit der Seite.
 *  - Beliebige Adresse: geht als Alias nicht (ein Alias zeigt immer auf eine Seite dieser Installation),
 *    deshalb entsteht im Hintergrund ein Eintrag in tl_gozi_redirect.
 *  - Kein Ziel: 410 Gone, ebenfalls ueber tl_gozi_redirect.
 *
 * Der Haken „erledigt" ist fuer alles, was gar keine Weiterleitung verdient (Scanner, Tippfehler).
 */
$GLOBALS['TL_DCA']['tl_gozi_404'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'notCreatable' => true,
        'notCopyable' => true,
        'enableVersioning' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pfad,host' => 'unique',
                'tstamp' => 'index',
                'erledigt' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTABLE,
            'fields' => ['tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
            'defaultSearchField' => 'pfad',
        ],
        'label' => [
            'fields' => ['pfad', 'host', 'zaehler', 'tstamp', 'zielTyp'],
            'showColumns' => true,
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'alias.svg',
                'primary' => true,
            ],
            'weiterleiten' => [
                'href' => '',
                'icon' => 'forward_1.svg',
            ],
            'toggle' => [
                'href' => 'act=toggle&amp;field=erledigt',
                'icon' => 'visible.svg',
            ],
            'show' => [],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(document.getElementById(\'tl_confirm\').textContent))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => ['zielTyp'],
        'default' => '{ziel_legend},zielTyp;{quelle_legend},uebersicht',
    ],
    'subpalettes' => [
        'zielTyp_'.ManualRedirects::ZIEL_SEITE => 'zielSeite',
        'zielTyp_'.ManualRedirects::ZIEL_URL => 'zielUrl,code',
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => [
            'sorting' => true,
            'flag' => DataContainer::SORT_DAY_DESC,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'erstmals' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'pfad' => [
            'search' => true,
            'sorting' => true,
            'sql' => "varchar(255) COLLATE utf8mb4_bin NOT NULL default ''",
        ],
        'host' => [
            'filter' => true,
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'root' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'verweis' => [
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'zaehler' => [
            'sorting' => true,
            'flag' => DataContainer::SORT_DESC,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        // Kein Datenbankfeld: die Zeile in Worten, damit beim Zuordnen sichtbar ist, worum es geht.
        'uebersicht' => [
            'eval' => ['tl_class' => 'clr'],
        ],
        'zielTyp' => [
            'exclude' => true,
            'inputType' => 'select',
            'options' => [ManualRedirects::ZIEL_SEITE, ManualRedirects::ZIEL_URL, ManualRedirects::ZIEL_GONE],
            'reference' => &$GLOBALS['TL_LANG']['tl_gozi_404']['zielTypen'],
            'eval' => ['submitOnChange' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default '".ManualRedirects::ZIEL_SEITE."'",
        ],
        'zielSeite' => [
            'exclude' => true,
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'zielUrl' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 1022, 'tl_class' => 'clr long'],
            'sql' => "varchar(1022) NOT NULL default ''",
        ],
        'code' => [
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['301', '302', '303', '307', '308'],
            'reference' => &$GLOBALS['TL_LANG']['tl_gozi_redirect']['codes'],
            'eval' => ['tl_class' => 'w50'],
            'sql' => "varchar(3) NOT NULL default '301'",
        ],
        'erledigt' => [
            'toggle' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => ['type' => 'boolean', 'default' => false],
        ],
        'redirect' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
    ],
];
