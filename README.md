# Alias-Weiterleitungen für Contao 5

**Alte Adressen führen weiter zur richtigen Seite, auch nach dem Umbenennen.**

Wird in Contao der Alias einer Seite geändert, zum Beispiel von `leistungen/cms-systeme-5`
auf `leistungen/cms-systeme-6`, ist die alte Adresse sonst tot: Google, Newsletter, Social-Media-Posts
und Partner-Links laufen ins Leere, Besucher sehen eine Fehlerseite, Rankings gehen verloren.
Dieses Bundle merkt sich jede frühere Adresse und leitet sie dauerhaft per 301 auf die aktuelle Seite um.
Suchmaschinen übernehmen damit die Bewertung der alten Adresse auf die neue.

![Seiteneinstellungen mit der Liste der Weiterleitungen](https://raw.githubusercontent.com/goldenerzirkel/contao-alias-redirect/main/docs/seiteneinstellungen-weiterleitungen.png)

## Was Sie davon haben

- **Kein Linkverlust:** Jede Umbenennung wird automatisch abgefangen, ohne dass jemand daran denken muss.
- **SEO bleibt erhalten:** 301-Weiterleitungen übertragen die Sichtbarkeit der alten Adresse auf die neue.
- **Alles an einem Ort:** Die Weiterleitungen stehen direkt in den Seiteneinstellungen, nicht in einer
  separaten Liste. Wer die Seite pflegt, sieht auch ihre alten Adressen.
- **Nachvollziehbar:** Änderungen laufen über die normale Versionierung von Contao und lassen sich
  zurücknehmen.
- **Mehrsprachig:** Funktioniert mit Sprachpräfixen wie `/de/…` und `/en/…` sowie mit mehreren Domains
  in einer Installation.

## So arbeiten Redakteure damit

1. Seite öffnen, Alias ändern, speichern. Fertig. Der alte Alias steht jetzt im Feld
   **Weiterleitungen auf diese Seite**.
2. Weitere alte Adressen, etwa von einer früheren Website, lassen sich dort von Hand eintragen.
   Ein Eintrag je Zeile, ohne Schrägstrich am Anfang.
3. Soll eine Umbenennung ausnahmsweise **keine** Weiterleitung erzeugen, vor dem Speichern den Haken
   **Keine Weiterleitung anlegen** setzen. Der Haken gilt nur für dieses eine Speichern.

Trägt eine Seite eine Adresse wieder selbst, gewinnt immer die echte Seite. Es entstehen keine
Weiterleitungsketten: Alle alten Adressen zeigen direkt auf die aktuelle.

## Die Arbeitsliste: was ins Leere läuft

Im Navigationsbereich **Weiterleitungen** stehen zwei Punkte.

**Ins Leere gelaufen** sammelt jede Adresse, die mit 404 beantwortet wurde — eine Zeile je Adresse und
Domain, mit Zähler, Verweisgeber und Zeitpunkt. **Ziel festlegen** öffnet eine Maske mit drei
Möglichkeiten:

* **Seite in diesem System** — der Regelweg. Eine Seite wählen, speichern: die Adresse steht danach im
  Feld *Weiterleitungen auf diese Seite* dieser Seite, zusammen mit allen anderen alten Adressen.
* **Beliebige Adresse (extern)** — eine vollständige Adresse, auch auf einem fremden Server, mit
  wählbarer Art der Weiterleitung (301, 302, 303, 307, 308). Ein Alias kann immer nur auf eine Seite
  dieser Installation zeigen; im Hintergrund entsteht deshalb ein Eintrag unter *Weiterleitungen*.
* **Kein Ziel (410 Gone)** — die Adresse ist bewusst weg.

In allen drei Fällen gilt der Eintrag danach als erledigt und verschwindet aus der Arbeitsliste. Was
gar keine Weiterleitung verdient, bekommt den Haken **Erledigt**.

Der Weg über die Seite wird mit Begründung abgelehnt, wenn er nicht tragen kann: die Seite liegt in
einem Seitenbaum, der zu dieser Domain nicht gehört; die Adresse enthält Zeichen, die in keinem
Alias vorkommen dürfen (Leerzeichen, Klammern, Prozentzeichen — typisch bei Scannern); oder sie ist
der heutige Alias der Seite selbst. Dann bleibt die zweite Möglichkeit.

Nicht protokolliert werden Backend-Aufrufe, Contaos eigene Adressen (`/_…`), `/.well-known/…`
(Zertifikate, App-Verknüpfungen, `security.txt`), fehlende Bilder, Stylesheets und Schriften sowie
alles außer GET und HEAD. Der Abfrageteil (`?x=1`) wird nicht gespeichert — er macht aus einer Adresse
beliebig viele und wird beim Weiterleiten ohnehin wieder angehängt.

## Eigene Weiterleitungen

**Weiterleitungen** ist die Liste für alles, was sich keiner Seite als Alias zuordnen lässt — sie füllt
sich von selbst, wenn in der 404-Maske eine externe Adresse oder „kein Ziel" gewählt wird, und nimmt
daneben von Hand angelegte Einträge auf:

| Feld | Bedeutung |
|---|---|
| Alte Adresse | Pfad ohne Domain und ohne Schrägstrich am Anfang; `.html` wird ignoriert |
| Domain | nur für diese Domain; leer = für alle |
| Art des Ziels | Seite im Seitenbaum, beliebige Adresse, oder kein Ziel (410 Gone) |
| Art der Weiterleitung | 301, 302, 303, 307 oder 308 |

Eine passende Domain gewinnt gegen eine leere. Diese Weiterleitungen gelten **vor** den
Alias-Weiterleitungen an den Seiten — sie sind die ausdrückliche Entscheidung.

Für Weiterleitungen nach **Muster** (reguläre Ausdrücke, Platzhalter) bleibt
`terminal42/contao-url-rewrite` zuständig; siehe `docs/vergleich-terminal42-url-rewrite.md`.

## Installation für Administratoren

Im Contao Manager unter **Pakete** nach `gozi/contao-alias-redirect` suchen und installieren, oder auf
der Kommandozeile:

```
composer require gozi/contao-alias-redirect
```

Danach im Contao Manager die **Datenbank aktualisieren** (oder `contao:migrate`). Es kommen zwei Felder an
der Seitentabelle hinzu, sonst ändert sich nichts.

Voraussetzungen: Contao 5.3 oder neuer, PHP 8.2 oder neuer.

### Entfernte Seiten: 410 statt 404

Eine Seite, die es bewusst nicht mehr gibt, bekommt den Seitentyp **Entfernt (410 Gone)**. Sie bleibt im
Seitenbaum mit ihrem Alias und ihrer Liste alter Aliase und antwortet unter all diesen Adressen mit
410 Gone. Suchmaschinen streichen die Adresse dann schneller als bei 404, Besucher sehen die Seite im
Layout, etwa mit einem Hinweis, wo es weitergeht.

## Nachrichten, Termine und FAQ

Dieselben zwei Felder stehen an Nachrichten (tl_news), Terminen (tl_calendar_events) und FAQ (tl_faq) hinter dem Alias.
Ändert ein Redakteur den Alias einer Nachricht, wandert der alte in die Liste; die alte Adresse
`/blog/alter-alias` leitet mit 301 auf die heutige Adresse der Nachricht. Gilt in dem Seitenbaum, in dem
die Leseseite des Archivs, Kalenders bzw. der FAQ-Kategorie liegt.

## Index alter Aliase

Die Liste an der Seite bleibt die Wahrheit. Für die Suche bei jeder Anfrage führt das Bundle zusätzlich
die Tabelle `tl_gozi_alias_redirect` (ein Eintrag je altem Alias, mit Index). Sie wird beim Speichern,
Löschen und Wiederherstellen einer Seitenversion nachgeführt; `contao:migrate` legt sie an und füllt
sie. Neu aufbauen, etwa nach einem Import:

```bash
php bin/console gozi:alias-redirect:rebuild
```

Mit dem Index prüft das Bundle alte Aliase **vor** dem Router. Nur so wird ein alter Ordner-Alias wie
`leistungen/alte-seite` weitergeleitet, den Contao sonst als Parameter der Elternseite `leistungen`
liest. Eine veröffentlichte Seite, die den Alias selbst trägt, gewinnt weiterhin.

## Rechte für Redakteure

Administratoren sehen die neuen Felder sofort. Redakteursgruppen brauchen in den
**Benutzergruppen** unter **Erlaubte Felder → Seiten** die Freigabe für
**Weiterleitungen auf diese Seite** und **Keine Weiterleitung anlegen**.

## Häufige Fragen

**Was passiert mit `.html`-Adressen und Parametern?**
`alte-seite.html` wird ebenso erkannt wie `alte-seite`. Angehängte Parameter wie `?utm_source=…` bleiben
bei der Weiterleitung erhalten.

**Was ist mit Adressen, die zu einer anderen Domain gehören?**
Gesucht wird nur im Seitenbaum der aufgerufenen Domain. Eine Adresse, die auf eine andere Domain
umgezogen ist, liefert hier eine normale Fehlerseite.

**Muss ich die Weiterleitungen irgendwann löschen?**
Nein. Sie kosten nichts und schaden nicht. Löschen lohnt nur, wenn eine alte Adresse bewusst wieder
frei werden soll.

## Für Entwickler

| Datei | Zweck |
|---|---|
| `src/Service/AliasRedirects.php` | Listenlogik und Auflösung alter Alias → Seite |
| `src/EventListener/PageAliasListener.php` | Felder in den Paletten; `alias.save` legt den alten Alias in die Liste; Schalter zurücksetzen |
| `src/EventListener/RedirectOnNotFoundListener.php` | `kernel.request` (Priorität 16) für Bäume mit veröffentlichter 404-Seite, `kernel.exception` (Priorität 100) ohne; Sprachpräfix wird als `_locale` an den URL-Generator gegeben; Suche nur in den Wurzeln des aufgerufenen Hosts |
| `contao/dca/tl_page.php` | die Felder `gozi_redirects` (Listen-Assistent) und `gozi_noRedirect` |
| `src/Service/NotFoundLog.php` | Protokoll der 404: was hineingehört, Zusammenfassen je Adresse, Aufräumen |
| `src/Service/ManualRedirects.php` | Auflösung der von Hand gepflegten Weiterleitungen (Host-Vorrang) |
| `src/EventListener/RecordNotFoundListener.php` | `kernel.response` (Priorität −64): jede 404-Antwort kommt ins Protokoll — der einzige Punkt, an dem beide 404-Wege von Contao zusammenlaufen |
| `src/Backend/NotFoundCallbacks.php` | was beim Zuordnen wirklich passiert; `hefteAnSeite()` ist ohne DataContainer aufrufbar und damit prüfbar |
| `src/Backend/NotFoundOperations.php` | Knöpfe, Zeilen und Übersicht der 404-Liste |
| `src/Backend/Aus404.php` | Vorbelegung beim Anlegen einer eigenen Weiterleitung, über `default`-Closures im DCA |
| `contao/dca/tl_gozi_404.php`, `contao/dca/tl_gozi_redirect.php` | Arbeitsliste und Weiterleitungstabelle |
| `tests/alias-redirect-suite.php` | Listenlogik, Auflösung gegen die Datenbank, 301 per HTTP; Aufruf aus der Projektwurzel einer Installation |
| `tests/404-liste-suite.php` | Protokoll, Zuordnen an eine Seite, eigene Weiterleitungen, 301/410 per HTTP |

Für die Entwicklung: Path-Repository auf das Bundle-Verzeichnis und
`composer require gozi/contao-alias-redirect:@dev`.

## Lizenz und Kontakt

LGPL-3.0-or-later. Goldener Zirkel, https://goldener-zirkel.com. Fragen und Fehlermeldungen:
https://github.com/goldenerzirkel/contao-alias-redirect/issues
