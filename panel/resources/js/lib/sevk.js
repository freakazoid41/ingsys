// lib/sevk.js — shared Sevk Onay helpers (single source, was triplicated).
// A file belongs to the Sevk flow when its tag/type carries the sevk_docs
// group or a proforma/irsaliye slot — matches backend registerContent tags:
// proforma_file**sevk_docs**row / irsaliye_file**sevk_docs**row.
export function isSevkFile(f) {
  const hay = `${f?.entity_tag || ''} ${f?.file_type_key || ''} ${f?.file_type || ''}`.toLowerCase();
  return hay.includes('sevk') || hay.includes('proforma') || hay.includes('irsaliye');
}

export const SEVK_ORDER_STATUSES = [
  'doc_trans_order_sevk_started',
  'doc_trans_order_sevk_kalite_ok',
  'doc_trans_order_sevk_addressed',
  'doc_trans_order_sevk_dated',
  'doc_trans_order_sevk_proforma',
  'doc_trans_order_sevk_shipped',
];

export const isSevkStatus = (op) => SEVK_ORDER_STATUSES.includes(op || '');
