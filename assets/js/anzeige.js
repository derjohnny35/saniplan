// Anzeige im Sekretariat. Die Seite lädt nie komplett neu: Sie holt regelmäßig neue Daten und
// berechnet die aktuelle Schicht selbst. Ist der Server weg, bleibt der zuletzt geladene Plan stehen.
import {
    html, render, useState, useEffect, useRef,
    api, tageslage, zelle, personName, datumDeutsch, verantwortlicheText,
} from 'saniplan/gemeinsam';

const ABRUF_INTERVALL = 60 * 1000;
const ABRUF_INTERVALL_OHNE_DATEN = 15 * 1000;
const MAX_ZEITABWEICHUNG = 5 * 60 * 1000;
const APP_VERSION = document.body.dataset.version;

// Testmodus: ?testzeit=2026-11-16T09:45 simuliert eine andere Uhrzeit (die Uhr läuft von dort aus weiter).
const TESTZEIT = new URLSearchParams(window.location.search).get('testzeit');
const TEST_VERSATZ = TESTZEIT && !isNaN(Date.parse(TESTZEIT)) ? Date.parse(TESTZEIT) - Date.now() : 0;
const aktuelleZeit = () => new Date(Date.now() + TEST_VERSATZ);

function uhrzeit(datum) {
    return datum.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

function App() {
    const [daten, setDaten] = useState(null);
    const [fehlerSeit, setFehlerSeit] = useState(null);
    const [fehlerText, setFehlerText] = useState('');
    const [abweichung, setAbweichung] = useState(0);
    const [jetzt, setJetzt] = useState(aktuelleZeit());
    const datenRef = useRef(null);

    useEffect(() => {
        const t = setInterval(() => setJetzt(aktuelleZeit()), 1000);
        return () => clearInterval(t);
    }, []);

    useEffect(() => {
        let timer;
        let aktiv = true;
        async function abrufen() {
            const gesendet = Date.now();
            try {
                const bisher = datenRef.current;
                const antwort = await api('anzeige', { parameter: bisher ? { v: bisher.datenversion } : {}, zeitlimit: 10000 });
                if (!aktiv) {
                    return;
                }
                // Neue Programmversion nach einem git pull: nur jetzt neu laden, denn der Server ist erreichbar.
                if (antwort.appversion !== APP_VERSION) {
                    window.location.reload();
                    return;
                }
                const empfangen = Date.now();
                setAbweichung(antwort.serverzeit - (gesendet + empfangen) / 2);
                if (!antwort.unveraendert) {
                    datenRef.current = antwort;
                    setDaten(antwort);
                    const stand = document.getElementById('stand');
                    if (stand) {
                        stand.textContent = datumDeutsch(antwort.stand);
                    }
                }
                setFehlerSeit(null);
            } catch (e) {
                if (aktiv) {
                    setFehlerSeit((seit) => seit || new Date());
                    setFehlerText(e.message);
                }
            }
            if (aktiv) {
                timer = setTimeout(abrufen, datenRef.current ? ABRUF_INTERVALL : ABRUF_INTERVALL_OHNE_DATEN);
            }
        }
        abrufen();
        return () => {
            aktiv = false;
            clearTimeout(timer);
        };
    }, []);

    if (!daten) {
        if (fehlerSeit) {
            return html`<div class="anzeige">
                <div class="banner fehler">
                    <strong>Der Bereitschaftsplan konnte nicht geladen werden.</strong>
                    <span>${fehlerText} Es wird automatisch erneut versucht. Bitte den ausgedruckten Plan verwenden
                    und die verantwortliche Person kontaktieren. Details: <a href="diagnose/">Diagnose</a></span>
                </div>
            </div>`;
        }
        return html`<div class="anzeige"><p class="laden">Bereitschaftsplan wird geladen …</p></div>`;
    }

    const kontakt = verantwortlicheText(daten.verantwortliche);
    const lage = tageslage(daten.schichten, jetzt);
    const zeitFalsch = Math.abs(abweichung) > MAX_ZEITABWEICHUNG;

    return html`<div class="anzeige">
        ${TEST_VERSATZ !== 0 && html`<div class="banner warnung">
            <strong>Testmodus: simulierte Uhrzeit.</strong> <a href="./">Zur normalen Anzeige</a>
        </div>`}
        ${fehlerSeit && html`<div class="banner fehler">
            <strong>Keine Verbindung zum Server seit ${uhrzeit(fehlerSeit)} Uhr.</strong>
            <span>Angezeigt wird der zuletzt geladene Plan. Falls das länger anhält, bitte ${kontakt}.</span>
        </div>`}
        ${zeitFalsch && html`<div class="banner warnung">
            <strong>Die Uhrzeit dieses Computers weicht um ${Math.round(Math.abs(abweichung) / 60000)} Minuten von der Serverzeit ab.</strong>
            <span>Die angezeigte Schicht könnte falsch sein. Bitte ${kontakt}.</span>
        </div>`}
        ${daten.uhr_anzeigen && html`<div class="uhr">
            <span class="datum">${jetzt.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })}</span>
            <span class="zeit">${jetzt.toLocaleTimeString('de-DE')}</span>
        </div>`}
        <${Lage} daten=${daten} lage=${lage} />
        <${Notfallkontakte} daten=${daten} />
    </div>`;
}

function Lage({ daten, lage }) {
    if (lage.art === 'schicht') {
        return html`
            <h1 class="schicht-titel">${lage.schicht.bezeichnung}
                <span class="zeitraum">${lage.schicht.beginn}–${lage.schicht.ende} Uhr</span></h1>
            <div class="gebaeude-raster">
                ${Object.entries(daten.gebaeude).map(([schluessel, name]) => html`
                    <${GebaeudeKarte} name=${name} daten=${daten}
                        ids=${zelle(daten.plaene[schluessel], lage.wochentag, lage.schicht.id)} />`)}
            </div>
            ${lage.naechste && html`<${Naechste} daten=${daten} lage=${lage} />`}`;
    }
    const texte = {
        wochenende: 'Heute finden keine Schichten statt.',
        vorher: `Die erste Schicht beginnt um ${lage.naechste && lage.naechste.beginn} Uhr.`,
        zwischen: `Gerade läuft keine Schicht. Die nächste beginnt um ${lage.naechste && lage.naechste.beginn} Uhr.`,
        nachher: 'Die letzte Schicht ist bereits vorbei.',
    };
    return html`
        <div class="hinweis-gross">
            <p>${texte[lage.art]}</p>
            <p>Möglicherweise ist eine/r der unten aufgeführten Notfallkontakte erreichbar.</p>
        </div>
        ${lage.naechste && html`<${Naechste} daten=${daten} lage=${lage} />`}`;
}

function GebaeudeKarte({ name, daten, ids }) {
    return html`<section class="karte ${ids.length === 0 ? 'leer' : ''}">
        <h2>${name}</h2>
        ${ids.length === 0
            ? html`<p class="niemand">Für diese Schicht ist niemand eingeteilt.<br />Bitte einen Notfallkontakt anrufen.</p>`
            : html`<${PersonenTabelle} personen=${ids.map((id) => daten.personen[id]).filter(Boolean)} />`}
    </section>`;
}

function PersonenTabelle({ personen }) {
    return html`<table class="personen">
        <thead><tr><th>Name</th><th>Klasse</th><th>Telefonnummer</th></tr></thead>
        <tbody>
            ${personen.map((p) => html`<tr key=${p.id}>
                <td class="name">${personName(p)}</td>
                <td>${p.klasse}</td>
                <td class="telefon">${p.telefon}</td>
            </tr>`)}
        </tbody>
    </table>`;
}

function Naechste({ daten, lage }) {
    const s = lage.naechste;
    const teile = Object.entries(daten.gebaeude).map(([schluessel, name]) => {
        const namen = zelle(daten.plaene[schluessel], lage.wochentag, s.id)
            .map((id) => personName(daten.personen[id])).join(', ');
        return html`<span class="naechste-gebaeude"><strong>${name}:</strong> ${namen || 'niemand eingeteilt'}</span>`;
    });
    return html`<p class="naechste">Danach ab ${s.beginn} Uhr (${s.bezeichnung}) — ${teile}</p>`;
}

function Notfallkontakte({ daten }) {
    const personen = daten.notfallkontakte.map((id) => daten.personen[id]).filter(Boolean);
    return html`<section class="karte notfall">
        <h2>Jederzeit erreichbare SchülerInnen für schwere Notfälle bzw. falls niemand sonst erreichbar ist</h2>
        ${personen.length === 0
            ? html`<p class="niemand">Es sind keine Notfallkontakte eingetragen.</p>`
            : html`<${PersonenTabelle} personen=${personen} />`}
    </section>`;
}

render(html`<${App} />`, document.getElementById('app'));
