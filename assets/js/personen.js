// Personenverwaltung
import { html, render, useState, useEffect, useCallback, api, personName, Meldung, Dialog } from 'saniplan/gemeinsam';

const LEER = { id: 0, nachname: '', vorname: '', klasse: '', telefon: '', spitzname: '' };

function App() {
    const [personen, setPersonen] = useState(null);
    const [suche, setSuche] = useState('');
    const [bearbeiten, setBearbeiten] = useState(null);
    const [importOffen, setImportOffen] = useState(false);
    const [meldung, setMeldung] = useState(null);

    const laden = useCallback(async () => {
        try {
            const d = await api('personen');
            setPersonen(d.personen);
        } catch (e) {
            setMeldung({ art: 'fehler', text: 'Personen konnten nicht geladen werden: ' + e.message });
        }
    }, []);

    useEffect(() => { laden(); }, [laden]);

    async function loeschen(p) {
        let text = `${personName(p)} wirklich löschen?`;
        if (p.einsaetze > 0) {
            text += `\n\nDie Person ist ${p.einsaetze}× im Bereitschaftsplan eingeteilt und wird dort ebenfalls entfernt.`;
        }
        if (p.notfallkontakt) {
            text += '\n\nDie Person ist außerdem als Notfallkontakt eingetragen.';
        }
        if (!window.confirm(text)) {
            return;
        }
        try {
            await api('person-loeschen', { daten: { id: p.id } });
            setMeldung({ art: 'ok', text: `${personName(p)} wurde gelöscht.` });
            laden();
        } catch (e) {
            setMeldung({ art: 'fehler', text: e.message });
        }
    }

    async function alleLoeschen() {
        const eingabe = window.prompt('Damit werden ALLE Personen gelöscht und beide Bereitschaftspläne geleert '
            + '(z. B. zum Schuljahreswechsel). Zum Bestätigen bitte ALLE LÖSCHEN eingeben:');
        if (eingabe === null) {
            return;
        }
        try {
            await api('personen-alle-loeschen', { daten: { bestaetigung: eingabe.trim() } });
            setMeldung({ art: 'ok', text: 'Alle Personen wurden gelöscht.' });
            laden();
        } catch (e) {
            setMeldung({ art: 'fehler', text: e.message === 'Bestätigung fehlt.' ? 'Abgebrochen – die Eingabe stimmte nicht.' : e.message });
        }
    }

    const begriff = suche.trim().toLowerCase();
    const liste = (personen || []).filter((p) => !begriff
        || `${p.id} ${p.vorname} ${p.nachname} ${p.klasse} ${p.telefon} ${p.spitzname}`.toLowerCase().includes(begriff));

    return html`
        <h1>Personen</h1>
        <${Meldung} meldung=${meldung} schliessen=${() => setMeldung(null)} />
        <div class="werkzeugleiste" style="margin-bottom:12px">
            <input type="search" placeholder="Suchen …" value=${suche} onInput=${(e) => setSuche(e.target.value)} />
            <span class="abstand"></span>
            <button class="primaer" onClick=${() => setBearbeiten({ ...LEER })}>+ Neue Person</button>
            <button onClick=${() => setImportOffen(true)}>CSV importieren</button>
            <button class="gefahr" onClick=${alleLoeschen} disabled=${!personen || personen.length === 0}>Alle löschen</button>
        </div>
        ${!personen && !meldung && html`<p>Wird geladen …</p>`}
        ${personen && html`<table class="tabelle">
            <thead><tr>
                <th class="zahl">ID</th><th>Nachname</th><th>Vorname</th><th>Klasse</th><th>Telefon</th><th>Spitzname</th>
                <th class="zahl">Einteilungen</th><th></th>
            </tr></thead>
            <tbody>
                ${liste.map((p) => html`<tr key=${p.id}>
                    <td class="zahl">${p.id}</td>
                    <td>${p.nachname}</td>
                    <td>${p.vorname}</td>
                    <td>${p.klasse}</td>
                    <td>${p.telefon}</td>
                    <td>${p.spitzname}</td>
                    <td class="zahl">${p.einsaetze}${p.notfallkontakt ? html` <span class="badge" title="Notfallkontakt">Notfall</span>` : ''}</td>
                    <td style="white-space:nowrap;text-align:right">
                        <button class="klein" onClick=${() => setBearbeiten({ ...p })}>Bearbeiten</button>
                        <button class="klein gefahr" onClick=${() => loeschen(p)}>Löschen</button>
                    </td>
                </tr>`)}
                ${liste.length === 0 && html`<tr><td colspan="8">${personen.length === 0
                    ? 'Noch keine Personen angelegt.' : 'Keine Person gefunden.'}</td></tr>`}
            </tbody>
        </table>
        <p class="leise">${personen.length} Personen insgesamt.</p>`}
        ${bearbeiten && html`<${PersonDialog} person=${bearbeiten} schliessen=${() => setBearbeiten(null)}
            gespeichert=${(text) => { setBearbeiten(null); setMeldung({ art: 'ok', text }); laden(); }} />`}
        ${importOffen && html`<${ImportDialog} schliessen=${() => setImportOffen(false)}
            fertig=${(text) => { setImportOffen(false); setMeldung({ art: 'ok', text }); laden(); }} />`}
    `;
}

function PersonDialog({ person, schliessen, gespeichert }) {
    const [p, setP] = useState(person);
    const [fehler, setFehler] = useState('');
    const feld = (name, titel, typ = 'text') => html`
        <label for=${'f-' + name}>${titel}</label>
        <input id=${'f-' + name} type=${typ} value=${p[name]} onInput=${(e) => setP({ ...p, [name]: e.target.value })} />`;

    async function speichern(e) {
        e.preventDefault();
        try {
            await api('person-speichern', { daten: p });
            gespeichert(`${personName(p)} wurde gespeichert.`);
        } catch (err) {
            setFehler(err.message);
        }
    }

    return html`<${Dialog} titel=${p.id ? 'Person bearbeiten' : 'Neue Person'} schliessen=${schliessen}>
        <form onSubmit=${speichern}>
            ${fehler && html`<${Meldung} meldung=${{ art: 'fehler', text: fehler }} />`}
            <div class="formular">
                ${feld('vorname', 'Vorname')}
                ${feld('nachname', 'Nachname')}
                ${feld('klasse', 'Klasse')}
                ${feld('telefon', 'Telefonnummer', 'tel')}
                ${feld('spitzname', 'Spitzname')}
                <span class="hilfe">optional</span>
            </div>
            <div class="werkzeugleiste">
                <button type="button" onClick=${schliessen}>Abbrechen</button>
                <button type="submit" class="primaer">Speichern</button>
            </div>
        </form>
    <//>`;
}

function ImportDialog({ schliessen, fertig }) {
    const [csv, setCsv] = useState('');
    const [vorschau, setVorschau] = useState(null);
    const [fehler, setFehler] = useState('');

    async function datei(e) {
        const f = e.target.files[0];
        if (!f) {
            return;
        }
        const puffer = await f.arrayBuffer();
        // UTF-8 versuchen, sonst Windows-1252 (Excel-Export)
        let text;
        try {
            text = new TextDecoder('utf-8', { fatal: true }).decode(puffer);
        } catch (err) {
            text = new TextDecoder('windows-1252').decode(puffer);
        }
        setCsv(text);
        pruefen(text);
    }

    async function pruefen(text) {
        setFehler('');
        try {
            setVorschau(await api('personen-import', { daten: { csv: text } }));
        } catch (err) {
            setFehler(err.message);
        }
    }

    async function uebernehmen() {
        try {
            const r = await api('personen-import', { daten: { csv, uebernehmen: true } });
            const neu = r.personen.filter((p) => p.status === 'neu').length;
            const geaendert = r.personen.filter((p) => p.status === 'geaendert').length;
            fertig(`Import abgeschlossen: ${neu} neu, ${geaendert} geändert.`);
        } catch (err) {
            setFehler(err.message);
        }
    }

    const statusText = { neu: 'neu', geaendert: 'geändert', unveraendert: 'unverändert' };

    return html`<${Dialog} titel="Personen aus CSV importieren" schliessen=${schliessen} breit>
        <p>Format wie bisher, Trennzeichen <code>;</code>, erste Zeile ist die Kopfzeile:<br />
            <code>Id;Name;Vorname;Klasse;Telefonnummer;Nickname</code></p>
        <p>Personen mit bereits vorhandener ID werden aktualisiert, alle anderen neu angelegt. Es wird niemand gelöscht.</p>
        <div class="werkzeugleiste" style="justify-content:flex-start;margin:0 0 8px">
            <input type="file" accept=".csv,text/csv,text/plain" onChange=${datei} />
        </div>
        <textarea rows="6" placeholder="… oder CSV-Text hier einfügen" value=${csv}
            onInput=${(e) => { setCsv(e.target.value); setVorschau(null); }}></textarea>
        ${fehler && html`<${Meldung} meldung=${{ art: 'fehler', text: fehler }} />`}
        ${vorschau && vorschau.fehler.length > 0 && html`<div class="meldung fehler"><span>
            <strong>Bitte korrigieren:</strong><br />${vorschau.fehler.map((f) => html`${f}<br />`)}</span></div>`}
        ${vorschau && html`<table class="tabelle" style="margin-top:12px">
            <thead><tr><th class="zahl">ID</th><th>Name</th><th>Klasse</th><th>Telefon</th><th>Spitzname</th><th>Status</th></tr></thead>
            <tbody>${vorschau.personen.map((p) => html`<tr key=${p.id}>
                <td class="zahl">${p.id}</td>
                <td>${personName(p)}${p.status === 'geaendert' && personName(p.vorher) !== personName(p)
                    ? html`<br /><small>vorher: ${personName(p.vorher)}</small>` : ''}</td>
                <td>${p.klasse}</td><td>${p.telefon}</td><td>${p.spitzname}</td>
                <td><span class="badge ${p.status}">${statusText[p.status]}</span></td>
            </tr>`)}</tbody>
        </table>`}
        <div class="werkzeugleiste">
            <button onClick=${schliessen}>Abbrechen</button>
            <button onClick=${() => pruefen(csv)} disabled=${!csv.trim()}>Prüfen</button>
            <button class="primaer" onClick=${uebernehmen}
                disabled=${!vorschau || vorschau.fehler.length > 0 || vorschau.personen.length === 0}>Übernehmen</button>
        </div>
    <//>`;
}

render(html`<${App} />`, document.getElementById('app'));
