# saniplan

Der Saniplan™ zeigt im Sekretariat an, welche Schulsanitäterinnen und Schulsanitäter gerade Bereitschaft haben, und hilft dabei, den Bereitschaftsplan im Stundenraster des Leibniz-Gymnasiums zu verwalten. Es gibt zwei Gebäude: das Hauptgebäude (LGÖ) und die Musikschule.

> **Version 2 ist in Arbeit** (Branch `v2`). Der Umsetzungsplan steht in [docs/v2-plan.md](docs/v2-plan.md).

## Funktionen

- **Anzeige**: die aktuelle Schicht für beide Gebäude mit Name, Klasse und Telefonnummer, dazu die nächste Schicht und die Notfallkontakte. Die Anzeige wechselt die Schicht von selbst. Ist der Server kurz nicht erreichbar, zeigt sie den zuletzt geladenen Plan weiter an.
- **Bereitschaftsplan-Editor**: Personen per Drag & Drop oder per Klick einteilen, mit Rückgängig, Warnungen bei Doppelbelegung, Druckansicht und CSV-Export. Frühere Versionen lassen sich wiederherstellen. Eine kurze Bedienungsanleitung ist im Editor eingebaut (unten links).
- **Personen**: Sanis anlegen, bearbeiten, löschen oder per CSV importieren.
- **Einstellungen**: Notfallkontakte, Verantwortliche für Fehler, Uhr-Anzeige, Stand des Plans und Schichtzeiten.
- **Diagnose**: zeigt an, ob PHP, SQLite, Schreibrechte, Import und Uhrzeit in Ordnung sind.

## Voraussetzungen

- Apache (oder ein anderer Webserver) mit **PHP 7.0 oder neuer**
- PHP-Erweiterung **`pdo_sqlite`** (Debian/Raspberry Pi OS: Paket `php-sqlite3`)
- Der Webserver-Benutzer (meist `www-data`) darf in das Verzeichnis `data/` schreiben.

Node, Composer, ein Build-Schritt oder eine separate Datenbank sind nicht nötig.

## Installation

```sh
cd /var/www/html
git clone https://github.com/derjohnny35/saniplan.git
```

Danach die Seite im Browser öffnen, z. B. `http://<IP-des-Pi>/saniplan/`. Beim ersten Aufruf wird die Datenbank `data/saniplan.sqlite` automatisch angelegt.

## Update

```sh
cd /var/www/html/saniplan
git pull
```

Mehr ist nicht nötig. Änderungen an der Datenbank werden beim nächsten Seitenaufruf automatisch übernommen. Offene Anzeigen im Sekretariat laden die neue Version von selbst, sobald der Server erreichbar ist.

Ob alles läuft, zeigt die Diagnoseseite unter `…/saniplan/diagnose/`.

## Umstieg von Version 1

Der Umstieg passiert automatisch beim ersten Aufruf nach dem `git pull`:

1. Version 2 liest die vorhandenen Dateien ein. Das sind `data/personalData.csv`, `data/bereitschaftsplan.csv`, `data/bereitschaftsplanMusikschule.csv` und `settings/config.json`.
2. Danach werden diese Dateien nach `data/archiv-v1/` verschoben und bleiben dort als Sicherung liegen.
3. Auf der Diagnoseseite steht das Import-Protokoll. **Bitte einmal unter *Einstellungen* prüfen, ob die richtigen Notfallkontakte eingetragen sind.**

Die Pläne für die ungerade Woche (A/B-Woche) gibt es in Version 2 nicht mehr. Sie werden nicht übernommen.

> **Hinweis für Entwickler:** Das Release, das die alten CSV-Dateien aus dem Repository entfernt (2.0.1), erst veröffentlichen, wenn die Diagnoseseite auf dem Pi „Import abgeschlossen, Altdateien archiviert“ meldet. Sonst bricht `git pull` dort ab. Die Gründe stehen in [docs/v2-plan.md](docs/v2-plan.md#umstieg-von-v1-zwei-releases).

## Bedienung

### Anzeige
Die Startseite ist für das Sekretariat gedacht und kann dauerhaft offen bleiben. Sie muss nie neu geladen werden.

- **Während einer Schicht** stehen die eingeteilten Personen für Hauptgebäude und Musikschule groß da, darunter die nächste Schicht.
- **Ist niemand eingeteilt**, oder ist es vor Schulbeginn, nach Schulende oder am Wochenende, werden die Notfallkontakte angezeigt.
- Erscheint oben ein Hinweis auf eine **fehlende Verbindung** oder eine **falsche Uhrzeit**, bitte an die in den Einstellungen eingetragenen Verantwortlichen wenden. Im Notfall gilt der ausgedruckte Plan.

### Bereitschaftsplan-Editor
Erreichbar über den Button oben rechts. Die wichtigsten Funktionen:

- **Einteilen**: Eine Person aus der Liste rechts in eine Zelle ziehen. Alternativ die Person anklicken und dann nacheinander die gewünschten Zellen anklicken.
- **Entfernen**: Auf das × an der Person in der Zelle klicken.
- **Rückgängig/Wiederholen**: Strg+Z / Strg+Y
- **Gebäude wechseln**: Umschalter oben. Ungespeicherte Änderungen bleiben beim Wechsel erhalten.
- **Speichern**: Übernimmt den Plan sofort in die Anzeige und setzt den Stand auf heute.
- **Drucken**: Erzeugt den Papierplan für den Notfall. Bitte nach jeder Änderung neu ausdrucken.

### Personen
Personen lassen sich einzeln pflegen oder zum Schuljahreswechsel per CSV importieren. Format (Trennzeichen `;`, erste Zeile ist die Kopfzeile):

```csv
Id;Name;Vorname;Klasse;Telefonnummer;Nickname
1;Mustermann;Max;10c;1234/567890;Maxi
2;Arbeit;Andi;12c;1234/567889;
```

### Einstellungen
- **Notfallkontakte**: Personen, die immer unten auf der Anzeige stehen, für schwere Notfälle oder wenn sonst niemand erreichbar ist.
- **Verantwortliche**: Namen, die bei Fehlermeldungen als Ansprechpersonen genannt werden.
- **Uhr anzeigen**
- **Stand des Bereitschaftsplans**: wird beim Speichern im Editor automatisch gesetzt.
- **Schichten**: Bezeichnung, Beginn und Ende jeder Schicht.

## Datenschutz

Der Saniplan enthält Namen und Telefonnummern von Schülerinnen und Schülern. Er darf nur in einem Netz betrieben werden, das für Schülerinnen und Schüler sowie von außen nicht erreichbar ist. Es gibt bewusst keinen Login. Jede Person im Netz kann Pläne, Personen und Einstellungen ändern.

Die Datei `data/saniplan.sqlite` enthält alle Daten und wird nicht ins Repository eingecheckt.
