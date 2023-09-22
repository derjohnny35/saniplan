setInterval(aktualisiereUhr, 1000);

function aktualisiereUhr() {
    const jetzt = new Date();
    const uhrElement = document.getElementById('time');
    uhrElement.innerHTML = jetzt.toLocaleTimeString();
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