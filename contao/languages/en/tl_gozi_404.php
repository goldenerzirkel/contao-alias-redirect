<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_gozi_404']['pfad'] = ['Address', 'The requested path without host and query string.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['host'] = ['Host', 'The host the address was requested on.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zaehler'] = ['Requests', 'How often this address ran into nothing.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['tstamp'] = ['Last seen', 'When the address was requested last.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['erstmals'] = ['First seen', 'When the address ran into nothing for the first time.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['verweis'] = ['Referrer', 'The page the request came from (if sent).'];
$GLOBALS['TL_LANG']['tl_gozi_404']['erledigt'] = ['Done', 'Handled — either redirected or deliberately left without a target.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['ziel_legend'] = 'Redirect to';
$GLOBALS['TL_LANG']['tl_gozi_404']['quelle_legend'] = 'The address that ran into nothing';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielTyp'] = ['Target', 'A page of this installation, any address (including a foreign server), or no target at all.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielSeite'] = ['Page', 'The address is added to this page as an old alias (field "Redirects to this page"), together with all other old addresses of that page.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielUrl'] = ['Target address', 'Full address including http(s)://. An alias can only point to a page of this installation — for a foreign address an entry under "Redirects" is created in the background.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['code'] = ['Type of redirect', '301 tells search engines the address has moved permanently. 302/303/307 are temporary.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielTypen'] = [
    'seite' => 'Page in this system',
    'url' => 'Any address (external)',
    'gone' => 'No target (410 Gone)',
];
$GLOBALS['TL_LANG']['tl_gozi_404']['uebersicht'] = ['Overview', ''];
$GLOBALS['TL_LANG']['tl_gozi_404']['edit'] = ['Set target', 'Set a target for address ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleiten'] = ['Open in redirects', 'Open the redirect for this address under "Redirects" or create a new one there'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFehltSeite'] = 'This page no longer exists.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFremderHost'] = 'The chosen page belongs to "%s", the address ran into nothing on "%s". An alias only applies within its page tree — please create an own redirect instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielKeinAlias'] = 'The address "%s" cannot be an alias (allowed are letters, digits, dot, hyphen, underscore and slash). Please create an own redirect instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielIstAlias'] = 'This is the current alias of that page, so the address should be reachable. A redirect will not help here — check publication and page tree instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleitungZeigen'] = 'Edit the redirect for this address';
$GLOBALS['TL_LANG']['tl_gozi_404']['toggle'] = ['Toggle done', 'Mark entry as done'];
$GLOBALS['TL_LANG']['tl_gozi_404']['show'] = ['Details', 'Show details of entry ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_404']['delete'] = ['Delete', 'Delete entry ID %s'];
