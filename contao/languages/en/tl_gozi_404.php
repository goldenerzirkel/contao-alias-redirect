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
$GLOBALS['TL_LANG']['tl_gozi_404']['zielSeite'] = ['Page', 'The address is added to this page as an old alias (field "Redirects to this page"), together with all other old addresses of that page.'];
$GLOBALS['TL_LANG']['tl_gozi_404']['uebersicht'] = ['Overview', ''];
$GLOBALS['TL_LANG']['tl_gozi_404']['anSeite'] = ['Attach to a page', 'Attach address ID %s to a page as an old alias'];
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleiten'] = ['Own redirect', 'Create an own redirect for this address — for targets that cannot be an alias (other brand, external address, 410)'];
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFehltSeite'] = 'This page no longer exists.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielFremderHost'] = 'The chosen page belongs to "%s", the address ran into nothing on "%s". An alias only applies within its page tree — please create an own redirect instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielKeinAlias'] = 'The address "%s" cannot be an alias (allowed are letters, digits, dot, hyphen, underscore and slash). Please create an own redirect instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['zielIstAlias'] = 'This is the current alias of that page, so the address should be reachable. A redirect will not help here — check publication and page tree instead.';
$GLOBALS['TL_LANG']['tl_gozi_404']['weiterleitungZeigen'] = 'Edit the redirect for this address';
$GLOBALS['TL_LANG']['tl_gozi_404']['toggle'] = ['Toggle done', 'Mark entry as done'];
$GLOBALS['TL_LANG']['tl_gozi_404']['show'] = ['Details', 'Show details of entry ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_404']['delete'] = ['Delete', 'Delete entry ID %s'];
