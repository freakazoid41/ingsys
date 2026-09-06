# 05 — Order System State (snapshot)

> **Updated:** 2026-09-07
> **Read after:** `tedarik-system-process.md`
> **2026-09-07 — Bilgilendirmeler PickleTable: `/tedarikpanel/bilgilendirmeler` 60vh max, same card-rows as DList tedarik (0 7px gap, 13px 14px #fff/#e8e8ea), headers hidden, widths 185/265/135/175/160 (920px), sidebar `bilgilendirmeCount` + `isBilgilendirmelerActive` + bell `Tüm Gör`. Libs `lib/notificationMaps.js` + `lib/notificationHelpers.js` (`buildNotificationRows`) + `lib/dateUtils fmtDateTime`, height via manual 60vh enforce (was 75vh).**

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
- **Cleanup (2026-09-06):** removed dead `setup()` return, fixed duplicate `file_qnid` in `openFile`, extracted `statusIcon(f)` method (was 3x duplicated ternary), extracted `resetFrame()` method, fixed `'instant' in window` check, replaced `href="javascript:;"` with `@click.prevent`

### OForm.vue
- `getFieldValue(name)` extracted as method (was duplicated at `printMalzemeKabul` and `printMalzemeCinsMiktar`)
- `calcCloneSuffix(orderNo)` extracted as async method (was duplicated at both print methods)
- Imports `formatDate` from `dateUtils.js`

### DList.vue
- Tedarik "Aksiyonlar" column: replaced single text button + Swal modal with individual icon buttons (`ki-eye` Önizle, `ki-notepad-edit` Detay, `ki-arrow-right` İlişkiye Git)
- Retake button has text label "Yeniden Talep Et" alongside icon (like admin view)
- "Detaylar" text button removed (redundant with Detay icon button)
- Column width reduced `210px → 160px` for tedarik

### Bilgilendirmeler.vue (2026-09-07)
- `PickleTable local` 60vh max (same card-rows `0 7px` `13px 14px` as DList), headers hidden (`thead{display:none}`), dummy `document_card` for alignment
- Widths `185/265/135/175/160` (`920px` min), pills `102/138px`, `bili-*` CSS classes replace inline `style.cssText` (icon/status)
- Libs: `lib/notificationMaps.js` `CAT_META/CHIP_DEFS/getCatMeta` + `lib/notificationHelpers.js` `buildNotificationRows/parseOrder/parseFile/isFileCat` (replaces 7× mk) + `lib/dateUtils fmtDateTime`, manual 60vh enforce (was `useTedarikHeight` 75vh)
- Sidebar `TedarikPanel:244` `router-link` + `bilgilendirmeCount` (`unreadTotal`) + `isBilgilendirmelerActive`, bell adds `Tüm Gör → Bilgilendirmeler`
- Route `router/index.js:62` `/tedarikpanel/bilgilendirmeler` `TedarikBilgilendirmeler`
