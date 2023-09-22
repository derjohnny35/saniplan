<?php
// Pfad zur JSON-Datei
$json_file = 'config.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON-Daten aus dem Formular erhalten
    $json_data = [
        'useTwoWeekPlan' => isset($_POST['useTwoWeekPlan']) ? true : false,
        'emergencyContacts' => explode(',', $_POST['emergencyContacts']),
        'refreshRate' => $_POST['refreshRate'],
        'verantwortliche' => explode(',', $_POST['verantwortliche']),
        'reportingLevel' => $_POST['reportingLevel'],
    ];

    // JSON-Daten in eine Zeichenkette konvertieren
    $json_string = json_encode($json_data, JSON_PRETTY_PRINT);

    // JSON-Daten in die Datei schreiben
    if (file_put_contents($json_file, $json_string)) {
        echo 'JSON-Daten erfolgreich gespeichert.';
        header('Location: ../');
        exit();
    } else {
        echo 'Fehler beim Speichern der JSON-Daten.';
    }
}

$json_data = file_get_contents($json_file);
$json_data = json_decode($json_data, true);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Einstellungen</title>
</head>

<body>
    <h1>Einstellungen</h1>
    <form action="" method="post">
        <label for="useTwoWeekPlan">verschiedene Pläne für gerade/ungerade Woche</label>
        <input type="checkbox" name="useTwoWeekPlan" id="useTwoWeekPlan" <?php echo $json_data['useTwoWeekPlan'] ? 'checked' : ''; ?>>
        <br><br>

        <label for="emergencyContacts">Ids von den Notfallkontakten</label>
        <input type="text" name="emergencyContacts" id="emergencyContacts"
            value="<?php echo implode(',', $json_data['emergencyContacts']); ?>">
        <br><br>

        <label for="refreshRate">Aktualisierungsrate der Website [s]</label>
        <input type="text" name="refreshRate" id="refreshRate" value="<?php echo $json_data['refreshRate']; ?>">
        <br><br>

        <label for="verantwortliche">verantwortliche Personen für Fehlerbehebung</label>
        <input type="text" name="verantwortliche" id="verantwortliche"
            value="<?php echo implode(',', $json_data['verantwortliche']); ?>">
        <br><br>

        <label for="reportingLevel">Reporting Level (0 = keine, 1 = nur Errors, 2 = Warnings und Errors)</label>
        <input type="text" name="reportingLevel" id="reportingLevel" value="<?php echo $json_data['reportingLevel']; ?>">
        <br><br>

        <input type="submit" value="Einstellungen speichern">
    </form>
</body>

</html>