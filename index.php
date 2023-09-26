<?php
// Pfad zur JSON-Datei
$json_file = 'config.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON-Daten aus dem Formular erhalten
    $json_data = [
        'useTwoWeekPlan' => isset($_POST['useTwoWeekPlan']) ? true : false,
        'emergencyContacts' => explode(',', $_POST['emergencyContacts']),
        'verantwortliche' => explode(',', $_POST['verantwortliche']),
        'reportingLevel' => $_POST['reportingLevel'],
        'stand' => $_POST['stand'],
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
    <meta charset="UTF-8" />
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <script src="../script.js" type="text/javascript"></script>
    <style>
        body {
            margin-left: 35px;
            font-size: 120%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-container {
            display: grid;
            grid-template-columns: auto 250px auto;
            justify-content: left;
            gap: 20px;
        }

        label {
            padding-right: 10px;
        }

        input {
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>

<body>
    <h1>Einstellungen</h1>
    <form action="" method="post" class="form-container">
        <label for="useTwoWeekPlan">verschiedene Pläne für gerade/ungerade Woche</label>
        <input type="checkbox" name="useTwoWeekPlan" id="useTwoWeekPlan" <?php echo $json_data['useTwoWeekPlan'] ? 'checked' : ''; ?>>
        <br>

        <label for="emergencyContacts">Ids der Notfallkontakte</label>
        <input type="text" name="emergencyContacts" id="emergencyContacts"
            value="<?php echo implode(',', $json_data['emergencyContacts']); ?>">
        <br>

        <label for="verantwortliche">verantwortliche Personen für Fehlerbehebung</label>
        <input type="text" name="verantwortliche" id="verantwortliche"
            value="<?php echo implode(',', $json_data['verantwortliche']); ?>">
        <br>

        <label for="reportingLevel">Reporting Level (0 = keine, 1 = nur Errors, 2 = Warnings und Errors)</label>
        <input type="text" name="reportingLevel" id="reportingLevel"
            value="<?php echo $json_data['reportingLevel']; ?>">
        <br>

        <label for="stand">Stand des Bereitschaftsplans</label>
        <input type="text" name="stand" id="stand" value="<?php echo $json_data['stand']; ?>">
        <input type="button" value="Heute" onclick="aktuellesDatum()" style="width: 100px" />

        <input type="submit" value="Einstellungen speichern" style="height: 40px">
    </form>
</body>

</html>