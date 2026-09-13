<?php

declare(strict_types=1);

/*
 * 404-Liste und Weiterleitungen: Dienste gegen die echte Datenbank, Frontend per HTTP.
 * Legt Wegwerf-Daten an und raeumt sie am Ende (auch nach Fatal) weg.
 *
 * Aufruf aus der Projektwurzel:
 *   /Applications/MAMP/bin/php/php8.4.17/bin/php vendor/gozi/contao-alias-redirect/tests/404-liste-suite.php
 */

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Gozi\AliasRedirectBundle\Backend\NotFoundCallbacks;
use Gozi\AliasRedirectBundle\Service\AliasIndex;
use Gozi\AliasRedirectBundle\Service\AliasRedirects;
use Gozi\AliasRedirectBundle\Service\ManualRedirects;
use Gozi\AliasRedirectBundle\Service\NotFoundLog;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpFoundation\Request;

require getcwd().'/vendor/autoload.php';
$kernel = ContaoKernel::fromInput(getcwd(), new ArgvInput([]));
$kernel->boot();
$c = $kernel->getContainer();
$c->get('contao.framework')->initialize();
// Die Meldungen der Callbacks kommen aus der Sprachdatei — im Backend laedt Contao sie zur Tabelle,
// hier von Hand. Damit prueft die Suite nebenbei, dass die Schluessel und ihre Platzhalter stimmen.
\Contao\System::loadLanguageFile('tl_gozi_404');
$db = $c->get('database_connection');

$pass = 0; $fail = 0;
function ok(bool $cond, string $name, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { ++$pass; echo "  ✅ $name\n"; } else { ++$fail; echo "  ❌ $name".($detail ? " — $detail" : '')."\n"; }
}

$log = new NotFoundLog($db);
$vonHand = new ManualRedirects($db);
$aliase = new AliasRedirects($db);
$index = new AliasIndex($db, $aliase);
$callbacks = new NotFoundCallbacks($db, $log, $vonHand, $aliase, $index);

$marke = 'zzz404-'.bin2hex(random_bytes(3));
$seite = 0;
register_shutdown_function(static function () use ($db, &$seite, $marke, $index): void {
    $db->executeStatement('DELETE FROM '.NotFoundLog::TABELLE.' WHERE pfad LIKE ?', [$marke.'%']);
    $db->executeStatement('DELETE FROM '.ManualRedirects::TABELLE.' WHERE pfad LIKE ?', [$marke.'%']);
    if ($seite > 0) { $db->delete('tl_page', ['id' => $seite]); }
    $index->neuAufbauen();
    echo "\n(aufgeraeumt: $marke)\n";
});

echo "## Was gehoert ins Protokoll\n";
$anfrage = static fn (string $pfad, string $methode = 'GET') => Request::create('https://example.org'.$pfad, $methode);
ok($log->gehoertHinein($anfrage('/unternehmen/alte-seite')), 'normale Adresse: ja');
ok(!$log->gehoertHinein($anfrage('/unternehmen/alte-seite', 'POST')), 'POST: nein');
ok(!$log->gehoertHinein($anfrage('/contao/login')), 'Backend: nein');
ok(!$log->gehoertHinein($anfrage('/_contao/preview')), 'Contao-Innenleben: nein');
ok(!$log->gehoertHinein($anfrage('/files/layout/stil.css')), 'Stylesheet: nein');
ok(!$log->gehoertHinein($anfrage('/files/bild.PNG')), 'Bild, auch in Grossbuchstaben: nein');
ok($log->gehoertHinein($anfrage('/unternehmen/bericht.pdf')), 'PDF: ja (kann eine umgezogene Datei sein)');
ok(!$log->gehoertHinein($anfrage('/')), 'Startseite: nein');

echo "\n## Adressen normalisieren\n";
ok('a/b' === $log->normalisiere('/a/b/'), 'Schraegstriche aussen weg');
ok('a/b' === $log->normalisiere('/a/b.html'), '.html weg');
ok('a/b' === $vonHand->normalisiere('a/b.htm'), 'beide Dienste schreiben gleich');
ok('ae ue' === $log->normalisiere('/ae%20ue'), 'Prozentkodierung aufgeloest');

echo "\n## Protokoll fuellen\n";
$id = $log->erfasse('/'.$marke.'/eins', 'example.org', 0, 'https://fremd.example/seite');
ok($id > 0, "Eintrag angelegt (#$id)");
$zweit = $log->erfasse('/'.$marke.'/eins', 'example.org');
ok($zweit === $id, 'dieselbe Adresse: kein zweiter Eintrag');
ok(2 === (int) $db->fetchOne('SELECT zaehler FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$id]), 'Zaehler steht auf 2');
ok('https://fremd.example/seite' === (string) $db->fetchOne('SELECT verweis FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$id]), 'Verweisgeber gemerkt');
$anderer = $log->erfasse('/'.$marke.'/eins', 'andere.example');
ok($anderer > 0 && $anderer !== $id, 'gleiche Adresse auf anderem Host: eigener Eintrag');

echo "\n## An eine Seite haengen (der Regelweg)\n";
$root = (int) $db->fetchOne("SELECT id FROM tl_page WHERE type = 'root' AND published = 1 ORDER BY sorting LIMIT 1");
$dns = (string) $db->fetchOne('SELECT dns FROM tl_page WHERE id = ?', [$root]);
$praefix = trim((string) $db->fetchOne('SELECT urlPrefix FROM tl_page WHERE id = ?', [$root]), '/');
$db->insert('tl_page', ['pid' => $root, 'sorting' => 999999, 'tstamp' => time(), 'title' => 'ZZZ 404-Test', 'alias' => $marke.'-ziel', 'type' => 'regular', 'published' => 1]);
$seite = (int) $db->lastInsertId();
ok($seite > 0, "Zielseite #$seite angelegt (alias $marke-ziel)");

$pfadMitPraefix = ('' !== $praefix ? $praefix.'/' : '').$marke.'/alt';
$logId = $log->erfasse('/'.$pfadMitPraefix, '' !== $dns ? $dns : 'example.org');
$callbacks->hefteAnSeite($logId, $seite);
$liste = unserialize((string) $db->fetchOne('SELECT gozi_redirects FROM tl_page WHERE id = ?', [$seite]), ['allowed_classes' => false]);
ok(\is_array($liste) && \in_array($marke.'/alt', $liste, true), 'Adresse steht als alter Alias an der Seite', print_r($liste, true));
ok('' === $praefix || !\in_array($pfadMitPraefix, $liste ?: [], true), 'das Sprachpraefix der Wurzel ist abgeschnitten');
ok(1 === (int) $db->fetchOne('SELECT erledigt FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$logId]), '404-Eintrag gilt als erledigt');
ok(null !== $index->finde($marke.'/alt', [$root], ['tl_page']), 'der Index kennt die Adresse sofort');

echo "\n## Was nicht als Alias geht\n";
$kaputt = $log->erfasse('/'.$marke.'/a b(c)', '' !== $dns ? $dns : 'example.org');
try {
    $callbacks->hefteAnSeite($kaputt, $seite);
    ok(false, 'Adresse mit Leerzeichen und Klammern wird abgewiesen');
} catch (\InvalidArgumentException $e) {
    ok(stripos($e->getMessage(), 'alias') !== false, 'Adresse mit Leerzeichen und Klammern wird abgewiesen', $e->getMessage());
}
$eigen = $log->erfasse('/'.$marke.'-ziel', '' !== $dns ? $dns : 'example.org');
try {
    $callbacks->hefteAnSeite($eigen, $seite);
    ok(false, 'der heutige Alias der Seite wird abgewiesen');
} catch (\InvalidArgumentException $e) {
    ok(true, 'der heutige Alias der Seite wird abgewiesen');
}
if ('' !== $dns) {
    $fremd = $log->erfasse('/'.$marke.'/fremder-host', 'fremde-marke.example');
    try {
        $callbacks->hefteAnSeite($fremd, $seite);
        ok(false, 'Seite aus einem anderen Seitenbaum wird abgewiesen');
    } catch (\InvalidArgumentException $e) {
        ok(str_contains($e->getMessage(), $dns), 'Seite aus einem anderen Seitenbaum wird abgewiesen', $e->getMessage());
    }
}

echo "\n## Eigene Weiterleitungen\n";
$db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 1, 'pfad' => $marke.'/extern', 'host' => '', 'zielTyp' => ManualRedirects::ZIEL_URL, 'zielUrl' => 'https://example.org/ziel', 'code' => '302']);
$ohneHost = (int) $db->lastInsertId();
$db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 1, 'pfad' => $marke.'/extern', 'host' => 'genau.example', 'zielTyp' => ManualRedirects::ZIEL_URL, 'zielUrl' => 'https://example.org/genau', 'code' => '301']);
$mitHost = (int) $db->lastInsertId();
$db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 0, 'pfad' => $marke.'/aus', 'host' => '', 'zielTyp' => ManualRedirects::ZIEL_URL, 'zielUrl' => 'https://example.org/aus', 'code' => '301']);

$treffer = $vonHand->finde('/'.$marke.'/extern', 'genau.example');
ok(null !== $treffer && $treffer['id'] === $mitHost, 'passender Host gewinnt gegen leeren Host');
$treffer = $vonHand->finde('/'.$marke.'/extern', 'irgendwo.example');
ok(null !== $treffer && $treffer['id'] === $ohneHost, 'leerer Host gilt fuer alle anderen');
ok(null === $vonHand->finde('/'.$marke.'/aus', 'example.org'), 'abgeschaltete Weiterleitung greift nicht');
ok(null !== $vonHand->finde('/'.$marke.'/extern.html', 'genau.example'), '.html stoert nicht');
$vonHand->treffer($mitHost);
ok(1 === (int) $db->fetchOne('SELECT zaehler FROM '.ManualRedirects::TABELLE.' WHERE id = ?', [$mitHost]), 'Treffer werden gezaehlt');

echo "\n## Frontend per HTTP\n";
if ('' === $dns) {
    echo "  (uebersprungen: die erste Wurzel hat keinen Rechnernamen)\n";
} else {
    $hole = static function (string $pfad) use ($dns): array {
        $ch = curl_init('https://'.$dns.$pfad);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30]);
        $antwort = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        preg_match('/^Location:\s*(.+)$/mi', $antwort, $t);

        return ['code' => $code, 'ziel' => trim($t[1] ?? '')];
    };

    $db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 1, 'pfad' => $marke.'/http-url', 'host' => $dns, 'zielTyp' => ManualRedirects::ZIEL_URL, 'zielUrl' => 'https://example.org/gelandet', 'code' => '301']);
    $r = $hole('/'.$marke.'/http-url');
    ok(301 === $r['code'] && str_contains($r['ziel'], 'example.org/gelandet'), 'eigene Weiterleitung auf eine Adresse: 301', $r['code'].' '.$r['ziel']);

    $db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 1, 'pfad' => $marke.'/http-seite', 'host' => $dns, 'zielTyp' => ManualRedirects::ZIEL_SEITE, 'zielSeite' => $seite, 'code' => '301']);
    $r = $hole('/'.$marke.'/http-seite');
    ok(301 === $r['code'] && str_contains($r['ziel'], $marke.'-ziel'), 'eigene Weiterleitung auf eine Seite: 301 auf deren Adresse', $r['code'].' '.$r['ziel']);

    $db->insert(ManualRedirects::TABELLE, ['tstamp' => time(), 'aktiv' => 1, 'pfad' => $marke.'/http-weg', 'host' => $dns, 'zielTyp' => ManualRedirects::ZIEL_GONE, 'code' => '301']);
    $r = $hole('/'.$marke.'/http-weg');
    ok(410 === $r['code'], 'Ziel „kein Ziel": 410 Gone', (string) $r['code']);

    $frisch = '/'.$marke.'/frisch-'.time();
    $r = $hole($frisch);
    $eintrag = $db->fetchAssociative('SELECT * FROM '.NotFoundLog::TABELLE.' WHERE pfad = ?', [trim($frisch, '/')]);
    ok(404 === $r['code'] && false !== $eintrag, 'eine echte 404-Anfrage landet im Protokoll', (string) $r['code']);

    $r = $hole('/'.$marke.'/http-url');
    ok(null === $db->fetchOne('SELECT id FROM '.NotFoundLog::TABELLE.' WHERE pfad = ?', [$marke.'/http-url'])
        || false === $db->fetchOne('SELECT id FROM '.NotFoundLog::TABELLE.' WHERE pfad = ?', [$marke.'/http-url']),
        'eine weitergeleitete Adresse kommt NICHT ins Protokoll');
}

echo "\n## Aufraeumen\n";
$db->executeStatement('UPDATE '.NotFoundLog::TABELLE.' SET tstamp = ? WHERE pfad = ?', [time() - 200 * 86400, $marke.'/eins']);
$weg = $log->aufraeumen(90);
ok($weg >= 1, "alte, nicht erledigte Eintraege verfallen ($weg geloescht)");
ok(false !== $db->fetchOne('SELECT id FROM '.NotFoundLog::TABELLE.' WHERE id = ?', [$logId]), 'erledigte Eintraege bleiben stehen');

echo "\n".($fail > 0 ? "❌ $fail von ".($pass + $fail)." fehlgeschlagen" : "✅ alle $pass Zusicherungen erfuellt")."\n";
exit($fail > 0 ? 1 : 0);
