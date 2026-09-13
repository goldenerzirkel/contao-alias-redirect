<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_gozi_404']['pfad'] = ['Adresse', 'Der angefragte Pfad ohne Domain und ohne Abfrageteil.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['host'] = ['Domain', 'Die Domain, unter der die Adresse angefragt wurde.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zaehler'] = ['Aufrufe', 'Wie oft diese Adresse ins Leere gelaufen ist.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['tstamp'] = ['Zuletzt', 'Wann die Adresse zuletzt angefragt wurde.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['erstmals'] = ['Erstmals', 'Wann die Adresse zum ersten Mal ins Leere lief.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['verweis'] = ['Verweisgeber', 'Die Seite, von der aus der Aufruf kam (falls mitgeschickt).'];
$GLOBALS['TL_LANG']['tl_gozi_404']['erledigt'] = ['Erledigt', 'Abgehakt — entweder als Weiterleitung angelegt oder bewusst ohne Ziel gelassen.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['ziel_legend'] = 'Weiterleiten auf';
$GLOBALS['TL_LANG']['tl_gozi_404']['quelle_legend'] = 'Die aufgelaufene Adresse';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielTyp'] = ['Ziel', 'Eine Seite dieser Installation, eine beliebige Adresse (auch auf einem fremden Server) oder gar kein Ziel.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielSeite'] = ['Seite', 'Die Adresse wird als alter Alias an diese Seite gehängt (Feld „Weiterleitungen auf diese Seite"). Dort steht sie danach zusammen mit allen anderen alten Adressen dieser Seite.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielUrl'] = ['Zieladresse', 'Vollständige Adresse mit http(s)://. Ein Alias kann nur auf eine Seite dieser Installation zeigen — für eine fremde Adresse entsteht deshalb im Hintergrund ein Eintrag unter „Weiterleitungen".'];
$GLOBALS['TL_LANG']['tl_gozi_404']['code'] = ['Art der Weiterleitung', '301 sagt Suchmaschinen, dass die Adresse dauerhaft umgezogen ist. 302/303/307 sind vorübergehend.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielTypen'] = [
    'seite' => 'Seite in diesem System',
    'url' => 'Beliebige Adresse (extern)',
    'gone' => 'Kein Ziel (410 Gone)',
];
$GLOBALS['TL_LANG']['tl_gozi_404']['uebersicht'] = ['Übersicht', ''];
$GLOBALS['TL_LANG']['tl_gozi_404']['edit'] = ['Ziel festlegen', 'Für Adresse ID %s ein Ziel festlegen'];
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleiten'] = ['In den Weiterleitungen öffnen', 'Die Weiterleitung zu dieser Adresse unter „Weiterleitungen" öffnen oder dort eine neue anlegen'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFehltSeite'] = 'Diese Seite gibt es nicht mehr.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFremderHost'] = 'Die gewählte Seite gehört zu „%s", die Adresse lief unter „%s" auf. Ein Alias gilt nur innerhalb seines Seitenbaums — dafür bitte eine eigene Weiterleitung anlegen.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielKeinAlias'] = 'Die Adresse „%s" taugt nicht als Alias (erlaubt sind Buchstaben, Ziffern, Punkt, Bindestrich, Unterstrich und Schrägstrich). Dafür bitte eine eigene Weiterleitung anlegen.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielIstAlias'] = 'Das ist der heutige Alias dieser Seite — die Adresse müsste also erreichbar sein. Hier hilft keine Weiterleitung, sondern ein Blick auf Veröffentlichung und Seitenbaum.';
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleitungZeigen'] = 'Die Weiterleitung zu dieser Adresse bearbeiten';
$GLOBALS['TL_LANG']['tl_gozi_404']['toggle'] = ['Erledigt an/aus', 'Eintrag als erledigt kennzeichnen'];
$GLOBALS['TL_LANG']['tl_gozi_404']['show'] = ['Einzelheiten', 'Einzelheiten des Eintrags ID %s anzeigen'];
$GLOBALS['TL_LANG']['tl_gozi_404']['delete'] = ['Löschen', 'Eintrag ID %s löschen'];
