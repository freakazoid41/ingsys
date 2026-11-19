# Malzeme Tedarik — Test Supplement (for later sessions)

> Companion to `tedarik-system-process.md` (mechanics) + `memory/05-order-system-state.md` (live snapshot).
> This file is **how to test**, not how it works. Keep mechanics in the bible, keep steps here.

## 1. Env

```bash
cd panel
# DB tedarikNewApp @ 127.0.0.1:5431, .env IS_TEST=true (111111 SMS, recaptcha bypass)
docker start tedarikNewApp || true
php artisan migrate:status # 27 done
npm run build # after any Vue change
php artisan serve --host=127.0.0.1 --port=8000
# base: http://localhost:8000/tedarik
```

## 2. Test users (IS_TEST=true, SMS 111111, pw Kadir412.)

| User | Email | Perms | Use |
|---|---|---|---|
| Admin | `kadir@kontent.com.tr` | `per-00` all | SAP view, file approve `per-07-02`, Kalite `per-07-02`, cancel `per-05-04` |
| Supplier YILDIZ | `kbbozat41@hotmail.com` | `05 + 05-01/02` loader pattern | Tedarik send via `PUT /v1/document` edit permission — bound lifnrs via `clientQnidList` |
| Approver | reseller bound to order lifnr, e.g. `0000300184` | `per-05-01/02` | Find via `persons` → `clientQnidList` → `lifnr`. Admin sees all |

## 2b. How many users + who needs what (minimum 2, recommended 4)

- **Supplier (reseller)** — sends via `PUT /v1/document` (`per-05-02` edit/upload); `per-041-02` (Tedarik module) + `per-05-01` (read orders) + `per-06` (firma) + bound `cliid → lifnr` via `clientQnidList`. No `07-02`, no `05-04/05`. Cannot approve own order (backend `per-07-02` gate).
- **Admin (keyuser)** — reviews: `per-041-01` + `per-05-01` + `per-07` + `per-07-01` (file list) + `per-07-02` (file accept/reject + Kalite `approved/rejected`) + `per-05-04` (cancel `orders/cancel` + `DELETE`) + `per-05-05` (rename). Plus notif groups `tedarik-02/03/04`.
- **+2nd supplier** (different `lifnr`) — proves LIFNR+SYSTEM isolation, no cross-see.
- **+2nd admin** (`ADM` vs `GDZ`, `grp_code BOTH` for kadir covers both) — proves BUKRS gate `4000→GDZ / 5000→ADM` for `tedarik-01..07`.

Router gates: `/tedarikpanel/orders` needs `05-01`, `/documents` needs `per-07` + `07-01`.

Find supplier lifnr:
```sql
SELECT se.entity_value lifnr FROM sys_con_entities se
JOIN sys_con_ops so ON so.id=se.conn_id
JOIN documents d ON d.id=so.main_id
WHERE d.qnid IN (SELECT ... clientQnidList) AND se.entity_tag='lifnr';
```

## 3. Routes

- `/tedarik` login (orange) → 2FA `code_1..6` → `/module-select` or direct
- `/tedarikpanel/orders` list (reseller LIFNR+SYSTEM scoped), `/tedarikpanel/orders/form/:id` 6-step detail
- `/tedarikpanel/documents`, `/tedarikpanel/documents/:id` file detail
- `/coalpanel/orders`, `/coalpanel/documents` admin same components via `isTedarik`
- `/planlamapanel/*` is planlama, not here

Guards: `orders` needs `per-05-01`, `documents` needs `per-07-01`, Kalite button needs `per-07-02`, cancel `per-05-04`, rename `per-05-05`.

## 4. SAP seed

```bash
php artisan orders:reset # full wipe serials/files/reads/cache + sync --fresh (preferred)
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --dry-run
php artisan orders:sync --json=/tmp/sap_fresh_payload.json
# payload row: {BUKRS:4000→GDZ/5000→ADM, LIFNR→spec_code, EBELN→order_no, EBELP→MATNR**EBELP, MCOD1→ctitle, TXZ01→title, MENGE→qty, MEINS→ST/KG/M, BEDAT d/m/Y→created_at, SUBMI→buying_no}
# idempotent: skip if order_no exists. clients kept.
```

## 5. Happy path (manual, 5 min)

1. Supplier login → `/tedarikpanel/orders` sees own EBELN, e.g. `3510004200`, drum `Beklemede`.
2. Open order → Step1 transfer type `at_once` (or `partial` + select items + split amounts, KG/M serial sums must match).
3. Step2/3 fill `order_desc` + `imalatci_firma_adi` (required, detached `v-model` — must prefer `tedarikImalatci` over `getFieldValue`, see gotcha).
4. Step4 print Kabul/Cins PDFs → sign → scan.
5. Step5 upload `transfer_kabul_file` + `transfer_cins_file` (single, `hideAdd`) + per-item `item_test_file` + `images`. Temp via `POST /v1/temp-upload` → reference.
6. `Gönder` → `PUT /v1/document/:qnid` with `transfer_mode + selected_items/item_serials` → `processOrderTransfer` → `transfer_sent`. Toast success → list `Dosyalar Kontrol Ediliyor`. **Server-enforced (2026-11-12):** `processOrderTransfer` rejects the transfer (`success:false`) when `transfer_kabul_file` or `transfer_cins_file` entities are missing — the UI Swal ("Eksik Dosya") is now backed by the API, a raw PUT cannot push an order to `transfer_sent` with zero files.
7. Admin `/coalpanel/documents` sees 4 rows `group_key=EBELN`. `DForm` eye preview → `Onayla` (`doc_file_accepted per-07-02`) per file.
8. `syncOrderStatusFromFiles` auto: any `rejected` → `files_rejected`, all `accepted` → `ready_for_shipment`.
9. Kalite: `POST /v1/trans/set-status approved` (`per-07-02` only, backend enforced) → `acceptAllOrderFiles` closes.
10. Partial: `3510004300` select item `Böl 500` → `Gönder` → clone `4300-1`, base qty `1000→500`. Files move to clone (by design, base empties). Delete clone `Parçayı Sil` → qty back `500→1000`.

## 6. Rejection loop

1. Admin `DForm Reddet` + note → `doc_file_rejected` + order `files_rejected` + note `Dosya reddedildi: <note>`.
2. Supplier sees red chip, `Gönder` still visible (`canPrintKabul = files_rejected`).
3. Re-upload same slot → `doc_file_refreshed`, order back `transfer_sent`.
4. Check `LList` side-panel shows `from → to + note`.

## 7. API quick (browser fetch, auth cookie)

```js
// list
const fd=new FormData(); fd.append('tableReq', JSON.stringify({filter:[{key:'form-type',type:'=',value:'op-doc-order-form'},{key:'type',type:'=',value:'op-doc-order'}],scale:{page:1,limit:5}}));
await (await fetch('/api/v1/table/documents',{method:'POST',body:fd})).json();
// send at_once
const env=new FormData(); env.append('data', JSON.stringify({typeKey:'op-doc-order', transfer_mode:'at_once'})); // + files dynamicFile**
await (await fetch('/api/v1/document/<qnid>',{method:'PUT',body:env})).json();
// approve (admin only)
const s=new FormData(); s.append('id','<qnid>'); s.append('op_key','doc_trans_order_approved');
await (await fetch('/api/v1/trans/set-status',{method:'POST',body:s})).json();
// file status
const f=new FormData(); f.append('id','<file_qnid>'); f.append('op_key','doc_file_accepted');
await (await fetch('/api/v1/trans/set-file-status',{method:'POST',body:f})).json();
```

## 8. Browser E2E (reds-browser skill)

```bash
cd panel
npm i -D playwright && npx playwright install chromium
node tests/e2e/run.js # headless, logs + /tmp/step*.png, keeps E2E Browser data
node tests/e2e/run-headed.js # headed slowMo, watch live, leaves browser 15s
npx playwright test --config=tests/e2e/playwright.config.js --headed
```
Skill: `~/.config/opencode/skills/reds-browser/SKILL.md` — trigger `browser test / visible`.
Login: `email/pass` → `#submit-button` → `code_1..6 = 1` → module-select → planlamapanel/tedarikpanel.
Flatpickr inputs are `readonly` — set via `evaluate removeAttribute + input/change`, not `fill`.
Detached `tedarikImalatci/desc` — set `v-model` var, not DOM. File slot needs `input+change`.

## 9. DB checks

```sql
-- last status
SELECT so.op_key FROM transactions t JOIN sys_options so ON so.id=t.type_id
WHERE t.target_id=(SELECT id FROM documents WHERE qnid='<qnid>') AND so.group_key='op-trans-op-doc-order' ORDER BY t.id DESC LIMIT 1;
-- files + last file op
SELECT df.qnid, se.entity_tag, (SELECT so.op_key FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=df.id AND t.op_id=1 ORDER BY t.id DESC LIMIT 1) last_op
FROM document_files df JOIN sys_con_entities se ON se.entity_value=df.id::text AND se.table_tag='document_files'
JOIN documents d ON d.id=df.relation_id::int WHERE d.qnid='<order_qnid>';
-- clone link
SELECT order_no FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id WHERE so.main_id=(SELECT id FROM documents WHERE qnid='<clone>') AND entity_tag='order_no';
```

## 10. Gotchas (test blockers)

- `transfer_mode` locked after first send. `at_once` forbidden after partial (`hasActivePartitions`). Delete clones (`status=0`) to unlock.
- Clone owns files by design — base shows 0 after partial, second partial needs fresh upload.
- **Partial inventory rule (2026-11-19):** selected items must be direct children of the order and `op-doc-order-item`; duplicate qnids are rejected; the cumulative stock cap applies to **ST only**. **KG/M splits may exceed the recorded quantity and the original remainder intentionally goes negative** — real deliveries over-ship slightly because of unit conversion/rounding, and the negative is how the system shows it. ST never goes below 0. Direct item edits may carry a negative quantity only for KG/M; serial quantities are never negative.
- **Partial file move is intentional (2026-11-19):** after the clone is created, all order-level files move to the clone (`moveOrderFilesToDocument`) and each selected item's files move to the cloned item (form-key filtered). It relinks `document_files.relation_id` and rewrites the entity tag's conn id — the base intentionally shows 0 files. Do not turn this into a copy; the admin must review exactly the shipment's files.
- `imalatci` print trap: `tedarikImalatci.trim() || getFieldValue || entities` — DOM query alone is empty.
- `ST>=300` no serial UI, `ST<300` auto N rows qty1, KG/M sum must match split.
- `PUT multipart` via `parsePut`, `entity_tag ** count`: single `**` = version, `**img-` multi = append.
- Hard refresh after `vite build` — stale `app-*.js` is #1 false failure.
- `orders:sync --fresh` alone leaves files/serials — always use `orders:reset`.
- Export PDFs are print-only, `items` from request — verify DB qty separately.
- **Locked Step-5 file blocks render once (2026-11-19):** when `isFilesLocked` and a `transfer_kabul_file` / `transfer_cins_file` exists without a file-status transaction, the blue locked row is the only thing that may render. The `v-else` chain must hang off the locked `<template>` wrapper, not the status row — otherwise the green “Beklemede” upload block renders underneath and the same file appears twice.
- **File status must left-join the actor (2026-11-19):** `getFormData` (`DocumentServiceProvider`) and `Document_files::tableList` resolve `last_status` through `user_logs → users → persons`. System actions insert transactions with `log_id=0` (e.g. `doc_file_accepted` from Kalite/accept-all), so **inner joins returned `last_status: null`** and the UI fell back to “Beklemede” / no chip even though the file was approved. Both subqueries now use `left join` + `coalesce(p.name,'Sistem')`. Verified on `023ac842…`: both files show **Onaylandı** (no Beklemede), admin document list shows “Doküman Onaylandı”.
- **Document search alias safety (2026-11-19):** `Document_files::tableList` `all` search must strip SQL aliases with `strripos(' as ')` — some column definitions use `as  x` (double space), others `as x` (single). The old `explode('as  ')` leaked `as last_trans_at` into the WHERE (`... as last_trans_at::text`) and the `created_at` branch forgot `$where .=`, producing `or or`. Fixed with a local `$colExpr` helper and the missing append. Verified on `/tedarikpanel/documents` and `/coalpanel/documents`: `3510011001-4` → 3 rows, `ZZZZ-NOPE` → 0, no 500.
- **Main search performance (2026-11-19):** the `all` search on `Documents::tableList` and `Document_files::tableList` no longer ilike-scans the per-row JSON subqueries (`status`, `main_attr`, `relation_detail`, `old_versions`, `last_status`) — orders search was 12.6 s, documents search 24.3 s. Now: cheap scalar columns + an indexed EAV `exists` (`Documents`: form conn `main_id=i.id AND conn_id=0`; `Document_files`: `main_id IN (d.id, d.parent_id)`), backed by the new `idx_sco_main` index (migration `2026_11_19_000001_add_sys_con_ops_main_id_index`). **Status text + pill labels stay searchable** via a second indexed `exists` on `transactions` (op_key/title/note + a CASE mapping `Beklemede/Onaylandı/Reddedildi` to their stored titles). Measured: orders 0.94–1.18 s, documents 1.07–1.31 s; statuses (`Onaylandı` 13.6k files, `Beklemede`, `Reddedildi`, note text) all match; products/clients/categories sub-second.

## 11. Sevk happy path (2026-11-16)
1. `ready` → `POST /v1/orders/sevk/start` → `sevk_started`. 2. depo+`kalite_ok`. 3. adres→`addressed`. 4. tarih→`dated`. 5. proforma+irsaliye upload (`PUT /v1/document`, `sevk_docs`) → `proforma`. 6. `POST /v1/orders/sevk/control {uygun:1/0}` (Değil loops to `dated`). 7. ship→`shipped` (needs `kontrol_ok=1`). 8. Kalite `approved`. Dökümanlar tab: same control on proforma rows.
