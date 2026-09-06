<?php

declare(strict_types=1);

// Einmal-Schalter: „Beim Speichern KEINE Weiterleitung anlegen“. Wird nach dem Speichern zurueckgesetzt
// (siehe PageAliasListener), damit er nicht still fuer alle kuenftigen Umbenennungen gilt.
$GLOBALS['TL_DCA']['tl_page']['fields']['gozi_noRedirect'] = [
    'inputType' => 'checkbox',
    'exclude' => true,
    'eval' => ['tl_class' => 'w50 m12', 'doNotCopy' => true],
    'sql' => ['type' => 'boolean', 'default' => false],
];

// Die Weiterleitungen dieser Seite — Contaos Listen-Assistent: anlegen, bearbeiten, loeschen wie jedes
// andere Feld, und damit in der Versionierung von tl_page. Der alte Alias kommt beim Umbenennen von selbst
// dazu (PageAliasListener). Kein eigener Navigationspunkt.
$GLOBALS['TL_DCA']['tl_page']['fields']['gozi_redirects'] = [
    'inputType' => 'listWizard',
    'exclude' => true,
    'eval' => ['multiple' => true, 'tl_class' => 'clr', 'doNotCopy' => true, 'rgxp' => 'folderalias', 'maxlength' => 255],
    'sql' => ['type' => 'blob', 'notnull' => false],
];

// Seitentyp „Entfernt (410 Gone)": Alias und Liste alter Aliase wie bei jeder Seite, dazu Layout und
// Veröffentlichung. Die Felder gozi_noRedirect/gozi_redirects hängt der onload-Callback hinter den Alias.
$GLOBALS['TL_DCA']['tl_page']['palettes']['gozi_gone'] = '{title_legend},title,alias,type;{meta_legend},pageTitle,robots,description;{layout_legend:hide},includeLayout;{publish_legend},published,start,stop';
