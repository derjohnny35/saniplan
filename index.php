<head>
    <meta charset="UTF-8" />
    <title>Bereitschaftsplan Schulsanis</title>
    <meta http-equiv="refresh" content="15" >
    <link rel="icon" href="https://www.lgoe.de/wp-content/uploads/2019/09/favicon-150x150.png" sizes="32x32">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php
    include("person.php");

    $personalData = fopen('data/personalData.csv', "r") or die("Ein Fehler is aufgetreten!\nHerrn S. oder J.H kontaktieren!");
    $filesize = count(file("data/personalData.csv"));
    $personalAll = [];
    
    error_reporting(0);
    while(!feof($personalData)) {
        $temp = explode(";", fgets($personalData));
        $personalAll[] = new Person($temp[0], $temp[1], $temp[2], $temp[3], $temp[4]);
    }
    error_reporting(E_ALL);

    $time = date("G:i:s");
    $day = date("N");
    $week = date("W");
    $shiftnr = null;

    //$time = "10:34:45";

    if(strtotime($time) < strtotime("07:45:00")){
        $shiftnr = 0;
    } else if(strtotime($time) >= strtotime("07:45:00" ) && strtotime($time) < strtotime("08:40:00" )){
        $shiftnr = 1;
    } else if(strtotime($time) >= strtotime("08:40:00" ) && strtotime($time) < strtotime("09:30:00" )){
        $shiftnr = 2;
    } else if(strtotime($time) >= strtotime("09:30:00" ) && strtotime($time) < strtotime("10:15:00" )){
        $shiftnr = 3;
    } else if(strtotime($time) >= strtotime("10:15:00" ) && strtotime($time) < strtotime("10:35:00" )){
        $shiftnr = 4;
    } else if(strtotime($time) >= strtotime("10:35:00" ) && strtotime($time) < strtotime("11:25:00" )){
        $shiftnr = 5;
    } else if(strtotime($time) >= strtotime("11:25:00" ) && strtotime($time) < strtotime("12:10:00" )){
        $shiftnr = 6;
    } else if(strtotime($time) >= strtotime("12:10:00" ) && strtotime($time) < strtotime("12:25:00" )){
        $shiftnr = 7;
    } else if(strtotime($time) >= strtotime("12:25:00" ) && strtotime($time) < strtotime("13:15:00" )){
        $shiftnr = 8;
    } else if(strtotime($time) >= strtotime("13:15:00" ) && strtotime($time) < strtotime("14:00:00" )){
        $shiftnr = 9;
    } else if(strtotime($time) >= strtotime("14:00:00" )){
        $shiftnr = 10;
    }

    if($week%2==0){
        $bereitschaftsplan = fopen('data/bereitschaftsplanGeradeWoche.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
    } else {
        $bereitschaftsplan = fopen('data/bereitschaftsplanUngeradeWoche.csv', "r") or die("<br>Ein Fehler is aufgetreten!<br>Herrn S. oder J.H kontaktieren!");
    }
    echo "<div class='clock'>".$time."</div>";
    
    switch($shiftnr){
        case 0:
            echo "Die erste Schicht hat noch nicht begonnen.<br>";
            break;
        case 10:
            echo "Die letzte Schicht ist bereits vorbei.<br>";
            break;
        default:
            echo "<table class='center' border='1'><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr>";

            for($i = 0; $i<$shiftnr+1; $i++){
                $shift = fgets($bereitschaftsplan);
            }
            $shift = explode(":",explode(";", $shift)[$day]);            

            for($i = 0; $i<count($shift); $i++){
                echo "<tr>";
                echo "<td>".$personalAll[$shift[$i]]->vorname." ".$personalAll[$shift[$i]]->name."</td><td>".$personalAll[$shift[$i]]->klasse."</td><td>".$personalAll[$shift[$i]]->handynummer."</td>";
                echo "</tr>";
            }
            echo "
                <tr id='placeholder'><td colspan='3'><hr></td></tr>
                <tr>            
                    <td colspan='3'>
                        <div class='unterueberschrift'>Jederzeit erreichbare SchülerInnen, Jederzeit erreichbare Schüler*innen für schwere Notfälle bzw. falls niemand sonst erreichbar ist:</div>
                    </td>
                </tr>
                <tr>
                    <td>".$personalAll[1]->vorname." ".$personalAll[1]->name."</td><td>".$personalAll[1]->klasse."</td><td>".$personalAll[1]->handynummer."</td>
                </tr>
                <tr>
                    <td>".$personalAll[2]->vorname." ".$personalAll[2]->name."</td><td>".$personalAll[2]->klasse."</td><td>".$personalAll[2]->handynummer."</td>
                </tr>
                <tr>
                    <td>".$personalAll[3]->vorname." ".$personalAll[3]->name."</td><td>".$personalAll[3]->klasse."</td><td>".$personalAll[3]->handynummer."</td>
                </tr>
            </table>";
            break;
    }
?>
    <div class='footer'>
        <div id='left'>Stand des Bereitschaftsplans: 17.11.2022</div>
        <div id='middle'>Version 1.0</div>
        <div id='right'>©Jonathan Hostadt 2022</div>
    </div>
</body>
