<?php
/*
 * Druckansicht des Bereitschaftsplans (A4 quer). Wird serverseitig erzeugt, damit sie auch
 * ohne JavaScript funktioniert. ?gebaeude=hauptgebaeude|musikschule|beide, ?drucken=1 öffnet den Druckdialog.
 */
require __DIR__ . '/../lib/bootstrap.php';

$fehler = '';
try {
    $db = db();
    $schichten = schichten_laden($db);
    $personen = array();
    foreach (personen_laden($db) as $p) {
        $personen[$p['id']] = $p;
    }
    $einstellungen = einstellungen_laden($db);
} catch (Throwable $e) {
    $fehler = $e->getMessage();
}

$alle = gebaeude_liste();
$wahl = isset($_GET['gebaeude']) ? (string) $_GET['gebaeude'] : 'beide';
$auswahl = isset($alle[$wahl]) ? array($wahl => $alle[$wahl]) : $alle;
$wochentage = array(1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Bereitschaftsplan_<?php echo h(date('d_m_Y')); ?></title>
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="../assets/css/druck.css?v=<?php echo rawurlencode(SANIPLAN_VERSION); ?>">
</head>

<body>
    <?php if ($fehler !== '') { ?>
        <p>Der Plan konnte nicht geladen werden: <?php echo h($fehler); ?></p>
    <?php } else {
        foreach ($auswahl as $gebaeude => $name) {
            $zellen = array();
            foreach (plan_laden($db, $gebaeude) as $z) {
                $zellen[$z['wochentag'] . '-' . $z['schicht_id']] = $z['person_ids'];
            }
            ?>
            <section class="seite">
                <header>
                    <h1>Bereitschaftsplan Schulsanitätsdienst – <?php echo h($name); ?></h1>
                    <div>Stand: <?php echo h(datum_deutsch($einstellungen['stand'])); ?></div>
                </header>
                <table class="plan">
                    <thead>
                        <tr>
                            <th class="schicht"></th>
                            <?php foreach ($wochentage as $tag) { ?><th><?php echo h($tag); ?></th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schichten as $s) { ?>
                            <tr>
                                <th class="schicht"><?php echo h($s['bezeichnung']); ?>
                                    <small><?php echo h($s['beginn'] . '–' . $s['ende']); ?></small></th>
                                <?php for ($tag = 1; $tag <= 5; $tag++) {
                                    $ids = isset($zellen[$tag . '-' . $s['id']]) ? $zellen[$tag . '-' . $s['id']] : array(); ?>
                                    <td>
                                        <?php foreach ($ids as $id) {
                                            if (!isset($personen[$id])) {
                                                continue;
                                            }
                                            $p = $personen[$id]; ?>
                                            <div class="person"><?php echo h(person_name($p)); ?>, <?php echo h($p['klasse']); ?>
                                                <span class="tel"><?php echo h($p['telefon']); ?></span></div>
                                        <?php } ?>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php if (count($einstellungen['notfallkontakte']) > 0) { ?>
                    <div class="notfall">
                        <strong>Notfallkontakte (für schwere Notfälle bzw. falls niemand erreichbar ist):</strong>
                        <?php
                        $teile = array();
                        foreach ($einstellungen['notfallkontakte'] as $id) {
                            if (isset($personen[$id])) {
                                $p = $personen[$id];
                                $teile[] = h(person_name($p) . ' (' . $p['klasse'] . ') ' . $p['telefon']);
                            }
                        }
                        echo implode(' · ', $teile);
                        ?>
                    </div>
                <?php } ?>
            </section>
        <?php }
    } ?>
    <?php if (isset($_GET['drucken'])) { ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php } ?>
</body>

</html>
