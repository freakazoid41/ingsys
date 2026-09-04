// lib/pipe.js — shared flatpickr range " to / — / - " → pipe converter
// Used by OList/DList: handleFiltreChoice, initDetailedPickers, DList range
// Backend expects "YYYY-MM-DD|YYYY-MM-DD" for Documents::tableList tarih_araligi and Document_files tarih_araligi
export const toPipe = (v) => {
  const s = String(v || '').trim();
  if (!s) return '';
  if (s.includes(' to ')) { const [a,b] = s.split(' to ').map(x=>x.trim()); return (a||'')+'|'+(b||''); }
  if (s.includes(' — ')) { const [a,b] = s.split(' — ').map(x=>x.trim()); return (a||'')+'|'+(b||''); }
  if (s.includes(' - ')) { const [a,b] = s.split(' - ').map(x=>x.trim()); return (a||'')+'|'+(b||''); }
  return s+'|'+s;
};
export default toPipe;
