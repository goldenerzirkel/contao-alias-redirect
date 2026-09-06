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
| `tests/alias-redirect-suite.php` | Listenlogik, Auflösung gegen die Datenbank, 301 per HTTP; Aufruf aus der Projektwurzel einer Installation |

Für die Entwicklung: Path-Repository auf das Bundle-Verzeichnis und
`composer require gozi/contao-alias-redirect:@dev`.

## Lizenz und Kontakt

LGPL-3.0-or-later. Goldener Zirkel, https://goldener-zirkel.com. Fragen und Fehlermeldungen:
https://github.com/goldenerzirkel/contao-alias-redirect/issues
