# saniplan
Website, um einen Bereitschaftsplan für Schulsanitäter im am LGÖ üblichen Zeitplan anzuzeigen.

## Konfiguration
Die Daten für den Bereitschaftsplan befinden sich in .csv Dateien.

Die Stammdaten der Schulsanis befinden sich in der 'personalData.csv'
In diese werden die Daten id (muss zwingend bei 1 beginnen und aufsteigen), Name, Vorname, Klasse, Telefonnummer, Nickname ab Zeile zwei jeweils mit ; getrennt eingetragen.

*Beispiel für data/personalData.csv*
```csv
id;Name;Vorname;Klasse;Telefonnummer;Nickname
1;Mustermann;Max;10c;1234/567890;Maxi
...
```

Die Schichteinteilung erfolgt in weiteren Dateien. Es gibt eine Datei für die Musikschule und eine Datei für das Hauptgebäude ('bereitschaftsplan.csv' und 'bereitschaftsplanMusikschule.csv'). Falls die Option "useTwoWeekPlan" aktiviert ist bedarf es nochmal zwei extra Dateien für die ungeraden Wochen ('bereitschaftsplanUngeradeWoche.csv' und 'bereitschaftsplanUngeradeWocheMusikschule.csv').
In diesen Dateien werden die eingeteilten Sanis im typischen Raster eingetragen. In der ersten Spalte stehen die Bezeichnungen der Wochentage. In der ersten Spalte steht jeweils die Bezeichnung der Schicht (irrelevant, kann auch leer gelassen werden).
Alle weiteren Felder werden mit den jeweils eingeteilten Personen eingetragen. In jeder Schicht müssen die Id, Vorname + Nachname oder der Nickname der eingeteilten Person mit : getrennt eingetragen werden.

*Beispiel für data/bereitschaftsplanUngeradeWoche.csv*
```csv
0;Montag;Dienstag;Mittwoch;Donnerstag;Freitag
1. Stunde;Maxi:Johnny;Franzi;Rudi;Leni;Uli
2. Stunde;...
```