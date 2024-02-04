<?php
include("config.php");
$config = new Config();
error_reporting($config->config["reportingLevel"]);
include("person.php");
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Bereitschaftsplan Schulsanis</title>
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" type="text/javascript"></script>
</head>

<body>
    <div class="header">
        <div id="settings" class="left" onclick="link('settings')">Einstellungen</div>
        <div id="settings" class="right" onclick="link('editor')">Bereitschaftsplan-Editor</div>
    </div>
    <?php
    $personalData = fopen('data/personalData.csv', "r") or die("Fehler beim öffnen von data/personalData.csv!<br><br>" . verantwortliche($config));
    $filesize = count(file("data/personalData.csv"));
    $personalAll = [];

    while (!feof($personalData)) {
        $temp = explode(";", fgets($personalData));
        if (count($temp) == 6) {
            $personalAll[] = new Person(trim($temp[0]), trim($temp[1]), trim($temp[2]), trim($temp[3]), trim($temp[4]), trim($temp[5]));
        }
    }

    $time = date("G:i:s");
    $day = date("N");
    $week = date("W");
    $shiftnr = null;
    //$time = "10:54:45";
    //$day = 5;

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
        $bereitschaftsplan = fopen('data/bereitschaftsplan.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplan.csv!<br><br>" . verantwortliche($config));
        $bereitschaftsplanm = fopen('data/bereitschaftsplanMusikschule.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplanMusikschule.csv!<br><br>" . verantwortliche($config));
    } else {
        $bereitschaftsplan = fopen('data/bereitschaftsplanUngeradeWoche.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplanUngeradeWoche.csv!<br><br>" . verantwortliche($config));
        $bereitschaftsplanm = fopen('data/bereitschaftsplanUngeradeWocheMusikschule.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplanUngeradeWocheMusikschule.csv!<br><br>" . verantwortliche($config));
    }
    if ($config->config["uhr"] == 1) {
        echo "<div class='clock'>" . date("d.m.y") . "&emsp;<span id='time'>" . date("G:i:s") . "</span></div>";
    } else if ($config->config["uhr"] == 2 && $config->config["reportingLevel"] == 2) {
        echo "<div class='clock'>" . date("d.m.y") . "&emsp;<span id='time'>" . date("G:i:s") . "</span></div>";
    }

    switch ($shiftnr) {
        case 0:
            echo "<div class=\"center\">Die erste Schicht hat noch nicht begonnen. Möglicherweise ist eine/r der folgenden SchülerInnen schon erreichbar:</div><br>";
            echo "<table class='center' border='1'><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr>";
            endTable($personalAll, $config);
            break;
        case 10:
            echo "<div class=\"center\">Die letzte Schicht ist bereits vorbei. Möglicherweise ist eine/r der folgenden SchülerInnen noch erreichbar:</div><br>";
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
                $personalID = getPersonId($personalAll, $shift[$i]);
                if ($personalID != 0) {
                    echo "<tr>";
                    echo "<td>" . $personalAll[$personalID]->vorname . " " . $personalAll[$personalID]->name . "</td><td>" . $personalAll[$personalID]->klasse . "</td><td>" . $personalAll[$personalID]->handynummer . "</td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='3'>Für die aktuelle Schicht ist niemand verfügbar!<td></tr>";
                }
            }

            echo "<tr id='placeholder'><td colspan='3' id='hr'><hr></td></tr><tr><th colspan='3' id='grey'>Musikschule</th></tr>";

            for ($i = 0; $i < $shiftnr + 1; $i++) {
                $shift = fgets($bereitschaftsplanm);
            }
            $shift = explode(":", explode(";", $shift)[$day]);
            for ($i = 0; $i < count($shift); $i++) {
                $personalID = getPersonId($personalAll, $shift[$i]);
                if ($personalID != 0) {
                    echo "<tr>";
                    echo "<td>" . $personalAll[$personalID]->vorname . " " . $personalAll[$personalID]->name . "</td><td>" . $personalAll[$personalID]->klasse . "</td><td>" . $personalAll[$personalID]->handynummer . "</td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='3'>Für die aktuelle Schicht ist niemand verfügbar!<td></tr>";
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

    function verantwortliche($config)
    {
        $names = $config->config["verantwortliche"];
        if (count($names) == 1) {
            return $names[0] . ' kontaktieren';
        } elseif (count($names) == 2) {
            return $names[0] . ' oder ' . $names[1] . ' kontaktieren';
        } elseif (count($names) > 2) {
            $last = array_pop($names);
            $namesString = implode(', ', $names) . ' oder ' . $last;
            return $namesString . ' kontaktieren';
        }
    }
    ?>
    <br>
    <div class='footer'>
        <div class='left'>Stand des Bereitschaftsplans:
            <?php echo $config->config["stand"]; ?>
        </div>
        <div>Saniplan Version 1.6</div>
        <div class='right'>©Jonathan Hostadt 2023</div>
        <script>console.log("Fakt: Der Informatik-LK 2022-24 war mit Abstand der coolste");</script>
    </div>
</body>

</html>