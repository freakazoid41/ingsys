# Malzeme Planlama Modülü — Build & Mechanics Bible (2026-11-09)

> **Read this when touching anything under `/planlamapanel`, `op-doc-request*`, `per-041-05*` or `planlama-*` notifications.**
> Canonical EAV mechanics live in `panel/documentation/form-system-mechanics.md`, `file-versioning-system.md`, `logging-mechanics.md`, `notification-*.md`. This file is the **module-specific** record: what was built, why, and the exact wiring. Live snapshot stays in `memory/05-order-system-state.md`.

---

## 1. What It Is

SAP-style procurement flow rebuilt for directorates (müdürlükler):
1. **İhtiyaç Listesi** (`op-doc-request` family) — admin enters monthly material needs (entered by hand or Excel import).
2. **Talep Dökümanı** (`op-doc-requestf` family) — created **only** from an İhtiyaç row ("Talep Oluştur"), filled per-month quantities by the bound müdürlük.
3. **Approval chain** — Yükleme → Müdürlük Onay/Red → Standardizasyon Onay (SAP release).

Both doc families share pages across panels via route prefix (`/coalpanel/planlama/*` admin, `/planlamapanel/*` own shell `PlanlamaPanel.vue`).

---

## 2. Dictionary (Seeded — `PlanlamaRequestSeeder.php`, idempotent)

| op_key | group | Meaning |
|---|---|---|
| `op-doc-request` / `op-doc-request-item` | `op-doc` | İhtiyaç header / item (display: İhtiyaç Listesi / İhtiyaç Kalemi) |
| `op-doc-request-form` / `op-doc-request-item-form` | `op-doc-forms` | Their form blocks |
| `doc_trans_request_created` | `op-trans-op-doc-request` | Birth (info only, no guard attached) |
| `op-doc-requestf` / `op-doc-requestf-item` | `op-doc` | Talep header / item (display: Talep Dökümanı…) |
| `op-doc-requestf-form` / `op-doc-requestf-item-form` | `op-doc-forms` | Their form blocks |
| `tr-request-man-awaiting` | `op-trans-op-doc-requestf` | Birth, auto-set on create (`DocumentServiceProvider::$birthMap`) |
| `tr-request-man-approved` / `tr-request-man-rejected` | same | Müdürlük decision; rejected is re-openable by revision |
| `tr-request-std-approved` | same | Standardizasyon decision (terminal), SAP release |
| `op-talep_excel_file` | `op-file-types` | File-type dict row for the attached Excel (titles in logs/lists) |

Legacy `op-doc-request/offer` rows from the coal era were purged 2026-09-08; these keys are new and unrelated.

---

## 3. Permissions & Roles

Module gate: `per-041-05 Malzeme Planlama` (module switcher, panel route guards, Standartizasyon menu).

| Code | Name | Used for |
|---|---|---|
| `per-041-05-01` | Müdürlük Talep Onay | Approve/reject Taleps (set-status gate), read both families |
| `per-041-05-02` | Müdürlük İhtiyaç Oluşturma | Create/edit İhtiyaç (+ read) |
| `per-041-05-03` | Talep Yükleme | Create/edit Taleps (incl. Excel import) |
| `per-041-05-04` | Malzeme Standardizasyon Yöneticiliği | Final std approve |

`docPermCheck` map (`PermissionHelpers.php`): request+item `edit→05-02`, `read→05-02,03,01,04` (all planlama roles can view); requestf+item `edit→05-03`, `read→03,01,04`. Status changes do **NOT** go through the generic map — `DocumentController::setStatus` enforces per-key: man-approved/man-rejected require `05-01`, std-approved requires `05-04` (403 otherwise).

`DocumentServiceProvider::setStatus` transition guard (requestf only):
```
awaiting → approved | rejected
approved → std-approved
rejected → awaiting            (re-submit after revision re-queues it)
terminal: std-approved; man-rejected may return to awaiting after revision
```

**Test users** (`PlanlamaTestUsersSeeder.php`, idempotent union — never removes perms):
- `kadir@kontent.com.tr` — admin + 05,05-01..05-04; notif groups `planlama-01, planlama-03`
- `kbbozat41@hotmail.com` (reseller, bound YILDIZ) — 05 + `05-03`
- `picklecanme@gmail.com` (reseller, bound `90000001` Planlama Müdürlüğü GDZ, pw `Kadir412.`) — 05 + `05-01`; notif group `planlama-01`

---

## 4. System Clients (Enums, Not Editable)

`SystemClientSeeder.php` (idempotent by `lifnr+client_system`): 3 müdürlüks × GDZ/ADM = 6 `op-doc-client` docs (`90000001 Planlama`, `90000002 Sistem İşletme`, `90000003 Bakım`), each with `is_system=1` entity.

- `Documents::tableList`: `op-doc-client` **excludes** `is_system=1` by default; opt in with `system_only=1` / `is_system=1` filter.
- `DocumentServiceProvider::registerContent` + `removeContent`: hard-block edit/delete of `is_system` docs (`DB::rollBack` + message).
- Frontend: `CList` hides Sil for `9000000x`, shows Sistem pill; `CForm` blocks save on system docs.
- Admin menu **Malzeme Standartizasyon → Müdürlükler** (`MList.vue`, read-only, `per-041-05`) lists only system clients.

---

## 5. İhtiyaç Listesi (`op-doc-request*`)

**Form** (`Form.vue`): header = Başlık* + Dönem (Başlangıç/Bitiş Ayı month pickers `m/Y`, required, side-by-side in a `sub` row — top-level fields ignore `col`) + Açıklama; items = `request_items` multiple (mal_grubu, ihale_grubu, prod_code*, title*, unit select*, NO quantity — forced `'0'` on save).

**Pages** (`pages/planlama/Request/`): `RList` (Başlık/Dönem/Oluşturulma, Detay + `Talep Oluştur` for 05-03), `RForm` (create guarded to 05-02 via route + button + submit guard; others get read-only detail: all fields disabled via `readonlyFields`, save bar/add/trash hidden via `.is-readonly` CSS, Excel upload hidden but **download stays**).

**Excel**: template = 5 base cols; `Kalemleri İndir` exports a **month matrix** (columns `YYYY-MM` expanded from the period, empty cells for quantities); upload maps both matrix and flat (Ay+Miktar) formats, reuses matching rows, adds only new ones.

---

## 6. Talep Dökümanı (`op-doc-requestf*`)

**Creation is ONLY via İhtiyaç row → `?from=<qnid>`** (`TList` has no create button; `TForm` without `from` bounces to list; backend create still needs `05-03`). On open, `linkFromSource` copies header (title/request_no/desc) + expands items × period months (`MM/YYYY`), one row per material×month.

**Editing rule (hard): save allowed only when NEW or status is `man-rejected`.** `canSave` gates: save bar, Excel buttons, quantity field, submit. Everything else is always read-only (`readonlyFields` incl. `talep_excel_file`, `ay`, all identity fields; only `quantity` is ever editable, and only while savable).

**Müdürlük picker**: create-time header select populated from the user's **bound system clients** (`POST /v1/table/documents` + `system_only=1`, reseller-scoped automatically). Single binding → auto-selected. Stamped as `firma_adi` (+`mudur_qnid`) on create only; shown read-only afterwards and in the list's Müdürlük column.

**Excel round-trip**: import validates the **full predestined set** (`prod+ay` pairs incl. empty/zero qty) against current rows — missing/extra rows abort with an itemized `Excel Geçersiz` warning. Valid files are **attached into the hidden `talep_excel` slot** (single, `hideAdd`, `accept .xls,.xlsx`) and versioned on save exactly like order files (new row per upload, `status` activeness, `replaced_id` chain). History endpoint `GET /v1/request/{qnid}/files` returns newest-first (tag-grouped query + single batched `DISTINCT ON` status lookup — **no chain walking**). Detail shows **Yüklenen Excel Dosyaları** (Güncel + vN, Önizle/İndir).

---

## 7. Notifications & Mail (Pipeline)

Types (`notification_details.json`, auto-listed in Bildirim Şablonları): `planlama-01` (Onaya Geldi), `planlama-02` (Reddedildi), `planlama-03` (Müdürlük Onayı).

| Step | Trigger | Mail | Bell |
|---|---|---|---|
| 2 — Talep created | `handleStore` after `registerContent` (requestf) | `planlamaTalepSent` → `planlama-01` group (fallback: all `05-01` holders if group empty) | same key, group-gated |
| 3 — man-rejected | `setStatus` requestf branch | direct to uploader (`users.email` via `documents.person_id`) | `planlama-02`, **owner-scoped** (`person_id == session`, fail-closed without session) |
| 3 — man-approved | `setStatus` requestf branch | `planlama-03` group (fallback: all `05-04` holders) | same key, group-gated |

Mail cards: `tedarikCard` + `TedarikMailHelper::gateway('/planlamapanel/talep-dokumanlari/form/{qnid}')` (gateway regexes extended to `/planlamapanel` in `AuthController`, `tedariklogin/page.js`; `?next=` survives 2FA). Tedarik bell, planlama bell and Bilgilendirmeler share `notificationMaps` CAT_META + `buildNotificationRows`; the admin `coalparts/Header.vue` consumes the same API/category keys but keeps its own mapping. Dashboards (`TedarikActivity`, `DashboardNotifications`) are built from the notification feed — never from `user_logs`.

Requeue: editing a rejected Talep auto-transitions to `awaiting` + re-fires step-2 mail. Response detail is refreshed post-transition (no stale pill).

---

## 8. Routes & Menus

- `/coalpanel/mudurlukler` (MList), `/coalpanel/planlama/talepler[/form/:id?]` (RList/RFormAdmin), `/coalpanel/planlama/talep-dokumanlari[/form/:id?]` (FList/FFormAdmin)
- `/planlamapanel` (Dashboard), `/talepler…`, `/talep-dokumanlari…` (same components, panel-aware names)
- Guards: planlama paths need `05`; talep-dokumanlari needs any of `01/03/04`; create form (`/talepler/form`, no id) needs `05-02`.
- Sidebar **Malzeme Standartizasyon**: Müdürlükler (`05`), İhtiyaç Listesi (module `05`, create inside needs `05-02`), Talep Dökümanları (`01/03/04`).

---

## 9. Gotchas (Learned Hard)

1. **Money mask divides by 100** (`VMasker maskMoney precision:2`): never put `isMasked/money` on quantity fields — typed `8` becomes `0.08`. Both request item schemas are mask-free.
2. **Top-level `col` is ignored** by `Form.vue` (fields render full-width); side-by-side needs a `sub` wrapper (Başlangıç/Bitiş Ayı pattern).
3. **Selects in multiple rows** must render into `inpGroup`, not `rowElm` — otherwise the row delete icon stacks above the select.
4. **`m-2` on textareas + `width:100%`** overflows past inputs — removed globally.
5. **`getFormData` status was NULL for fresh docs** (inner joins on `user_logs` killed birth rows with `log_id=0`) — now LEFT JOINs. Always `JSON.parse` the status (string, not array).
6. **`is_subarray`... `entity_tag` `**` count decides versioning**: single slot = prefix-match version chain; multi (`**img-id`) = append. Talep excel is single-slot → versions correctly.
7. **Detached `v-model` trap**: `tedarikImalatci`-style fields are invisible to `getFieldValue()` — prefer `(vModel?.trim()) || getFieldValue() || orderEntities`.
8. **`updateRow` only repaints passed columns**: include the action column key too, or row buttons go stale after status change.
9. **Check-then-act on `span.querySelectorAll('button')`** to lock whole rows during async ops; restore in `finally`.
10. **Auto-commits**: work is committed as you go (`"changes"` commits) — but **new files must be `git add`ed** or a fresh checkout breaks the build (happened with `lib/talepRows.js`, `lib/planlamaStatus.js`).
11. **Hard refresh after every build** — most "still broken" reports this session were stale `app-*.js` in cache.
