<?php

declare(strict_types=1);

/*
 * Weiterleitungen AN DER UEBERSETZUNG — dieselbe Liste wie an der Seite, nur je Sprache.
 *
 * Eine Uebersetzung traegt einen eigenen Alias (gozi-i18nl10n). Wird er geaendert, ist die alte
 * fremdsprachige Adresse ohne diese Liste tot — und zwar nicht als sauberes 404: Contao routet den
 * Pfad auf die Elternseite und haengt den Rest als Parameter an, was in einer 301-Kette auf eine
 * Adresse endet, die es nicht gibt (gemessen 13.09.2026 an /es/service-center/…).
 *
 * Die Eintraege gelten NUR in ihrer Sprache: derselbe alte Alias kann in zwei Sprachen zu
 * verschiedenen Seiten gehoeren. Der Index haelt die Sprache deshalb je Zeile fest.
 *
 * Ohne gozi-i18nl10n gibt es diese Tabelle nicht — dann tut diese Datei nichts.
 */
if (!isset($GLOBALS['TL_DCA']['tl_page_i18nl10n']['config'])) {
    return;
}

$GLOBALS['TL_DCA']['tl_page_i18nl10n']['fields']['gozi_redirects'] = [
    'inputType' => 'listWizard',
    'exclude' => true,
    'eval' => ['multiple' => true, 'tl_class' => 'clr', 'doNotCopy' => true, 'rgxp' => 'folderalias', 'maxlength' => 255],
    'sql' => ['type' => 'blob', 'notnull' => false],
];
