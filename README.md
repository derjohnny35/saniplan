# saniplan
Website, um den Bereitschaftsplan anzuzeigen

## Benutzung
Um den Bereitschaftsplan anzuzeigen, braucht es zwei .csv Dateien.
Eine, in der die Daten der Sanis eingetragen sind.

*Beispiel für data/personalData.csv*
```csv
id;Name;Vorname;Klasse;Telefonnummer;Nickname
1;Mustermann;Max;10c;1234/567890;Maxi
...
```
Dabei sind die ersten drei Personen die, die auf der Website als immer erreichbar darstehen.

Und eine (bzw. zwei Dateien für gerade und ungerade Wochen) .csv Datei, in der die Schichten eingetragen sind.

*Beispiel für data/bereitschaftsplanUngeradeWoche.csv*
```csv
0;Montag;Dienstag;Mittwoch;Donnerstag;Freitag
1. Stunde;Maxi:Johnny;Franzi;Rudi;Leni;Uli
2. Stunde;...
```
In jeder Schicht müssen die Ids der eingeteilten Sanis mit : getrennt eingetragen werden.
