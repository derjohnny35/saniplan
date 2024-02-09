<?php
$plan = '';
if (isset($_GET['plan'])) {
    if ($_GET['plan'] == 'musikschule') {
        $plan = 'musikschule';
    } else if ($_GET['plan'] == 'hauptgebaeude') {
        $plan = 'hauptgebaeude';
    } else {
        header("Location: ./?plan=hauptgebaeude");
    }
} else {
    header("Location: ./?plan=hauptgebaeude");
}
include("../config.php");
$config = new Config();
error_reporting($config->config["reportingLevel"]);
include("../person.php");

$personalData = fopen('../data/personalData.csv', "r") or die("Fehler beim öffnen von data/personalData.csv!<br><br>" . verantwortliche($config));
$filesize = count(file("../data/personalData.csv"));
$personalAll = [];

while (!feof($personalData)) {
    $temp = explode(";", str_replace(" ", "", htmlentities(fgets($personalData))));
    if (count($temp) == 6) {
        $personalAll[] = new Person(trim($temp[0]), trim($temp[1]), trim($temp[2]), trim($temp[3]), trim($temp[4]), trim($temp[5]));
    }
}
$bereitschaftsplan = '';
if ($plan == 'hauptgebaeude') {
    $bereitschaftsplan = fopen('../data/bereitschaftsplan.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplan.csv!<br><br>" . verantwortliche($config));
} else {
    $bereitschaftsplan = fopen('../data/bereitschaftsplanMusikschule.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplan.csv!<br><br>" . verantwortliche($config));
}
?>
<html>

<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <title>Bereitschaftsplan Editor</title>
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" type="text/javascript"></script>
</head>

<body>
    <div id="popup">
        <h2>Bereitschaftsplan-Editor Bedienungsanleitung</h2>
        <p>
            Dies ist ein eigens entwickelter Editor für den Bereitschaftsplan.
            In der Personalliste rechts sind alle Personen aus der Datei "personalData.csv" aufgelistet.
            Geladen wird immer der aktuelle Bereitschaftsplan, wie er in der Datei
            "bereitschaftsplan.csv"/"bereitschaftsplanmusikschule.csv" steht.<br>
            Der Editor bietet folgende Funktionen:
        <p>

        <p><b>1. Personen hinzufügen:</b></p>
        <p>Ziehe eine Person per Drag-n-Drop aus der Liste und platziere sie im Stundenplanraster.</p>

        <p><b>2. Personen verschieben:</b></p>
        <p>Klicke und halte die Maustaste über einer Person, ziehe sie an die gewünschte Stelle und lasse die Maustaste
            los.</p>

        <p><b>3. Person entfernen:</b></p>
        <p>Klicke auf eine Person im Stundenplan, um sie zu entfernen.</p>

        <p><b>4. Gebäude wechseln:</b></p>
        <p>Klicke auf das Feld oben links in der Tabelle, um zwischen den beiden Gebäuden umzuschalten.</p>

        <p><b>5. Plan speichern:</b></p>
        <p>Verwende den Button "Download", um den aktuellen Bereitschaftsplan herunterzuladen oder lege den Plan als
            neuen Bereitschaftsplan fest.</p>

        <button onclick="closePopup('popup')" class="btn" id="closePopup">Schließen</button>
        <p class="small">Entwickelt von Jonathan Hostadt 2024. Bei Fragen und Anregungen melde dich bei ihm.</p>
    </div>
    <div id="speichernpopup">
        <button onclick="save()" class="btn" id="download">Download</button><br><br>
        <button onclick="setNewPlan()" class="btn" id="closePopup">Als neuen Bereitschaftsplan
            einstellen</button><br><br>
        <button onclick="closePopup('speichernpopup')" class="btn" id="closePopup">Abbrechen</button><br><br>
    </div>
    <div id="overlay" onclick="closePopup('popup')"></div>
    <div class="header">
        <div><button id="btnsave" class="btn" onclick="showPopup('speichernpopup')">Speichern</button></div>
        <div>
            <h1>Bereitschaftsplan Editor</h1>
        </div>
        <div><button id="btnclear" class="btn" onclick="clearPlan()">Clear</button> <button id="btncancel" class="btn"
                onclick="cancel()">Zurück</button></div>
    </div>
    <div class="fullContent">
        <div class="stundenraster">
            <table id="stundenraster">
                <tr>
                    <?php
                    if ($plan == 'hauptgebaeude') {
                        echo "<th id=\"plan\" onclick=\"changePlan('musikschule')\">LGÖ</th>";
                    } else {
                        echo "<th id=\"plan\" onclick=\"changePlan('hauptgebaeude')\">Musikschule</th>";
                    } ?>
                    <th>Montag</th>
                    <th>Dienstag</th>
                    <th>Mittwoch</th>
                    <th>Donnerstag</th>
                    <th>Freitag</th>
                </tr>
                <?php
                $shift = fgets($bereitschaftsplan);
                for ($i = 0; $i < 9; $i++) {
                    $i = (int) $i;
                    $shift = fgets($bereitschaftsplan);
                    if ($i != 3 && $i != 7) {
                        echo "<tr>";
                        for ($j = 0; $j < 6; $j++) {
                            if ($j == 0) {
                                if ($i == 2) {
                                    echo "<td class=\"first\">" . ($i + 1) . ". Stunde<br>+ Pause";
                                } else if ($i == 6) {
                                    echo "<td class=\"first\">Pause +<br>" . $i . ". Stunde";
                                } else if ($i > 6) {
                                    echo "<td class=\"first\">" . ($i - 1) . ". Stunde";
                                } else if ($i > 2) {
                                    echo "<td class=\"first\">" . $i . ". Stunde";
                                } else {
                                    echo "<td class=\"first\">" . ($i + 1) . ". Stunde";
                                }
                            } else {
                                $shiftPersonal = explode(":", explode(";", $shift)[$j]);
                                echo "<td ondrop=\"drop(event)\" ondragover=\"allowDrop(event)\">";
                                foreach ($shiftPersonal as $key => $value) {
                                    if ($value != "") {
                                        $value = getPersonId($personalAll, $value);
                                        if ($value != 0) {
                                            echo "<li id=\"" . $value . " clone" . rand() . "\" class=\"draggable dropped\" draggable=\"true\" ondragstart=\"drag(event)\" onclick=\"remove(this)\">" . $personalAll[$value]->vorname . " " . $personalAll[$value]->name . ", " . $personalAll[$value]->klasse . "</li>";
                                        }
                                    }
                                }
                                echo "</td>";
                            }
                        }
                        echo "</tr>";
                    }
                }
                ?>
            </table>
        </div>

        <div class="personalListe">
            <ul id="personalliste">
                <?php
                foreach ($personalAll as $key => $person) {
                    if ($key != "0") {
                        echo "<li id=\"" . $person->id . "\" class=\"draggable\" draggable=\"true\" ondragstart=\"drag(event)\">" . $person->vorname . " " . $person->name . ", " . $person->klasse . "</li>";
                    }
                }
                ?>
            </ul>
        </div>
    </div>
    <div class='footer'>
        <div class='left' onclick="showPopup('popup')">Bedienungsanleitung</div>
        <div>Saniplan Editor Version 1.0</div>
        <div class='right'>©Jonathan Hostadt 2024</div>
        <script>console.log("Fakt: Der Informatik-LK 2022-24 war mit Abstand der coolste");</script>
    </div>
    <div id="unsavedData" style="visibility: hidden">0</div>
</body>

</html>

<?php
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
?>