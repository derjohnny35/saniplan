<?php
if (!defined('SANIPLAN')) { http_response_code(403); exit; }

/**
 * Liefert die Datenbankverbindung. Beim ersten Aufruf wird die Datenbank bei Bedarf
 * angelegt, migriert und (bei einer Neuinstallation) mit den Daten aus Version 1 befüllt.
 */
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('Die PHP-Erweiterung pdo_sqlite fehlt (Paket php-sqlite3).');
    }
    if (!is_dir(SANIPLAN_DATA) && !@mkdir(SANIPLAN_DATA, 0775, true)) {
        throw new RuntimeException('Das Verzeichnis data/ fehlt und kann nicht angelegt werden.');
    }
    if (!is_writable(SANIPLAN_DATA)) {
        throw new RuntimeException('Der Webserver darf nicht in das Verzeichnis data/ schreiben.');
    }

    $verbindung = new PDO('sqlite:' . SANIPLAN_DB);
    $verbindung->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $verbindung->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $verbindung->setAttribute(PDO::ATTR_TIMEOUT, 10);
    $verbindung->exec('PRAGMA busy_timeout = 10000');
    $verbindung->exec('PRAGMA foreign_keys = ON');

    db_migrieren($verbindung);

    $pdo = $verbindung;
    return $pdo;
}

function db_schema_version(PDO $db)
{
    return (int) $db->query('PRAGMA user_version')->fetchColumn();
}

/**
 * Bringt das Schema auf den neuesten Stand. Läuft unter einer Dateisperre, damit gleichzeitige
 * erste Aufrufe (z. B. mehrere offene Tabs nach einem git pull) nicht doppelt migrieren.
 */
function db_migrieren(PDO $db)
{
    $migrationen = saniplan_migrationen();
    $ziel = count($migrationen);
    if (db_schema_version($db) >= $ziel) {
        return;
    }

    $sperre = fopen(SANIPLAN_DATA . '/.lock', 'c');
    if ($sperre === false) {
        throw new RuntimeException('Die Sperrdatei data/.lock kann nicht angelegt werden.');
    }
    flock($sperre, LOCK_EX);
    $importErfolgt = false;
    try {
        $version = db_schema_version($db);
        $neuinstallation = ($version === 0);
        for ($v = $version + 1; $v <= $ziel; $v++) {
            $db->beginTransaction();
            try {
                call_user_func($migrationen[$v], $db);
                if ($v === 1 && $neuinstallation) {
                    import_v1_ausfuehren($db);
                    $importErfolgt = true;
                }
                $db->exec('PRAGMA user_version = ' . (int) $v);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        }
        // Erst nach erfolgreichem Commit die Altdateien beiseite räumen.
        if ($importErfolgt) {
            import_v1_archivieren($db);
        }
    } finally {
        flock($sperre, LOCK_UN);
        fclose($sperre);
    }
}

function einstellung_lesen(PDO $db, $schluessel, $standard = null)
{
    $st = $db->prepare('SELECT wert FROM einstellung WHERE schluessel = ?');
    $st->execute(array($schluessel));
    $wert = $st->fetchColumn();
    return $wert === false ? $standard : $wert;
}

function einstellung_schreiben(PDO $db, $schluessel, $wert)
{
    $st = $db->prepare('INSERT OR REPLACE INTO einstellung (schluessel, wert) VALUES (?, ?)');
    $st->execute(array($schluessel, (string) $wert));
}

/** Erhöht die Datenversion, damit offene Anzeigen die Änderung beim nächsten Abruf übernehmen. */
function daten_geaendert(PDO $db)
{
    $version = (int) einstellung_lesen($db, 'datenversion', '0');
    einstellung_schreiben($db, 'datenversion', $version + 1);
}
