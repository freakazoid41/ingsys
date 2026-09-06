// lib/notificationMaps.js — TEDARIK 7 + legacy rejected — single source for Bilgilendirmeler, TedarikHeader, Dashboard
// Mirrors statusMaps.js pattern but for notification op_keys. Import: `import { CAT_META, getCatMeta, CAT_LIST } from '@/lib/notificationMaps'`

export const CAT_META = {
  'tedarik-01': { label: 'Sipariş Sisteme Geldi', icon: 'ki-outline ki-add-files', color: '#7c2d12', bg: '#fff7ed', pillBg: '#FF5A1F', pillColor: '#fff', pillBorder: '#FF5A1F' },
  'tedarik-02': { label: 'Onaya Gönderildi', icon: 'ki-outline ki-send', color: '#92400e', bg: '#fef3c7', pillBg: '#FF5A1F', pillColor: '#fff', pillBorder: '#FF5A1F' },
  'tedarik-03': { label: 'İnceleme Bekliyor', icon: 'ki-outline ki-file', color: '#9a3412', bg: '#ffedd5', pillBg: '#f59e0b', pillColor: '#fff', pillBorder: '#f59e0b' },
  'tedarik-04': { label: 'Dosya Onaylandı', icon: 'ki-outline ki-check-circle', color: '#065f46', bg: '#dcfce7', pillBg: '#22c55e', pillColor: '#fff', pillBorder: '#22c55e' },
  'tedarik-05': { label: 'Yeniden Talep', icon: 'ki-outline ki-cross-circle', color: '#991b1b', bg: '#fee2e2', pillBg: '#ef4444', pillColor: '#fff', pillBorder: '#ef4444' },
  'tedarik-06': { label: 'Kalite Onayı', icon: 'ki-outline ki-shield-tick', color: '#064e3b', bg: '#d1fae5', pillBg: '#10b981', pillColor: '#fff', pillBorder: '#10b981' },
  'tedarik-07': { label: 'Sipariş Reddedildi', icon: 'ki-outline ki-cross', color: '#7f1d1d', bg: '#fee2e2', pillBg: '#ef4444', pillColor: '#fff', pillBorder: '#ef4444' },
  'rejected': { label: 'Reddedilen Dosya', icon: 'ki-outline ki-information', color: '#991b1b', bg: '#fee2e2', pillBg: '#ef4444', pillColor: '#fff', pillBorder: '#ef4444' },
};

export const CAT_LIST = Object.keys(CAT_META);

export function getCatMeta(cat) {
  return CAT_META[cat] || CAT_META['tedarik-01'];
}

// Chip definitions for filter bar — keeps Bilgilendirmeler.vue thin
export const CHIP_DEFS = [
  { key: 'all', label: 'Tümü', icon: 'ki-outline ki-element-11' },
  { key: 'tedarik-01', label: 'SAP Geldi', icon: CAT_META['tedarik-01'].icon },
  { key: 'tedarik-02', label: 'Onaya Gitti', icon: CAT_META['tedarik-02'].icon },
  { key: 'tedarik-03', label: 'Bekleyen', icon: CAT_META['tedarik-03'].icon },
  { key: 'tedarik-04', label: 'Onaylandı', icon: CAT_META['tedarik-04'].icon },
  { key: 'tedarik-05', label: 'Yeniden Talep', icon: CAT_META['tedarik-05'].icon },
  { key: 'tedarik-06', label: 'Kalite', icon: CAT_META['tedarik-06'].icon },
  { key: 'tedarik-07', label: 'Red', icon: CAT_META['tedarik-07'].icon },
];
