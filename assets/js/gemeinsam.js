// Gemeinsame Hilfsfunktionen für alle Seiten.
import { html, useEffect } from 'preact';

export { html, render, useState, useEffect, useMemo, useRef, useCallback, useReducer } from 'preact';

export const API_URL = new URL('../../api/', import.meta.url);

export const WOCHENTAGE = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

/**
 * Ruft die API auf. Wirft einen Error mit verständlicher Meldung; bei HTTP 409 hat der Error `konflikt = true`.
 * @param {string} route
 * @param {{daten?: object, parameter?: object, zeitlimit?: number}} [optionen]
 */
export async function api(route, optionen = {}) {
    const url = new URL(API_URL);
    url.searchParams.set('r', route);
    for (const [k, v] of Object.entries(optionen.parameter || {})) {
        url.searchParams.set(k, v);
    }
    const abbruch = new AbortController();
    const timer = setTimeout(() => abbruch.abort(), optionen.zeitlimit || 15000);
    let antwort;
    try {
        antwort = await fetch(url, {
            method: optionen.daten ? 'POST' : 'GET',
            headers: optionen.daten ? { 'Content-Type': 'application/json' } : {},
            body: optionen.daten ? JSON.stringify(optionen.daten) : undefined,
            cache: 'no-store',
            signal: abbruch.signal,
        });
    } catch (e) {
        throw new Error('Keine Verbindung zum Server.');
    } finally {
        clearTimeout(timer);
    }
    let inhalt = null;
    try {
        inhalt = await antwort.json();
    } catch (e) {
        throw new Error('Der Server hat eine ungültige Antwort geschickt (HTTP ' + antwort.status + ').');
    }
    if (!antwort.ok) {
        const fehler = new Error((inhalt && inhalt.fehler) || 'Fehler ' + antwort.status);
        fehler.konflikt = antwort.status === 409;
        throw fehler;
    }
    return inhalt;
}

/** "07:45" → 465 */
export function minuten(uhrzeit) {
    const [h, m] = uhrzeit.split(':').map(Number);
    return h * 60 + m;
}

/**
 * Ermittelt die Lage im Tagesablauf.
 * @returns {{art: 'wochenende'|'vorher'|'schicht'|'zwischen'|'nachher', wochentag: number, schicht?: object, naechste?: object}}
 */
export function tageslage(schichten, jetzt) {
    const tag = jetzt.getDay(); // 0 = Sonntag
    if (tag === 0 || tag === 6 || schichten.length === 0) {
        return { art: 'wochenende', wochentag: tag };
    }
    const t = jetzt.getHours() * 60 + jetzt.getMinutes() + jetzt.getSeconds() / 60;
    const sortiert = [...schichten].sort((a, b) => minuten(a.beginn) - minuten(b.beginn));
    const aktuell = sortiert.find((s) => minuten(s.beginn) <= t && t < minuten(s.ende));
    const naechste = sortiert.find((s) => minuten(s.beginn) > t);
    if (aktuell) {
        return { art: 'schicht', wochentag: tag, schicht: aktuell, naechste };
    }
    if (t < minuten(sortiert[0].beginn)) {
        return { art: 'vorher', wochentag: tag, naechste };
    }
    if (naechste) {
        return { art: 'zwischen', wochentag: tag, naechste };
    }
    return { art: 'nachher', wochentag: tag };
}

/** Personen-IDs einer Zelle aus einem Plan (Liste von {wochentag, schicht_id, person_ids}). */
export function zelle(plan, wochentag, schichtId) {
    const z = (plan || []).find((e) => e.wochentag === wochentag && e.schicht_id === schichtId);
    return z ? z.person_ids : [];
}

export function personName(p) {
    return p ? `${p.vorname} ${p.nachname}`.trim() : 'Unbekannt';
}

/** "2026-11-19" → "19.11.2026" */
export function datumDeutsch(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    return m ? `${m[3]}.${m[2]}.${m[1]}` : '–';
}

export function heuteIso() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

/** "Anna, Bernd oder Clara kontaktieren" */
export function verantwortlicheText(namen) {
    if (!namen || namen.length === 0) {
        return 'die verantwortliche Person kontaktieren';
    }
    if (namen.length === 1) {
        return namen[0] + ' kontaktieren';
    }
    return namen.slice(0, -1).join(', ') + ' oder ' + namen[namen.length - 1] + ' kontaktieren';
}

/** Kleine Meldungsleiste: {text, art: 'ok'|'fehler'|'hinweis'} */
export function Meldung({ meldung, schliessen }) {
    if (!meldung) {
        return null;
    }
    return html`<div class="meldung ${meldung.art || 'hinweis'}" role="status">
        <span>${meldung.text}</span>
        ${schliessen && html`<button class="schliessen" onClick=${schliessen} aria-label="Schließen">×</button>`}
    </div>`;
}

/** Modaler Dialog */
export function Dialog({ titel, schliessen, children, breit }) {
    useEffect(() => {
        const taste = (e) => e.key === 'Escape' && schliessen();
        window.addEventListener('keydown', taste);
        return () => window.removeEventListener('keydown', taste);
    }, [schliessen]);
    return html`<div class="overlay" onClick=${schliessen}>
        <div class="dialog ${breit ? 'breit' : ''}" role="dialog" aria-modal="true" onClick=${(e) => e.stopPropagation()}>
            <div class="dialog-kopf">
                <h2>${titel}</h2>
                <button class="schliessen" onClick=${schliessen} aria-label="Schließen">×</button>
            </div>
            <div class="dialog-inhalt">${children}</div>
        </div>
    </div>`;
}
