<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_gozi_redirect']['quelle_legend'] = 'Alte Adresse';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['ziel_legend'] = 'Ziel';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['hinweis_legend'] = 'Notiz';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['publish_legend'] = 'Veröffentlichung';

$GLOBALS['TL_LANG']['tl_gozi_redirect']['pfad'] = ['Alte Adresse', 'Der Pfad ohne Rechnernamen und ohne Schrägstrich am Anfang, z. B. „unternehmen/alte-seite". Eine Endung .html wird ignoriert.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['host'] = ['Rechnername', 'Nur für diesen Host, z. B. „de.pons.com". Leer lassen, wenn die Weiterleitung auf allen Hosts gelten soll.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielTyp'] = ['Art des Ziels', 'Eine Seite im Seitenbaum, eine beliebige Adresse oder gar kein Ziel (410 Gone).'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielSeite'] = ['Zielseite', 'Die Seite, auf die weitergeleitet wird — auch in einem anderen Seitenbaum.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielUrl'] = ['Zieladresse', 'Vollständige Adresse mit http(s)://.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['code'] = ['Art der Weiterleitung', '301 sagt Suchmaschinen, dass die Adresse dauerhaft umgezogen ist. 302/303/307 sind vorübergehend.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['hinweis'] = ['Notiz', 'Wozu diese Weiterleitung angelegt wurde — nur für die Redaktion sichtbar.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['aktiv'] = ['Aktiv', 'Nur aktive Weiterleitungen greifen.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zaehler'] = ['Treffer', 'Wie oft die Weiterleitung gegriffen hat.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['letzter'] = ['Zuletzt', 'Wann die Weiterleitung zuletzt gegriffen hat.'];

$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielTypen'] = [
    'seite' => 'Seite im Seitenbaum',
    'url' => 'Beliebige Adresse',
    'gone' => 'Kein Ziel (410 Gone)',
];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['codes'] = [
    '301' => '301 — dauerhaft umgezogen',
    '302' => '302 — vorübergehend',
    '303' => '303 — siehe dort',
    '307' => '307 — vorübergehend, Methode bleibt',
    '308' => '308 — dauerhaft, Methode bleibt',
];

$GLOBALS['TL_LANG']['tl_gozi_redirect']['new'] = ['Neue Weiterleitung', 'Eine neue Weiterleitung anlegen'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['edit'] = ['Bearbeiten', 'Weiterleitung ID %s bearbeiten'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['copy'] = ['Duplizieren', 'Weiterleitung ID %s duplizieren'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['delete'] = ['Löschen', 'Weiterleitung ID %s löschen'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['toggle'] = ['Aktiv an/aus', 'Weiterleitung ID %s an- oder abschalten'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['show'] = ['Einzelheiten', 'Einzelheiten der Weiterleitung ID %s anzeigen'];
