var interval = setInterval(aktualisiereUhr, 1000);

function aktualisiereUhr() {
    const jetzt = new Date();
    try {
        const uhrElement = document.getElementById('time');
        uhrElement.innerHTML = jetzt.toLocaleTimeString();
    }
    catch (err) {
        clearInterval(interval);
    }
}

function aktuellesDatum() {
    const heute = new Date();

    // Datum in einem gewünschten Format erstellen (z.B. "TT.MM.JJJJ")
    const tag = String(heute.getDate()).padStart(2, '0');
    const monat = String(heute.getMonth() + 1).padStart(2, '0'); // Monate sind nullbasiert
    const jahr = heute.getFullYear();

    const datumFeld = document.getElementById('stand');
    datumFeld.value = tag + '.' + monat + '.' + jahr;
}

function link(destination) {
    window.location.href = destination;
}