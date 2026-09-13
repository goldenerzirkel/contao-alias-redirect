<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['tl_gozi_redirect']['quelle_legend'] = 'Old address';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['ziel_legend'] = 'Target';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['hinweis_legend'] = 'Note';
$GLOBALS['TL_LANG']['tl_gozi_redirect']['publish_legend'] = 'Publish';

$GLOBALS['TL_LANG']['tl_gozi_redirect']['pfad'] = ['Old address', 'The path without host and without leading slash, e.g. "company/old-page". A .html suffix is ignored.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['host'] = ['Host', 'For this host only, e.g. "de.pons.com". Leave empty to apply on all hosts.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielTyp'] = ['Type of target', 'A page in the page tree, any address, or no target at all (410 Gone).'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielSeite'] = ['Target page', 'The page to redirect to — in any page tree.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielUrl'] = ['Target address', 'Full address including http(s)://.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['code'] = ['Type of redirect', '301 tells search engines the address has moved permanently. 302/303/307 are temporary.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['hinweis'] = ['Note', 'Why this redirect was created — visible to editors only.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['aktiv'] = ['Active', 'Only active redirects apply.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['zaehler'] = ['Hits', 'How often the redirect has applied.'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['letzter'] = ['Last hit', 'When the redirect applied last.'];

$GLOBALS['TL_LANG']['tl_gozi_redirect']['zielTypen'] = [
    'seite' => 'Page in the page tree',
    'url' => 'Any address',
    'gone' => 'No target (410 Gone)',
];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['codes'] = [
    '301' => '301 — moved permanently',
    '302' => '302 — found (temporary)',
    '303' => '303 — see other',
    '307' => '307 — temporary, method preserved',
    '308' => '308 — permanent, method preserved',
];

$GLOBALS['TL_LANG']['tl_gozi_redirect']['new'] = ['New redirect', 'Create a new redirect'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['edit'] = ['Edit', 'Edit redirect ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['copy'] = ['Duplicate', 'Duplicate redirect ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['delete'] = ['Delete', 'Delete redirect ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['toggle'] = ['Toggle active', 'Enable or disable redirect ID %s'];
$GLOBALS['TL_LANG']['tl_gozi_redirect']['show'] = ['Details', 'Show details of redirect ID %s'];
