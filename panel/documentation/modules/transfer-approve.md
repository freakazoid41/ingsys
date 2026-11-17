# Transfer Approve — Sevk Onay Path (LIVE v1)

> **Scope:** Way-1 of 2 order-approval paths. Way-2 Fabrika Kabul is OUT (later file).
> **Agreed:** split happens at `ready_for_shipment`. `doc_trans_order_approved` stays terminal. This file details everything BEFORE `approved` for Sevk.
> **Implemented 2026-11-16:** statuses seeded (6+2 file types), guards, `startSevk/advanceSevk`, `POST /v1/orders/sevk/*`, `GET /v1/order/{id}/partitions`, frontend labels + OList actions + OForm locks + OForm Sevk card both panels + Dökümanlar tab control. Build 5.29s, tests 66/247. TODO: SAS depo pull, doluluk takvim API, sevk mail, per-05-06/07 split.
> **Source image:** `image-1.png` (7 steps + 1 gateway, 3 actors).

## 1. Flow — image mapped to system

```
ready_for_shipment (all transfer_kabul/cins + item_test/images accepted)
  → [Admin] Send to Sevk Onay  (vs Fabrika — later)
  → 1. Standardizasyon: doküman Kalite onayı (depo_yeri SAS'tan gelir, hidden until here)
  → 2. Lojistik: sevk adres girişi (Lojistik Modülü)
  → 3. Tedarikçi: takvimden sevk tarihi seçimi
  → 4. Tedarikçi: proforma fatura + irsaliye upload (SAS fiyat/kur snapshot üzerinden)
  → 5. Lojistik: P.Fatura + İrsaliye Kontrolü — Uygun Değil → loop to 4, Uygun → forward
  → 6. Tedarikçi: sevkiyat gerçekleştir
  → 7. Lojistik: stok girişi yap → then `Sevk Onayı Ver` → doc_trans_order_approved
```

Actors: `Malzeme Standardizasyon Yöneticiliği` (step 1), `Lojistik Stok Yöneticiliği` (steps 2,5,7), `Tedarikçi` (steps 3,4,6).

## 2. Status machine delta — all before `approved`

Current: `created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected` auto. Sevk `approved` from ANY (bypass).

Live `op-trans-op-doc-order` keys (seeded, same group, no new table):

| op_key | title | who sets | from |
|---|---|---|---|
| `doc_trans_order_sevk_started` | Sevk Onayına Alındı | admin `per-05-03` | `ready_for_shipment` |
| `doc_trans_order_sevk_kalite_ok` | Sevk Kalite Onayı Verildi | standardizasyon | `sevk_started` |
| `doc_trans_order_sevk_addressed` | Sevk Adresi Girildi | lojistik | `sevk_kalite_ok` |
| `doc_trans_order_sevk_dated` | Sevk Tarihi Seçildi | tedarikçi | `sevk_addressed` |
| `doc_trans_order_sevk_proforma` | Proforma/İrsaliye Yüklendi | tedarikçi | `sevk_dated` (+ loop back from kontrol-red) |
| `doc_trans_order_sevk_shipped` | Sevkiyat Gerçekleşti | tedarikçi | `sevk_proforma` (only after kontrol-ok) |
| terminal | `doc_trans_order_approved` | lojistik+kalite `per-07-02` | `sevk_shipped` (+ existing paths) |

Guard matrix live in `DocumentServiceProvider::setStatus` `$allowed`:
```
ready_for_shipment → [approved, rejected, sevk_started]  // + fabrika_started later
sevk_started → [sevk_kalite_ok, rejected]
sevk_kalite_ok → [sevk_addressed, rejected]
sevk_addressed → [sevk_dated, rejected]
sevk_dated → [sevk_proforma, rejected]
sevk_proforma → [sevk_shipped, sevk_dated]  // dated = kontrol-red rework loop
sevk_shipped → [approved, rejected]
```
`approved` keeps ANY-bypass for Kalite button; sevk forward steps go through strict guard. `syncOrderStatusFromFiles` ignores `*sevk*/*proforma*/*irsaliye*` — only kabul/cins/test/images drive `files_rejected/ready`.

Reject anywhere in sevk chain → `rejected` terminal (same as today). No auto-flip from proforma reject — explicit `note` required.

## 3. EAV dictionary delta — no migrations

Live `sys_options` rows (`OrderSystemSeeder`):
- Statuses above × `group_key=op-trans-op-doc-order`.
- File types `op-file-types`: `op-sevk_proforma_file`, `op-sevk_irsaliye_file` (+ `op-proforma_file`, `op-irsaliye_file` for `fetchFileDetailBundle` join `op-||split_part`).
- Form key stays `op-doc-order-form` (extend, no new doc type). Clone `EBELN-X` inherits sevk EAV via existing `duplicateOrderItem/moveOrderFiles` — proforma files do NOT copy (fresh per clone).

New entities on `op-doc-order-form` (`sys_con_ops type=op-doc-order-form`, `table_tag=sys_con_ops` unless noted):

| entity_tag prefix | group_key | type | visibility | notes |
|---|---|---|---|---|
| `approval_path` | `sevk_group` | text `sevk\|fabrika` | admin only | stamped at split, locks path |
| `depo_yeri` | `sevk_group` | text (SAS) | hidden until `sevk_kalite_ok`; only Adm/Gdz users before | Not-1/Not-3 step 1. SAS pull, read-only |
| `depo_mail_group` | `sevk_group` | text | admin | which mail group got the kalite mail |
| `sevk_adresi` | `sevk_group` | select + `sevk_adres_diger` text | lojistik writes, supplier reads | single-choice from Portal mail-group list; `Diğer` manual allowed (Köşk etc.) — supplier NEVER sees depo_yeri, only this |
| `sevk_tarihi` | `sevk_group` | date `d/m/Y` | supplier picks, all read | from takvim module §6 |
| `sas_birim_fiyat` / `sas_kur` | `sevk_group` | text read-only | all read | SAP snapshot, supplier validates against |
| `proforma_file` / `irsaliye_file` | `sevk_docs` | file single `hideAdd` | supplier upload, lojistik control | `table_tag=document_files`, version chain same as kabul/cins (`replaced_id`), kontrol-red → new version |
| `proforma_kontrol_note` | `sevk_docs` | textarea | lojistik | `Uygun Değil` reason, logged |
| `sevkiyat_flag` / `stok_giris_flag` | `sevk_group` | switch `0/1` | tedarikçi / lojistik | explicit check-ins, feed status guards |

File-versioning: reuse `addFileToDb/finalizeTempFile` + `isMultiFile` single-slot (`**` count=2 → version). Proforma re-upload after `Uygun Değil` = same slot new version, then `sevk_dated → sevk_proforma` re-fire.

## 4. Permissions delta

Existing: order `read 05-01 / edit 05-02 / status 05-03 / cancel 05-04 / rename 05-05`, files `07-01 list / 07-02 decide`, Kalite `07-02`.

Live (reuses current perms; future split reserved):
- `per-05-06` Lojistik Sevk Yönetimi (steps 2,5,7: adres, kontrol, stok giriş)
- `per-05-07` Standardizasyon Sevk Kalite (step 1: `sevk_kalite_ok` + depo reveal)
- Supplier needs NO new perm — steps 3,4,6 ride on existing `05-02` edit + own-order LIFNR scope. Lojistik/Standardizasyon must hold `05-01` read too.

`docPermCheck` map: `op-doc-order status` stays `05-03` for split; `setStatus` enforces per-key like planlama (`DocumentController::setStatus` per-key 403): `sevk_started→05-03`, `sevk_kalite_ok→07-02`, `sevk_addressed→05-06` (Lojistik Stok Yöneticiliği: Adres/Tarih), `sevk_dated→05-02|05-06` (supplier or Lojistik). Live since 2026-11-17; propagated to admin + İş Birimi roles. Reuse planlama pattern (`order-planning.md:48`).

Role mapping (to confirm): Admin gets all; İş Birimi gets `05-06`? New `Lojistik` role vs extend İş Birimi — Master decision. Minimum: Admin + İş Birimi ключ.

## 5. Backend API delta

Reuse `POST /v1/trans/set-status {id,qnid,op_key,note}` with new guards above — NO generic bypass for sevk keys (mirror tender `set-status` block for ihale/offer).

Live endpoints (all `docPermCheck` + LIFNR scope + `UserLog` enriched + `Transactions op0`):
- `POST /v1/orders/sevk/start {qnid, path:sevk}` → validates `ready_for_shipment` + all doc files accepted → writes `approval_path=sevk` + `sevk_started`. Fails closed otherwise.
- `POST /v1/orders/sevk/kalite {qnid, depo_yeri, note}` (`05-07`) → stamps depo + `sevk_kalite_ok` + fires depo mail group (§7).
- `POST /v1/orders/sevk/address {qnid, sevk_adresi, diger?}` (`05-06`) → `sevk_addressed`.
- `POST /v1/orders/sevk/date {qnid, sevk_tarihi}` (supplier, own order) → validates takvim slot free → `sevk_dated`.
- `PUT /v1/document/:qnid` already handles proforma/irsaliye files (temp-upload → finalize). After upload supplier calls `POST /v1/orders/sevk/proforma {qnid}` → `sevk_proforma`.
- `POST /v1/orders/sevk/control {qnid, uygun:bool, note}` (`05-06`) → `true: →sev_proforma stays + kontrol-ok flag allowing ship` (or direct `sevk_proforma→sevk_shipped` gate flag); `false: →sevk_dated` + `proforma_kontrol_note` + mail to supplier.
- `POST /v1/orders/sevk/ship {qnid}` (supplier) → requires kontrol-ok → `sevk_shipped`.
- `POST /v1/orders/sevk/stock {qnid}` (`05-06`) → `stok_giris_flag=1`, then existing `Sevk Onayı Ver` (`set-status approved` + `acceptAll`) closes.

Helpers: `resolveFileOrderDoc()` connection-first reused. `orderPartitions(qnid)` indexed `order_no LIKE base-%` powers `OForm.checkHasPartitions` + `calcCloneSuffix` (replaces 17s `tableList all=` scan, now 0.09s). Reseller scope unchanged (LIFNR+SYSTEM fail-closed).

Logging: every transition `log-order-update {actor,document,from,to,note}` + enriched file logs for proforma (`log-file-added`, same as kabul). Kontrol-red note JSON `{"note":"…"}` parsed by `noteOf()` already.

## 6. Frontend delta

- `OForm.vue` `Sevk Card` live both panels (tedarik + admin) (after transfer card, before files): path banner (`Sevk Onay` vs `Fabrika` placeholder), step stepper 1-7 with actor labels, per-step visibility: depo (admin-only until kalite), adres (lojistik input / supplier readonly), takvim slot picker (supplier), proforma/irsaliye upload (supplier) + kontrol buttons (lojistik), ship button (supplier), stok button (lojistik). Reuse `readonlyFields` + `isLocked` pattern + detached `v-model` rule (`(vModel.trim())||getFieldValue()`).
- Takvim: NEW component `SevkCalendar.vue` — ambar doluluk API (to build, §8) → selectable dates only; used by supplier step 3 + yüklenici in/out later. Not a full calendar lib — blocked until doluluk endpoint defined.
- `OList.vue`: new filter `sevk_status` + pill colors (reuse `statusUtils`), `Aksiyonlar` per-step buttons gated by perms. Supplier sees only own path steps.
- `DList/DForm` live: proforma/irsaliye files visible; `DForm` shows `Uygun / Uygun Degil` (order-level `sevkControl`, `per-05-03`) instead of `Onayla/Reddet`; `DList` pill opens same control. `fetchFileDetailBundle` lists sevk files; sync ignores them.
- Build rule unchanged: `npm run build` + test both panels, hard refresh `app-*.js`.

## 7. Mail + notifications

- Step 1 after `sevk_kalite_ok`: mail to depo-based groups (like `tedarik-04/05` BUKRS+LIFNR gate, but keyed by `depo_yeri`). Needs new `sevk-01` type + group admin UI (reuse `sys_notification_types` + `getNotificationUsers`).
- Kontrol-red → direct mail to uploader (reuse `planlama-02` owner-scoped pattern).
- Proforma upload → mail to `05-06` holders.
- Bell categories: extend `TEDARIK_CATS` or new `SEVK_CATS` with scoped `mark-all` (same 30s cache pattern as `performance-2026-11-12.md`).
- `?next=` gateway already supports `/tedarikpanel|/coalpanel` — add `/lojistik` if new shell, else reuse.

## 8. SAP / SAS gaps (BLOCKERS to confirm)

1. `Depo Yeri` SAS field name + endpoint — Portal pulls where? Batch sync or live call? (Not-1 step 1)
2. `SAS Birim Fiyat + Kur` field mapping (`NETPR/WEMNG` today NOT stored) — need payload sample.
3. Sevk adresi master list — Portal-defined mail-group person selects; where stored? New `op-doc-sevk-address` dict or EAV select options?
4. Ambar doluluk source for takvim — no API today; takvim build blocked until defined.
5. Stok girişi posts back to SAP? Or Portal-only flag? If SAP, need BAPI/RFC spec.

## 9. Test plan (happy path, 10 min)

1. `orders:reset` → supplier sends `at_once` with kabul/cins+tests → `transfer_sent`.
2. Admin accepts all files → `ready_for_shipment`.
3. Admin `Sevk'e Gönder` → `sevk_started` + `approval_path=sevk`.
4. Standardizasyon kalite + depo → `sevk_kalite_ok`, depo hidden from supplier verified.
5. Lojistik adres → `sevk_addressed`, supplier sees adres NOT depo.
6. Supplier takvim date → `sevk_dated`.
7. Supplier proforma+irsaliye → `sevk_proforma`.
8. Lojistik `Uygun Değil + note` → back `sevk_dated`, supplier re-upload → `sevk_proforma`.
9. Lojistik `Uygun` → supplier ship → `sevk_shipped`.
10. Lojistik stok giriş → `approved`. Verify logs side-panel + mails + LIFNR isolation + both panels parity.

## 10. Explicitly OUT (Fabrika path)

`fabrika_started` + its steps, `AwardToStock` handoff, multi-path UI toggle, auto-disqualify cron — later file. Guard allows `ready → sevk_started` now; add `fabrika_started` key + branch when specced. No code touches fabrika keys in this phase.

## 10. OList tabs (2026-11-17)
- `Sevk Onay Süreci` tab filters `transactions IN (6 sevk keys)` — live, returns sevk orders in both panels (admin + tedarik headers + Filtreler dropdown).
- `Fabrika Kabul` tab filters `doc_trans_order_fabrika_started` (Way-2 not seeded → empty by design, toast warns). Reserve for Fabrika spec.

## 11. Side tabs (2026-11-17)
- Sidebar (admin `Tedarik Onay` submenu + tedarik panel menu): `Sevk Onay Süreci` → `OrderList?f=sevk`, `Fabrika Kabul` → `?f=fabrika`.
- `OList` applies `?f` on mount + watches it (`applySideTab`); same 6-key IN filter as the in-list tab. Fabrika empty by design.

## 12. Focus mode (2026-11-17)
- Entering detail from `Sevk Onay Süreci` tab (`?f=sevk`) or any sevk status auto-scrolls to `#sevk-card` with a purple ring; other step cards collapse behind `Tüm detayı göster` (both panels). Query `?f` is preserved across Detaylar/back/parent navigation.
- Also fixed: `navigateToParentOrder` now uses exact `siparis_kodu` instead of slow `all` scan.

## 13. Pipeline audit (2026-11-17)
- Generic `POST /v1/trans/set-status` blocks `doc_trans_order_sevk_*` (mirrors tender block) — all sevk steps only via `sevk/start|advance|control` with EAV + perm validation. Live-proven 422.
- `documentFileStatus` whitelists `doc_file_accepted|rejected` only + null guards (file/link/type). `acceptAll` closure semantics unchanged (accepts all incl. sevk on terminal approve).
- Matrix check: 18/18 `sys_options` keys seeded, guard map + frontend labels + routes present, full suite 66/247 green.

## 14. per-05-06 live (2026-11-17)
- `per-05-06 Lojistik Stok Yöneticiliği (Sevk Adresi / Tarihi Girişi)` seeded in `role_details.json` + granted to `immutable-admin` + `immutable-satınalma-keyuser`, propagated to all holders + version bump. Supplier (reseller) excluded. Live-proven: supplier `addressed` → 403, admin `addressed` → ok, supplier `dated` → ok.

## 15. Real-browser E2E (2026-11-17, ALL GREEN 19/19)
- `panel/tests/e2e/sevk.run.mjs` (headed Chromium, 2 users): scratch GDZ order 3510006329 (pickle's own lifnr) setup → started via API, then browser: Sevk tabs (admin + supplier), focused detail (scroll + ring + collapsed steps + no stray Kaydet), Kalite modal from Aksiyonlar (depo prompt + perm note), multiline adres UI, supplier date/tiles/ship UI, Dökümanlar Uygun control, supplier scoping (own rows only, foreign 403 + bounce). Stops at shipped; full cleanup verified (order back at created, zero residue).
- Caught & fixed across runs: (1) `syncOrderStatusFromFiles` yanked sevk orders back to ready on any upload — now skips `sevk_*` states; (2) read-only steps rendered inputs + values doubled — inputs hidden unless editable; (3) supplier app boot fired 2× `roles/*` 401s — store skips without per-04-03; (4) foreign direct-qnid read was 200 for resellers — `assertOrderOwnership` gate on order family GET/PUT (fail-closed like tableList); (5) `sevkControl` accepted from any status — now requires `sevk_proforma`; (6) favicon 404 (`/public/css/favicon.ico` → `/favicon.ico` in 5 blades).
## 16. Post-E2E hardening (2026-11-17)
- `assertOrderOwnership` gate (see permission-system-analysis.md) — reseller direct reads now 403 like lists.
- `sevkControl` requires `sevk_proforma` (see logging-mechanics.md).
- `permissiondata` store skips roles fetch without `per-04-03`.
- Favicon 404 fixed in 5 blades (`/favicon.ico`).
- DEV_ADMIN hardcoded perm list re-synced (`per-05-06` was missing and shadowed the seeder until E2E caught the empty adres step).

## 17. Permission matrix (2026-11-17, all live-proven, zero mutations)
- kb (no 05-03/07-02/05-06): start/kalite/adres/control → 403; file-status → 403; foreign order GET → 403; generic sev set-status → 422.
- pickle (05-02, own GDZ order): dated/ship at wrong state → 422 (perm passes, guard blocks); own GET → 200; foreign GET → 403; tables exclude foreign rows.
- Admin: generic sev set-status → 422 (dedicated endpoints only); start on file-less ready → 422 (completeness gate).
## 18. `startSevk` completeness gate (2026-11-17)
- Start requires ≥1 reviewed file AND zero unaccepted active doc files (sevk files excluded) — a manually-flagged ready order can't enter Sevk.
