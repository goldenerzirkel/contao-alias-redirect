<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;
use Gozi\AliasRedirectBundle\Backend\Aus404;
use Gozi\AliasRedirectBundle\Service\ManualRedirects;

/*
 * Weiterleitungen von Hand — ueber Wurzel- und Markengrenzen hinweg.
 *
 * Je Zeile genau eine Adresse. Muster und Platzhalter bleiben bei terminal42/contao-url-rewrite
 * (siehe docs/vergleich-terminal42-url-rewrite.md), damit nicht zwei Werkzeuge dasselbe tun.
 *
 * Die Vorbelegung beim Anlegen aus einem 404-Eintrag laeuft ueber „default" als Closure: DC_Table::create()
 * ruft sie beim Anlegen auf (DC_Table.php:809-823) — kein eigener onload-Callback, kein eigener Controller.
 */
$GLOBALS['TL_DCA']['tl_gozi_redirect'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pfad,host' => 'index',
                'aktiv' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTABLE,
            'fields' => ['pfad'],
            'panelLayout' => 'filter;search,limit',
            'defaultSearchField' => 'pfad',
        ],
        'label' => [
            'fields' => ['pfad', 'host', 'code', 'zaehler'],
            'showColumns' => true,
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
                'primary' => true,
            ],
            'copy' => ['href' => 'act=copy', 'icon' => 'copy.svg'],
            'toggle' => [
                'href' => 'act=toggle&amp;field=aktiv',
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
        'default' => '{quelle_legend},pfad,host;{ziel_legend},zielTyp;{hinweis_legend:hide},hinweis;{publish_legend},aktiv',
    ],
    'subpalettes' => [
        'zielTyp_'.ManualRedirects::ZIEL_SEITE => 'zielSeite,code',
        'zielTyp_'.ManualRedirects::ZIEL_URL => 'zielUrl,code',
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => 'int(10) unsigned NOT NULL default 0'],
        'pfad' => [
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'inputType' => 'text',
            'default' => static fn () => Aus404::pfad(),
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) COLLATE utf8mb4_bin NOT NULL default ''",
        ],
        'host' => [
            'exclude' => true,
            'filter' => true,
            'search' => true,
            'inputType' => 'text',
            'default' => static fn () => Aus404::host(),
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50', 'decodeEntities' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'zielTyp' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'options' => [ManualRedirects::ZIEL_SEITE, ManualRedirects::ZIEL_URL, ManualRedirects::ZIEL_GONE],
            'reference' => &$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielTypen'],
            'eval' => ['mandatory' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(16) NOT NULL default '".ManualRedirects::ZIEL_SEITE."'",
        ],
        'zielSeite' => [
            'exclude' => true,
            'inputType' => 'pageTree',
            'foreignKey' => 'tl_page.title',
            'eval' => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql' => 'int(10) unsigned NOT NULL default 0',
            'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'zielUrl' => [
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 1022, 'tl_class' => 'clr long'],
            'sql' => "varchar(1022) NOT NULL default ''",
        ],
        'code' => [
            'exclude' => true,
            'filter' => true,
            'inputType' => 'select',
            'options' => ['301', '302', '303', '307', '308'],
            'reference' => &$GLOBALS['TL_LANG']['tl_gozi_redirect']['codes'],
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(3) NOT NULL default '301'",
        ],
        'hinweis' => [
            'exclude' => true,
            'search' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'clr long'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'aktiv' => [
            'toggle' => true,
            'filter' => true,
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['doNotCopy' => true],
            'sql' => ['type' => 'boolean', 'default' => true],
        ],
        'zaehler' => [
            'sorting' => true,
            'flag' => DataContainer::SORT_DESC,
            'eval' => ['doNotCopy' => true],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'letzter' => [
            'eval' => ['doNotCopy' => true],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'aus404' => [
            'default' => static fn () => Aus404::id(),
            'eval' => ['doNotCopy' => true],
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
    ],
];
