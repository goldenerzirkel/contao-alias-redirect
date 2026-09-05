<?php

declare(strict_types=1);

/*
 * Alias-Weiterleitungen: Dienst gegen die echte Datenbank, Frontend per HTTP.
 * Legt eine Wegwerf-Seite unter der ersten Wurzel an und raeumt sie am Ende (auch nach Fatal) weg.
 *
 * Aufruf aus der Projektwurzel:
 *   /Applications/MAMP/bin/php/php8.4.17/bin/php bundles/contao-alias-redirect/tests/alias-redirect-suite.php
 */

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;
use Symfony\Component\Console\Input\ArgvInput;

require getcwd().'/vendor/autoload.php';
$kernel = ContaoKernel::fromInput(getcwd(), new ArgvInput([]));
$kernel->boot();
$c = $kernel->getContainer();
$c->get('contao.framework')->initialize();
$db = $c->get('database_connection');

$pass = 0; $fail = 0;
function ok(bool $cond, string $name, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { ++$pass; echo "  ✅ $name\n"; } else { ++$fail; echo "  ❌ $name".($detail ? " — $detail" : '')."\n"; }
}

$svc = new AliasRedirects($db);

echo "## Listenlogik (ohne Datenbank)\n";
ok(['alt'] === $svc->mitAltemAlias(null, 'alt', 'neu'), 'leere Liste + alter Alias → [alt]');
ok(['alt', 'uralt'] === $svc->mitAltemAlias(['uralt'], 'alt', 'neu'), 'alter Alias kommt VORN dazu, Bestand bleibt');
ok(['uralt'] === $svc->mitAltemAlias(['uralt', 'neu'], 'neu', 'neu'), 'kein Wechsel → nur bereinigt, eigener Alias raus');
ok(['a', 'b'] === $svc->bereinige(['/a/', ' b ', '', 'a', 'mein-alias'], 'mein-alias'), 'bereinige: Schraegstriche, Leere, Doppelte, eigener Alias');
ok(['alt'] === $svc->mitAltemAlias(serialize(['alt', 'neu']), 'alt', 'neu'), 'serialisierte Liste: neuer Alias faellt raus, alter bleibt einmal');

echo "\n## Aufloesung gegen die Datenbank\n";
$root = (int) $db->fetchOne("SELECT id FROM tl_page WHERE type = 'root' AND published = 1 ORDER BY sorting LIMIT 1");
ok($root > 0, 'eine veroeffentlichte Wurzel gefunden');
$db->insert('tl_page', ['pid' => $root, 'sorting' => 999999, 'tstamp' => time(), 'title' => 'ZZZ Alias-Test', 'alias' => 'zzz-alias-neu', 'type' => 'regular', 'published' => 1, 'gozi_redirects' => serialize(['zzz-alias-alt', 'zzz/alias/ordner'])]);
$seite = (int) $db->lastInsertId();
register_shutdown_function(static function () use ($db, $seite): void { if ($seite > 0) { $db->delete('tl_page', ['id' => $seite]); echo "\n(aufgeraeumt: Testseite #$seite)\n"; } });
ok($seite > 0, "Testseite #$seite angelegt (alias zzz-alias-neu, Weiterleitungen zzz-alias-alt, zzz/alias/ordner)");
ok($svc->finde('zzz-alias-alt') === $seite, 'finde(zzz-alias-alt) → Testseite');
ok($svc->finde('/zzz-alias-alt/') === $seite, 'Schraegstriche stoeren nicht');
ok($svc->finde('zzz/alias/ordner') === $seite, 'Ordner-Alias wird gefunden');
ok($svc->finde('zzz-alias-alt', [$root]) === $seite, 'mit passender Wurzel');
ok(null === $svc->finde('zzz-alias-alt', [999999]), 'mit fremder Wurzel: nichts');
ok(null === $svc->finde('zzz-alias-neu'), 'der echte Alias ist keine Weiterleitung');
ok(null === $svc->finde('zzz-alias-al'), 'kein Teiltreffer (LIKE-Vorauswahl wird exakt geprueft)');
ok($svc->rootId($seite) === $root, 'rootId ueber die pid-Kette');

echo "\n## Frontend per HTTP (301 auf die Seite)\n";
$dns = (string) $db->fetchOne('SELECT dns FROM tl_page WHERE id = ?', [$root]);
$host = '' !== $dns ? $dns : 'goldener-zirkel.int';
$basis = 'https://'.$host;
$hole = static function (string $pfad) use ($basis): array {
    $ch = curl_init($basis.$pfad);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 20]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    preg_match('/^location:\s*(.+)$/im', $raw, $m);
    return [$code, trim($m[1] ?? '')];
};
[$code, $ort] = $hole('/zzz-alias-alt');
ok(301 === $code && str_contains($ort, '/zzz-alias-neu'), "GET /zzz-alias-alt → 301 auf …/zzz-alias-neu", "$code $ort");
[$code, $ort] = $hole('/zzz-alias-alt.html');
ok(301 === $code && str_contains($ort, '/zzz-alias-neu'), "GET /zzz-alias-alt.html → 301 (Suffix wird abgestreift)", "$code $ort");
[$code, $ort] = $hole('/zzz/alias/ordner?x=1');
ok(301 === $code && str_contains($ort, '/zzz-alias-neu') && str_contains($ort, 'x=1'), 'Ordner-Alias + Query bleibt erhalten', "$code $ort");
[$code, $ort] = $hole('/zzz-alias-neu');
ok(200 === $code, 'der echte Alias liefert 200 — er gewinnt immer', (string) $code);
[$code] = $hole('/zzz-gibt-es-nicht');
ok(404 === $code, 'Unbekanntes bleibt 404', (string) $code);

echo "\n## Mit veroeffentlichter 404-Seite (Contao wirft dann KEINE Exception — Route tl_page.<id>.error_404)\n";
// Befund pons-contao 05.09.2026: der Exception-Weg sah nie etwas, wenn die Wurzel eine 404-Seite hat.
$db->insert('tl_page', ['pid' => $root, 'sorting' => 999997, 'tstamp' => time(), 'title' => 'ZZZ 404', 'alias' => 'zzz-404', 'type' => 'error_404', 'published' => 1]);
$seite404 = (int) $db->lastInsertId();
register_shutdown_function(static function () use ($db, $seite404): void { if ($seite404 > 0) { $db->delete('tl_page', ['id' => $seite404]); echo "(aufgeraeumt: 404-Seite #$seite404)\n"; } });
$c->get('contao.framework')->getAdapter(\Contao\Controller::class);
// Routen-Cache: die 404-Route entsteht aus tl_page — Cache leeren, damit der Router sie kennt.
exec('bash clear-cache.sh 2>&1', $o, $rc);
[$code, $ort] = $hole('/zzz-alias-alt');
ok(301 === $code && str_contains($ort, '/zzz-alias-neu'), 'MIT 404-Seite: GET /zzz-alias-alt → 301 (Request-Weg)', "$code $ort");
[$code, $ort] = $hole('/zzz/alias/ordner?x=1');
ok(301 === $code && str_contains($ort, 'x=1'), 'MIT 404-Seite: Ordner-Alias + Query', "$code $ort");
[$code] = $hole('/zzz-gibt-es-nicht');
ok(404 === $code, 'MIT 404-Seite: Unbekanntes liefert die 404-Seite (Status 404)', (string) $code);
$db->delete('tl_page', ['id' => $seite404]); $seite404 = 0;
exec('bash clear-cache.sh 2>&1');

echo "\n".str_repeat('=', 50)."\nERGEBNIS: $pass bestanden, $fail fehlgeschlagen\n";
exit($fail > 0 ? 1 : 0);
