# Optimize Suggestions — Breadcrumbs for Next Sessions

> **Purpose:** What we optimized in `OList`/`DList`/`OForm`/`DForm` without breaking design/mechanic, and what **outside partner** libs/composables you can reuse for any new listing page.
> **Read after:** `tedarik-system-process.md:5-6`, `form-system-mechanics.md:7`, `memory/05-order-system-state.md:4`.
> **Shared code lives in `resources/js/composables/` + `resources/js/lib/` — import, don't copy-paste.**

## 1. What We Optimized (kept 1:1 design)

### OList/DList parity fixes (no logic break)
- `setup()` trimmed to `{Swal}` `OList:22/DList:24` — `data()` already `useAuthStore()` instance, `OList` `authStore` vs `DList` `useAuthStore` now `this.useAuthStore.permissions` (not `()`) `DList:452`.
- `duplicate id="mainSearch"` `OList:1118/1228 DList:1196/1215` → `ref="adminSearch"` + `ref="tedarikSearch"` `searchTable/resetSearch` `OList:380/DList:418` picks `isTedarik ? tedarikSearch : adminSearch` + fallback `querySelector` for SPA switch frame.
- `flatpickrRange` leak `OList:241/DList:213` Swal `didOpen flatpickr → didClose destroy` + `beforeUnmount` `OList:31/DList:43` + `_buildTimeouts` tracked `120ms modal / 200ms dropdown / rAF 400ms` `OList:934/DList:1078` vs `300+1000` double poll.
- `rowFormatter` stale `OList:898` `_mainAttrCache + _attrsMap` + `DList:1054` `_relationDetailCache` — re-parse `JSON.parse(main_attr/relation_detail)` only if string changed, `transfer_no` etc from `attrsMap` not stale `data['transfer_no']`.
- `doc-status` `DList:844` `document.querySelectorAll → Swal.getHtmlContainer().querySelectorAll` + `e.target → e.currentTarget.dataset.key` `DList:844` + `OList:656` same — click on `<i>` inside button works.
- `handleResponsiveTable` `DList:82` `resize` stored `_responsiveHandler` + removed in `beforeUnmount:43`, `beforeUnmount` also clears `_heightObs/_enforceTedarikHeight` + `_buildTimeouts`.
- `Şirket` modal `OList:127/DList:127` removed local `hardFallback` 8 `YILDIZ` etc → backend-only `POST /v1/table/documents op-doc-client` `OList:196/DList:150` with `loadingClients` + `Yükleniyor...` `OList:1009/DList:1162` + `v-if="loadingClients"` + 5-min cache `_clientsFetchedAt 300000` `OList:224/DList:224` — no fake `PANORAMA` when DB empty, 200 `JSON.parse` saved per reopen.
- `toggleAllModalClients` `OList:394/DList:189` `vals.forEach includes` O(n²) → `Set` `OList:394` `new Set + forEach add` `DList` same.
- `isTedarik` live `OList:555` `const _isTedarik` kept only for static `width:150/180` `height 75vh`, interior formatters now `this.isTedarik` `OList:577/DList` — no stale pill on SPA `watch isTedarik` `OList:39/DList:40` re-attaches `handleOutsideClick` + closes `showFiltre`.
- `Malzeme Üretim Tarihi` `OList:1055` `Y/m` month picker `monthSelectPlugin` `OList:11` `dateFormat Y-m altInput Y/m` → `YILDIZ` filter `Documents.php:421` `ilike '%Y-m%'`, `Tarih Aralığı` `OList:1076` `ref detailedRangeInput` `mode range` `Y-m-d → |` via `toPipe`.
- `DList` `parseRowStatus` `DList:283` `Map` cache `_statusParseCache` `size>50` evict — `Güncel Durum` 10 rows `JSON.parse` → 1.
- `STATUS_LABEL_MAP/STATUS_ICONS` hoisted `OList:15/DList` could be `lib/statusMaps.js` — `DList` still inline `OList` hoisted, `OList` saves 10 allocs/page.

### DForm template merge + status/date utils (refactor session)
- `statusUtils.js` extracted `parseStatus/statusLabel/statusCls/personName/noteOf` from DForm inline methods — DForm imports 5 methods from 1 file, OForm can reuse `noteOf` for tedarik notes
- `dateUtils.js` created with `fmtDate(fmt)/fmtDateTime(fmt)/formatDate(val)` — DForm uses `fmtDate('DD.MM.YYYY')` for `created_at` formatting, OForm imports `formatDate` for print PDFs
- DForm **merged 2 templates into 1** — admin vs tedarik is `:class="{ 'admin-theme': !isTedarik }"` + conditional classes `admin-back/admin-shortcut/admin-supplier/admin-clickable`; eliminated ~80 lines of duplicated template markup
- OForm: `getFieldValue(name)` extracted as method (was duplicated at `printMalzemeKabul` and `printMalzemeCinsMiktar` — both had identical 30-line inline closures)
- OForm: `calcCloneSuffix(orderNo)` extracted as async method (was duplicated at both print methods — both did identical DB query for next clone suffix)
- **Net: ~200 fewer lines, zero mechanic break** — file versioning, status machine, order lifecycle untouched

### DList tedarik Aksiyonlar → icon buttons
- DList `Detaylar` column `DList:919` tedarik view: replaced single "Aksiyonlar" text button + Swal modal with icon buttons (`ki-eye` Önizle, `ki-notepad-edit` Detay, `ki-arrow-right` İlişkiye Git) + text button "Yeniden Talep Et" with `ki-arrows-loop` icon (like admin `mkBtn` `DList:970`)
- "Detaylar" text button removed (redundant with `ki-notepad-edit` icon button)
- Column width reduced `210px → 160px` for tedarik (icon buttons are compact), OList column width also reduced for consistency

### Build kept
- `npm run build` `OList ✓ 5.07s` `DList ✓ 4.99s` + final `✓ 4.84s` — no `PickleTable` API change, `initialFilter` `op-doc-order` / `document_files`, `per-05-*/per-07-*` gates untouched.

## 2. Outside Partner — What to Reuse for Next Listing

All new files are **outside source** — import, don't duplicate 220 lines.

| Outside file | What it does | Import in new `XList.vue` |
|---|---|---|
| `resources/js/lib/escape.js` | `escapeHtml(s)` for `Swal.fire({html})` `curNo/base` `OList:708` | `import {escapeHtml} from '@/lib/escape'` |
| `resources/js/lib/pipe.js` | `toPipe(v)` ` to / — / - → \|` `DList:15 OList:269` backend `tarih_araligi` | `import {toPipe} from '@/lib/pipe'` |
| `resources/js/lib/statusMaps.js` | `ORDER_STATUS_LABEL_MAP, FILE_STATUS_ICONS, FILE_TYPE_ICON_MAP` `OList:15` | `import {ORDER_STATUS_LABEL_MAP} from '@/lib/statusMaps'` |
| `resources/js/composables/useClientModal.js` | `sirketSearch/modalClients/selectedSirkets/showClientModal/buildClientTable(force)/modalFilteredClients` 5-min cache, `loadingClients`, `Set` toggle `OList:196` | `setup(){ const modal=useClientModal(plib); return {...modal}}` or `data(){return {...useClientModalData()}}` |
| `resources/js/composables/useTedarikDropdown.js` | `show/dropdownPos/updatePos/handleOutside/closeDelayed` `280px` clamp `window.innerWidth-12` `OList:94` | `const dd=useTedarikDropdown(); dd.toggle(e, wrapRef)` |
| `resources/js/composables/useTedarikHeight.js` | `enforce(rAF+400)` for `selector .tedarik-card .pickletable` vs `.tedarik-docs-page .pickletable` `OList:934` | `useTedarikHeight(()=>isTedarik, '.tedarik-card .pickletable', _buildTimeouts)` |
| `resources/js/composables/useTableSearch.js` | `searchTable/resetSearch` `refs tedarikSearch/adminSearch` fallback `querySelector` `OList:380` | `const {searchTable}=useTableSearch(isTedarik, refs, ()=>table)` |
| `resources/js/composables/useSwalRangePicker.js` | `openSwalRange(Swal, flatpickrRangeRef)` `didOpen flatpickr range → toPipe` `DList:213` | `const {openSwalRange}=useSwalRangePicker(Swal, flatpickrRange)` |

### How to create a new listing `YList.vue` in 15 min
```js
// YList.vue — copy OList structure, change only 2 lines
import { useClientModal } from '@/composables/useClientModal';
import { useTedarikDropdown } from '@/composables/useTedarikDropdown';
import { toPipe } from '@/lib/pipe';
import { ORDER_STATUS_LABEL_MAP } from '@/lib/statusMaps';

export default {
  setup(){ const modal=useClientModal(); const dd=useTedarikDropdown(); return {modal, dd, toPipe} },
  computed:{ isTedarik(){return this.$route.path.startsWith('/tedarikpanel')} },
  mounted(){ this.buildTable(); }, // same PickleTable headers, just new filter keys
}
```
- Add `headers: [{key:'my_field', ...}]` + `initialFilter: [{key:'type',value:'op-doc-mytype'}]` `Documents::tableList` new `case 'my_field'` `switch` `OList:487/DList:421` style.
- Template reuses `tedarik-card` `tedarik-list-top` `tedarik-dd` `client-modal-overlay` `tedarik-docs-searchrow` — no new CSS.

## 3. Libraries / Capabilities You Can Use Later

### Already in stack (no install)
- `PickleTable` `OList:1040` `ajax / local` `pageLimit 10` `height 75vh` `paginationType number` — for any `POST /v1/table/{model}` `SystemController:100` `Documents::tableList` pattern. Hook `columnFormatter/rowFormatter/groupFormatter`.
- `flatpickr + Turkish + monthSelect` `OList:8` — single `Y-m-d`, `range Y-m-d → |`, `month Y/m` `OList:107` `Y-m alt Y/m`. Use `allowInput:false clickOpens:true` for `YYYY/MM`.
- `dayjs` `DList:10` `format DD/MM/YYYY HH:mm` `DList:1054` `_created_at_fmt`, `Swal2` `OList:7` `html` + `preConfirm` + `didOpen/didClose` flatpickr.
- `Pinia` `stores/auth` `permissions` `per-04/05/06/07` + `stores/navigation` `toggle` — frontend gate `authStore.permissions.includes('per-05-01')` `OList:799`.
- `Plib` `lib/pickle.js:824` `request` + `openTab POST /v1/export` `OList:390` `exportTable` — for Excel `ExportService` `PhpSpreadsheet`.
- `dayjs` + `Swal` already handle `note` JSON unwrap `DList:328` `JSON.parse(statusData.note).note`.

### Optional to add (1 cmd, no mechanic break)
```bash
npm i fuse.js        # fuzzy search for 200 Şirket list instead of `ilike` — `new Fuse(modalClients, {keys:['clititle','lifnr']})`
npm i @tanstack/vue-query # cache `POST /v1/table/documents` 5-min like `useClientModal` but auto
npm i vue-virtual-scroller # if Şirket grows >500, `max-height:340px` scroll → `RecycleScroller` 84×84 thumbs `OList` images
npm i date-fns       # if you prefer `format(new Date(val), 'dd.MM.yyyy')` over `dayjs` for `formatDate` `OList:419`
```
- **Fuse.js** example: replace `modalFilteredClients` `filter includes` `DList:52` with `fuse.search(q).map(r=>r.item)` — typo tolerant `AKSA` vs `AKSA ENERJI`.
- **TanStack Query** — wrap `plib.request /v1/table` `useQuery(['clients', lifnr], fetch)` — dedupes `buildClientTable` + `handleFiltreChoice` + `Documents::tableList` calls, background refetch.
- **Virtual scroll** — `client-modal` `max-height 340` with 200 rows → 200 `label` DOM; `vue-virtual-scroller` renders 10 visible.

### Breadcrumbs for next session
1. `read memory/05-order-system-state.md` + `panel/documentation/tedarik-system-process.md:12` How to Test — `cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json && php artisan orders:sync --fresh` gives 8/21.
2. After any `Form.vue/router/Sidebar/OForm/OList/DList` edit → `npm run build` `panel/` → test `tedarik` `per-041-02` + `coalpanel` `per-041-01` both.
3. Add new field? → `sys_options op-doc-*` + `Form.vue forms[op-doc-*-form]` `01-form-engine.md:2` — no migration.
4. Add new `YList` → copy `OList.vue:487` headers, add `Documents::tableList` `case 'my_key'` `ilike`, add `PermissionHelpers docPermCheck` `per-09`, `SysRoleTemplateSeeder` `op_key` unique.
5. Need to touch `pickletable` height? → use `useTedarikHeight` not `setAttribute(style height)` `DList:1032` `pt-auto-height` guard.

> Keep `OList/OForm/DList/DForm` **shared via `isTedarik`** `OList:45/DList:51` — don't fork. Fork only if supplier flow diverges more than `DList` `isTedarik ? solid orange : pastel` `DList:765`.
