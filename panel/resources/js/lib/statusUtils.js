// lib/statusUtils.js — shared file status helpers used by DForm, OForm, LList
// Extracted to avoid duplication across pages

/**
 * Parse last_status from a file row — handles both object and JSON string
 */
export function parseStatus(f) {
    const s = f?.last_status;
    if (!s) return {};
    if (typeof s === 'string') { try { return JSON.parse(s); } catch (e) { return {}; } }
    return s;
}

/**
 * Human-readable label for a file's status
 */
export function statusLabel(f) {
    const s = parseStatus(f);
    if (s.title) return s.title;
    const k = s.op_key;
    if (k === 'doc_file_accepted') return 'Başarılı';
    if (k === 'doc_file_rejected') return 'Başarısız';
    if (k === 'doc_file_waiting') return 'Kontrol Bekliyor';
    return 'Bekleniyor';
}

/**
 * CSS class key for a file's status (is-success, is-fail, is-refresh, is-waiting)
 */
export function statusCls(f) {
    const k = parseStatus(f)?.op_key;
    if (k === 'doc_file_accepted') return 'is-success';
    if (k === 'doc_file_rejected') return 'is-fail';
    if (k === 'doc_file_refreshed') return 'is-refresh';
    return 'is-waiting';
}

/**
 * Actor name from file's last_status
 */
export function personName(f) {
    const s = parseStatus(f);
    return s.name || '—';
}

/**
 * Extract the inner note from file status JSON.
 * t.description is JSON: {"actor":".. <email>","note":"real reason"|null}
 * We unwrap the inner "note" field, never show raw JSON when note is null.
 */
export function noteOf(f) {
    const s = parseStatus(f);
    let n = s.note || '';
    try {
        const parsed = JSON.parse(n);
        if (parsed && typeof parsed.note === 'string') n = parsed.note;
        else if (parsed && parsed.note == null) n = '';
        else if (parsed && parsed.note != null) n = String(parsed.note);
    } catch (e) {}
    return (n || '').trim();
}
