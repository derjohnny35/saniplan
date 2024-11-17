<?php
$plan = '';
if (isset($_GET['plan'])) {
    if ($_GET['plan'] == 'Musikschule') {
        $plan = 'musikschule';
    } else if ($_GET['plan'] == 'LGÖ') {
        $plan = 'hauptgebaeude';
    } else {
        $plan = 'hauptgebaeude';
    }
} else {
    $plan = 'hauptgebaeude';
}
include("../config.php");
$config = new Config();
error_reporting($config->config["reportingLevel"]);
include("../person.php");

$personalData = fopen('../data/personalData.csv', "r") or die("Fehler beim öffnen von data/personalData.csv!<br><br>" . verantwortliche($config));
$filesize = count(file("../data/personalData.csv"));
$personalAll = [];

while (!feof($personalData)) {
    $temp = explode(";", str_replace(" ", "", fgets($personalData)));
    if (count($temp) == 6) {
        $personalAll[] = new Person(trim($temp[0]), htmlentities(trim($temp[1])), htmlentities(trim($temp[2])), trim($temp[3]), trim($temp[4]), htmlentities(trim($temp[5])));
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
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="print.css">
</head>

<body>
    <div class="outer">
        <div class="header">
            <div class="left"></div>
            <div class="mid">
                <h3>Bereitschaftsplan<?php echo " " . $_GET['plan'] ?></h3>
            </div>
            <h3>
                <div id="datum" class="right"></div>
            </h3>
        </div>
    </div>
    <div class="stundenraster">
        <table id="stundenraster">
            <tr>
                <th></th>
                <th>Montag</th>
                <th>Dienstag</th>
                <th>Mittwoch</th>
                <th>Donnerstag</th>
                <th>Freitag</th>
            </tr>
            <?php
            $shift = fgets($bereitschaftsplan);
            for ($i = 0; $i < 9; $i++) {
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
                            echo "<td>";
                            foreach ($shiftPersonal as $key => $value) {
                                if ($value != "") {
                                    $value = getPersonId($personalAll, $value);
                                    if ($value != 0) {
                                        echo "<li>" . $personalAll[$value]->vorname . " " . $personalAll[$value]->name . ", " . $personalAll[$value]->klasse . "</li>";
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
<script>
    function aktuellesDatum() {
        const heute = new Date();

        const tag = String(heute.getDate()).padStart(2, '0');
        const monat = String(heute.getMonth() + 1).padStart(2, '0');
        const jahr = heute.getFullYear();

        return tag + '.' + monat + '.' + jahr;
    }

    window.addEventListener('load',
        function () {
            document.getElementById('datum').innerHTML = "Stand: " + aktuellesDatum();
            document.title = 'Bereitschaftsplan_' + aktuellesDatum().replace(".", "_");
            window.print();
        }, false);
</script>