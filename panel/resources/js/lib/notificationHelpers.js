// lib/notificationHelpers.js — pure, testable, no Vue — transforms delivery payloads → PickleTable rows
// Keeps Bilgilendirmeler.vue thin and reuses dateUtils + notificationMaps

import { fmtDateTime } from '@/lib/dateUtils';
import { getCatMeta } from '@/lib/notificationMaps';

function parseOrder(o) {
  let orderNo = o.order_no || o.group_key || '';
  let ctitle = '';
  try {
    const arr = JSON.parse(o.main_attr || '[]');
    arr.forEach(d => {
      if (d.Key === 'order_no' && !orderNo) orderNo = d.Value;
      if (d.Key === 'ctitle' && !ctitle) ctitle = d.Value;
    });
  } catch {}
  return { orderNo: orderNo || o.qnid || '-', ctitle, qnid: o.qnid || o.id || o.relation_qnid || '' };
}

function parseFile(f) {
  return {
    orderNo: f.group_key || '-',
    ctitle: f.ctitle || '',
    qnid: f.qnid || f.id || f.relation_qnid || '',
  };
}

function uid(cat, qnid) {
  try { return `${cat}-${qnid}-${crypto.randomUUID().slice(0, 6)}`; }
  catch { return `${cat}-${qnid}-${Math.random().toString(36).slice(2, 8)}`; }
}

function makeRow(cat, orderNo, ctitle, qnid, created_at) {
  const meta = getCatMeta(cat);
  return {
    id: uid(cat, qnid),
    qnid, cat, catLabel: meta.label,
    file_type: meta.label,
    group_key: orderNo,
    ctitle,
    created_at: created_at || '',
    _created_at_fmt: fmtDateTime(created_at),
    last_status: JSON.stringify({ op_key: cat, title: meta.label }),
  };
}

/**
 * Single entry for all 7 TEDARIK feeds.
 * @param {object} notifications - response from GET /api/v1/notifications
 * @param {Array} rejectedFiles - authStore.currentStatus.rejectedFiles
 * @returns {Array} sorted newest-first
 */
export function buildNotificationRows(notifications, rejectedFiles = []) {
  const n = notifications || {};
  const rows = [];

  const addOrders = (arr, cat) => {
    if (!Array.isArray(arr)) return;
    arr.forEach(o => {
      const m = parseOrder(o);
      rows.push(makeRow(cat, m.orderNo, m.ctitle, m.qnid, o.created_at));
    });
  };
  const addFiles = (arr, cat) => {
    if (!Array.isArray(arr)) return;
    arr.forEach(f => {
      const m = parseFile(f);
      rows.push(makeRow(cat, m.orderNo, m.ctitle, m.qnid, f.created_at));
    });
  };

  addOrders(n.orderImported, 'tedarik-01');
  addOrders(n.orderSent, 'tedarik-02');
  addFiles(n.pendingFiles, 'tedarik-03');
  addFiles(n.fileApproved, 'tedarik-04');
  addFiles(n.fileRejected, 'tedarik-05');
  addOrders(n.orderApproved, 'tedarik-06');
  addOrders(n.orderRejected, 'tedarik-07');

  (rejectedFiles || []).forEach(fl => {
    rows.push(makeRow('rejected', fl.cli_id || '', '', fl.cli_id || '', fl.created_at || ''));
  });

  rows.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
  return rows;
}

// file-type helpers for row actions
export const FILE_CATS = ['tedarik-03', 'tedarik-04', 'tedarik-05'];
export function isFileCat(cat) { return FILE_CATS.includes(cat); }
