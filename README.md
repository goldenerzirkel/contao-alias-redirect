# gozi/contao-alias-redirect

Alte Seiten-Aliase bleiben als **301-Weiterleitung** erhalten — automatisch beim Umbenennen, sichtbar und
bearbeitbar **direkt an der Seite**, versioniert mit ihr. Kein eigener Navigationspunkt, keine eigene Tabelle.

![Seiteneinstellungen: Seitenalias, Schalter „Keine Weiterleitung anlegen" und die Liste „Weiterleitungen auf diese Seite"](https://raw.githubusercontent.com/goldenerzirkel/contao-alias-redirect/main/docs/seiteneinstellungen-weiterleitungen.png)

## Was es tut

- **Umbenennen:** Ändert ein Redakteur den Alias, wandert der alte Alias in das Feld
  „Weiterleitungen auf diese Seite" — im selben Speichervorgang, also in derselben Version.
- **Nicht in Reihe:** Die Einträge hängen an der *Seite*. Jeder neue Alias ist damit automatisch das
  Ziel *aller* alten; es gibt keine Kette alt1 → alt2 → alt3.
- **Einmal-Schalter:** „Keine Weiterleitung anlegen" setzt das Merken für *dieses* Speichern aus und
  wird danach zurückgesetzt.
- **Bearbeiten:** Das Feld ist Contaos Listen-Assistent — Zeilen anlegen, ändern, sortieren, löschen wie
  überall; jede Änderung steht in der Versionierung von `tl_page`.
- **Frontend:** Läuft eine Anfrage ins Leere (404), wird der Pfad als alter Alias nachgeschlagen —
  ohne `.html`-Suffix, mit und ohne Sprachpräfix, je Wurzel — und per **301** auf die Seite geleitet;
  die Query bleibt erhalten. Ein Alias, den eine Seite wieder echt trägt, gewinnt immer: Contao findet
  die Seite, die Weiterleitung kommt gar nicht erst zum Zug.

## Einbau

```
composer require gozi/contao-alias-redirect
```

Contao 5.3 oder neuer, PHP 8.2. Zwei Spalten an `tl_page` (`gozi_redirects`, `gozi_noRedirect`) legt
`contao:migrate` bzw. der Contao Manager an. Für die Entwicklung geht auch ein Path-Repository auf das
Bundle-Verzeichnis mit `composer require gozi/contao-alias-redirect:@dev`.

## Dateien

| Datei | Zweck |
|---|---|
| `src/Service/AliasRedirects.php` | Listenlogik (bereinigen, alten Alias einreihen) und Auflösung alter Alias → Seite |
| `src/EventListener/PageAliasListener.php` | Felder in alle Paletten mit Alias; `alias.save` legt den alten Alias in die Liste; Liste bereinigen; Schalter zurücksetzen |
| `src/EventListener/RedirectOnNotFoundListener.php` | `kernel.request` (Priorität 16, nach dem Router): Contao 5 routet unbekannte Pfade bei vorhandener 404-Seite direkt auf deren Catch-all-Route → 301 noch vor dem Controller. Dazu `kernel.exception` (Priorität 100) für Bäume ohne 404-Seite. Sprachpräfix des Pfads (`/de/…`) wird als `_locale` an den URL-Generator gegeben |
| `contao/dca/tl_page.php` | die zwei Felder |
| `tests/alias-redirect-suite.php` | Listenlogik, Auflösung gegen die Datenbank, 301 per HTTP |
