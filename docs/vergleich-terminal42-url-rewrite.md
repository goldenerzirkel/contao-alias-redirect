# Vergleich mit terminal42/contao-url-rewrite (06.09.2026)

Kai: *„schau mal ob du dort etwas für unser alias-redirect abschauen kannst. etwas anderer ansatz, aber ähnlich."*
Gelesen: terminal42/contao-url-rewrite (vendor, installiert, 0 Einträge in tl_url_rewrite) gegen
gozi/contao-alias-redirect dev.

## Die zwei Ansätze

| | terminal42 URL-Rewrite | gozi Alias-Redirect |
|---|---|---|
| Datenmodell | eigene Tabelle `tl_url_rewrite`, ein Datensatz je Regel | Liste `gozi_redirects` an der Seite, versioniert mit ihr |
| Entstehung | Redakteur legt Regeln an (oder config.yml) | automatisch beim Umbenennen des Alias |
| Auflösung | Regeln werden **Symfony-Routen** (Loader im ChainRouter), Router-Cache wird bei jeder Änderung gelöscht | erst wenn Contao nichts findet: `kernel.request` (Prio 16, 404-Route) bzw. `kernel.exception`, dann Suche in `tl_page` per `LIKE` auf dem serialisierten Feld |
| Treffer | exakter Pfad oder Muster `{param}` mit Regex-Anforderungen, Hosts, Bedingung (ExpressionLanguage) | exakter alter Alias, mit/ohne `.html`, mit/ohne Sprachpräfix, Wurzeln des Hosts |
| Antwort | 301, 302, 303, 307, 308 oder **410 Gone**; Ziel mit Platzhaltern, Insert-Tags, bedingten Zielen; Query optional | immer 301 auf die Seite, Query bleibt |
| Pflege | `inactive`, `priority`, `comment`, `examples`, QR-Code, Backend-Modul mit allen Regeln | nur in den Seiteneinstellungen; kein Überblick über alle Aliase |
| Cache | Router-Cache-Löschung bei onsubmit, ondelete, oncopy, **onrestore_version** | keine Zwischenspeicherung; jede 404 eine Datenbankabfrage |

Der Unterschied im Kern: terminal42 löst **vor** Contao auf (Route hat Vorrang, auch wenn eine Seite
denselben Pfad hätte), wir lösen **nach** Contao auf (eine Seite mit dem Alias gewinnt immer). Für unseren
Zweck ist „nach Contao" richtig — genau das verhindert Ketten und Fehlleitungen bei wiederbenutzten Aliasen.

## Abschauen, in dieser Reihenfolge

1. **Restore-Version ist ein Schreibvorgang.** terminal42 hängt an `config.onrestore_version`. Bei uns läuft
   `alias.save` und `config.onsubmit` — stellt ein Redakteur eine alte Seitenversion wieder her, ändert sich
   der Alias, OHNE dass der alte in die Liste wandert. Callback `config.onrestore_version` ergänzen: Alias
   der wiederhergestellten Version gegen den vorherigen vergleichen, alten Alias einreihen. Kleinster
   Eingriff, echte Lücke.
2. **Index statt LIKE.** Die Suche `LIKE '%s:N:"alias";%'` über `tl_page` reicht für Dutzende Seiten; bei
   Tausenden Seiten und vielen 404-Aufrufen (Bots) ist sie eine Volltabellen-Abfrage je Anfrage. Vorbild
   ist die kompilierte Route: eine abgeleitete Tabelle `tl_gozi_alias_redirect (alias, root, pid)` mit
   Index, gepflegt in denselben Callbacks (save, delete, restore) plus Befehl zum Neuaufbau. Die Liste an
   der Seite bleibt die Wahrheit (Versionierung), die Tabelle ist nur Cache. Bringt zugleich:
3. **Überblick im Backend.** Ein Lese-Modul „Weiterleitungen" (aus der Indextabelle): alter Alias →
   Seite, mit Sprung in die Seiteneinstellungen. Heute muss man wissen, an welcher Seite ein Alias hängt.
4. **410 Gone.** Bewusst entfernte Seiten sollten nicht 404 liefern, sondern 410 — Suchmaschinen streichen
   sie dann schneller. Bei uns hängt die Liste an einer Seite, die es noch gibt; für gelöschte Seiten
   fehlt ein Ort. Vorschlag: beim Löschen einer Seite ihre Aliase (aktueller + Liste) in die Indextabelle
   mit `pid = 0` übernehmen → Antwort 410. Kein neues Feld, kein zweiter Editor.
5. **Antwortcode wählbar** (301/302) — für uns nur, wenn ein Anwendungsfall kommt. Alte Aliase sind
   dauerhaft umgezogen, 301 ist richtig.

Nicht abschauen: Muster mit Platzhaltern, Bedingungen, bedingte Ziele, Insert-Tags im Ziel, QR-Codes.
Das ist ein anderes Werkzeug (Kurz-URLs, Kampagnen), das terminal42 bereits abdeckt; beide Bundles
laufen nebeneinander.

## Aufwand

| Punkt | Umfang |
|---|---|
| 1 restore_version | ein Callback, ein Test |
| 2 Indextabelle + Neuaufbau | Migration, Service, drei Callbacks, Befehl `gozi:alias-redirect:rebuild`, Suite-Erweiterung |
| 3 Backend-Überblick | ein Lese-Modul auf der Indextabelle |
| 4 410 für gelöschte Seiten | ondelete-Callback, Listener-Zweig, Test |

1 sofort, 2–4 zusammen als Version 1.1.
