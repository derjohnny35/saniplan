<?php
include("../config.php");
$config = new Config();
error_reporting($config->config["reportingLevel"]);
include("../person.php");

$personalData = fopen('../data/personalData.csv', "r") or die("Fehler beim öffnen von data/personalData.csv!<br><br>" . verantwortliche($config));
$filesize = count(file("../data/personalData.csv"));
$personalAll = [];

while (!feof($personalData)) {
    $temp = explode(";", fgets($personalData));
    if (count($temp) == 6) {
        $personalAll[] = new Person(trim($temp[0]), trim($temp[1]), trim($temp[2]), trim($temp[3]), trim($temp[4]), trim($temp[5]));
    }
}
$bereitschaftsplan = fopen('../data/bereitschaftsplan.csv', "r") or die("Fehler beim öffnen von data/bereitschaftsplan.csv!<br><br>" . verantwortliche($config));
?>
<html>

<head>
    <meta charset="UTF-8" />
    <title>Bereitschaftsplan Editor</title>
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" type="text/javascript"></script>
</head>

<body>
    <div class="header">
        <div><button id="btnsave">Speichern</button></div>
        <div>
            <h1>Bereitschaftsplan Editor</h1>
        </div>
        <div><button id="btncancel" onclick="cancel()">Abbrechen</button></div>
    </div>
    <div class="fullContent">
        <div class="stundenraster">
            <table>
                <tr>
                    <?php echo "<th>LGÖ</th>"; ?>
                    <th>Montag</th>
                    <th>Dienstag</th>
                    <th>Mittwoch</th>
                    <th>Donnerstag</th>
                    <th>Freitag</th>
                </tr>
                <?php
                $shift = fgets($bereitschaftsplan);
                for ($i = 0; $i < 7; $i++) {
                    $shift = fgets($bereitschaftsplan);
                    echo "<tr>";
                    for ($j = 0; $j < 6; $j++) {
                        if ($j == 0) {
                            switch ($i) {
                                case 2:
                                    echo "<td class=\"first\">" . $i + 1 . ". Stunde<br>+ Pause";
                                    break;

                                case 5:
                                    echo "<td class=\"first\">Pause +<br>" . $i + 1 . ". Stunde";
                                    break;

                                default:
                                    echo "<td class=\"first\">" . $i + 1 . ". Stunde";
                                    break;
                            }
                        } else {
                            $shiftPersonal = explode(":", explode(";", $shift)[$j]);
                            //echo "<td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->vorname . " " . $personalAll[getPersonId($personalAll, $shift[$i])]->name . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->klasse . "</td><td>" . $personalAll[getPersonId($personalAll, $shift[$i])]->handynummer . "</td>";
                            echo "<td ondrop=\"drop(event)\" ondragover=\"allowDrop(event)\">";
                            foreach ($shiftPersonal as $key => $value) {
                                $value = getPersonId($personalAll, $value);
                                echo "<li id=\"" . $value . " clone" . rand() . "\" class=\"draggable dropped\" draggable=\"true\" ondragstart=\"drag(event)\" onclick=\"this.remove()\">" . $personalAll[$value]->vorname . " " . $personalAll[$value]->name . ", " . $personalAll[$value]->klasse . "</li>";
                            }
                            echo "</td>";
                        }
                    }
                    echo "</tr>";
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
        <div class='left'></div>
        <div>Saniplan Editor Version 0.9</div>
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

function createLiClone($personalAll, $personalID)
{

}
?>