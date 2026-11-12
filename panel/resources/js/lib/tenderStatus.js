// lib/tenderStatus.js — single source for tender/offer status pills, labels,
// colors and icons (IhList, IhOfferList, IhOffer, dashboards).
export const TENDER_STATUS = {
  'tender-created':   { label: 'İhale Oluşturuldu', color: '#FF5A1F', icon: 'ki-outline ki-time' },
  'tender-activated': { label: 'İhale Aktif',       color: '#22c55e', icon: 'ki-outline ki-check-circle' },
  'tender-complated': { label: 'İhale Tamamlandı',  color: '#0e8ea4', icon: 'ki-outline ki-flag' },
};

export const OFFER_STATUS = {
  'offer-sended':   { label: 'Teklif Gönderildi',   color: '#FF5A1F', icon: 'ki-outline ki-send' },
  'offer-accepted': { label: 'Teklif Kabul Edildi', color: '#22c55e', icon: 'ki-outline ki-check-circle' },
  'offer-rejected': { label: 'Teklif Reddedildi',   color: '#ef4444', icon: 'ki-outline ki-cross-circle' },
};

/** Split a `op**label**note` status string. */
export function parseStatus(raw) {
  const parts = String(raw || '').split('**');
  return { opKey: parts[0] || '', title: parts[1] || '', note: parts[2] || '' };
}

export function tenderMeta(opKey) {
  return TENDER_STATUS[opKey] || TENDER_STATUS['tender-created'];
}

export function offerMeta(opKey) {
  return OFFER_STATUS[opKey] || OFFER_STATUS['offer-sended'];
}

export function tenderLabel(opKey) {
  return (TENDER_STATUS[opKey] || TENDER_STATUS['tender-created']).label;
}

export function offerLabel(opKey) {
  return (OFFER_STATUS[opKey] || OFFER_STATUS['offer-sended']).label;
}
