# Saniplan v2 – Umsetzungsplan

Stand: 19.11.2026 · Branch `v2`

## Ziele

1. **Ausfallsicherheit**: Die Anzeige im Sekretariat zeigt immer etwas Sinnvolles, auch wenn Daten fehlen oder der Pi kurz weg ist.
2. **Bedienbarkeit**: Plan, Personen und Einstellungen lassen sich vollständig im Browser pflegen. Niemand muss mehr CSV-Dateien von Hand bearbeiten.
3. **Wartbarkeit**: Klare Struktur, kein doppelter Code, das Stundenraster steht nicht mehr fest im Code.

## Rahmenbedingungen (festgelegt)

| Vorgabe | Konsequenz |
|---|---|
| Update auf dem Pi nur per `git pull`, ohne weitere Konfiguration | PHP + Apache wie bisher, **kein Build-Schritt**, kein Node, kein Composer. Die Datenbank wird automatisch angelegt und migriert. |
| PHP-Version auf dem Pi unbekannt, wahrscheinlich 7.x | Der Code ist **kompatibel mit PHP 7.0**: keine nullable/void-Typen, keine Pfeilfunktionen, keine typisierten Eigenschaften, kein `match`, kein `str_contains`. |
| SQLite ist installiert (`pdo_sqlite`) | Die Datenbank liegt in `data/saniplan.sqlite`. Der Webserver darf in `data/` schreiben, das hat v1 schon getan. |
| Kein Passwort | Alle Seiten sind ohne Login erreichbar. Das Netz ist für Schüler nicht zugänglich. |
| Pi wird nicht abgesichert | Kein Backup, kein Watchdog. Fallback ist der ausgedruckte Plan, deshalb ist eine gute Druckansicht Pflicht. |
| A/B-Woche entfällt | Pro Gebäude gibt es genau einen Wochenplan. Die Dateien `…UngeradeWoche….csv` werden ignoriert. |
| Keine Vertretungsfunktion | – |
| Genau zwei Gebäude | Hauptgebäude (LGÖ) und Musikschule stehen fest im Code, keine Verwaltung dafür. |
| Zugriff per `http://<IP>` | **Keine Service Worker** (die gehen nur mit HTTPS). Die Anzeige wird stattdessen so gebaut, dass sie nie neu lädt (siehe unten). |
| Sekretariat nutzt aktuelle Browser | Die Oberfläche nutzt ES-Module. Die Bibliotheken liegen als Dateien im Repo, es gibt kein CDN, weil der Pi eventuell kein Internet hat. |

## Technik

- **Server**: PHP 7.0+ ohne Framework, eine kleine eigene Bibliothek unter `lib/`, JSON-API unter `api/`.
- **Datenbank**: SQLite über PDO, `PRAGMA foreign_keys=ON`, `busy_timeout`, Schreibzugriffe in Transaktionen.
- **Oberfläche**: Preact + htm als eine vendorte Datei (`assets/vendor/`, ca. 15 KB), dazu eigenes CSS. Kein Build.
- Die Seiten sind schlanke PHP-Hüllen (`index.php`, `editor/index.php` …). Sie liefern HTML-Gerüst und Footer aus und laden das jeweilige JS-Modul.

## Verzeichnisstruktur (Ziel)

```
index.php                 Anzeige (URL bleibt gleich, die Lesezeichen funktionieren weiter)
editor/index.php          Bereitschaftsplan-Editor
editor/druck.php          Druckansicht (A4 quer, beide Gebäude wählbar)
personen/index.php        Personenverwaltung
settings/index.php        Einstellungen (URL bleibt gleich)
diagnose/index.php        Diagnose: PHP-Version, SQLite, Schreibrechte, Import-Status, Uhrzeit
api/index.php             JSON-API (?r=anzeige, plan, personen, einstellungen, schichten, …)
lib/                      PHP-Code (bootstrap, db, migrationen, import_v1, layout); .htaccess: Zugriff verweigert
assets/css/               Styles (Bildschirm + Druck)
assets/js/                Module je Seite + gemeinsame Hilfsfunktionen (Zeitlogik, API-Client)
assets/vendor/            preact + htm (Standalone-Modul)
data/                     NICHT im Git (außer .htaccess): saniplan.sqlite, archiv-v1/
docs/                     Dieser Plan
```

`lib/` und `data/` bekommen eine `.htaccess` mit `Require all denied`. Ob Apache sie beachtet (`AllowOverride`), ist unbekannt. Im geschlossenen Netz ist das akzeptabel. Zusätzlich bricht jede Datei in `lib/` ab, wenn sie direkt aufgerufen wird.

## Datenmodell

```sql
person        (id INTEGER PK, nachname, vorname, klasse, telefon)   -- Spalte spitzname existiert noch, wird seit 2.0.2 nicht mehr genutzt
schicht       (id INTEGER PK, position, bezeichnung, beginn 'HH:MM', ende 'HH:MM')
einteilung    (gebaeude TEXT CHECK IN ('hauptgebaeude','musikschule'),
               wochentag INTEGER 1–5, schicht_id → schicht, person_id → person (ON DELETE CASCADE),
               position INTEGER,
               PRIMARY KEY (gebaeude, wochentag, schicht_id, person_id))
notfallkontakt(person_id PK → person (ON DELETE CASCADE), position)
einstellung   (schluessel PK, wert)   -- verantwortliche (JSON-Liste), stand (YYYY-MM-DD), uhr_anzeigen (0/1)
plan_historie (id PK, zeitpunkt, gebaeude, daten JSON)  -- Snapshot bei jedem Speichern, für „Wiederherstellen“
```

- Die Schema-Version steht in `PRAGMA user_version`. Migrationen sind eine geordnete Liste in `lib/migrationen.php` und laufen bei jedem Request, der eine veraltete Version vorfindet: unter `flock` auf `data/.lock`, in einer Transaktion.
- **Standard-Schichten** wie im v1-Editor (zehn Zeilen, Pausen sind an die angrenzende Stunde gekoppelt):

| Pos | Bezeichnung | Zeit |
|---|---|---|
| 1 | 1. Stunde | 07:45–08:40 |
| 2 | 2. Stunde | 08:40–09:30 |
| 3 | 3. Stunde + Pause | 09:30–10:35 |
| 4 | 4. Stunde | 10:35–11:25 |
| 5 | 5. Stunde | 11:25–12:10 |
| 6 | Pause + 6. Stunde | 12:10–13:15 |
| 7 | 7. Stunde | 13:15–14:00 |
| 8 | 8. Stunde | 14:00–14:45 |
| 9 | 9. Stunde | 14:45–15:35 |
| 10 | 10. Stunde | 15:35–16:20 |

  Bezeichnungen und Zeiten lassen sich in den Einstellungen ändern.
- Die Einstellungen `reportingLevel` und `useTwoWeekPlan` entfallen. An ihre Stelle tritt die Diagnoseseite.

## Umstieg von v1 (zwei Releases)

Die CSV-Dateien und `settings/config.json` sind in v1 eingecheckt, auf dem Pi aber lokal geändert. Ein Commit, der sie löscht, würde `git pull` dort abbrechen lassen. Das umgehen wir mit zwei Releases.

### Release 2.0: Dateien bleiben im Repo, unverändert

Beim ersten Request ohne Datenbank (unter Lock):

1. Schema anlegen und Standard-Schichten einfügen.
2. Gibt es `data/personalData.csv`, Personen importieren:
   - Trennzeichen `;`, erste Zeile ist die Kopfzeile, Zeilenenden CRLF oder LF, BOM entfernen.
   - Liegt die Datei nicht in UTF-8 vor, wird sie aus Windows-1252 umgewandelt.
   - Die ID wird übernommen.
3. Gibt es `data/bereitschaftsplan.csv` bzw. `data/bereitschaftsplanMusikschule.csv`, die Einteilungen importieren:
   - Es werden genau die Zeilen genommen, die auch der v1-Editor liest: Datenzeilen 1–12 ohne die 4. und 8.
   - Spalte 1–5 entspricht Montag bis Freitag.
   - Einträge sind durch `:` getrennt und werden über die ID, den Spitznamen aus v1 oder „Vorname Nachname“ einer Person zugeordnet. Der Spitzname selbst wird seit 2.0.2 nicht mehr übernommen.
   - Fehlende Zeilen bleiben leer, nicht zuordenbare Einträge kommen ins Import-Protokoll.
4. Aus `settings/config.json` übernehmen: Verantwortliche, Stand (`TT.MM.JJJJ` → ISO) und Uhr-Anzeige.
   - **Notfallkontakte** so übernehmen, wie v1 sie *tatsächlich angezeigt* hat. v1 hat den Wert als Zeilenindex in `personalData.csv` behandelt (Kopfzeile = 0), nicht als ID.
   - Im Import-Protokoll steht ein Hinweis, die Notfallkontakte einmal zu prüfen.
5. Das Import-Protokoll wird in `einstellung` gespeichert und auf der Diagnoseseite angezeigt.
6. **Nur wenn alles erfolgreich war (Commit)**: Alle v1-Dateien nach `data/archiv-v1/` verschieben. Das sind die vier `bereitschaftsplan*.csv`, `personalData.csv` und `settings/config.json`.
   - Falls `settings/` für den Webserver nicht beschreibbar ist, wird `config.json` nach `data/archiv-v1/` kopiert. Danach wird die Originaldatei mit **exakt dem eingecheckten v1-Inhalt** überschrieben. Dann gilt sie für git als unverändert, und Release 2.0.1 kann sie trotzdem per Pull entfernen.
   - Die Diagnoseseite zeigt an, ob alle Altdateien weg bzw. zurückgesetzt sind.

### Release 2.0.1: Altdateien aus dem Repo entfernen

- `git rm` für `data/*.csv` und `settings/config.json`. In `.gitignore` kommen `data/*` (außer `data/.htaccess`) und `settings/config.json`.
- **Erst pushen, wenn die Diagnoseseite auf dem Pi „Import abgeschlossen, Altdateien archiviert“ zeigt.** Holt der Pi beide Releases auf einmal, bricht `git pull` ab. Daten gehen dabei nicht verloren.

### Weitere Risiken beim Umstieg

- Wurden auf dem Pi auch **Code-Dateien** lokal geändert (z. B. `index.php`), bricht der Pull von 2.0 ebenfalls ab. Davon gehen wir nicht aus. Falls es doch passiert, hilft einmalig `git checkout -- <datei>`.
- Neue Installation ohne v1-Daten: Die Datenbank startet leer, Personen legt man in der Oberfläche an oder importiert sie per CSV.

## Seiten und Funktionen

### Anzeige (`index.php`) – das Wichtigste

- Die Seite lädt einmal die **gesamte Woche** über `api/?r=anzeige`: Schichten, beide Pläne, die benötigten Personen, Notfallkontakte, Verantwortliche, Stand, Serverzeit, App-Version und Daten-Version.
- **Die aktuelle Schicht berechnet der Browser** jede Sekunde selbst. Es gibt kein Neuladen zur exakten Sekunde mehr. Ist der Tab im Hintergrund gedrosselt, stimmt die Anzeige trotzdem, sobald er wieder aktiv ist.
- **Seite lädt nie komplett neu.** Alle 60 s wird `api/?r=anzeige&v=<datenversion>` abgefragt:
  - Neue Daten werden übernommen, ohne dass die Seite neu lädt.
  - Ändert sich die App-Version (nach einem `git pull`), lädt die Seite *nur dann* neu, wenn der Server gerade erreichbar ist.
  - Erreicht die Seite den Server nicht, zeigt sie den zuletzt geladenen Plan weiter an. Ein Banner sagt dann „Keine Verbindung zum Server seit HH:MM – angezeigt wird der zuletzt geladene Plan“.
- Mögliche Zustände:
  - **Während einer Schicht**: Personen je Gebäude in großer Schrift mit gut lesbarer Telefonnummer, darunter klein die nächste Schicht.
  - **Schicht ohne Einteilung**: Hinweis „Niemand eingeteilt“, die Notfallkontakte rücken direkt darunter.
  - **Vor der ersten / nach der letzten Schicht, Wochenende**: Eigener Hinweistext und die Notfallkontakte.
  - Die **Notfallkontakte** stehen immer am Ende der Seite.
- **Uhr** (abschaltbar): Bleibt die Abweichung zwischen Server- und Browserzeit über 5 Minuten, erscheint ein sichtbarer, aber unaufdringlicher Hinweis statt `alert()`. Dazu der Satz „Bitte an … wenden“ mit den Verantwortlichen.
- Kaputte Daten oder eine fehlende DB führen nie zu einer weißen Seite. Stattdessen erscheint eine verständliche Meldung mit den Verantwortlichen und einem Link zur Diagnoseseite.
- Die Kopfzeile verlinkt Einstellungen, Editor und Personen.

### Editor (`editor/`)

- Ein Raster aus Schichten und Montag bis Freitag. Oben lässt sich das Gebäude umschalten. Ungespeicherte Änderungen gehen dabei nicht verloren, weil beide Gebäude im Speicher gehalten werden.
- Personenliste rechts mit **Suchfeld**.
- Personen zuweisen auf zwei Wegen:
  - **Drag & Drop** wie bisher.
  - **Person anklicken, dann Zellen anklicken.** Das geht auch mit Touch und Tastatur, und man kann eine Person schnell in mehrere Zellen setzen.
- Eine Person in einer Zelle lässt sich über ein ×-Symbol entfernen. Es gibt **Rückgängig/Wiederholen** (Strg+Z/Strg+Y) statt Bestätigungsdialogen.
- **Hinweise im Raster**:
  - Leere Zellen sind dezent markiert.
  - Steht eine Person zur selben Zeit in beiden Gebäuden, ist die Zelle deutlich markiert.
  - Die Anzahl der Einsätze pro Person steht in der Liste.
- Buttons:
  - **Speichern** schreibt in die DB, setzt den Stand auf heute und legt einen Historien-Snapshot an.
  - **Drucken**
  - **Leeren**
  - **CSV exportieren**
  - **Frühere Version wiederherstellen** (Liste der Snapshots)
  - **Zurück**
- Bei ungespeicherten Änderungen gibt es eine Markierung und eine `beforeunload`-Warnung.
- **Kurze Bedienungsanleitung** als Popup unten links, an v2 angepasst.

### Druckansicht (`editor/druck.php`)

- A4 quer, ein Gebäude pro Seite oder beide nacheinander, mit Stand-Datum und Notfallkontakten samt Telefonnummer. Das ist der Papier-Fallback.

### Personen (`personen/`)

- Tabelle zum Suchen und Sortieren. Personen anlegen, bearbeiten, löschen.
- Beim Löschen erscheint ein Hinweis, in wie vielen Schichten und ob als Notfallkontakt die Person eingeteilt ist.
- **CSV-Import** im Format `Id;Name;Vorname;Klasse;Telefonnummer` (eine alte Nickname-Spalte wird ignoriert) mit Vorschau: neu / geändert / unverändert.
- „Alle Personen löschen“ für den Schuljahreswechsel, mit doppelter Bestätigung.

### Einstellungen (`settings/`)

- **Notfallkontakte** aus der Personenliste wählen und sortieren, statt IDs einzutippen.
- **Verantwortliche** als Liste von Namen.
- **Uhr anzeigen** ja/nein.
- **Stand** als Datumsfeld mit „Heute“-Button.
- **Schichten**: Bezeichnung, Beginn und Ende bearbeiten. Überschneidungen und Lücken werden geprüft.

### Diagnose (`diagnose/`)

- PHP-Version, `pdo_sqlite`, Schreibrechte auf `data/`, DB-Größe, Schema-Version
- Import-Status und Protokoll, ob Altdateien vorhanden sind (wichtig für Release 2.0.1)
- Server- und Browserzeit nebeneinander, Zeitzone
- App-Version

### Auf allen Seiten

- Footer wie bisher: Stand, Version, Copyright.
- Zeitzone `Europe/Berlin` steht im Code fest, PHP und JS rechnen beide in lokaler Zeit.

## API (Auszug)

| Methode | Route | Zweck |
|---|---|---|
| GET | `api/?r=anzeige` | Alles für die Anzeige, mit `datenversion` und `appversion` |
| GET/PUT | `api/?r=plan&gebaeude=…` | Plan lesen/speichern (der ganze Plan eines Gebäudes, transaktional) |
| GET | `api/?r=historie&gebaeude=…` | Snapshots |
| GET/POST/PUT/DELETE | `api/?r=personen[&id=…]` | Personenverwaltung |
| POST | `api/?r=personen-import` | CSV-Import (`vorschau=1` für Trockenlauf) |
| GET/PUT | `api/?r=einstellungen` | Einstellungen inkl. Notfallkontakte |
| GET/PUT | `api/?r=schichten` | Stundenraster |
| GET | `api/?r=diagnose` | Diagnosedaten |

Alle Schreibzugriffe validieren die Eingaben serverseitig und laufen in einer Transaktion. Fehler kommen als JSON `{fehler: "…"}` mit passendem HTTP-Status zurück.

## Umsetzungsschritte

1. ✅ Branch, Plan, README
2. ✅ Grundgerüst: `lib/bootstrap.php`, DB-Verbindung, Migrationen mit Lock, `.gitignore`, `.htaccess`, Diagnoseseite
3. ✅ v1-Import inkl. Archivierung und Rücksetz-Fallback für `config.json`
4. ✅ JSON-API
5. ✅ Anzeige (mit Testmodus `?testzeit=2026-11-16T09:45` zum Prüfen beliebiger Uhrzeiten)
6. ✅ Editor + Druckansicht
7. ✅ Personen + Einstellungen
8. ✅ Alte v1-Code-Dateien entfernt
9. ✅ Tests in Docker (siehe unten)
10. ✅ Release 2.0 auf Branch `v2`, Release 2.0.1 (Altdateien entfernt) auf `main`. Ablauf auf dem Pi: `git pull origin v2` → Diagnoseseite öffnen → `git pull origin main` (siehe README)

### Durchgeführte Tests (19.11.2026)

| Szenario | Umgebung | Ergebnis |
|---|---|---|
| Umstieg mit den v1-Beispieldaten aus dem Repo | `php:7.0-apache` | Import korrekt, alle Altdateien archiviert |
| Pi-Simulation: v1-Klon mit lokal geänderten Daten (Windows-1252, CRLF, doppelte ID, Zeile mit 5 Feldern, Spitznamen, „Vorname Nachname“, unbekannter Eintrag, abweichende Pausenzeile, Notfallkontakte als Zeilenindex) → `git pull` auf v2 | `php:8.3-apache` | Pull ohne Konflikt, Import und Protokoll korrekt, Notfallkontakte wie in v1 angezeigt |
| Danach simuliertes Release 2.0.1 (Altdateien per `git rm` entfernt) → `git pull` | – | Pull ohne Konflikt, Arbeitsverzeichnis sauber |
| `settings/` für den Webserver nicht beschreibbar | `php:7.0-apache`, echte Linux-Rechte | `config.json` kopiert und byte-genau auf den Git-Stand zurückgesetzt |
| Neuinstallation ohne v1-Daten | `php:7.0-apache` | Leere Datenbank, alle Seiten erreichbar |
| API: Validierung, Konflikterkennung beim Speichern, Import mit Fehlern | `php:7.0-apache` | wie erwartet |
| Editor: Klick-Zuweisung, Drag & Drop, Rückgängig, Speichern, Druckansicht | Chromium | wie erwartet |
| Anzeige: Schicht, leere Schicht, Server während des Betriebs gestoppt | Chromium | Plan bleibt stehen, Hinweis „Keine Verbindung seit …“ |

Nicht getestet: echter Raspberry Pi und der Browser im Sekretariat.

## Bewusst nicht enthalten

A/B-Woche · Vertretungen · Login · Backups/Watchdog · mehr als zwei Gebäude · Offline-Cache per Service Worker
