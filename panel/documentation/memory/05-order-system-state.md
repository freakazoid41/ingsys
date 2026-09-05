# 05 — Order System State (snapshot)

> **Updated:** 2026-09-06
> **Read after:** `tedarik-system-process.md`

## Current Live Data

- **8 orders** (8 EBELN → 21 rows)
- **21 items** across 8 orders
- **8 clients**
- **7 files**
- **0 serials**
- **37 transitions** (`grp_code=GDZ`)
- Source: `/tmp/sap_fresh_payload.json`

## Status Machine (order-level)

```
doc_trans_order_created
  → doc_trans_order_transfer_sent (DList only: per-07-02)
  → doc_trans_order_approved
  → doc_trans_order_rejected
  → doc_trans_order_ready_for_shipment
```

## File-level Status Machine

```
doc_file_waiting (default)
  → doc_file_accepted (per-07-02)
  → doc_file_rejected (per-07-02)
  → doc_file_refreshed (re-upload cycle)
```

## Key Permissions

| Permission | Gate |
|---|---|
| `per-04-01` | Coal panel list view |
| `per-05-01` | Order list view |
| `per-05-03` | Kalite onayı (order approve) |
| `per-05-04` | Cancel / reject |
| `per-05-05` | Rename partitioned order |
| `per-07-02` | File status change (accept/reject) |

## Refactoring State (2026-09-06)

### Shared utilities created
- `resources/js/lib/statusUtils.js` — `parseStatus`, `statusLabel`, `statusCls`, `personName`, `noteOf`
- `resources/js/lib/dateUtils.js` — `fmtDate`, `fmtDateTime`, `formatDate`

### DForm.vue
- Merged 2 templates into 1 (admin + tedarik) via `:class="{ 'admin-theme': !isTedarik }"`
- Imports from `statusUtils.js` and `dateUtils.js`
- Removed ~80 lines of duplicated template markup

### OForm.vue
- `getFieldValue(name)` extracted as method (was duplicated at `printMalzemeKabul` and `printMalzemeCinsMiktar`)
- `calcCloneSuffix(orderNo)` extracted as async method (was duplicated at both print methods)
- Imports `formatDate` from `dateUtils.js`

### DList.vue
- Tedarik "Aksiyonlar" column: replaced single text button + Swal modal with individual icon buttons (`ki-eye` Önizle, `ki-notepad-edit` Detay, `ki-arrow-right` İlişkiye Git)
- Retake button has text label "Yeniden Talep Et" alongside icon (like admin view)
- "Detaylar" text button removed (redundant with Detay icon button)
- Column width reduced `210px → 160px` for tedarik
