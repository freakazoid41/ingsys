// lib/escape.js — shared XSS helper for Swal innerHTML and fallback lists
// No design change, no mechanic change — use: escapeHtml(curNo) inside `Swal.fire({html: `<b>${escapeHtml(curNo)}</b>`})`
const ESCAPE_RE = /[&<>"']/g;
const ESCAPE_MAP = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' };
export const escapeHtml = (s) => String(s ?? '').replace(ESCAPE_RE, c => ESCAPE_MAP[c]);
export default escapeHtml;
