<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Bereitschaftsplan Schulsanis</title>
    <meta http-equiv="refresh" content="15">
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <a href="settings">
        <div class="header">Einstellungen</div>
    </a>

    <?php
    include("person.php");
    include("config.php");
    $config = new Config();

    $personalData = fopen('data/personalData.csv', "r") or die("Ein Fehler is aufgetreten!\nHerrn S. oder J.H kontaktieren!");
    $filesize = count(file("data/personalData.csv"));
    $personalAll = [];

    while (!feof($personalData)) {
        $temp = explode(";", fgets($personalData));
        $personalAll[] = new Person(trim($temp[0]), trim($temp[1]), trim($temp[2]), trim($temp[3]), trim($temp[4]), trim($temp[5]));
    }

    $time = date("G:i:s");
    $day = date("N");
    $week = date("W");
    $shiftnr = null;
    $time = "08:34:45";
    $day = 1;

    if (strtotime($time) < strtotime("07:45:00")) {
        $shiftnr = 0;
    } else if (strtotime($time) >= strtotime("07:45:00") && strtotime($time) < strtotime("08:40:00")) {
        $shiftnr = 1;
    } else if (strtotime($time) >= strtotime("08:40:00") && strtotime($time) < strtotime("09:30:00")) {
        $shiftnr = 2;
    } else if (strtotime($time) >= strtotime("09:30:00") && strtotime($time) < strtotime("10:15:00")) {
        $shiftnr = 3;
    } else if (strtotime($time) >= strtotime("10:15:00") && strtotime($time) < strtotime("10:35:00")) {
        $shiftnr = 4;
    } else if (strtotime($time) >= strtotime("10:35:00") && strtotime($time) < strtotime("11:25:00")) {
        $shiftnr = 5;
    } else if (strtotime($time) >= strtotime("11:25:00") && strtotime($time) < strtotime("12:10:00")) {
        $shiftnr = 6;
    } else if (strtotime($time) >= strtotime("12:10:00") && strtotime($time) < strtotime("12:25:00")) {
        $shiftnr = 7;
    } else if (strtotime($time) >= strtotime("12:25:00") && strtotime($time) < strtotime("13:15:00")) {
        $shiftnr = 8;
    } else if (strtotime($time) >= strtotime("13:15:00") && strtotime($time) < strtotime("14:00:00")) {
        $shiftnr = 9;
    } else if (strtotime($time) >= strtotime("14:00:00")) {
        $shiftnr = 10;
    }

    if ($week % 2 == 0 || ($config->config["useTwoWeekPlan"] == false)) {
        $bereitschaftsplan = fopen('data/bereitschaftsplan.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
        $bereitschaftsplanm = fopen('data/bereitschaftsplanMusikschule.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
    } else {
        $bereitschaftsplan = fopen('data/bereitschaftsplanUngeradeWoche.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
        $bereitschaftsplanm = fopen('data/bereitschaftsplanUngeradeWocheMusikschule.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
    }
    echo "<div class='clock'>" . date("d.m.y") . "&emsp;" . date("G:i") . " Uhr</div>";

    switch ($shiftnr) {
        case 0:
            echo "Die erste Schicht hat noch nicht begonnen. Möglicherweise ist eine/r der folgenden SchülerInnen schon erreichbar:<br><br>";
            echo "<table class='center' border='1'><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr>";
            endTable($personalAll, $config);
            break;
        case 10:
            echo "Die letzte Schicht ist bereits vorbei. Möglicherweise ist eine/r der folgenden SchülerInnen noch erreichbar:<br><br>";
            echo "<table class='center' border='1'><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr>";
            endTable($personalAll, $config);
            break;
        default:
            echo "<table class='center' border='1'><tr><th colspan='3' id='grey'>Hauptgebäude</th></tr><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr>";

            for ($i = 0; $i < $shiftnr + 1; $i++) {
                $shift = fgets($bereitschaftsplan);
            }
            $shift = explode(":", explode(";", $shift)[$day]);
            for ($i = 0; $i < count($shift); $i++) {
                echo "<tr>";
                echo "<td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->vorname . " " . $personalAll[getPersonId($personalAll, $shift[$i])]->name . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->klasse . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->handynummer . "</td>";
                echo "</tr>";
            }

            echo "<tr id='placeholder'><td colspan='3' id='hr'><hr></td></tr><tr><th colspan='3' id='grey'>Musikschule</th></tr>";

            for ($i = 0; $i < $shiftnr + 1; $i++) {
                $shift = fgets($bereitschaftsplanm);
            }
            $shift = explode(":", explode(";", $shift)[$day]);
            for ($i = 0; $i < count($shift); $i++) {
                if (!$personalAll[(int) $shift[$i]]->vorname == 0) {
                    echo "<tr>";
                    echo "<td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->vorname . " " . $personalAll[getPersonId($personalAll, $shift[$i])]->name . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->klasse . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->handynummer . "</td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='3'>Es ist für die aktuelle Schicht niemand verfügbar!<td></tr>";
                }
            }
            echo "<tr id='placeholder'><td colspan='3' id='hr'><hr></td></tr>
            <tr>            
                <td colspan='3'>
                    <div class='unterueberschrift'>Jederzeit erreichbare SchülerInnen für schwere Notfälle bzw. falls niemand sonst erreichbar ist:</div>
                </td>
            </tr>";
            endTable($personalAll, $config);
            break;
    }

    function getPersonId($personalAll, $value)
    {
        if (is_numeric($value)) {
            $value = (int) $value;
        }
        for ($i = 0; $i < sizeof($personalAll); $i++) {
            if (strcmp($personalAll[$i]->id, $value) == 0) {
                return $i;
            }
            if (strcmp($personalAll[$i]->nick, $value) == 0) {
                return $i;
            }
            if (strcmp($personalAll[$i]->vorname . " " . $personalAll[$i]->name, $value) == 0) {
                return $i;
            }
        }
        return 0;
    }

    function endTable($personalAll, $config)
    {
        for ($i = 0; $i < sizeof($config->config["emergencyContacts"]); $i++) {
            $id = $config->config["emergencyContacts"][$i];
            echo "
            <tr>
                <td>" . $personalAll[$id]->vorname . " " . $personalAll[$id]->name . "</td><td>" . $personalAll[$id]->klasse . "</td><td>" . $personalAll[$id]->handynummer . "</td>
            </tr>";
        }
        echo "</table>";
    }
    include("footer.html");
    ?>
