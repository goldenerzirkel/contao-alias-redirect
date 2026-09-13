<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Die ins Leere laufenden Adressen — Arbeitsliste.
 *
 * Eintraege entstehen ausschliesslich beim Auflaufen einer Anfrage (RecordNotFoundListener), deshalb
 * nicht anlegbar. Bearbeitet wird genau ein Feld: die Seite, an die die Adresse gehaengt wird.
 *
 * Der Regelweg ist die Alias-Weiterleitung AN DER SEITE: die Adresse wandert in tl_page.gozi_redirects
 * der gewaehlten Seite. Damit steht an einer Seite, welche alten Adressen auf sie zeigen — an einem Ort,
 * versioniert mit der Seite. Fuer alles, was so nicht geht (externes Ziel, andere Marke, 410, Adresse
 * taugt nicht als Alias), fuehrt der zweite Knopf in die eigene Weiterleitungstabelle.
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
            'palettes' => [
        'default' => '{ziel_legend},zielSeite;{quelle_legend},uebersicht',
    ],
    'fields' => ['tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
            'defaultSearchField' => 'pfad',
        ],
        'label' => [
            'palettes' => [
        'default' => '{ziel_legend},zielSeite;{quelle_legend},uebersicht',
    ],
    'fields' => ['pfad', 'host', 'zaehler', 'tstamp'],
            'showColumns' => true,
        ],
        'operations' => [
            'anSeite' => [
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
        'default' => '{ziel_legend},zielSeite;{quelle_legend},uebersicht',
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
        'zielSeite' => [
            'exclude' => true,
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
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
