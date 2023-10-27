var checkTime = (function (uhrElement) {
    var executed = false;
    return function (uhrElement) {
        if (!executed) {
            executed = true;
            let clientzeit = uhrElement.innerHTML;
            let heute = new Date();
            let zeit = `${heute.getHours()}:${heute.getMinutes()}:${heute.getSeconds()}`;
            if (timeDifferenceInSeconds(zeit, clientzeit) > 300) {
                alert("Serverzeit und Clientzeit unterscheiden sich um mehr als 5 Minuten!\nInformieren Sie den Verantwortlichen!");
            }
        }
    }
})();
var intervalTimer = setInterval(aktualisiereUhr, 1000);

function aktualisiereUhr() {
    const jetzt = new Date();
    try {
        const uhrElement = document.getElementById('time');
        checkTime(uhrElement);
        uhrElement.innerHTML = jetzt.toLocaleTimeString();
        reloadOnShiftChange();
    }
    catch (err) {
        //if (window.location.pathname.split("/").at(-2) != "settings") {
        clearInterval(intervalTimer);
        //alert("Es ist ein Fehler aufgetreten!");
        //alert(err);
        //}
    }
}

function reloadOnShiftChange() {
    const schichtbeginn = ["7:45:0", "8:40:0", "9:30:0", "10:15:0", "10:35:0", "11:25:0", "12:10:0", "12:25:0", "13:15:0", "14:0:0"];
    let heute = new Date();
    let zeit = `${heute.getHours()}:${heute.getMinutes()}:${heute.getSeconds()}`;
    for (let i = 0; i < schichtbeginn.length; i++) {
        if (zeit == schichtbeginn[i]) location.reload();
    }
}

function aktuellesDatum() {
    const heute = new Date();

    const tag = String(heute.getDate()).padStart(2, '0');
    const monat = String(heute.getMonth() + 1).padStart(2, '0');
    const jahr = heute.getFullYear();

    const datumFeld = document.getElementById('stand');
    datumFeld.value = tag + '.' + monat + '.' + jahr;
}

function link(destination) {
    window.location.href = destination;
}

function checkdTime() {
    let clientzeit = document.getElementById('time').innerHTML;
    let heute = new Date();
    let zeit = `${heute.getHours()}:${heute.getMinutes()}:${heute.getSeconds()}`;
    if (timeDifferenceInSeconds(zeit, clientzeit) > 300) {
        alert("Server und Clientzeit unterscheiden sich um mehr als 5 Minuten!");
    }
}

function timeDifferenceInSeconds(time1, time2) {
    const [hours1, minutes1, seconds1] = time1.split(':').map(Number);
    const [hours2, minutes2, seconds2] = time2.split(':').map(Number);

    const totalSeconds1 = hours1 * 3600 + minutes1 * 60 + seconds1;
    const totalSeconds2 = hours2 * 3600 + minutes2 * 60 + seconds2;

    return Math.abs(totalSeconds1 - totalSeconds2);
}