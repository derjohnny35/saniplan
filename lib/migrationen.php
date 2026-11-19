<?php
if (!defined('SANIPLAN')) { http_response_code(403); exit; }

/**
 * Geordnete Liste der Schema-Migrationen. Index = Schema-Version nach der Migration.
 * Neue Migrationen nur hinten anhängen, bestehende nie verändern.
 * Hinweis: Das SQLite auf älteren Pis kennt kein UPSERT, keine Window-Funktionen und kein DROP COLUMN.
 * Deshalb bleibt die Spalte person.spitzname bestehen, wird aber seit 2.0.2 nicht mehr verwendet.
 */
function saniplan_migrationen()
{
    return array(
        1 => function (PDO $db) {
            $db->exec("CREATE TABLE person (
                id INTEGER PRIMARY KEY,
                nachname TEXT NOT NULL DEFAULT '',
                vorname TEXT NOT NULL DEFAULT '',
                klasse TEXT NOT NULL DEFAULT '',
                telefon TEXT NOT NULL DEFAULT '',
                spitzname TEXT NOT NULL DEFAULT ''
            )");
            $db->exec("CREATE TABLE schicht (
                id INTEGER PRIMARY KEY,
                position INTEGER NOT NULL,
                bezeichnung TEXT NOT NULL,
                beginn TEXT NOT NULL,
                ende TEXT NOT NULL
            )");
            $db->exec("CREATE TABLE einteilung (
                gebaeude TEXT NOT NULL CHECK (gebaeude IN ('hauptgebaeude', 'musikschule')),
                wochentag INTEGER NOT NULL CHECK (wochentag BETWEEN 1 AND 5),
                schicht_id INTEGER NOT NULL REFERENCES schicht(id) ON DELETE CASCADE,
                person_id INTEGER NOT NULL REFERENCES person(id) ON DELETE CASCADE,
                position INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (gebaeude, wochentag, schicht_id, person_id)
            )");
            $db->exec("CREATE INDEX einteilung_person ON einteilung (person_id)");
            $db->exec("CREATE TABLE notfallkontakt (
                person_id INTEGER PRIMARY KEY REFERENCES person(id) ON DELETE CASCADE,
                position INTEGER NOT NULL
            )");
            $db->exec("CREATE TABLE einstellung (
                schluessel TEXT PRIMARY KEY,
                wert TEXT NOT NULL
            )");
            $db->exec("CREATE TABLE plan_historie (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                zeitpunkt TEXT NOT NULL,
                gebaeude TEXT NOT NULL,
                daten TEXT NOT NULL
            )");

            $schichten = array(
                array('1. Stunde', '07:45', '08:40'),
                array('2. Stunde', '08:40', '09:30'),
                array('3. Stunde + Pause', '09:30', '10:35'),
                array('4. Stunde', '10:35', '11:25'),
                array('5. Stunde', '11:25', '12:10'),
                array('Pause + 6. Stunde', '12:10', '13:15'),
                array('7. Stunde', '13:15', '14:00'),
                array('8. Stunde', '14:00', '14:45'),
                array('9. Stunde', '14:45', '15:35'),
                array('10. Stunde', '15:35', '16:20'),
            );
            $st = $db->prepare('INSERT INTO schicht (position, bezeichnung, beginn, ende) VALUES (?, ?, ?, ?)');
            foreach ($schichten as $i => $s) {
                $st->execute(array($i + 1, $s[0], $s[1], $s[2]));
            }

            einstellung_schreiben($db, 'verantwortliche', '[]');
            einstellung_schreiben($db, 'stand', '');
            einstellung_schreiben($db, 'uhr_anzeigen', '1');
            einstellung_schreiben($db, 'datenversion', '1');
            einstellung_schreiben($db, 'installiert_am', date('c'));
        },
    );
}
