# gozi/contao-alias-redirect

Alte Seiten-Aliase bleiben als **301-Weiterleitung** erhalten — automatisch beim Umbenennen, sichtbar und
bearbeitbar **direkt an der Seite**, versioniert mit ihr. Kein eigener Navigationspunkt, keine eigene Tabelle.

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

`composer.json` der Installation: Path-Repository auf `bundles/contao-alias-redirect`, dann
`composer require gozi/contao-alias-redirect:@dev`. Zwei Spalten an `tl_page`
(`gozi_redirects`, `gozi_noRedirect`) legt `contao:migrate` an.

## Dateien

| Datei | Zweck |
|---|---|
| `src/Service/AliasRedirects.php` | Listenlogik (bereinigen, alten Alias einreihen) und Auflösung alter Alias → Seite |
| `src/EventListener/PageAliasListener.php` | Felder in alle Paletten mit Alias; `alias.save` legt den alten Alias in die Liste; Liste bereinigen; Schalter zurücksetzen |
| `src/EventListener/RedirectOnNotFoundListener.php` | `kernel.exception` (Priorität 100): 404 → 301 |
| `contao/dca/tl_page.php` | die zwei Felder |
| `tests/alias-redirect-suite.php` | Listenlogik, Auflösung gegen die Datenbank, 301 per HTTP |

## Belegt (04.09.2026)

Suite 20/0. Backend-Nutzertest per Browser: Umbenennen legt den alten Alias in die Liste, Versionierung
trägt das Feld, alter und mittlerer Alias führen per 301 auf den aktuellen, der aktuelle liefert 200.
Bild: `docs/backend-weiterleitungen.png`.
