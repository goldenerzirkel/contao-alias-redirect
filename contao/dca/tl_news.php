<?php

declare(strict_types=1);

/*
 * Alte Aliase an Nachrichten und Terminen — dieselben zwei Felder wie an der Seite; in die Paletten hängt sie
 * RecordAliasListener (onload) hinter den Alias.
 */
$GLOBALS['TL_DCA']['tl_news']['fields']['gozi_noRedirect'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['tl_class' => 'w50 m12', 'doNotCopy' => true],
    'sql' => ['type' => 'boolean', 'default' => false],
];
$GLOBALS['TL_DCA']['tl_news']['fields']['gozi_redirects'] = [
    'inputType' => 'listWizard',
    'exclude' => true,
    'eval' => ['multiple' => true, 'tl_class' => 'clr', 'doNotCopy' => true, 'rgxp' => 'folderalias', 'maxlength' => 255],
    'sql' => ['type' => 'blob', 'notnull' => false],
];
