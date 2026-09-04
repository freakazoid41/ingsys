// lib/statusMaps.js — single source for order/file status labels & icons
// OList/DList both duplicated labelMap + statusIcons per row (10× alloc). Import here: `import { ORDER_STATUS_LABEL_MAP, FILE_STATUS_ICONS } from '@/lib/statusMaps'`
export const ORDER_STATUS_LABEL_MAP = {
  'doc_trans_order_transfer_sent': 'Dosyalar Kontrol Ediliyor',
  'doc_trans_order_approved': 'Kalite Onayı Verildi',
  'doc_trans_order_created': 'Beklemede',
  'doc_trans_order_ready_for_shipment': 'Sipariş Sevke Hazır',
  'doc_trans_order_rejected': 'Sipariş Reddedildi',
  'doc_trans_order_files_rejected': 'Reddedilen Dosyalar Mevcut',
};
export const FILE_STATUS_ICONS = {
  doc_trans_order_created:'ki-outline ki-file-added',
  doc_trans_order_transfer_sent:'ki-outline ki-magnifier',
  doc_trans_order_ready_for_shipment:'ki-outline ki-truck',
  doc_trans_order_approved:'ki-outline ki-check-circle',
  doc_trans_order_rejected:'ki-outline ki-cross-circle',
  doc_trans_order_files_rejected:'ki-outline ki-file-danger',
  doc_file_waiting:'ki-outline ki-hourglass',
  doc_file_accepted:'ki-outline ki-check-circle',
  doc_file_rejected:'ki-outline ki-cross-circle',
  doc_file_refreshed:'ki-outline ki-arrows-loop',
};
export const FILE_TYPE_ICON_MAP = {
  test: { icon:'ki-flask', bg:'#fef3ff', col:'#a21caf', bd:'#f5d0fe' },
  cins: { icon:'ki-chart-simple', bg:'#fff7ed', col:'#c2410c', bd:'#fed7aa' },
  kabul:{ icon:'ki-clipboard', bg:'#ecfdf5', col:'#047857', bd:'#a7f3d0' },
  default:{ icon:'ki-document', bg:'#f1f5f9', col:'#475569', bd:'#e2e8f0' },
};
