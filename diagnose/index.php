<?php
require __DIR__ . '/../lib/bootstrap.php';

$pruefungen = array(); // [titel, ok (true/false/null = Hinweis), text]
$pruefungen[] = array('PHP-Version', version_compare(PHP_VERSION, '7.0.0', '>='), PHP_VERSION);
$pruefungen[] = array('SQLite (pdo_sqlite)', extension_loaded('pdo_sqlite'),
    extension_loaded('pdo_sqlite') ? 'vorhanden' : 'fehlt – Paket php-sqlite3 installieren');
$pruefungen[] = array('Schreibrechte data/', is_dir(SANIPLAN_DATA) && is_writable(SANIPLAN_DATA),
    is_writable(SANIPLAN_DATA) ? 'beschreibbar' : 'nicht beschreibbar für den Webserver');

$db = null;
$dbFehler = '';
try {
    $db = db();
} catch (Throwable $e) {
    $dbFehler = $e->getMessage();
}
$pruefungen[] = array('Datenbank', $db !== null, $db !== null
    ? 'Schema-Version ' . db_schema_version($db) . ', ' . round(filesize(SANIPLAN_DB) / 1024) . ' KB'
    : $dbFehler);

$import = null;
if ($db !== null) {
    $import = json_decode(einstellung_lesen($db, 'import_v1', 'null'), true);
    $anzahl = (int) $db->query('SELECT COUNT(*) FROM person')->fetchColumn();
    $einteilungen = (int) $db->query('SELECT COUNT(*) FROM einteilung')->fetchColumn();
    $pruefungen[] = array('Daten', null, $anzahl . ' Personen, ' . $einteilungen . ' Einteilungen');
}

$blockierend = v1_blockierende_dateien();
$pruefungen[] = array('Altdateien aus Version 1', count($blockierend) === 0, count($blockierend) === 0
    ? 'Import abgeschlossen, Altdateien archiviert – bereit für Release 2.0.1'
    : 'noch vorhanden: ' . implode(', ', $blockierend));

$pruefungen[] = array('Serverzeit', null, date('d.m.Y H:i:s') . ' (' . date_default_timezone_get() . ')');

seite_kopf('Diagnose – Saniplan', '../', '');
?>
<main class="inhalt schmal">
    <h1>Diagnose</h1>
    <table class="tabelle diagnose">
        <?php foreach ($pruefungen as $p) { ?>
            <tr>
                <th><?php echo h($p[0]); ?></th>
                <td class="<?php echo $p[1] === null ? '' : ($p[1] ? 'ok' : 'fehler'); ?>">
                    <?php echo $p[1] === null ? '' : ($p[1] ? '✔ ' : '✖ '); ?><?php echo h($p[2]); ?>
                </td>
            </tr>
        <?php } ?>
        <tr>
            <th>Browserzeit</th>
            <td id="browserzeit" data-serverzeit="<?php echo round(microtime(true) * 1000); ?>">–</td>
        </tr>
        <tr>
            <th>App-Version</th>
            <td><?php echo h(SANIPLAN_VERSION); ?></td>
        </tr>
    </table>

    <h2>Umstieg von Version 1</h2>
    <?php if (!is_array($import)) { ?>
        <p>Kein Import-Protokoll vorhanden.</p>
    <?php } else { ?>
        <p>Durchgeführt am <?php echo h(date('d.m.Y H:i', strtotime($import['zeitpunkt']))); ?>.</p>
        <ul>
            <?php foreach ($import['protokoll'] as $zeile) { ?>
                <li><?php echo h($zeile); ?></li>
            <?php } ?>
        </ul>
        <?php if (!empty($import['archivierung'])) { ?>
            <h3>Archivierung der Altdateien</h3>
            <ul>
                <?php foreach ($import['archivierung'] as $zeile) { ?>
                    <li><?php echo h($zeile); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    <?php } ?>
</main>
<script>
    (function () {
        var feld = document.getElementById('browserzeit');
        var abweichung = Math.round((Date.now() - Number(feld.getAttribute('data-serverzeit'))) / 1000);
        feld.textContent = new Date().toLocaleString('de-DE') + ' (Abweichung zum Server ca. ' + abweichung + ' s)';
        feld.className = Math.abs(abweichung) > 300 ? 'fehler' : 'ok';
    })();
</script>
<?php
seite_fuss('../', '');
