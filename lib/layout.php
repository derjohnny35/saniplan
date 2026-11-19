<?php
if (!defined('SANIPLAN')) { http_response_code(403); exit; }

/**
 * Gibt den Seitenkopf aus.
 * @param string $titel  Seitentitel
 * @param string $basis  relativer Pfad zum Hauptverzeichnis ('' oder '../')
 * @param string $aktiv  Kennung des aktiven Navigationspunkts
 * @param string $klasse zusätzliche CSS-Klasse für <body>
 */
function seite_kopf($titel, $basis, $aktiv, $klasse = '')
{
    $v = rawurlencode(SANIPLAN_VERSION);
    $navigation = array(
        'anzeige' => array('', 'Anzeige'),
        'editor' => array('editor/', 'Bereitschaftsplan-Editor'),
        'personen' => array('personen/', 'Personen'),
        'einstellungen' => array('settings/', 'Einstellungen'),
    );
    ?><!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($titel); ?></title>
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="<?php echo h($basis); ?>assets/css/saniplan.css?v=<?php echo $v; ?>">
</head>

<body class="<?php echo h($klasse); ?>" data-version="<?php echo h(SANIPLAN_VERSION); ?>">
    <header class="kopf">
        <nav class="navigation">
            <?php foreach ($navigation as $kennung => $eintrag) { ?>
                <a href="<?php echo h($basis . ($eintrag[0] === '' ? './' : $eintrag[0])); ?>"
                    class="<?php echo $kennung === $aktiv ? 'aktiv' : ''; ?>"><?php echo h($eintrag[1]); ?></a>
            <?php } ?>
        </nav>
    </header>
<?php
}

/**
 * Gibt den Seitenfuß aus und lädt optional ein JS-Modul.
 * @param string $basis  relativer Pfad zum Hauptverzeichnis
 * @param string $modul  Name des Moduls unter assets/js/ (ohne .js) oder ''
 * @param string $links  zusätzlicher HTML-Inhalt für die linke Fußzeile
 */
function seite_fuss($basis, $modul, $links = '')
{
    $stand = '';
    try {
        $stand = einstellung_lesen(db(), 'stand', '');
    } catch (Throwable $e) {
        // Der Fuß soll auch ohne Datenbank erscheinen.
    }
    ?>
    <footer class="fuss">
        <div class="links"><?php echo $links; ?>Stand des Bereitschaftsplans:
            <span id="stand"><?php echo h(datum_deutsch($stand)); ?></span></div>
        <div>Saniplan Version <?php echo h(SANIPLAN_VERSION); ?> · <a href="<?php echo h($basis); ?>diagnose/">Diagnose</a></div>
        <div class="rechts">©Jonathan Hostadt 2026</div>
        <script>console.log("Fakt: Der Informatik-LK 2022-24 war mit Abstand der coolste");</script>
    </footer>
    <?php if ($modul !== '') {
        // Import-Map mit Versionsparameter: Nach einem Update lädt der Browser garantiert die neuen Module.
        // Relative Adressen in Import-Maps müssen mit ./ oder ../ beginnen.
        $v = '?v=' . rawurlencode(SANIPLAN_VERSION);
        $praefix = $basis === '' ? './' : $basis;
        $karte = array('imports' => array(
            'preact' => $praefix . 'assets/vendor/preact-htm.module.js' . $v,
            'saniplan/gemeinsam' => $praefix . 'assets/js/gemeinsam.js' . $v,
        ));
        ?>
        <script type="importmap"><?php echo json_encode($karte, JSON_UNESCAPED_SLASHES); ?></script>
        <script type="module" src="<?php echo h($basis . 'assets/js/' . $modul . '.js' . $v); ?>"></script>
    <?php } ?>
</body>

</html>
<?php
}

/** 2026-11-19 → 19.11.2026 */
function datum_deutsch($iso)
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m)) {
        return $m[3] . '.' . $m[2] . '.' . $m[1];
    }
    return '–';
}
