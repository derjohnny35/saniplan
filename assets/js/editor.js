// Bereitschaftsplan-Editor
import {
    html, render, useState, useEffect, useMemo, useRef, useCallback, useReducer,
    api, WOCHENTAGE, personName, Meldung, Dialog,
} from 'saniplan/gemeinsam';

const CSV_TITEL = { hauptgebaeude: 'LGÖ', musikschule: 'Musikschule' };

/** Liste von Zellen (API) → {"tag-schicht": [ids]} */
function alsKarte(zellen, schichtIds, personIds) {
    const karte = {};
    for (const z of zellen || []) {
        if (!schichtIds.has(z.schicht_id) || z.wochentag < 1 || z.wochentag > 5) {
            continue;
        }
        const ids = z.person_ids.filter((id) => personIds.has(id));
        if (ids.length) {
            karte[`${z.wochentag}-${z.schicht_id}`] = ids;
        }
    }
    return karte;
}

/** {"tag-schicht": [ids]} → Liste von Zellen (API) */
function alsZellen(karte) {
    return Object.entries(karte)
        .filter(([, ids]) => ids.length > 0)
        .map(([schluessel, ids]) => {
            const [tag, schicht] = schluessel.split('-').map(Number);
            return { wochentag: tag, schicht_id: schicht, person_ids: ids };
        });
}

/** Zustand der Pläne inkl. Rückgängig/Wiederholen. */
function planReducer(zustand, aktion) {
    switch (aktion.art) {
        case 'setzen':
            return { plaene: aktion.plaene, zurueck: [], vor: [] };
        case 'aendern': {
            const alt = zustand.plaene;
            const kopie = { ...alt[aktion.gebaeude] };
            aktion.veraendern(kopie);
            for (const k of Object.keys(kopie)) {
                if (!kopie[k] || kopie[k].length === 0) {
                    delete kopie[k];
                }
            }
            if (JSON.stringify(kopie) === JSON.stringify(alt[aktion.gebaeude])) {
                return zustand;
            }
            return {
                plaene: { ...alt, [aktion.gebaeude]: kopie },
                zurueck: [...zustand.zurueck.slice(-99), alt],
                vor: [],
            };
        }
        case 'zurueck':
            if (!zustand.zurueck.length) {
                return zustand;
            }
            return {
                plaene: zustand.zurueck[zustand.zurueck.length - 1],
                zurueck: zustand.zurueck.slice(0, -1),
                vor: [...zustand.vor, zustand.plaene],
            };
        case 'vor':
            if (!zustand.vor.length) {
                return zustand;
            }
            return {
                plaene: zustand.vor[zustand.vor.length - 1],
                zurueck: [...zustand.zurueck, zustand.plaene],
                vor: zustand.vor.slice(0, -1),
            };
        default:
            return zustand;
    }
}

function App() {
    const [basis, setBasis] = useState(null);
    const [ladeFehler, setLadeFehler] = useState('');
    const [verlauf, dispatch] = useReducer(planReducer, { plaene: null, zurueck: [], vor: [] });
    const plaene = verlauf.plaene;
    const [gespeichert, setGespeichert] = useState('');
    const [gebaeude, setGebaeude] = useState('hauptgebaeude');
    const [auswahl, setAuswahl] = useState(null);
    const [suche, setSuche] = useState('');
    const [dialog, setDialog] = useState(null);
    const [meldung, setMeldung] = useState(null);
    const [ziel, setZiel] = useState(null);
    const [speichert, setSpeichert] = useState(false);

    const laden = useCallback(async () => {
        try {
            const d = await api('editor');
            const schichtIds = new Set(d.schichten.map((s) => s.id));
            const personIds = new Set(d.personen.map((p) => p.id));
            const p = {};
            for (const g of Object.keys(d.gebaeude)) {
                p[g] = alsKarte(d.plaene[g], schichtIds, personIds);
            }
            setBasis(d);
            dispatch({ art: 'setzen', plaene: p });
            setGespeichert(JSON.stringify(p));
        } catch (e) {
            setLadeFehler(e.message);
        }
    }, []);

    useEffect(() => { laden(); }, [laden]);

    const ungespeichert = plaene !== null && JSON.stringify(plaene) !== gespeichert;

    // Warnung beim Verlassen mit ungespeicherten Änderungen
    const ungespeichertRef = useRef(false);
    ungespeichertRef.current = ungespeichert;
    useEffect(() => {
        const warnen = (e) => {
            if (ungespeichertRef.current) {
                e.preventDefault();
                e.returnValue = 'Es gibt ungespeicherte Änderungen.';
                return e.returnValue;
            }
        };
        window.addEventListener('beforeunload', warnen);
        const anleitung = () => setDialog('anleitung');
        window.addEventListener('anleitung-oeffnen', anleitung);
        return () => {
            window.removeEventListener('beforeunload', warnen);
            window.removeEventListener('anleitung-oeffnen', anleitung);
        };
    }, []);

    /** Übernimmt eine Änderung am aktiven Gebäude und merkt sich den vorherigen Stand für „Rückgängig“. */
    const aendern = useCallback((veraendern) => {
        dispatch({ art: 'aendern', gebaeude, veraendern });
    }, [gebaeude]);
    const rueckgaengig = useCallback(() => dispatch({ art: 'zurueck' }), []);
    const wiederholen = useCallback(() => dispatch({ art: 'vor' }), []);

    useEffect(() => {
        const taste = (e) => {
            if (e.target.matches && e.target.matches('input, textarea')) {
                return;
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
                e.preventDefault();
                rueckgaengig();
            } else if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
                e.preventDefault();
                wiederholen();
            } else if (e.key === 'Escape') {
                setAuswahl(null);
            }
        };
        window.addEventListener('keydown', taste);
        return () => window.removeEventListener('keydown', taste);
    }, [rueckgaengig, wiederholen]);

    const personen = useMemo(() => {
        const karte = new Map();
        for (const p of (basis ? basis.personen : [])) {
            karte.set(p.id, p);
        }
        return karte;
    }, [basis]);

    // Einsätze je Person (beide Gebäude) und Doppelbelegungen
    const { einsaetze, konflikte } = useMemo(() => {
        const zaehler = new Map();
        const konflikt = new Set();
        if (plaene) {
            const gebaeudeListe = Object.keys(plaene);
            for (const g of gebaeudeListe) {
                for (const [k, ids] of Object.entries(plaene[g])) {
                    for (const id of ids) {
                        zaehler.set(id, (zaehler.get(id) || 0) + 1);
                        if (gebaeudeListe.some((andere) => andere !== g && (plaene[andere][k] || []).includes(id))) {
                            konflikt.add(`${k}-${id}`);
                        }
                    }
                }
            }
        }
        return { einsaetze: zaehler, konflikte: konflikt };
    }, [plaene]);

    if (ladeFehler) {
        return html`<${Meldung} meldung=${{ art: 'fehler', text: 'Der Plan konnte nicht geladen werden: ' + ladeFehler }} />`;
    }
    if (!basis || !plaene) {
        return html`<p>Editor wird geladen …</p>`;
    }

    const plan = plaene[gebaeude];

    // ----- Aktionen -----
    function umschalten(id, schluessel) {
        aendern((k) => {
            const ids = k[schluessel] || [];
            k[schluessel] = ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id];
        });
    }

    function entfernen(id, schluessel) {
        aendern((k) => { k[schluessel] = (k[schluessel] || []).filter((x) => x !== id); });
    }

    function ablegen(e, schluessel) {
        e.preventDefault();
        setZiel(null);
        let info;
        try {
            info = JSON.parse(e.dataTransfer.getData('text/plain'));
        } catch (fehler) {
            return;
        }
        if (!info || !personen.has(info.id)) {
            return;
        }
        aendern((k) => {
            if (info.von && info.von !== schluessel && !e.ctrlKey) {
                k[info.von] = (k[info.von] || []).filter((x) => x !== info.id);
            }
            const ids = k[schluessel] || [];
            if (!ids.includes(info.id)) {
                k[schluessel] = [...ids, info.id];
            }
        });
    }

    function ziehenStarten(e, id, von) {
        e.dataTransfer.setData('text/plain', JSON.stringify({ id, von }));
        e.dataTransfer.effectAllowed = 'copyMove';
    }

    async function speichern(erzwingen = false) {
        setSpeichert(true);
        try {
            const daten = { basis: basis.datenversion, erzwingen, plaene: {} };
            for (const g of Object.keys(plaene)) {
                daten.plaene[g] = alsZellen(plaene[g]);
            }
            const antwort = await api('plan-speichern', { daten });
            setGespeichert(JSON.stringify(plaene));
            setBasis({ ...basis, datenversion: antwort.datenversion });
            const stand = document.getElementById('stand');
            if (stand) {
                const [j, m, t] = antwort.stand.split('-');
                stand.textContent = `${t}.${m}.${j}`;
            }
            setMeldung({ art: 'ok', text: 'Gespeichert. Die Anzeige im Sekretariat übernimmt den Plan innerhalb einer Minute. Bitte den Plan neu ausdrucken.' });
        } catch (e) {
            if (e.konflikt) {
                if (window.confirm(e.message + '\n\nTrotzdem speichern? Dabei werden die anderen Änderungen am Plan überschrieben.')) {
                    setSpeichert(false);
                    return speichern(true);
                }
                setMeldung({ art: 'fehler', text: 'Nicht gespeichert. Tipp: Seite neu laden, um den aktuellen Stand zu sehen (eigene Änderungen gehen dabei verloren).' });
            } else {
                setMeldung({ art: 'fehler', text: 'Speichern fehlgeschlagen: ' + e.message });
            }
        }
        setSpeichert(false);
    }

    function drucken() {
        if (ungespeichert && !window.confirm('Es gibt ungespeicherte Änderungen. Gedruckt wird der zuletzt gespeicherte Plan. Trotzdem fortfahren?')) {
            return;
        }
        window.open('druck.php?gebaeude=beide&drucken=1', '_blank');
    }

    function csvExportieren() {
        let csv = CSV_TITEL[gebaeude] + ';' + WOCHENTAGE.join(';') + ';\n';
        for (const s of basis.schichten) {
            csv += s.bezeichnung;
            for (let tag = 1; tag <= 5; tag++) {
                csv += ';' + (plan[`${tag}-${s.id}`] || []).join(':');
            }
            csv += ';\n';
        }
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
        link.download = gebaeude === 'hauptgebaeude' ? 'bereitschaftsplan.csv' : 'bereitschaftsplanMusikschule.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function versionLaden(daten, zeitpunkt) {
        const schichtIds = new Set(basis.schichten.map((s) => s.id));
        const neu = alsKarte(daten, schichtIds, new Set(personen.keys()));
        aendern((k) => {
            for (const key of Object.keys(k)) {
                delete k[key];
            }
            Object.assign(k, neu);
        });
        setDialog(null);
        setMeldung({ art: 'hinweis', text: `Version vom ${new Date(zeitpunkt).toLocaleString('de-DE')} geladen. Zum Übernehmen bitte speichern.` });
    }

    const suchbegriff = suche.trim().toLowerCase();
    const liste = basis.personen.filter((p) => !suchbegriff
        || `${p.vorname} ${p.nachname} ${p.klasse}`.toLowerCase().includes(suchbegriff));

    return html`
        <div class="editor-kopf">
            <h1>Bereitschaftsplan-Editor</h1>
            <div class="umschalter" role="tablist">
                ${Object.entries(basis.gebaeude).map(([g, name]) => html`
                    <button class=${g === gebaeude ? 'aktiv' : ''} onClick=${() => setGebaeude(g)}>${name}</button>`)}
            </div>
            ${ungespeichert && html`<span class="ungespeichert">● ungespeicherte Änderungen</span>`}
            <span class="abstand" style="flex:1"></span>
            <div class="werkzeugleiste">
                <button onClick=${rueckgaengig} disabled=${!verlauf.zurueck.length} title="Strg+Z">↶ Rückgängig</button>
                <button onClick=${wiederholen} disabled=${!verlauf.vor.length} title="Strg+Y">↷ Wiederholen</button>
                <button onClick=${() => aendern((k) => { for (const key of Object.keys(k)) { delete k[key]; } })}
                    title="Alle Einteilungen dieses Gebäudes entfernen (rückgängig machbar)">Leeren</button>
                <button onClick=${() => setDialog('historie')}>Frühere Versionen</button>
                <button onClick=${csvExportieren}>CSV exportieren</button>
                <button onClick=${drucken}>Drucken</button>
                <button class="primaer" onClick=${() => speichern(false)} disabled=${!ungespeichert || speichert}>
                    ${speichert ? 'Speichert …' : 'Speichern'}</button>
            </div>
        </div>
        <${Meldung} meldung=${meldung} schliessen=${() => setMeldung(null)} />
        <div class="editor">
            <table class="raster ${auswahl ? 'mit-auswahl' : ''}">
                <thead>
                    <tr><th class="schicht"></th>${WOCHENTAGE.map((t) => html`<th>${t}</th>`)}</tr>
                </thead>
                <tbody>
                    ${basis.schichten.map((s) => html`<tr key=${s.id}>
                        <th class="schicht">${s.bezeichnung}<small>${s.beginn}–${s.ende}</small></th>
                        ${[1, 2, 3, 4, 5].map((tag) => {
                            const schluessel = `${tag}-${s.id}`;
                            const ids = plan[schluessel] || [];
                            return html`<td key=${schluessel}
                                class="${ids.length ? '' : 'leer'} ${ziel === schluessel ? 'ziel' : ''}"
                                onDragOver=${(e) => { e.preventDefault(); e.dataTransfer.dropEffect = e.ctrlKey ? 'copy' : 'move'; if (ziel !== schluessel) { setZiel(schluessel); } }}
                                onDragLeave=${(e) => { if (!e.currentTarget.contains(e.relatedTarget)) { setZiel(null); } }}
                                onDrop=${(e) => ablegen(e, schluessel)}
                                onClick=${() => auswahl && umschalten(auswahl, schluessel)}>
                                ${ids.map((id) => {
                                    const p = personen.get(id);
                                    const konflikt = konflikte.has(`${schluessel}-${id}`);
                                    return html`<div key=${id} draggable="true"
                                        class="chip ${konflikt ? 'konflikt' : ''} ${auswahl === id ? 'hervorgehoben' : ''}"
                                        title=${konflikt ? 'Zur selben Zeit auch im anderen Gebäude eingeteilt!' : personName(p)}
                                        onDragStart=${(e) => ziehenStarten(e, id, schluessel)}
                                        onClick=${(e) => { e.stopPropagation(); setAuswahl(auswahl === id ? null : id); }}>
                                        <span class="chip-name">${personName(p)}</span>
                                        <span class="chip-klasse">${p ? p.klasse : ''}</span>
                                        <button aria-label="Entfernen" title="Aus dieser Schicht entfernen"
                                            onClick=${(e) => { e.stopPropagation(); entfernen(id, schluessel); }}>×</button>
                                    </div>`;
                                })}
                            </td>`;
                        })}
                    </tr>`)}
                </tbody>
            </table>
            <aside class="personenliste">
                <input type="search" placeholder="Person suchen …" value=${suche} onInput=${(e) => setSuche(e.target.value)} />
                <p class="hinweis">${auswahl
                    ? html`<strong>${personName(personen.get(auswahl))}</strong> ausgewählt – Zellen anklicken zum Ein-/Austragen. Esc beendet.`
                    : 'Person in eine Zelle ziehen oder anklicken und dann Zellen anklicken.'}</p>
                <ul>
                    ${liste.map((p) => html`<li key=${p.id} draggable="true"
                        class=${auswahl === p.id ? 'ausgewaehlt' : ''}
                        onDragStart=${(e) => ziehenStarten(e, p.id, null)}
                        onClick=${() => setAuswahl(auswahl === p.id ? null : p.id)}>
                        <span>${personName(p)}, ${p.klasse}</span>
                        <span class="anzahl" title="Einteilungen in beiden Gebäuden">${einsaetze.get(p.id) || 0}×</span>
                    </li>`)}
                    ${liste.length === 0 && html`<li>Keine Person gefunden. Neue Personen unter <a href="../personen/">Personen</a> anlegen.</li>`}
                </ul>
            </aside>
        </div>
        ${dialog === 'anleitung' && html`<${Anleitung} schliessen=${() => setDialog(null)} />`}
        ${dialog === 'historie' && html`<${Historie} gebaeude=${gebaeude} name=${basis.gebaeude[gebaeude]}
            schliessen=${() => setDialog(null)} laden=${versionLaden} />`}
    `;
}

function Anleitung({ schliessen }) {
    return html`<${Dialog} titel="Bedienungsanleitung" schliessen=${schliessen}>
        <div class="anleitung">
            <h3>Einteilen</h3>
            <p>Eine Person aus der Liste rechts in eine Zelle ziehen. Oder die Person anklicken und danach die
                gewünschten Zellen anklicken – ein zweiter Klick trägt sie wieder aus. Esc beendet die Auswahl.</p>
            <h3>Verschieben und Entfernen</h3>
            <p>Eine Person im Plan in eine andere Zelle ziehen. Mit gedrückter Strg-Taste wird sie kopiert.
                Das × an der Person entfernt sie aus der Schicht.</p>
            <h3>Rückgängig</h3>
            <p>Jeder Schritt lässt sich mit Strg+Z rückgängig machen und mit Strg+Y wiederholen.</p>
            <h3>Gebäude</h3>
            <p>Oben zwischen Hauptgebäude und Musikschule umschalten. Rot markiert sind Personen, die zur selben
                Zeit in beiden Gebäuden eingeteilt sind.</p>
            <h3>Speichern und Drucken</h3>
            <p>„Speichern“ übernimmt beide Pläne sofort in die Anzeige und setzt den Stand auf heute. Danach bitte
                neu ausdrucken – der Ausdruck ist der Notfallplan, falls der Saniplan einmal ausfällt.
                Unter „Frühere Versionen“ lässt sich ein älterer Stand wiederherstellen.</p>
            <h3>Personen</h3>
            <p>Neue Sanis werden unter „Personen“ angelegt.</p>
            <div class="werkzeugleiste"><button class="primaer" onClick=${schliessen}>Schließen</button></div>
        </div>
    <//>`;
}

function Historie({ gebaeude, name, schliessen, laden }) {
    const [liste, setListe] = useState(null);
    const [fehler, setFehler] = useState('');
    useEffect(() => {
        api('historie', { parameter: { gebaeude } }).then(setListe).catch((e) => setFehler(e.message));
    }, [gebaeude]);

    async function waehlen(eintrag) {
        try {
            const version = await api('historie', { parameter: { id: eintrag.id } });
            laden(version.daten, version.zeitpunkt);
        } catch (e) {
            setFehler(e.message);
        }
    }

    return html`<${Dialog} titel=${'Frühere Versionen – ' + name} schliessen=${schliessen}>
        ${fehler && html`<${Meldung} meldung=${{ art: 'fehler', text: fehler }} />`}
        ${!liste && !fehler && html`<p>Wird geladen …</p>`}
        ${liste && liste.length === 0 && html`<p>Es wurden noch keine Versionen gespeichert.</p>`}
        ${liste && liste.length > 0 && html`<table class="tabelle">
            <thead><tr><th>Gespeichert am</th><th class="zahl">Einteilungen</th><th></th></tr></thead>
            <tbody>${liste.map((e, i) => html`<tr key=${e.id}>
                <td>${new Date(e.zeitpunkt).toLocaleString('de-DE')}${i === 0 ? ' (aktuell)' : ''}</td>
                <td class="zahl">${e.einteilungen}</td>
                <td><button class="klein" onClick=${() => waehlen(e)}>In den Editor laden</button></td>
            </tr>`)}</tbody>
        </table>`}
    <//>`;
}

render(html`<${App} />`, document.getElementById('app'));
