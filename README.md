# saniplan
Der Saniplan™ ist ein Tool, um einen Bereitschaftsplan im typischen Stunden-Raster des Leibniz-Gymnasiums anzuzeigen und zu verwalten.

## Konfiguration
Um den Saniplan™ einzurichten benötigt man drei bzw. fünf csv-Dateien.

Eine Datei davon ist die "personalData.csv". In dieser befinden sich die Stammdaten Id, Name, Vorname, Klasse, Telefonnummer und ggf. ein Nickname der Personen. Die Daten werden mit ; getrennt. Die Id muss numerisch und eindeutig sowie nicht 0 sein.

*Beispiel für data/personalData.csv*
```csv
id;Name;Vorname;Klasse;Telefonnummer;Nickname
1;Mustermann;Max;10c;1234/567890;Maxi
1;Arbeit;Andi;12c;1234/567889;AA
...
```

Die Schichteinteilung erfolgt in zwei bzw. vier weiteren csv-Dateien. Es gibt eine Datei für die Musikschule und eine Datei für das Hauptgebäude ('bereitschaftsplan.csv' und 'bereitschaftsplanMusikschule.csv'). Es gibt die Option, einen zweiwöchigen Zyklus zu aktivieren. Falls diese Option mit der Eigenschaft "useTwoWeekPlan" aktiviert ist bedarf es nochmal zwei extra Dateien für die ungeraden Wochen ('bereitschaftsplanUngeradeWoche.csv' und 'bereitschaftsplanUngeradeWocheMusikschule.csv').

In diesen Dateien werden die eingeteilten Sanis im typischen Raster eingetragen. In der ersten Spalte steht jeweils die Bezeichnung der Schicht (letztendlich ist es egal, was drin steht. Eine Spalte muss aber am Anfang stehen). Außerdem wird die erste Zeile nicht beachtet.

Alle weiteren Felder werden mit den jeweils eingeteilten Personen eingetragen. In jeder Schicht müssen die Id, Vorname + Nachname oder der Nickname der eingeteilten Person mit : getrennt eingetragen werden.
Die Schichten werden jeweils mit ; getrennt. Dabei ist es wichtig, dass auch nach  der letzen Schicht ein ; kommt, um Fehler zu vermeiden.

*Beispiel für data/bereitschaftsplanUngeradeWoche.csv*
```csv
0;Montag;Dienstag;Mittwoch;Donnerstag;Freitag;
1. Stunde;Maxi:Johnny;Franzi;Rudi;Leni;Uli;
2. Stunde;...
```
## Einstellungen
über den Button Einstellungen kommt man in die Einstellungen. Dort kann man sechs Einstellungen verwalten:
1. Ob der Bereitschaftsplan im wöchentlichen Wechsel angezeigt wird (True/False).
2. Die Ids der "Notfallkontakte". Diese werden unterhalb der normalen Schichten angezeigt. Das ist dazu gedacht, falls niemand erreichbar ist, oder es zu schweren Notfällen kommt. Es können beliebig viele Ids per , getrennt eingetragen werden.
3. Verantwortliche Personen zur Fehlerbehebung. Falls es zu einem Fehler kommt wird eine Meldung angezeigt in der steht, an wen man sich wenden soll. In das Feld kann man beliebig viele Namen mit , getrennt eintragen, die dann in der Nachricht angezeigt werden.
4. Reporting Level. Einstellung, ob Errors und/oder Fehler angezeigt werden sollen (0/1/2).
5. Anzeigeeinstellung für die Uhr
6. Stand des Bereitschaftsplans. Dort kann man einstellen, welches Datum unten links als Stand angezeigt wird.

## Bereitschaftsplan-Editor
Über den Button oben rechts gelangt man zum eingebauten Bereitschaftsplan-Editor. In diesem kann man die anzuzeigenen Bereitschaftspläne anzeigen, ändern und herunterladen. Eine Bedienungsanleitung dafür gibt es über den Button unten links.
