<?php
if (!defined('SANIPLAN')) { http_response_code(403); exit; }

/*
 * Einmaliger Umstieg von Version 1 (CSV-Dateien + settings/config.json).
 *
 * Grundsatz: Ein Fehler beim Einlesen einzelner Dateien darf die Neuinstallation nie blockieren.
 * Probleme landen im Import-Protokoll, das die Diagnoseseite anzeigt.
 */

/** Relative Pfade aller v1-Datendateien, die in Git eingecheckt waren. */
function import_v1_dateien()
{
    return array(
        'personen' => 'data/personalData.csv',
        'hauptgebaeude' => 'data/bereitschaftsplan.csv',
        'musikschule' => 'data/bereitschaftsplanMusikschule.csv',
        'ungerade_hauptgebaeude' => 'data/bereitschaftsplanUngeradeWoche.csv',
        'ungerade_musikschule' => 'data/bereitschaftsplanUngeradeWocheMusikschule.csv',
        'config' => 'settings/config.json',
    );
}

function import_v1_ausfuehren(PDO $db)
{
    $protokoll = array();
    $dateien = import_v1_dateien();
    $gefunden = false;
    foreach ($dateien as $pfad) {
        if (is_file(SANIPLAN_ROOT . '/' . $pfad)) {
            $gefunden = true;
        }
    }
    if (!$gefunden) {
        einstellung_schreiben($db, 'import_v1', json_encode(array(
            'zeitpunkt' => date('c'),
            'status' => 'keine_daten',
            'protokoll' => array('Keine Daten aus Version 1 gefunden – Neuinstallation mit leerer Datenbank.'),
        ), JSON_UNESCAPED_UNICODE));
        return;
    }

    // 1. Personen
    $v1Liste = array();   // Personen in der Reihenfolge, wie v1 sie intern geführt hat (Index 0 = Kopfzeile)
    $personen = array();  // Für die Zuordnung der Planeinträge: Liste von [id, spitzname, "Vorname Nachname"]
    $zeileZuId = array(); // Zeilennummer in personalData.csv => ID in der Datenbank
    $pfad = SANIPLAN_ROOT . '/' . $dateien['personen'];
    if (is_file($pfad)) {
        $zeilen = import_text_zeilen($pfad);
        $vergebeneIds = array();
        $maxId = 0;
        foreach ($zeilen as $nr => $zeile) {
            $felder = array_map('trim', explode(';', $zeile));
            if (count($felder) === 6) {
                $v1Liste[] = $nr; // v1 hat nur Zeilen mit genau 6 Feldern berücksichtigt
            }
            if ($nr > 0 && ctype_digit($felder[0]) && (int) $felder[0] > $maxId) {
                $maxId = (int) $felder[0];
            }
        }
        $einfuegen = $db->prepare('INSERT INTO person (id, nachname, vorname, klasse, telefon) VALUES (?, ?, ?, ?, ?)');
        $anzahl = 0;
        foreach ($zeilen as $nr => $zeile) {
            if ($nr === 0 || trim($zeile) === '') {
                continue; // Kopfzeile bzw. Leerzeile
            }
            $felder = array_map('trim', explode(';', $zeile));
            if (count($felder) < 5) {
                $protokoll[] = 'personalData.csv Zeile ' . ($nr + 1) . ': zu wenige Felder, übersprungen („' . $zeile . '“).';
                continue;
            }
            $idText = $felder[0];
            $nachname = $felder[1];
            $vorname = $felder[2];
            $klasse = $felder[3];
            $telefon = $felder[4];
            $spitzname = isset($felder[5]) ? $felder[5] : '';
            if ($nachname === '' && $vorname === '') {
                $protokoll[] = 'personalData.csv Zeile ' . ($nr + 1) . ': kein Name, übersprungen.';
                continue;
            }
            if (ctype_digit($idText) && (int) $idText > 0 && !isset($vergebeneIds[(int) $idText])) {
                $id = (int) $idText;
            } else {
                $id = ++$maxId;
                $protokoll[] = 'personalData.csv Zeile ' . ($nr + 1) . ': ID „' . $idText . '“ ungültig oder doppelt, '
                    . $vorname . ' ' . $nachname . ' hat jetzt die ID ' . $id . '.';
            }
            $vergebeneIds[$id] = true;
            $einfuegen->execute(array($id, $nachname, $vorname, $klasse, $telefon));
            $zeileZuId[$nr] = $id;
            // Für die Zuordnung zählt die ID aus der Datei (wie in v1), nicht eine neu vergebene.
            // Der Spitzname wird nicht gespeichert, nur zum Zuordnen alter Planeinträge verwendet.
            $personen[] = array('id_text' => $idText, 'id' => $id, 'spitzname' => $spitzname, 'name' => $vorname . ' ' . $nachname);
            $anzahl++;
        }
        $protokoll[] = $anzahl . ' Personen aus personalData.csv übernommen.';
    } else {
        $protokoll[] = 'personalData.csv nicht gefunden – keine Personen übernommen.';
    }

    // 2. Pläne der beiden Gebäude
    $schichtIds = $db->query('SELECT position, id FROM schicht ORDER BY position')->fetchAll(PDO::FETCH_KEY_PAIR);
    // Datenzeile (0-basiert, ohne Kopfzeile) => Schicht-Position, genau wie der v1-Editor gelesen hat.
    $zeileZuSchicht = array(0 => 1, 1 => 2, 2 => 3, 4 => 4, 5 => 5, 6 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 10);
    $einteilen = $db->prepare('INSERT OR IGNORE INTO einteilung (gebaeude, wochentag, schicht_id, person_id, position) VALUES (?, ?, ?, ?, ?)');
    foreach (array('hauptgebaeude', 'musikschule') as $gebaeude) {
        $pfad = SANIPLAN_ROOT . '/' . $dateien[$gebaeude];
        $dateiname = basename($pfad);
        if (!is_file($pfad)) {
            $protokoll[] = $dateiname . ' nicht gefunden – Plan bleibt leer.';
            continue;
        }
        $zeilen = import_text_zeilen($pfad);
        array_shift($zeilen); // Kopfzeile
        $anzahl = 0;
        foreach ($zeileZuSchicht as $zeilenNr => $position) {
            if (!isset($zeilen[$zeilenNr])) {
                continue;
            }
            $felder = explode(';', $zeilen[$zeilenNr]);
            for ($tag = 1; $tag <= 5; $tag++) {
                $eintraege = isset($felder[$tag]) ? explode(':', $felder[$tag]) : array();
                $pos = 0;
                foreach ($eintraege as $eintrag) {
                    $eintrag = trim($eintrag);
                    if ($eintrag === '') {
                        continue;
                    }
                    $personId = import_person_finden($personen, $eintrag);
                    if ($personId === 0) {
                        $protokoll[] = $dateiname . ', ' . import_zeilen_label($felder) . ', ' . wochentag_name($tag)
                            . ': „' . $eintrag . '“ keiner Person zuzuordnen, ausgelassen.';
                        continue;
                    }
                    $einteilen->execute(array($gebaeude, $tag, $schichtIds[$position], $personId, $pos++));
                    $anzahl++;
                }
            }
        }
        // Hinweis, falls die Pausenzeilen (von Hand bearbeitet) vom Editor-Stand abweichen.
        foreach (array(3 => 2, 7 => 6) as $pause => $vergleich) {
            if (isset($zeilen[$pause], $zeilen[$vergleich])) {
                $a = array_slice(explode(';', trim($zeilen[$pause])), 1, 5);
                $b = array_slice(explode(';', trim($zeilen[$vergleich])), 1, 5);
                if ($a !== $b) {
                    $protokoll[] = $dateiname . ': Die Zeile „' . import_zeilen_label(explode(';', $zeilen[$pause]))
                        . '“ wich von „' . import_zeilen_label(explode(';', $zeilen[$vergleich])) . '“ ab. Übernommen wurde wie im v1-Editor „'
                        . import_zeilen_label(explode(';', $zeilen[$vergleich])) . '“. Bitte im Editor prüfen.';
                }
            }
        }
        $protokoll[] = $anzahl . ' Einteilungen aus ' . $dateiname . ' übernommen.';
    }
    foreach (array('ungerade_hauptgebaeude', 'ungerade_musikschule') as $schluessel) {
        if (is_file(SANIPLAN_ROOT . '/' . $dateien[$schluessel])) {
            $protokoll[] = basename($dateien[$schluessel]) . ' wird nicht übernommen (A/B-Woche gibt es nicht mehr).';
        }
    }

    // 3. Einstellungen
    $pfad = SANIPLAN_ROOT . '/' . $dateien['config'];
    $config = is_file($pfad) ? json_decode(import_text($pfad), true) : null;
    if (is_array($config)) {
        if (isset($config['verantwortliche']) && is_array($config['verantwortliche'])) {
            $namen = array_values(array_filter(array_map('trim', $config['verantwortliche']), 'strlen'));
            einstellung_schreiben($db, 'verantwortliche', json_encode($namen, JSON_UNESCAPED_UNICODE));
        }
        if (isset($config['stand']) && preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', trim($config['stand']), $m)
            && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            einstellung_schreiben($db, 'stand', sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]));
        }
        if (isset($config['uhr'])) {
            // v1: 1 = Uhr anzeigen, 2 = nur im Debug-Modus
            einstellung_schreiben($db, 'uhr_anzeigen', ((string) $config['uhr'] === '1') ? '1' : '0');
        }
        if (isset($config['emergencyContacts']) && is_array($config['emergencyContacts'])) {
            // v1 hat den Wert als Position in der internen Personenliste benutzt (Kopfzeile = 0), nicht als ID.
            $notfall = $db->prepare('INSERT OR IGNORE INTO notfallkontakt (person_id, position) VALUES (?, ?)');
            $pos = 0;
            foreach ($config['emergencyContacts'] as $wert) {
                $wert = trim((string) $wert);
                if (!ctype_digit($wert)) {
                    continue;
                }
                $index = (int) $wert;
                if ($index > 0 && isset($v1Liste[$index]) && isset($zeileZuId[$v1Liste[$index]])) {
                    $notfall->execute(array($zeileZuId[$v1Liste[$index]], $pos++));
                } else {
                    $protokoll[] = 'Notfallkontakt „' . $wert . '“ aus config.json konnte keiner Person zugeordnet werden.';
                }
            }
            $protokoll[] = $pos . ' Notfallkontakte übernommen. Bitte unter Einstellungen prüfen, ob es die richtigen Personen sind.';
        }
        $protokoll[] = 'Einstellungen aus settings/config.json übernommen.';
    } elseif (is_file($pfad)) {
        $protokoll[] = 'settings/config.json ist nicht lesbar – Standardeinstellungen werden verwendet.';
    }

    einstellung_schreiben($db, 'import_v1', json_encode(array(
        'zeitpunkt' => date('c'),
        'status' => 'importiert',
        'protokoll' => $protokoll,
    ), JSON_UNESCAPED_UNICODE));
}

/** Ordnet einen Planeintrag (ID, Spitzname oder „Vorname Nachname“) wie v1 einer Person zu. */
function import_person_finden(array $personen, $wert)
{
    $vergleich = ctype_digit($wert) ? (string) (int) $wert : $wert;
    foreach ($personen as $p) {
        if ($p['id_text'] === $vergleich || ($p['spitzname'] !== '' && $p['spitzname'] === $wert) || $p['name'] === $wert) {
            return $p['id'];
        }
    }
    return 0;
}

function import_zeilen_label(array $felder)
{
    return trim($felder[0]);
}

function wochentag_name($tag)
{
    $namen = array(1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag');
    return isset($namen[$tag]) ? $namen[$tag] : '';
}

/** Liest eine Textdatei als UTF-8 (BOM entfernt, Windows-1252 wird umgewandelt). */
function import_text($pfad)
{
    $text = (string) @file_get_contents($pfad);
    return text_nach_utf8($text);
}

function text_nach_utf8($text)
{
    if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
        $text = substr($text, 3);
    }
    if (preg_match('//u', $text) !== 1) {
        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        } elseif (function_exists('iconv')) {
            $text = (string) iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        } else {
            $text = @utf8_encode($text);
        }
    }
    return $text;
}

function import_text_zeilen($pfad)
{
    $zeilen = preg_split('/\r\n|\r|\n/', import_text($pfad));
    while (count($zeilen) > 0 && trim(end($zeilen)) === '') {
        array_pop($zeilen);
    }
    return $zeilen;
}

/**
 * Räumt die v1-Dateien nach data/archiv-v1/. Klappt das Verschieben nicht, wird kopiert und die
 * Originaldatei auf den in Git eingecheckten Inhalt zurückgesetzt. Danach gilt sie für git als
 * unverändert und Release 2.0.1 kann sie per „git pull“ entfernen.
 */
function import_v1_archivieren(PDO $db)
{
    $archiv = SANIPLAN_DATA . '/archiv-v1';
    $ergebnis = array();
    $originale = v1_originale();
    foreach (import_v1_dateien() as $relativ) {
        $pfad = SANIPLAN_ROOT . '/' . $relativ;
        if (!is_file($pfad)) {
            continue;
        }
        if (!is_dir($archiv)) {
            @mkdir($archiv, 0775, true);
        }
        $ziel = $archiv . '/' . basename($relativ);
        if (file_exists($ziel)) {
            $ziel = $archiv . '/' . date('Ymd-His') . '-' . basename($relativ);
        }
        if (@rename($pfad, $ziel)) {
            $ergebnis[] = $relativ . ' → data/archiv-v1/' . basename($ziel);
            continue;
        }
        $kopiert = @copy($pfad, $ziel);
        if ($kopiert && @unlink($pfad)) {
            $ergebnis[] = $relativ . ' → data/archiv-v1/' . basename($ziel);
        } elseif ($kopiert && isset($originale[$relativ])
            && @file_put_contents($pfad, base64_decode($originale[$relativ])) !== false) {
            $ergebnis[] = $relativ . ': nach data/archiv-v1/ kopiert und auf den Git-Stand zurückgesetzt.';
        } else {
            $ergebnis[] = $relativ . ': konnte NICHT archiviert werden (fehlende Schreibrechte).';
        }
    }
    $import = json_decode(einstellung_lesen($db, 'import_v1', '{}'), true);
    $import['archivierung'] = $ergebnis;
    einstellung_schreiben($db, 'import_v1', json_encode($import, JSON_UNESCAPED_UNICODE));
}

/**
 * Liefert die v1-Dateien, die einen „git pull“ von Release 2.0.1 blockieren würden:
 * Sie existieren noch und unterscheiden sich vom eingecheckten Stand.
 */
function v1_blockierende_dateien()
{
    $originale = v1_originale();
    $blockierend = array();
    foreach ($originale as $relativ => $inhalt) {
        $pfad = SANIPLAN_ROOT . '/' . $relativ;
        if (is_file($pfad) && @file_get_contents($pfad) !== base64_decode($inhalt)) {
            $blockierend[] = $relativ;
        }
    }
    return $blockierend;
}
