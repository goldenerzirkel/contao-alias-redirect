<?php

declare(strict_types=1);

/*
 * Alte Aliase an Nachrichten, Terminen und FAQ — dieselben zwei Felder wie an der Seite; in die Paletten hängt sie
 * RecordAliasListener (onload) hinter den Alias.
 */
// Ohne das zugehörige Contao-Bundle gibt es tl_faq nicht. Ohne diese Bedingung legte
// contao:migrate eine Rumpftabelle tl_faq nur mit diesen beiden Spalten an (DcaSchemaProvider
// ruft createTable für jede Tabelle mit sql-Feldern). Gleiches Muster wie tl_page_i18nl10n.php;
// die Ladereihenfolge in Plugin.php stellt sicher, dass config bei vorhandenem Bundle gesetzt ist.
if (!isset($GLOBALS['TL_DCA']['tl_faq']['config'])) {
    return;
}

$GLOBALS['TL_DCA']['tl_faq']['fields']['gozi_noRedirect'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['tl_class' => 'w50 m12', 'doNotCopy' => true],
    'sql' => ['type' => 'boolean', 'default' => false],
];
$GLOBALS['TL_DCA']['tl_faq']['fields']['gozi_redirects'] = [
    'inputType' => 'listWizard',
    'exclude' => true,
    'eval' => ['multiple' => true, 'tl_class' => 'clr', 'doNotCopy' => true, 'rgxp' => 'folderalias', 'maxlength' => 255],
    'sql' => ['type' => 'blob', 'notnull' => false],
];
