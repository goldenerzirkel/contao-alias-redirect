<?php

declare(strict_types=1);

/*
 * Eigener Navigationsbereich „Weiterleitungen": die Arbeitsliste der ins Leere laufenden Adressen und
 * die von Hand gepflegten Weiterleitungen. Die Weiterleitungen EINER Seite stehen weiterhin an der Seite
 * selbst (tl_page.gozi_redirects) — dafuer gibt es hier bewusst keinen zweiten Ort.
 *
 * Die Rechte regelt Contao von selbst: ein registriertes Backend-Modul steht in tl_user_group.modules
 * zur Auswahl.
 */
$GLOBALS['BE_MOD']['gozi_redirects'] = [
    'gozi_404' => [
        'tables' => ['tl_gozi_404'],
    ],
    'gozi_redirect' => [
        'tables' => ['tl_gozi_redirect'],
    ],
];
