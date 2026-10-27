<?php

declare(strict_types=1);

namespace Gozi\AliasRedirectBundle\Service;

use Contao\Message;
use Doctrine\DBAL\Connection;

/**
 * WER TRAEGT DIESE ADRESSE? — Seitenpfade und Weiterleitungen eines Baums an einer Stelle.
 *
 * Anlass (27.10.2026): Seite 1244 wurde zweimal kopiert, die Uebersetzungs-Aliase kamen 1:1 mit, und
 * ein doppelter Alias stand zugleich als Weiterleitung im System. Geprueft wurde nirgends: beim
 * Umbenennen wanderte der alte Alias in die Liste, obwohl eine andere Seite ihn noch trug, und von Hand
 * Eingetragenes wurde nur auf Schreibweise bereinigt. Steht eine Adresse zweimal als Weiterleitung, ist
 * das Ziel Zufall: AliasIndex::finde() nimmt die kleinere Id.
 *
 * Gezaehlt wird, was eine Adresse bilden kann, auch unveroeffentlicht — sonst wird es beim
 * Veroeffentlichen still zum Duplikat:
 *  - Seitenpfad einer Seite (tl_page.alias) — gilt in JEDER Sprache: gozi-i18nl10n loest /<sprache>/<alias>
 *    auch gegen tl_page.alias auf (PageAliasResolver::resolveInbound),
 *  - Seitenpfad einer Uebersetzung — gilt in ihrer Sprache,
 *  - Weiterleitung an einer Seite — greift in JEDER Sprache (so loest AliasIndex::finde sie auf),
 *  - Weiterleitung an einer Uebersetzung — gilt in ihrer Sprache.
 *
 * Wann zwei kollidieren: Zwei Weiterleitungen, sobald sich ihre Sprachen ueberschneiden — dann ist
 * offen, wohin die Adresse fuehrt. Eine Weiterleitung gegen einen Seitenpfad zaehlt die Weiterleitung an
 * der Seite als Basissprache: Weiterleitungen greifen erst bei 404, und eine PONS-Seite (Basis en) soll
 * ihren alten englischen Alias behalten duerfen, auch wenn eine deutsche Uebersetzung einer anderen Seite
 * denselben Wortlaut traegt — auf /de/… gewinnt diese ohnehin, /en/… braucht die Weiterleitung.
 *
 * Nachrichten, Termine und FAQ bleiben aussen vor: ihr Alias ist kein Seitenpfad.
 */
final class AliasBelegung
{
    public const SEITENPFAD = 'seitenpfad';
    public const WEITERLEITUNG = 'weiterleitung';

    /** @var array<int, array{pid:int, type:string, language:string}>|null */
    private ?array $baum = null;

    /** @var array<int, true> Seiten, nach denen schon einmal neu geladen wurde — jede hoechstens einmal */
    private array $nachgeladen = [];

    private ?bool $mitUebersetzungen = null;

    public function __construct(
        private readonly Connection $db,
        private readonly AliasRedirects $redirects,
    ) {
    }

    /**
     * Wo wird diese Adresse im Baum schon verwendet — ausser an der Seite, um die es gerade geht?
     *
     * @param string|null $sprache        Sprache der Adresse; null bei einer Weiterleitung an der Seite (jede Sprache)
     * @param bool        $weiterleitung  ob die Adresse als Weiterleitung eingetragen werden soll (sonst als Seitenpfad)
     *
     * @return list<array{art:string, seite:int, titel:string, sprache:?string, veroeffentlicht:bool}>
     */
    public function wer(string $alias, int $wurzel, ?string $sprache, bool $weiterleitung, int $ausserSeite = 0): array
    {
        // Weiterleitungen stehen bereinigt in der Liste; Seitenpfade woertlich („apps-schule/“ ≠ „apps-schule“)
        $alias = $weiterleitung ? trim($alias, "/ \t\n\r") : $alias;
        if ('' === $alias || $wurzel < 1) {
            return [];
        }
        $anfrage = ['art' => $weiterleitung ? self::WEITERLEITUNG : self::SEITENPFAD, 'sprache' => $sprache];
        $basis = $this->basissprache($wurzel);
        $treffer = [];
        foreach ($this->sammeln($alias) as $v) {
            if ($v['wurzel'] !== $wurzel || $v['seite'] === $ausserSeite || !$this->kollidieren($anfrage, $v, $basis)) {
                continue;
            }
            unset($v['wurzel'], $v['alias']);
            $treffer[] = $v;
        }

        return $treffer;
    }

    /**
     * Ein Seitenpfad, der gerade gespeichert wird: belegt er die Adresse einer anderen Seite?
     *
     * Geaendert: Ausnahme, Contao zeigt sie am Feld und speichert nichts. Unveraendert (Bestand, der
     * schon doppelt ist): nur eine Meldung — sonst liesse sich die Seite gar nicht mehr speichern, obwohl
     * der Fehler an der ANDEREN Seite behoben werden muss.
     *
     * @throws \RuntimeException
     */
    public function pruefeSeitenpfad(string $alias, int $wurzel, ?string $sprache, int $seite, bool $geaendert, bool $nurWeiterleitungen = false): void
    {
        $belegt = $this->wer($alias, $wurzel, $sprache, false, $seite);
        if ($nurWeiterleitungen) {
            // Seitenpfade gegen Seitenpfade hat jemand anderes schon geprueft (gozi-i18nl10n) — sonst kaeme dieselbe Meldung zweimal
            $belegt = array_values(array_filter($belegt, static fn (array $v): bool => self::WEITERLEITUNG === $v['art']));
        }
        if ([] === $belegt) {
            return;
        }
        $meldung = sprintf($this->text('seitenpfadBelegt'), trim($alias, '/'), $this->beschreibe($belegt));
        if ($geaendert) {
            throw new \RuntimeException($meldung);
        }
        $this->melde($meldung, true);
    }

    /** Meldung im Backend; ohne Sitzung (Kommandozeile, Migration) gibt es keinen Meldungsbereich. */
    public function melde(string $meldung, bool $fehler = false): void
    {
        try {
            $fehler ? Message::addError($meldung) : Message::addInfo($meldung);
        } catch (\Throwable) {
        }
    }

    /**
     * Die Weiterleitungsliste eines Datensatzes: jede Adresse, die anderswo schon steht, mit Fundstelle.
     *
     * Neu dazugekommene Eintraege mit Konflikt: Ausnahme, gespeichert wird nichts. Eintraege, die schon in
     * der Liste standen: nur eine Meldung — wie bei pruefeSeitenpfad. Sonst sperrte ein alter Eintrag
     * jedes Speichern der Seite, auch eine Umbenennung, deren eigener alter Alias dann verloren ginge.
     *
     * @param list<string>             $liste  bereinigt
     * @param list<string>|string|null $vorher die gespeicherte Liste vor diesem Speichern
     *
     * @throws \RuntimeException mit allen neuen Konflikten auf einmal — nicht einer je Speichern
     */
    public function pruefeListe(array $liste, int $wurzel, ?string $sprache, int $seite, array|string|null $vorher = null): void
    {
        $bisher = $this->redirects->bereinige($vorher);
        $neu = [];
        $alt = [];
        foreach ($liste as $alias) {
            $belegt = $this->wer($alias, $wurzel, $sprache, true, $seite);
            if ([] !== $belegt) {
                $zeile = '„'.$alias.'“: '.$this->beschreibe($belegt);
                \in_array($alias, $bisher, true) ? $alt[] = $zeile : $neu[] = $zeile;
            }
        }
        if ([] !== $neu) {
            throw new \RuntimeException(sprintf($this->text('weiterleitungBelegt'), implode(' — ', $neu)));
        }
        if ([] !== $alt) {
            $this->melde(sprintf($this->text('weiterleitungBelegt'), implode(' — ', $alt)), true);
        }
    }

    /** Meldungstext aus MSC.gozi_belegung, mit deutscher Vorgabe fuer die Kommandozeile. */
    public function text(string $schluessel): string
    {
        $vorgaben = [
            'seitenpfadBelegt' => 'Die Adresse „%s“ wird schon verwendet: %s.',
            'weiterleitungBelegt' => 'Diese Weiterleitungen werden schon verwendet und können hier nicht stehen: %s. Bitte entfernen oder an der anderen Seite austragen.',
            'alterAliasBelegt' => 'Der alte Alias „%s“ wurde nicht als Weiterleitung übernommen, weil er noch verwendet wird: %s.',
        ];

        return (string) ($GLOBALS['TL_LANG']['MSC']['gozi_belegung'][$schluessel] ?? $vorgaben[$schluessel] ?? $schluessel);
    }

    /**
     * Alle Konflikte im Bestand — fuer gozi:alias-redirect:pruefen.
     *
     * @return list<array{wurzel:int, alias:string, verwendungen:list<array{art:string, seite:int, titel:string, sprache:?string, veroeffentlicht:bool}>}>
     */
    public function konflikte(): array
    {
        $gruppen = [];
        foreach ($this->sammeln(null) as $v) {
            if ($v['wurzel'] > 0) {
                $gruppen[$v['wurzel'].'|'.$v['alias']][] = $v;
            }
        }
        $aus = [];
        foreach ($gruppen as $gruppe) {
            if (\count($gruppe) < 2) {
                continue;
            }
            $basis = $this->basissprache($gruppe[0]['wurzel']);
            $beteiligt = [];
            foreach ($gruppe as $i => $a) {
                foreach ($gruppe as $j => $b) {
                    if ($i < $j && $a['seite'] !== $b['seite'] && $this->kollidieren($a, $b, $basis)) {
                        $beteiligt[$i] = true;
                        $beteiligt[$j] = true;
                    }
                }
            }
            if ([] === $beteiligt) {
                continue;
            }
            ksort($beteiligt);
            $verwendungen = [];
            foreach (array_keys($beteiligt) as $i) {
                $v = $gruppe[$i];
                unset($v['wurzel'], $v['alias']);
                $verwendungen[] = $v;
            }
            $aus[] = ['wurzel' => $gruppe[0]['wurzel'], 'alias' => $gruppe[0]['alias'], 'verwendungen' => $verwendungen];
        }
        usort($aus, static fn (array $a, array $b): int => [$a['wurzel'], $a['alias']] <=> [$b['wurzel'], $b['alias']]);

        return $aus;
    }

    /**
     * Fundstellen als Text, so wie sie am Feld und auf der Kommandozeile erscheinen:
     * Seite 1445 „Barrierefreiheit“ (DE, Weiterleitung, unveröffentlicht); Seite 1244 …
     *
     * @param list<array{art:string, seite:int, titel:string, sprache:?string, veroeffentlicht:bool}> $verwendungen
     */
    public function beschreibe(array $verwendungen): string
    {
        $t = static fn (string $k, string $vorgabe): string => (string) ($GLOBALS['TL_LANG']['MSC']['gozi_belegung'][$k] ?? $vorgabe);
        $teile = [];
        foreach ($verwendungen as $v) {
            $merkmale = [
                null === $v['sprache'] ? $t('alleSprachen', 'alle Sprachen') : strtoupper($v['sprache']),
                self::WEITERLEITUNG === $v['art'] ? $t('weiterleitung', 'Weiterleitung') : $t('seitenpfad', 'Seitenpfad'),
            ];
            if (!$v['veroeffentlicht']) {
                $merkmale[] = $t('unveroeffentlicht', 'unveröffentlicht');
            }
            $teile[] = sprintf($t('seite', 'Seite %d „%s“ (%s)'), $v['seite'], $v['titel'], implode(', ', $merkmale));
        }

        return implode('; ', $teile);
    }

    /**
     * @param array{art:string, sprache:?string} $a
     * @param array{art:string, sprache:?string} $b
     */
    private function kollidieren(array $a, array $b, string $basis): bool
    {
        $sa = $a['sprache'];
        $sb = $b['sprache'];
        if (self::WEITERLEITUNG !== $a['art'] || self::WEITERLEITUNG !== $b['art']) {
            // Weiterleitung gegen Seitenpfad: die Weiterleitung an der Seite zaehlt als Basissprache
            $sa = self::WEITERLEITUNG === $a['art'] && null === $sa ? $basis : $sa;
            $sb = self::WEITERLEITUNG === $b['art'] && null === $sb ? $basis : $sb;
        }

        // null = jede Sprache
        return null === $sa || null === $sb || $sa === $sb;
    }

    /**
     * Alle Verwendungen einer Adresse — oder aller Adressen, wenn $alias null ist.
     *
     * @return list<array{art:string, alias:string, wurzel:int, seite:int, titel:string, sprache:?string, veroeffentlicht:bool}>
     */
    private function sammeln(?string $alias): array
    {
        $aus = [];
        $liste = AliasRedirects::FELD;
        // Vorauswahl der Listen ueber LIKE auf dem serialisierten Feld (s:N:"alias";), exakt geprueft per bereinige()
        $like = null === $alias ? null : '%"'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $alias).'"%';

        $seiten = null === $alias
            ? $this->db->fetchAllAssociative("SELECT id, title, alias, published, $liste AS liste FROM tl_page WHERE alias <> '' OR ($liste IS NOT NULL AND $liste <> '')")
            : $this->db->fetchAllAssociative("SELECT id, title, alias, published, $liste AS liste FROM tl_page WHERE alias = ? OR $liste LIKE ?", [$alias, $like]);
        foreach ($seiten as $s) {
            $wurzel = $this->wurzel((int) $s['id']);
            $gemeinsam = ['wurzel' => $wurzel, 'seite' => (int) $s['id'], 'titel' => (string) $s['title'], 'veroeffentlicht' => (bool) $s['published']];
            // Seitenpfade woertlich: „apps-schule/“ ist eine andere Adresse als „apps-schule“ (PONS, Seite 207)
            $eigener = (string) $s['alias'];
            if ('' !== $eigener && (null === $alias || $eigener === $alias)) {
                $aus[] = ['art' => self::SEITENPFAD, 'alias' => $eigener, 'sprache' => null] + $gemeinsam;
            }
            foreach ($this->redirects->bereinige($s['liste'], $eigener) as $a) {
                if (null === $alias || $a === $alias) {
                    $aus[] = ['art' => self::WEITERLEITUNG, 'alias' => $a, 'sprache' => null] + $gemeinsam;
                }
            }
        }

        if (!$this->mitUebersetzungen()) {
            return $aus;
        }
        $sql = "SELECT i.pid, i.language, IF(i.title <> '', i.title, p.title) AS titel, i.alias, i.i18nl10n_published AND p.published AS veroeffentlicht, i.$liste AS liste
            FROM ".AliasIndex::UEBERSETZUNG.' i JOIN tl_page p ON p.id = i.pid';
        $uebersetzungen = null === $alias
            ? $this->db->fetchAllAssociative($sql." WHERE i.alias <> '' OR (i.$liste IS NOT NULL AND i.$liste <> '')")
            : $this->db->fetchAllAssociative($sql." WHERE i.alias = ? OR i.$liste LIKE ?", [$alias, $like]);
        foreach ($uebersetzungen as $u) {
            $gemeinsam = ['wurzel' => $this->wurzel((int) $u['pid']), 'seite' => (int) $u['pid'], 'titel' => (string) $u['titel'], 'sprache' => (string) $u['language'], 'veroeffentlicht' => (bool) $u['veroeffentlicht']];
            $eigener = (string) $u['alias'];
            if ('' !== $eigener && (null === $alias || $eigener === $alias)) {
                $aus[] = ['art' => self::SEITENPFAD, 'alias' => $eigener] + $gemeinsam;
            }
            foreach ($this->redirects->bereinige($u['liste'], $eigener) as $a) {
                if (null === $alias || $a === $alias) {
                    $aus[] = ['art' => self::WEITERLEITUNG, 'alias' => $a] + $gemeinsam;
                }
            }
        }

        return $aus;
    }

    /** Wurzel ueber die pid-Kette — aus EINER Abfrage aller Seiten, nicht einer je Ebene. */
    public function wurzel(int $seite): int
    {
        $baum = $this->baum($seite);
        for ($i = 0, $id = $seite; $i < 50 && isset($baum[$id]); ++$i) {
            if ('root' === $baum[$id]['type']) {
                return $id;
            }
            $id = $baum[$id]['pid'];
        }

        return 0;
    }

    public function basissprache(int $wurzel): string
    {
        return $this->baum()[$wurzel]['language'] ?? '';
    }

    /** @return array<int, array{pid:int, type:string, language:string}> */
    private function baum(int $seite = 0): array
    {
        // Eine unbekannte Seite ist nach dem Laden entstanden (Kopie, neue Seite) — dann neu lesen
        if (null === $this->baum || ($seite > 0 && !isset($this->baum[$seite]) && !isset($this->nachgeladen[$seite]))) {
            if (null !== $this->baum) {
                $this->nachgeladen[$seite] = true;
            }
            $this->baum = [];
            foreach ($this->db->fetchAllAssociative('SELECT id, pid, type, language FROM tl_page') as $z) {
                $this->baum[(int) $z['id']] = ['pid' => (int) $z['pid'], 'type' => (string) $z['type'], 'language' => (string) $z['language']];
            }
        }

        return $this->baum;
    }

    private function mitUebersetzungen(): bool
    {
        if (null === $this->mitUebersetzungen) {
            try {
                $sm = $this->db->createSchemaManager();
                $this->mitUebersetzungen = $sm->tablesExist([AliasIndex::UEBERSETZUNG])
                    && isset($sm->listTableColumns(AliasIndex::UEBERSETZUNG)[strtolower(AliasRedirects::FELD)]);
            } catch (\Throwable) {
                $this->mitUebersetzungen = false;
            }
        }

        return $this->mitUebersetzungen;
    }
}
