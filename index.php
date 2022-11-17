<?php
    include("person.php");
    $personalData = fopen('personalData.csv', "r") or die("Ein Fehler is aufgetreten!\nHerrn S. oder J.H kontaktieren!");
    $bereitschaftsplan = fopen('bereitschaftsplan.csv', "r") or die("Ein Fehler is aufgetreten!\nHerrn S. oder J.H kontaktieren!");
    $filesize = count(file("personalData.csv"));
    $personalAll = array();

    while(!feof($personalData)) {
        echo fgets($personalData) . "<br>";
    }
?>