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
    $personalAll[] = new Person(trim($temp[0]), trim($temp[1]), trim($temp[2]), trim($temp[3]), trim($temp[4]), trim($temp[5]));
}
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
    <h1>Bereitschaftsplan Editor</h1>
    <div class="fullContent">
        <div class="stundenraster">
            <table>
                <tr>
                    <th></th>
                    <th>Montag</th>
                    <th>Dienstag</th>
                    <th>Mittwoch</th>
                    <th>Donnerstag</th>
                    <th>Freitag</th>
                </tr>
                <tr>
                    <td>1. Stunde</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>2.</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>3. Stunde<br> + Pause</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>4.</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>5.</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>Pause + 6.</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
                <tr>
                    <td>7.</td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                    <td ondrop="drop(event)" ondragover="allowDrop(event)"></td>
                </tr>
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
</body>

</html>