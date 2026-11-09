// lib/talepRows.js — shared item-row helpers for planlama forms (RForm + TForm).
// Rows live inside Form.vue multiple groups; these helpers scrape/fill them by
// entity prefix (prod_code**group**rowId). No dependency on panel state.

export function expandMonths(bas, bit) {
  const p = s => {
    const m = String(s || '').trim().match(/^(\d{1,2})\/(\d{4})$/);
    if (!m) return null;
    return { m: parseInt(m[1], 10), y: parseInt(m[2], 10) };
  };
  const b = p(bas), e = p(bit);
  if (!b || !e || b.m < 1 || b.m > 12 || e.m < 1 || e.m > 12) return [];
  const out = [];
  let m = b.m, y = b.y, guard = 0;
  while ((y < e.y || (y === e.y && m <= e.m)) && guard++ < 36) {
    out.push(String(m).padStart(2, '0') + '/' + y);
    if (++m > 12) { m = 1; y++; }
  }
  // flag truncation instead of silent cut — callers can toast when 36 reached but period not exhausted
  if ((y < e.y || (y === e.y && m <= e.m)) && guard >= 36) out.truncated = true;
  return out;
}

export function itemRowsIn(areaEl) {
  if (!areaEl) return [];
  return Array.from(areaEl.querySelectorAll('.multiple-item-row'));
}

function valOf(row, prefix) {
  const inp = row.querySelector(`[name^="${prefix}**"]`);
  return inp ? String(inp.value || '').trim() : '';
}

export function rowKeyOf(row) {
  const p = row.querySelector('[name^="prod_code**"]');
  const a = row.querySelector('[name^="ay**"]');
  const pv = p ? String(p.value || '').trim() : '';
  if (!pv) return null;
  return pv + '|' + (a ? String(a.value || '').trim() : '');
}

export function findMatchingRow(rows, item) {
  return rows.find(r => {
    const p = r.querySelector('[name^="prod_code**"]');
    if (!p || p.value.trim() !== String(item.prod_code || '').trim()) return false;
    const a = r.querySelector('[name^="ay**"]');
    const av = a ? a.value.trim() : '';
    return !av || !item.ay || av === item.ay;
  });
}

export function findEmptyRow(rows) {
  return rows.find(r => {
    const p = r.querySelector('[name^="prod_code**"]');
    const t = r.querySelector('[name^="title**"]');
    return p && t && !p.value && !t.value;
  });
}

export function fillItemRow(row, item, zeroEmpty = false) {
  const set = (prefix, val) => {
    if (val === undefined || val === null || val === '') {
      if (!(zeroEmpty && prefix === 'quantity')) return;
      val = '0';
    }
    const inp = row.querySelector(`[name^="${prefix}**"]`);
    if (!inp) return;
    inp.value = String(val);
    inp.dispatchEvent(new Event('input', { bubbles: true }));
  };
  set('mal_grubu', item.mal_grubu);
  set('ihale_grubu', item.ihale_grubu);
  set('prod_code', item.prod_code);
  set('title', item.title);
  set('ay', item.ay);
  set('quantity', item.quantity);
  if (item.unit) {
    const sel = row.querySelector('select[name^="unit**"]');
    if (sel) {
      sel.value = item.unit;
      sel.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }
}
