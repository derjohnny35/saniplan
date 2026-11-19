<?php
/*
 * JSON-API. Nur GET (lesen) und POST (schreiben), damit es mit jeder Apache-Konfiguration läuft.
 * Aufruf: api/?r=<route>
 */
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function antwort($daten, $status = 200)
{
    http_response_code($status);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function eingabe()
{
    $daten = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($daten)) {
        throw new EingabeFehler('Ungültige Anfrage.');
    }
    return $daten;
}

function nur_post()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new EingabeFehler('Diese Aktion erfordert POST.');
    }
}

/** Führt $funktion in einer Transaktion aus und erhöht die Datenversion. */
function schreiben(PDO $db, $funktion)
{
    $db->beginTransaction();
    try {
        $ergebnis = call_user_func($funktion);
        daten_geaendert($db);
        $db->commit();
        return $ergebnis;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function serverzeit()
{
    return array('serverzeit' => round(microtime(true) * 1000));
}

$route = isset($_GET['r']) ? (string) $_GET['r'] : '';

try {
    if ($route === 'ping') {
        antwort(array('ok' => true, 'appversion' => SANIPLAN_VERSION) + serverzeit());
    }

    $db = db();
    $datenversion = (int) einstellung_lesen($db, 'datenversion', '0');

    switch ($route) {
        case 'anzeige':
            // Kurze Antwort, wenn sich seit dem letzten Abruf nichts geändert hat.
            if (isset($_GET['v']) && (int) $_GET['v'] === $datenversion) {
                antwort(array('unveraendert' => true, 'appversion' => SANIPLAN_VERSION, 'datenversion' => $datenversion) + serverzeit());
            }
            $einstellungen = einstellungen_laden($db);
            $plaene = array();
            $benoetigt = array_flip($einstellungen['notfallkontakte']);
            foreach (array_keys(gebaeude_liste()) as $g) {
                $plaene[$g] = plan_laden($db, $g);
                foreach ($plaene[$g] as $zelle) {
                    foreach ($zelle['person_ids'] as $pid) {
                        $benoetigt[$pid] = true;
                    }
                }
            }
            $personen = array();
            foreach (personen_laden($db) as $p) {
                if (isset($benoetigt[$p['id']])) {
                    $personen[$p['id']] = $p;
                }
            }
            antwort(array(
                'appversion' => SANIPLAN_VERSION,
                'datenversion' => $datenversion,
                'gebaeude' => gebaeude_liste(),
                'schichten' => schichten_laden($db),
                'plaene' => $plaene,
                'personen' => (object) $personen,
                'notfallkontakte' => $einstellungen['notfallkontakte'],
                'verantwortliche' => $einstellungen['verantwortliche'],
                'stand' => $einstellungen['stand'],
                'uhr_anzeigen' => $einstellungen['uhr_anzeigen'],
            ) + serverzeit());
            break;

        case 'editor':
            $plaene = array();
            foreach (array_keys(gebaeude_liste()) as $g) {
                $plaene[$g] = plan_laden($db, $g);
            }
            antwort(array(
                'datenversion' => $datenversion,
                'gebaeude' => gebaeude_liste(),
                'schichten' => schichten_laden($db),
                'personen' => personen_laden($db),
                'plaene' => $plaene,
            ));
            break;

        case 'plan-speichern':
            nur_post();
            $e = eingabe();
            if (empty($e['erzwingen']) && isset($e['basis']) && (int) $e['basis'] !== $datenversion) {
                antwort(array('fehler' => 'Plan, Personen oder Schichten wurden inzwischen an anderer Stelle geändert.', 'konflikt' => true), 409);
            }
            if (!isset($e['plaene']) || !is_array($e['plaene']) || count($e['plaene']) === 0) {
                throw new EingabeFehler('Es wurden keine Pläne übermittelt.');
            }
            schreiben($db, function () use ($db, $e) {
                foreach ($e['plaene'] as $gebaeude => $zellen) {
                    plan_speichern($db, (string) $gebaeude, $zellen);
                }
                einstellung_schreiben($db, 'stand', date('Y-m-d'));
            });
            antwort(array('ok' => true, 'datenversion' => $datenversion + 1, 'stand' => date('Y-m-d')));
            break;

        case 'historie':
            $gebaeude = isset($_GET['gebaeude']) ? (string) $_GET['gebaeude'] : '';
            if (isset($_GET['id'])) {
                $st = $db->prepare('SELECT id, zeitpunkt, gebaeude, daten FROM plan_historie WHERE id = ?');
                $st->execute(array((int) $_GET['id']));
                $eintrag = $st->fetch();
                if (!$eintrag) {
                    throw new EingabeFehler('Diese Version gibt es nicht mehr.');
                }
                $eintrag['daten'] = json_decode($eintrag['daten'], true);
                antwort($eintrag);
            }
            $st = $db->prepare('SELECT id, zeitpunkt, daten FROM plan_historie WHERE gebaeude = ? ORDER BY id DESC');
            $st->execute(array($gebaeude));
            $liste = array();
            foreach ($st->fetchAll() as $z) {
                $anzahl = 0;
                foreach ((array) json_decode($z['daten'], true) as $zelle) {
                    $anzahl += count($zelle['person_ids']);
                }
                $liste[] = array('id' => (int) $z['id'], 'zeitpunkt' => $z['zeitpunkt'], 'einteilungen' => $anzahl);
            }
            antwort($liste);
            break;

        case 'personen':
            $einsaetze = einsaetze_je_person($db);
            $notfall = array_flip(notfallkontakte_laden($db));
            $liste = array();
            foreach (personen_laden($db) as $p) {
                $p['einsaetze'] = isset($einsaetze[$p['id']]) ? $einsaetze[$p['id']] : 0;
                $p['notfallkontakt'] = isset($notfall[$p['id']]);
                $liste[] = $p;
            }
            antwort(array('datenversion' => $datenversion, 'personen' => $liste));
            break;

        case 'person-speichern':
            nur_post();
            $e = eingabe();
            $p = person_pruefen($e);
            $id = isset($e['id']) ? (int) $e['id'] : 0;
            $neueId = schreiben($db, function () use ($db, $p, $id) {
                if ($id > 0) {
                    $st = $db->prepare('UPDATE person SET nachname = ?, vorname = ?, klasse = ?, telefon = ? WHERE id = ?');
                    $st->execute(array($p['nachname'], $p['vorname'], $p['klasse'], $p['telefon'], $id));
                    if ($st->rowCount() === 0) {
                        throw new EingabeFehler('Diese Person gibt es nicht mehr.');
                    }
                    return $id;
                }
                $st = $db->prepare('INSERT INTO person (nachname, vorname, klasse, telefon) VALUES (?, ?, ?, ?)');
                $st->execute(array($p['nachname'], $p['vorname'], $p['klasse'], $p['telefon']));
                return (int) $db->lastInsertId();
            });
            antwort(array('ok' => true, 'id' => $neueId));
            break;

        case 'person-loeschen':
            nur_post();
            $e = eingabe();
            $id = isset($e['id']) ? (int) $e['id'] : 0;
            schreiben($db, function () use ($db, $id) {
                $db->prepare('DELETE FROM person WHERE id = ?')->execute(array($id));
            });
            antwort(array('ok' => true));
            break;

        case 'personen-alle-loeschen':
            nur_post();
            $e = eingabe();
            if (!isset($e['bestaetigung']) || $e['bestaetigung'] !== 'ALLE LÖSCHEN') {
                throw new EingabeFehler('Bestätigung fehlt.');
            }
            schreiben($db, function () use ($db) {
                $db->exec('DELETE FROM person');
            });
            antwort(array('ok' => true));
            break;

        case 'personen-import':
            nur_post();
            $e = eingabe();
            $ergebnis = personen_csv_parsen(isset($e['csv']) ? $e['csv'] : '');
            $vorhanden = array();
            foreach (personen_laden($db) as $p) {
                $vorhanden[$p['id']] = $p;
            }
            $felder = array('nachname', 'vorname', 'klasse', 'telefon');
            $vorschau = array();
            foreach ($ergebnis['personen'] as $p) {
                $status = 'neu';
                if (isset($vorhanden[$p['id']])) {
                    $status = 'unveraendert';
                    foreach ($felder as $f) {
                        if ($vorhanden[$p['id']][$f] !== $p[$f]) {
                            $status = 'geaendert';
                        }
                    }
                }
                $p['status'] = $status;
                $p['vorher'] = isset($vorhanden[$p['id']]) ? $vorhanden[$p['id']] : null;
                $vorschau[] = $p;
            }
            if (!empty($e['uebernehmen'])) {
                if (count($ergebnis['fehler']) > 0) {
                    throw new EingabeFehler('Die Datei enthält Fehler. Bitte zuerst korrigieren.');
                }
                schreiben($db, function () use ($db, $vorschau) {
                    $neu = $db->prepare('INSERT INTO person (id, nachname, vorname, klasse, telefon) VALUES (?, ?, ?, ?, ?)');
                    $aendern = $db->prepare('UPDATE person SET nachname = ?, vorname = ?, klasse = ?, telefon = ? WHERE id = ?');
                    foreach ($vorschau as $p) {
                        if ($p['status'] === 'neu') {
                            $neu->execute(array($p['id'], $p['nachname'], $p['vorname'], $p['klasse'], $p['telefon']));
                        } elseif ($p['status'] === 'geaendert') {
                            $aendern->execute(array($p['nachname'], $p['vorname'], $p['klasse'], $p['telefon'], $p['id']));
                        }
                    }
                });
            }
            antwort(array('personen' => $vorschau, 'fehler' => $ergebnis['fehler'], 'uebernommen' => !empty($e['uebernehmen'])));
            break;

        case 'einstellungen':
            $einstellungen = einstellungen_laden($db);
            $einstellungen['personen'] = personen_laden($db);
            $einstellungen['schichten'] = schichten_laden($db);
            $einstellungen['einsaetze_je_schicht'] = array_map('intval', $db->query(
                'SELECT schicht_id, COUNT(*) FROM einteilung GROUP BY schicht_id')->fetchAll(PDO::FETCH_KEY_PAIR));
            antwort($einstellungen);
            break;

        case 'einstellungen-speichern':
            nur_post();
            $e = eingabe();
            $verantwortliche = array();
            foreach ((isset($e['verantwortliche']) && is_array($e['verantwortliche']) ? $e['verantwortliche'] : array()) as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $verantwortliche[] = $name;
                }
            }
            $stand = isset($e['stand']) ? (string) $e['stand'] : '';
            if ($stand !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $stand, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
                throw new EingabeFehler('Das Datum für den Stand ist ungültig.');
            }
            $notfall = array();
            foreach ((isset($e['notfallkontakte']) && is_array($e['notfallkontakte']) ? $e['notfallkontakte'] : array()) as $id) {
                $notfall[] = (int) $id;
            }
            schreiben($db, function () use ($db, $verantwortliche, $stand, $notfall, $e) {
                einstellung_schreiben($db, 'verantwortliche', json_encode($verantwortliche, JSON_UNESCAPED_UNICODE));
                einstellung_schreiben($db, 'stand', $stand);
                einstellung_schreiben($db, 'uhr_anzeigen', !empty($e['uhr_anzeigen']) ? '1' : '0');
                $db->exec('DELETE FROM notfallkontakt');
                $st = $db->prepare('INSERT OR IGNORE INTO notfallkontakt (person_id, position)
                    SELECT id, ? FROM person WHERE id = ?');
                foreach ($notfall as $pos => $id) {
                    $st->execute(array($pos, $id));
                }
            });
            antwort(array('ok' => true));
            break;

        case 'schichten-speichern':
            nur_post();
            $e = eingabe();
            $liste = isset($e['schichten']) && is_array($e['schichten']) ? $e['schichten'] : array();
            if (count($liste) === 0) {
                throw new EingabeFehler('Es muss mindestens eine Schicht geben.');
            }
            $sauber = array();
            foreach ($liste as $s) {
                $bezeichnung = isset($s['bezeichnung']) ? trim((string) $s['bezeichnung']) : '';
                $beginn = isset($s['beginn']) ? (string) $s['beginn'] : '';
                $ende = isset($s['ende']) ? (string) $s['ende'] : '';
                if ($bezeichnung === '') {
                    throw new EingabeFehler('Jede Schicht braucht eine Bezeichnung.');
                }
                if (!uhrzeit_gueltig($beginn) || !uhrzeit_gueltig($ende) || $beginn >= $ende) {
                    throw new EingabeFehler('„' . $bezeichnung . '“: Beginn und Ende sind ungültig (Format HH:MM, Beginn vor Ende).');
                }
                $sauber[] = array('id' => isset($s['id']) ? (int) $s['id'] : 0, 'bezeichnung' => $bezeichnung, 'beginn' => $beginn, 'ende' => $ende);
            }
            usort($sauber, function ($a, $b) {
                return strcmp($a['beginn'], $b['beginn']);
            });
            for ($i = 1; $i < count($sauber); $i++) {
                if ($sauber[$i]['beginn'] < $sauber[$i - 1]['ende']) {
                    throw new EingabeFehler('„' . $sauber[$i - 1]['bezeichnung'] . '“ und „' . $sauber[$i]['bezeichnung'] . '“ überschneiden sich.');
                }
            }
            schreiben($db, function () use ($db, $sauber) {
                $behalten = array();
                $aendern = $db->prepare('UPDATE schicht SET position = ?, bezeichnung = ?, beginn = ?, ende = ? WHERE id = ?');
                $neu = $db->prepare('INSERT INTO schicht (position, bezeichnung, beginn, ende) VALUES (?, ?, ?, ?)');
                foreach ($sauber as $pos => $s) {
                    $aendern->execute(array($pos + 1, $s['bezeichnung'], $s['beginn'], $s['ende'], $s['id']));
                    if ($s['id'] > 0 && $aendern->rowCount() > 0) {
                        $behalten[] = $s['id'];
                    } else {
                        $neu->execute(array($pos + 1, $s['bezeichnung'], $s['beginn'], $s['ende']));
                        $behalten[] = (int) $db->lastInsertId();
                    }
                }
                $db->exec('DELETE FROM schicht WHERE id NOT IN (' . implode(',', array_map('intval', $behalten)) . ')');
            });
            antwort(array('ok' => true));
            break;

        default:
            antwort(array('fehler' => 'Unbekannte Anfrage.'), 404);
    }
} catch (EingabeFehler $e) {
    antwort(array('fehler' => $e->getMessage()), 400);
} catch (Throwable $e) {
    error_log('Saniplan: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    antwort(array('fehler' => 'Interner Fehler: ' . $e->getMessage()), 500);
}
