<?php

declare(strict_types=1);

/*
 * Index alter Aliase — nur Schema, kein Backend-Modul. Die Wahrheit steht an der Seite (gozi_redirects);
 * diese Tabelle wird aus ihr abgeleitet (AliasIndex) und ist hier deklariert, damit contao:migrate sie
 * kennt und nicht zum Löschen vorschlägt.
 */
$GLOBALS['TL_DCA']['tl_gozi_alias_redirect'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'alias' => 'index',
                'pid' => 'index',
                'quelle,pid' => 'index',
            ],
        ],
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'quelle' => ['sql' => "varchar(32) NOT NULL default 'tl_page'"],
        'alias' => ['sql' => "varchar(255) COLLATE utf8mb4_bin NOT NULL default ''"],
        'root' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'pid' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'gone' => ['sql' => "tinyint(1) NOT NULL default 0"],
    ],
];
