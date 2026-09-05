// lib/dateUtils.js — shared date formatting used by DForm, OForm
// DForm uses dayjs, OForm uses manual splitting. Unified here.

import dayjs from 'dayjs';

/**
 * Format a date value as DD.MM.YYYY — used by DForm file cards
 * Falls back to raw string slice if dayjs can't parse
 */
export function fmtDate(v) {
    if (!v) return '—';
    const d = dayjs(v);
    return d.isValid() ? d.format('DD.MM.YYYY') : String(v).slice(0, 10);
}

/**
 * Format a date value as DD.MM.YYYY HH:mm
 */
export function fmtDateTime(v) {
    if (!v) return '—';
    const d = dayjs(v);
    return d.isValid() ? d.format('DD.MM.YYYY HH:mm') : String(v);
}

/**
 * Format a date value for display — handles SAP BEDAT (d/m/Y), ISO (Y-m-d),
 * already-formatted (with dots), and dash-separated formats.
 * Used by OForm header/meta display.
 */
export function formatDate(val) {
    if (!val) return '-';
    if (val.includes('.')) return val;
    if (val.includes('-')) {
        const parts = val.split(' ')[0].split('-');
        if (parts.length === 3) return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    try { const d = new Date(val); if (!isNaN(d)) return d.toLocaleDateString('tr-TR'); } catch (e) {}
    return val;
}
