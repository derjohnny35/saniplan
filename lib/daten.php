<?php
if (!defined('SANIPLAN')) { http_response_code(403); exit; }

/** Fehler, deren Meldung direkt der Benutzerin gezeigt werden kann (falsche Eingaben usw.). */
class EingabeFehler extends RuntimeException
{
}

function schichten_laden(PDO $db)
{
    $zeilen = $db->query('SELECT id, position, bezeichnung, beginn, ende FROM schicht ORDER BY position, beginn')->fetchAll();
    foreach ($zeilen as &$z) {
        $z['id'] = (int) $z['id'];
        $z['position'] = (int) $z['position'];
    }
    return $zeilen;
}

/** Alle Personen, nach Nachname/Vorname sortiert. */
function personen_laden(PDO $db)
{
    $zeilen = $db->query('SELECT id, nachname, vorname, klasse, telefon FROM person
        ORDER BY nachname COLLATE NOCASE, vorname COLLATE NOCASE')->fetchAll();
    foreach ($zeilen as &$z) {
        $z['id'] = (int) $z['id'];
    }
    return $zeilen;
}

function person_name(array $p)
{
    return trim($p['vorname'] . ' ' . $p['nachname']);
}

/**
 * Plan eines Gebäudes als Liste von Zellen: [{wochentag, schicht_id, person_ids: [...]}, ...].
 * Leere Zellen fehlen.
 */
function plan_laden(PDO $db, $gebaeude)
{
    $st = $db->prepare('SELECT wochentag, schicht_id, person_id FROM einteilung WHERE gebaeude = ?
        ORDER BY wochentag, schicht_id, position, person_id');
    $st->execute(array($gebaeude));
    $zellen = array();
    foreach ($st->fetchAll() as $z) {
        $schluessel = $z['wochentag'] . '-' . $z['schicht_id'];
        if (!isset($zellen[$schluessel])) {
            $zellen[$schluessel] = array(
                'wochentag' => (int) $z['wochentag'],
                'schicht_id' => (int) $z['schicht_id'],
                'person_ids' => array(),
            );
        }
        $zellen[$schluessel]['person_ids'][] = (int) $z['person_id'];
    }
    return array_values($zellen);
}

function notfallkontakte_laden(PDO $db)
{
    $ids = $db->query('SELECT person_id FROM notfallkontakt ORDER BY position')->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $ids);
}

function einstellungen_laden(PDO $db)
{
    $verantwortliche = json_decode(einstellung_lesen($db, 'verantwortliche', '[]'), true);
    return array(
        'verantwortliche' => is_array($verantwortliche) ? $verantwortliche : array(),
        'stand' => einstellung_lesen($db, 'stand', ''),
        'uhr_anzeigen' => einstellung_lesen($db, 'uhr_anzeigen', '1') === '1',
        'notfallkontakte' => notfallkontakte_laden($db),
    );
}

/**
 * Ersetzt den Plan eines Gebäudes. Muss innerhalb einer Transaktion aufgerufen werden.
 * $zellen: Liste von {wochentag, schicht_id, person_ids}
 */
function plan_speichern(PDO $db, $gebaeude, $zellen)
{
    $gebaeudeListe = gebaeude_liste();
    if (!isset($gebaeudeListe[$gebaeude])) {
        throw new EingabeFehler('Unbekanntes Gebäude.');
    }
    if (!is_array($zellen)) {
        throw new EingabeFehler('Ungültige Plandaten.');
    }
    $schichtIds = array_flip($db->query('SELECT id FROM schicht')->fetchAll(PDO::FETCH_COLUMN));
    $personIds = array_flip($db->query('SELECT id FROM person')->fetchAll(PDO::FETCH_COLUMN));

    $db->prepare('DELETE FROM einteilung WHERE gebaeude = ?')->execute(array($gebaeude));
    $einfuegen = $db->prepare('INSERT OR IGNORE INTO einteilung (gebaeude, wochentag, schicht_id, person_id, position)
        VALUES (?, ?, ?, ?, ?)');
    $sauber = array();
    foreach ($zellen as $zelle) {
        $tag = isset($zelle['wochentag']) ? (int) $zelle['wochentag'] : 0;
        $schicht = isset($zelle['schicht_id']) ? (int) $zelle['schicht_id'] : 0;
        if ($tag < 1 || $tag > 5 || !isset($schichtIds[$schicht])) {
            throw new EingabeFehler('Der Plan enthält eine ungültige Zelle. Bitte die Seite neu laden.');
        }
        $ids = array();
        $pos = 0;
        foreach ((isset($zelle['person_ids']) && is_array($zelle['person_ids']) ? $zelle['person_ids'] : array()) as $pid) {
            $pid = (int) $pid;
            if (!isset($personIds[$pid])) {
                throw new EingabeFehler('Der Plan enthält eine Person, die es nicht mehr gibt. Bitte die Seite neu laden.');
            }
            if (in_array($pid, $ids, true)) {
                continue;
            }
            $einfuegen->execute(array($gebaeude, $tag, $schicht, $pid, $pos++));
            $ids[] = $pid;
        }
        if (count($ids) > 0) {
            $sauber[] = array('wochentag' => $tag, 'schicht_id' => $schicht, 'person_ids' => $ids);
        }
    }

    // Snapshot für „Frühere Version wiederherstellen“; die letzten 50 je Gebäude bleiben erhalten.
    $db->prepare('INSERT INTO plan_historie (zeitpunkt, gebaeude, daten) VALUES (?, ?, ?)')
        ->execute(array(date('c'), $gebaeude, json_encode($sauber)));
    $db->prepare('DELETE FROM plan_historie WHERE gebaeude = ? AND id NOT IN
        (SELECT id FROM plan_historie WHERE gebaeude = ? ORDER BY id DESC LIMIT 50)')
        ->execute(array($gebaeude, $gebaeude));
}

/** Anzahl der Einteilungen je Person (beide Gebäude). */
function einsaetze_je_person(PDO $db)
{
    return array_map('intval', $db->query('SELECT person_id, COUNT(*) FROM einteilung GROUP BY person_id')
        ->fetchAll(PDO::FETCH_KEY_PAIR));
}

/**
 * Prüft und normalisiert eine Person aus einer Eingabe.
 * @return array [nachname, vorname, klasse, telefon]
 */
function person_pruefen($eingabe)
{
    $felder = array('nachname', 'vorname', 'klasse', 'telefon');
    $p = array();
    foreach ($felder as $f) {
        $wert = isset($eingabe[$f]) ? trim((string) $eingabe[$f]) : '';
        if (strlen($wert) > 100) {
            throw new EingabeFehler('Die Eingabe im Feld „' . $f . '“ ist zu lang.');
        }
        if (strpos($wert, ';') !== false || strpos($wert, ':') !== false) {
            throw new EingabeFehler('Die Zeichen ; und : sind in Personendaten nicht erlaubt.');
        }
        $p[$f] = $wert;
    }
    if ($p['nachname'] === '' && $p['vorname'] === '') {
        throw new EingabeFehler('Bitte mindestens einen Vor- oder Nachnamen angeben.');
    }
    return $p;
}

/**
 * Liest Personen aus CSV-Text im v1-Format (Id;Name;Vorname;Klasse;Telefonnummer). Weitere Spalten (z. B. der frühere Nickname) werden ignoriert.
 * Die erste Zeile wird als Kopfzeile übersprungen, wenn ihre erste Spalte keine Zahl ist.
 * @return array ['personen' => [...], 'fehler' => [...]]
 */
function personen_csv_parsen($text)
{
    $zeilen = preg_split('/\r\n|\r|\n/', text_nach_utf8((string) $text));
    $personen = array();
    $fehler = array();
    $ids = array();
    foreach ($zeilen as $nr => $zeile) {
        if (trim($zeile) === '') {
            continue;
        }
        $felder = array_map('trim', explode(';', $zeile));
        if ($nr === 0 && !ctype_digit($felder[0])) {
            continue;
        }
        if (count($felder) < 5) {
            $fehler[] = 'Zeile ' . ($nr + 1) . ': zu wenige Felder.';
            continue;
        }
        if (!ctype_digit($felder[0]) || (int) $felder[0] < 1) {
            $fehler[] = 'Zeile ' . ($nr + 1) . ': Die ID muss eine positive Zahl sein.';
            continue;
        }
        $id = (int) $felder[0];
        if (isset($ids[$id])) {
            $fehler[] = 'Zeile ' . ($nr + 1) . ': Die ID ' . $id . ' kommt mehrfach vor.';
            continue;
        }
        try {
            $p = person_pruefen(array(
                'nachname' => $felder[1],
                'vorname' => $felder[2],
                'klasse' => $felder[3],
                'telefon' => $felder[4],
            ));
        } catch (EingabeFehler $e) {
            $fehler[] = 'Zeile ' . ($nr + 1) . ': ' . $e->getMessage();
            continue;
        }
        $ids[$id] = true;
        $p['id'] = $id;
        $personen[] = $p;
    }
    return array('personen' => $personen, 'fehler' => $fehler);
}

function uhrzeit_gueltig($wert)
{
    return is_string($wert) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $wert) === 1;
}
