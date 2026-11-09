// lib/planlamaStatus.js — single source for Talep Dökümanı status text/color.
// Backend keys: tr-request-man-awaiting|man-approved|man-rejected, tr-request-std-approved
export const PLANLAMA_STATUS_LABEL = {
  'tr-request-man-awaiting': 'Talep Listesi Müdürlük Onayına Gönderildi',
  'tr-request-man-approved': 'Talep Listesi Müdürlük Tarafından Onaylandı',
  'tr-request-man-rejected': 'Talep Listesi Müdürlük Tarafından Reddedildi',
  'tr-request-std-approved': 'Talep Listesi Standardizasyon Tarafından Onaylandı',
};

export const PLANLAMA_STATUS_COLOR = {
  'tr-request-man-awaiting': '#FF5A1F',
  'tr-request-man-approved': '#0e7490',
  'tr-request-man-rejected': '#dc2626',
  'tr-request-std-approved': '#16a34a',
};

export const PLANLAMA_STATUS_TITLE = {
  'tr-request-man-approved': 'Talep Listesi Müdürlük Tarafından Onaylandı',
  'tr-request-man-rejected': 'Talep Listesi Müdürlük Tarafından Reddedildi',
  'tr-request-std-approved': 'Talep Listesi Standardizasyon Tarafından Onaylandı',
};

export function planlamaLabel(op) { return PLANLAMA_STATUS_LABEL[op] || op || '-'; }
export function planlamaColor(op) { return PLANLAMA_STATUS_COLOR[op] || '#64748b'; }
