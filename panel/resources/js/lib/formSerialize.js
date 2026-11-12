// lib/formSerialize.js — the single place that knows how a raw input value
// maps into the payload, instead of the renderer (Form.vue isDate branch) and
// the submitter (submitDynamicChanges) both guessing via class names.

/**
 * Normalize an input's DOM value into the wire format.
 * `data-fmt="date"` marks dd/MM/yyyy (+ optional HH:mm) fields.
 */
export function normalizeFormValue(el) {
  const value = String(el?.value ?? '');
  const fmt = el?.dataset?.fmt;
  if (fmt === 'date' && value) {
    const parts = value.split(' ');
    const dmy = parts[0].split('/');
    if (dmy.length === 3) {
      return dmy.reverse().join('-') + (parts[1] ? ' ' + parts[1] : '');
    }
  }
  return value;
}
