// Einstellungen und Schichtzeiten
import { html, render, useState, useEffect, useCallback, api, personName, heuteIso, minuten, Meldung } from 'saniplan/gemeinsam';

function App() {
    const [daten, setDaten] = useState(null);
    const [ladeFehler, setLadeFehler] = useState('');

    const laden = useCallback(async () => {
        try {
            setDaten(await api('einstellungen'));
        } catch (e) {
            setLadeFehler(e.message);
        }
    }, []);

    useEffect(() => { laden(); }, [laden]);

    if (ladeFehler) {
        return html`<${Meldung} meldung=${{ art: 'fehler', text: 'Einstellungen konnten nicht geladen werden: ' + ladeFehler }} />`;
    }
    if (!daten) {
        return html`<p>Wird geladen …</p>`;
    }
    return html`
        <h1>Einstellungen</h1>
        <${Allgemein} daten=${daten} neuLaden=${laden} />
        <${Schichten} daten=${daten} neuLaden=${laden} />`;
}

function Allgemein({ daten, neuLaden }) {
    const [verantwortliche, setVerantwortliche] = useState(daten.verantwortliche.join('\n'));
    const [uhr, setUhr] = useState(daten.uhr_anzeigen);
    const [stand, setStand] = useState(daten.stand);
    const [notfall, setNotfall] = useState(daten.notfallkontakte.filter((id) => daten.personen.some((p) => p.id === id)));
    const [hinzu, setHinzu] = useState('');
    const [meldung, setMeldung] = useState(null);

    const person = (id) => daten.personen.find((p) => p.id === id);

    function verschieben(index, richtung) {
        const neu = [...notfall];
        const ziel = index + richtung;
        [neu[index], neu[ziel]] = [neu[ziel], neu[index]];
        setNotfall(neu);
    }

    async function speichern(e) {
        e.preventDefault();
        try {
            await api('einstellungen-speichern', {
                daten: {
                    verantwortliche: verantwortliche.split('\n').map((s) => s.trim()).filter(Boolean),
                    uhr_anzeigen: uhr,
                    stand,
                    notfallkontakte: notfall,
                },
            });
            setMeldung({ art: 'ok', text: 'Einstellungen gespeichert.' });
            neuLaden();
        } catch (err) {
            setMeldung({ art: 'fehler', text: err.message });
        }
    }

    return html`<form class="abschnitt" onSubmit=${speichern}>
        <h2>Allgemein</h2>
        <${Meldung} meldung=${meldung} schliessen=${() => setMeldung(null)} />
        <div class="formular">
            <label>Notfallkontakte</label>
            <div>
                <ul class="sortierliste">
                    ${notfall.map((id, i) => {
                        const p = person(id);
                        return html`<li key=${id}>
                            <span>${personName(p)}, ${p.klasse} – ${p.telefon}</span>
                            <button type="button" class="klein" disabled=${i === 0} onClick=${() => verschieben(i, -1)} aria-label="Nach oben">↑</button>
                            <button type="button" class="klein" disabled=${i === notfall.length - 1} onClick=${() => verschieben(i, 1)} aria-label="Nach unten">↓</button>
                            <button type="button" class="klein gefahr" onClick=${() => setNotfall(notfall.filter((x) => x !== id))}>Entfernen</button>
                        </li>`;
                    })}
                    ${notfall.length === 0 && html`<li><span>Keine Notfallkontakte eingetragen.</span></li>`}
                </ul>
                <div class="werkzeugleiste">
                    <select value=${hinzu} onChange=${(e) => setHinzu(e.target.value)}>
                        <option value="">Person auswählen …</option>
                        ${daten.personen.filter((p) => !notfall.includes(p.id)).map((p) => html`
                            <option value=${p.id}>${p.nachname}, ${p.vorname} (${p.klasse})</option>`)}
                    </select>
                    <button type="button" disabled=${!hinzu} onClick=${() => { setNotfall([...notfall, Number(hinzu)]); setHinzu(''); }}>Hinzufügen</button>
                </div>
            </div>
            <span class="hilfe">Stehen immer unten auf der Anzeige – für schwere Notfälle oder wenn sonst niemand erreichbar ist.</span>

            <label for="verantwortliche">Verantwortliche</label>
            <textarea id="verantwortliche" rows="3" value=${verantwortliche} onInput=${(e) => setVerantwortliche(e.target.value)}></textarea>
            <span class="hilfe">Ein Name pro Zeile. Diese Personen werden bei Fehlermeldungen als Ansprechpersonen genannt.</span>

            <label for="uhr">Uhr anzeigen</label>
            <div><input id="uhr" type="checkbox" checked=${uhr} onChange=${(e) => setUhr(e.target.checked)} />
                <label for="uhr" style="font-weight:400"> Datum und Uhrzeit groß oben auf der Anzeige</label></div>

            <label for="stand">Stand des Plans</label>
            <div class="werkzeugleiste">
                <input id="stand" type="date" value=${stand} onInput=${(e) => setStand(e.target.value)} />
                <button type="button" onClick=${() => setStand(heuteIso())}>Heute</button>
            </div>
            <span class="hilfe">Wird beim Speichern im Editor automatisch auf das aktuelle Datum gesetzt.</span>
        </div>
        <div class="werkzeugleiste" style="margin-top:16px;justify-content:flex-end">
            <button type="submit" class="primaer">Einstellungen speichern</button>
        </div>
    </form>`;
}

function Schichten({ daten, neuLaden }) {
    const [liste, setListe] = useState(daten.schichten.map((s) => ({ ...s })));
    const [meldung, setMeldung] = useState(null);

    const aendern = (i, feld, wert) => setListe(liste.map((s, j) => (j === i ? { ...s, [feld]: wert } : s)));

    // Hinweise zu Überschneidungen und Lücken (der Server prüft Überschneidungen zusätzlich)
    const sortiert = [...liste].filter((s) => s.beginn && s.ende).sort((a, b) => minuten(a.beginn) - minuten(b.beginn));
    const hinweise = [];
    for (let i = 1; i < sortiert.length; i++) {
        const a = sortiert[i - 1];
        const b = sortiert[i];
        if (minuten(b.beginn) < minuten(a.ende)) {
            hinweise.push(`„${a.bezeichnung}“ und „${b.bezeichnung}“ überschneiden sich.`);
        } else if (minuten(b.beginn) > minuten(a.ende)) {
            hinweise.push(`Zwischen „${a.bezeichnung}“ und „${b.bezeichnung}“ ist eine Lücke (${a.ende}–${b.beginn}). In dieser Zeit zeigt die Anzeige nur die Notfallkontakte.`);
        }
    }

    async function speichern() {
        const entfernt = daten.schichten.filter((s) => !liste.some((n) => n.id === s.id));
        const betroffen = entfernt.reduce((summe, s) => summe + (daten.einsaetze_je_schicht[s.id] || 0), 0);
        if (betroffen > 0 && !window.confirm(`Beim Entfernen der Schicht(en) ${entfernt.map((s) => '„' + s.bezeichnung + '“').join(', ')} `
            + `gehen ${betroffen} Einteilungen verloren. Fortfahren?`)) {
            return;
        }
        try {
            await api('schichten-speichern', { daten: { schichten: liste } });
            setMeldung({ art: 'ok', text: 'Schichten gespeichert.' });
            neuLaden();
        } catch (e) {
            setMeldung({ art: 'fehler', text: e.message });
        }
    }

    return html`<div class="abschnitt">
        <h2>Schichten</h2>
        <p class="leise">Die Schichten bilden die Zeilen im Editor. Die Anzeige wechselt automatisch zur jeweiligen Beginnzeit.</p>
        <${Meldung} meldung=${meldung} schliessen=${() => setMeldung(null)} />
        <table class="tabelle">
            <thead><tr><th>Bezeichnung</th><th>Beginn</th><th>Ende</th><th class="zahl">Einteilungen</th><th></th></tr></thead>
            <tbody>
                ${liste.map((s, i) => html`<tr key=${s.id || 'neu' + i}>
                    <td><input type="text" value=${s.bezeichnung} onInput=${(e) => aendern(i, 'bezeichnung', e.target.value)} /></td>
                    <td><input type="time" value=${s.beginn} onInput=${(e) => aendern(i, 'beginn', e.target.value)} /></td>
                    <td><input type="time" value=${s.ende} onInput=${(e) => aendern(i, 'ende', e.target.value)} /></td>
                    <td class="zahl">${s.id ? (daten.einsaetze_je_schicht[s.id] || 0) : '–'}</td>
                    <td style="text-align:right"><button class="klein gefahr" onClick=${() => setListe(liste.filter((_, j) => j !== i))}>Entfernen</button></td>
                </tr>`)}
            </tbody>
        </table>
        ${hinweise.map((h) => html`<div class="meldung hinweis" style="margin-top:8px"><span>${h}</span></div>`)}
        <div class="werkzeugleiste" style="margin-top:12px">
            <button onClick=${() => setListe([...liste, { id: 0, bezeichnung: '', beginn: '', ende: '' }])}>+ Schicht hinzufügen</button>
            <span class="abstand"></span>
            <button onClick=${() => setListe(daten.schichten.map((s) => ({ ...s })))}>Zurücksetzen</button>
            <button class="primaer" onClick=${speichern}>Schichten speichern</button>
        </div>
    </div>`;
}

render(html`<${App} />`, document.getElementById('app'));
