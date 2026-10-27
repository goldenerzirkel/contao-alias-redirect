<?php

declare(strict_types=1);

/*
 * Wo wird eine Adresse schon verwendet? AliasBelegung gegen die echte Datenbank.
 * Legt vier Wegwerf-Seiten unter einer Wurzel mit Uebersetzungen an und raeumt sie am Ende (auch nach Fatal) weg.
 * Die Seiten sind unveroeffentlicht (AliasBelegung zaehlt sie trotzdem) — nie erreichbar, nie in Sitemap oder Suche.
 * Nur gegen eine lokale Datenbank laufen lassen: bricht der Prozess hart ab, bleiben die ZZZ-Seiten stehen.
 *
 * Nachgestellt ist der Fall vom 27.10.2026: Seite kopiert, Uebersetzungs-Alias 1:1 mitgekommen, Kopie umbenannt.
 *
 * Aufruf aus der Projektwurzel:
 *   /Applications/MAMP/bin/php/php8.4.17/bin/php vendor/gozi/contao-alias-redirect/tests/belegung-suite.php
 */

use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Gozi\AliasRedirectBundle\Service\AliasBelegung;
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

// Eine Wurzel, unter der es Uebersetzungen gibt und deren Basissprache nicht „de“ ist
$wurzel = (int) $db->fetchOne("SELECT r.id FROM tl_page r WHERE r.type = 'root' AND r.language <> 'de' ORDER BY r.sorting LIMIT 1");
if ($wurzel < 1) {
    echo "Keine Wurzel mit Basissprache ungleich de — Suite uebersprungen.\n";
    exit(0);
}
$basis = (string) $db->fetchOne('SELECT language FROM tl_page WHERE id = ?', [$wurzel]);

$seiten = [];
$neueSeite = static function (string $titel, string $alias, array $weiterleitungen = []) use ($db, $wurzel, &$seiten): int {
    $db->insert('tl_page', ['pid' => $wurzel, 'sorting' => 999990 + \count($seiten), 'tstamp' => time(), 'title' => $titel, 'alias' => $alias, 'type' => 'regular', 'published' => 0, 'gozi_redirects' => [] === $weiterleitungen ? null : serialize($weiterleitungen)]);

    return $seiten[] = (int) $db->lastInsertId();
};
$uebersetzung = static function (int $seite, string $sprache, string $alias, array $weiterleitungen = []) use ($db): void {
    $db->insert('tl_page_i18nl10n', ['pid' => $seite, 'language' => $sprache, 'tstamp' => time(), 'title' => 'ZZZ '.$sprache, 'alias' => $alias, 'i18nl10n_published' => '1', 'gozi_redirects' => [] === $weiterleitungen ? null : serialize($weiterleitungen)]);
};
register_shutdown_function(static function () use ($db, &$seiten): void {
    foreach ($seiten as $id) {
        $db->delete('tl_page_i18nl10n', ['pid' => $id]);
        $db->delete('tl_page', ['id' => $id]);
    }
    echo "\n(aufgeraeumt: Testseiten ".implode(', ', $seiten).")\n";
});

$original = $neueSeite('ZZZ Original', 'zzz-original');
$uebersetzung($original, 'de', 'zzz-vereinbarung');
$kopie = $neueSeite('ZZZ Kopie', 'zzz-kopie');
$uebersetzung($kopie, 'de', 'zzz-vereinbarung');                          // 1:1 mitkopiert
$mitListe = $neueSeite('ZZZ Liste', 'zzz-liste', ['zzz-weg']);            // Weiterleitung an der Seite: jede Sprache
$fremd = $neueSeite('ZZZ Fremd', 'zzz-fremd');
$uebersetzung($fremd, 'de', 'zzz-fremd-de', ['zzz-weg', 'zzz-nur-de']);   // Weiterleitung nur in de
echo "Wurzel $wurzel (Basis $basis), Seiten: Original $original, Kopie $kopie, Liste $mitListe, Fremd $fremd\n";

$b = new AliasBelegung($db, new AliasRedirects($db));
$seitenIn = static fn (array $v): array => array_values(array_unique(array_column($v, 'seite')));

echo "\n## Seitenpfade\n";
$v = $b->wer('zzz-vereinbarung', $wurzel, 'de', false, $kopie);
ok([$original] === $seitenIn($v), 'Kopie: de-Alias steht auch am Original', json_encode($v));
ok('seitenpfad' === ($v[0]['art'] ?? '') && 'de' === ($v[0]['sprache'] ?? ''), 'Fundstelle nennt Art und Sprache');
ok([] === $b->wer('zzz-vereinbarung', $wurzel, 'fr', false, $kopie), 'in einer anderen Sprache ist die Adresse frei');
ok([] === $b->wer('zzz-vereinbarung', $wurzel + 999999, 'de', false, $kopie), 'in einem anderen Baum ist die Adresse frei');
ok([] === $b->wer('zzz-original', $wurzel, $basis, false, $original), 'die eigene Seite zaehlt nicht');

echo "\n## Der Fall vom 27.10.: Kopie umbenennen\n";
$v = $b->wer('zzz-vereinbarung', $wurzel, 'de', true, $kopie);
ok([$original] === $seitenIn($v), 'alter Alias der Kopie ist nicht frei → kommt nicht in ihre Liste', json_encode($v));

echo "\n## Weiterleitungen\n";
$v = $b->wer('zzz-weg', $wurzel, 'fr', true, 0);
ok([$mitListe] === $seitenIn($v), 'fr: nur die Weiterleitung an der Seite (gilt ueberall), nicht die de-Weiterleitung', json_encode($v));
$v = $b->wer('zzz-weg', $wurzel, 'de', true, 0);
ok([$mitListe, $fremd] === $seitenIn($v), 'de: beide Weiterleitungen', json_encode($v));
ok([$fremd] === $seitenIn($b->wer('zzz-nur-de', $wurzel, null, true, 0)), 'Weiterleitung an einer Seite (jede Sprache) trifft die de-Weiterleitung');
ok([] === $b->wer('zzz-fremd-de', $wurzel, null, true, 0), 'Weiterleitung an der Seite gegen de-Seitenpfad: kein Konflikt (zaehlt als Basissprache)');
ok([$mitListe, $fremd] === $seitenIn($b->wer('zzz-weg', $wurzel, null, false, 0)), 'neuer Seiten-Alias (jede Sprache) trifft beide Weiterleitungen');
ok([$mitListe] === $seitenIn($b->wer('zzz-liste', $wurzel, 'de', false, 0)), 'Seiten-Alias gilt in jeder Sprache: de-Uebersetzung „zzz-liste“ ist belegt');
ok([$mitListe] === $seitenIn($b->wer('zzz-liste', $wurzel, 'fr', true, 0)), 'fr-Weiterleitung auf einen Seiten-Alias ist tot → belegt');
ok([] === $b->wer('zzz-liste/', $wurzel, 'de', false, 0), 'Seitenpfad woertlich: „zzz-liste/“ ist eine andere Adresse');

echo "\n## Pruefen mit Meldung\n";
try {
    $b->pruefeListe(['zzz-frei', 'zzz-vereinbarung'], $wurzel, 'de', $kopie);
    ok(false, 'pruefeListe wirft bei belegter Adresse');
} catch (\RuntimeException $e) {
    ok(str_contains($e->getMessage(), 'zzz-vereinbarung') && str_contains($e->getMessage(), (string) $original), 'Meldung nennt Adresse und Seite', $e->getMessage());
    ok(!str_contains($e->getMessage(), 'zzz-frei'), 'freie Eintraege stehen nicht in der Meldung');
}
try {
    $b->pruefeSeitenpfad('zzz-weg', $wurzel, null, $original, true);
    ok(false, 'pruefeSeitenpfad wirft, wenn der neue Alias anderswo Weiterleitung ist');
} catch (\RuntimeException $e) {
    ok(str_contains($e->getMessage(), (string) $mitListe), 'Meldung nennt die Seite mit der Weiterleitung', $e->getMessage());
}
$b->pruefeListe(['zzz-ganz-frei'], $wurzel, 'de', $kopie);
ok(true, 'freie Liste wirft nicht');
$b->pruefeListe(['zzz-ganz-frei', 'zzz-vereinbarung'], $wurzel, 'de', $kopie, serialize(['zzz-vereinbarung']));
ok(true, 'Konflikt, der schon in der gespeicherten Liste stand: nur Meldung, kein Abbruch');
try {
    $b->pruefeSeitenpfad('zzz-vereinbarung', $wurzel, 'de', $kopie, false);
    ok(true, 'unveraenderter doppelter Seitenpfad: nur Meldung, kein Abbruch');
} catch (\RuntimeException $e) {
    ok(false, 'unveraenderter doppelter Seitenpfad: nur Meldung, kein Abbruch', $e->getMessage());
}

echo "\n## Bestandsaufnahme\n";
$alle = [];
foreach ($b->konflikte() as $k) {
    if ($k['wurzel'] === $wurzel) {
        $alle[$k['alias']] = $seitenIn($k['verwendungen']);
    }
}
ok(isset($alle['zzz-vereinbarung']) && [$original, $kopie] === $alle['zzz-vereinbarung'], 'konflikte(): Original und Kopie', json_encode($alle['zzz-vereinbarung'] ?? null));
ok(isset($alle['zzz-weg']) && [$mitListe, $fremd] === $alle['zzz-weg'], 'konflikte(): doppelte Weiterleitung', json_encode($alle['zzz-weg'] ?? null));
ok(!isset($alle['zzz-fremd-de']) && !isset($alle['zzz-nur-de']), 'konflikte(): nichts Erfundenes');

echo "\n## gozi-i18nl10n: Generator je Baum und Sprache\n";
if (class_exists(\GoZi\I18nl10nBundle\Backend\TranslationAliasGenerator::class)) {
    $gen = new \GoZi\I18nl10nBundle\Backend\TranslationAliasGenerator($db, new \GoZi\I18nl10nBundle\Config\RootLanguages($db));
    $kopieZeile = (int) $db->fetchOne("SELECT id FROM tl_page_i18nl10n WHERE pid = ? AND language = 'de'", [$kopie]);
    ok([$original] === array_column($gen->verwendungen('zzz-vereinbarung', $kopie, 'de', $kopieZeile), 'seite'), 'verwendungen() nennt das Original');
    ok('zzz-vereinbarung-'.$kopie === $gen->generate('zzz-vereinbarung', '', $kopie, 'de', $kopieZeile), 'generate() haengt die Seiten-ID an');
} else {
    echo "  (gozi-i18nl10n nicht installiert — uebersprungen)\n";
}

echo "\n$pass bestanden, $fail fehlgeschlagen\n";
exit($fail > 0 ? 1 : 0);
