<?php
/*
 * Gemeinsamer Einstieg für alle Seiten und die API.
 * Kompatibel mit PHP 7.0: keine nullable Typen, keine Pfeilfunktionen, kein match, keine typisierten Eigenschaften.
 */
if (!defined('SANIPLAN')) {
    define('SANIPLAN', true);
}
define('SANIPLAN_VERSION', '2.0.0');
define('SANIPLAN_ROOT', dirname(__DIR__));
define('SANIPLAN_DATA', SANIPLAN_ROOT . '/data');
define('SANIPLAN_DB', SANIPLAN_DATA . '/saniplan.sqlite');

date_default_timezone_set('Europe/Berlin');
ini_set('display_errors', '0');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/migrationen.php';
require_once __DIR__ . '/import_v1.php';
require_once __DIR__ . '/v1_originale.php';
require_once __DIR__ . '/daten.php';
require_once __DIR__ . '/layout.php';

/** Die beiden Gebäude stehen fest. */
function gebaeude_liste()
{
    return array(
        'hauptgebaeude' => 'Hauptgebäude',
        'musikschule' => 'Musikschule',
    );
}

function h($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
