# Form System Mechanics (Dynamic Form / EAV Engine)

## 1. Overview

The form system is a **schema-less, EAV-style (Entity-Attribute-Value) engine** on top of PostgreSQL. Every business record (`documents`, `persons`, `transactions`, `document_files`) is a thin relational row; its "form fields" live in two generic tables:

- `sys_con_ops` — one row per **form instance** (a repeatable group/section of fields). Think "one filled-out form block".
- `sys_con_entities` — one row per **field value** inside a form instance. The `entity_tag` is the field name, `entity_value` is the value.

This is what allows the same `documents` table to hold requests, offers, clients, flats, meetings, etc. — the *shape* of each document type is data, not code.

**Key files:**
- `app/Providers/DocumentServiceProvider.php` — backend write/read engine (`registerContent`, `getFormData`)
- `app/Providers/PersonsServiceProvider.php` — same pattern for persons/users (`setPerson`, `getPerson`)
- `app/Http/Controllers/DocumentController.php` — HTTP entry point (`/v1/document/{id?}`)
- `app/Models/Documents.php`, `app/Models/Document_files.php`, `app/Models/Sys_con_ops.php`, `app/Models/Sys_con_entities.php` — models + `tableList()` raw SQL listing engines
- `app/Models/Sys_options.php` — the **dictionary** table that gives everything meaning
- `resources/js/components/coalparts/Form.vue` — the frontend form renderer that produces the payload
- `resources/js/pages/coalsystem/**/*Form.vue` — page-level form wrappers (RForm, OForm, CForm, UForm, FlatForm)

---

## 2. The dictionary: `sys_options`

`sys_options` is the beating heart. It stores:

| group_key | Purpose | Examples |
|-----------|---------|----------|
| `op-doc-*` | Document types | `op-doc-request`, `op-doc-offer`, `op-doc-client`, `op-doc-flat` |
| `op-doc-*-form` | Form template tags (what a document type's form blocks are called) | `op-doc-request-form`, `op-doc-client-form`, `op-doc-offer-form` |
| `op-trans-*` | Transaction/status types (per document type) | `doc_trans_created`, `doc_file_waiting`, `doc_file_rejected`, `doc_file_accepted` |
| `op-pert-*` | Person types | `op-pert-admin`, `op-pert-reseller`, `op-pert-buyer` |
| `per-*` | Permissions (see `permission-system-analysis.md`) | `per-05-01`, `per-08-02` |
| `log-*` | UserLog event types | `log-login`, `log-tender-update`, `log-file-added` |
| `form-main`, `form-file` | **Sub-type markers** | used in `sys_con_ops.sub_type_id` |

### Naming conventions (critical)

- `op_key` values use **kebab + numbered** codes. IDs are auto-incremented; **`op_key` is the stable business key** — always resolve IDs through `Sys_options::where('op_key', ...)`.
- `ctitle` is a hint column used in the code as a lookup discriminator: `['ctitle' => 'type_id', 'op_key' => $tag]` and `['ctitle' => 'sub_type_id', 'op_key' => 'form-main']`. This is fragile legacy magic — if you reuse it, keep the convention consistent.

Full `sys_options` schema (for seeding a new system): `id, status, parent_id, title, code, ttitle, ctitle, op_key, group_key, description, icon, timestamps`. `group_key` has an index — it's the primary query axis.

### Resolver helpers (the pattern to copy)

```php
// type of a form block
$typeId = Sys_options::where(['ctitle' => 'type_id', 'op_key' => $tag])->first()->id;
// sub-type marker for regular fields
$stypeIdMain = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->first()->id;
// sub-type marker for file fields
$stypeIdFile = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-file'])->first()->id;
```

---

## 3. Data model

### 3.1 `documents`

```sql
id, status (1 active / 0 passive), parent_type_id, parent_id,
type_id (→ sys_options.id of op-doc-*), person_id (creator's persons.id),
title, grp_code (multi-tenant marker, default = GLOBALS['SYS_CODE']),
qnid (UUID, generated in model boot), starting_at, ending_at, timestamps
```

- `qnid` is the **public identifier** sent to the client. All routes use `qnid`; internal joins use `id`.
- `person_id` is set at creation from `session('person_id')` — owner for reporting/scoping.
- `status = 0` means "passive/removed" for most types, but **"cancelled" for offers** (`op-doc-offer`).

### 3.2 `sys_con_ops` (form instance / group)

```sql
id, status (1 active), main_id (→ documents.id or persons.id),
conn_id (0 = root connection), type_id (→ sys_options op-doc-*-form),
sub_type_id (→ form-main / form-file marker), description, timestamps
```

- `main_id` is the **owner** of this form block (the document or person).
- `conn_id = 0` marks the canonical row. Non-zero `conn_id` links entities across tables (legacy glue, e.g. client↔person).

### 3.3 `sys_con_entities` (field value)

```sql
id, conn_id (→ sys_con_ops.id), table_tag, entity_tag, entity_value, qnid, timestamps
```

- `table_tag` tells the engine what the value points at:
  - `sys_con_ops` → a plain field value (text).
  - `document_files` → `entity_value` is a `document_files.id` (string) — a **file link**.
- `entity_tag` is the field name, e.g. `req_no`, `title`, `cliid`, `cont_imza_file**...`.
- **File links are special**: every upload appends a NEW entity row (never updates), so the entity table itself is the version history. Activeness is derived from the linked file's `status`.

### 3.4 `document_files`

```sql
id, status (1 active / 0 replaced|disabled), type_id, replaced_id (→ previous version id),
conn_id, relation_id (→ documents.id), relation ('documents' | 'temp' | ...),
title, description (ENCRYPTED filename), grp_code, qnid (UUID), selected_at, timestamps
```

### 3.5 `transactions` (status/history ledger)

```sql
id, op_id (0 = document-level, 1 = file-level), type_id (→ sys_options doc_trans_*),
log_id (→ user_logs.id — who did it), target_id (document.id or document_files.id),
description, note, amount, cur_id, rel_id, sign, period, qnid, timestamps
```

Status is **not** a column on documents/files — it's the *latest transaction* of the matching group. `documents.status` only means active/passive/cancelled.

### 3.6 `user_logs` (audit backbone)

```sql
id, user_id (→ users.id, who), sys_code (tenant), relation ('documents'|'persons'|'users'),
relation_id (→ the entity's id), type_id (→ sys_options log-*),
ip, description (JSON: { before, after, desc, note, ... }), timestamps
```

- Every mutation flow writes a `UserLog`; `transactions.log_id` points back to the `user_logs.id` of the actor's action.
- `description` is a free-form JSON convention — `before`/`after` full form snapshots for edits, `{ desc, note }` for status events, `{ file_id, old_file_id }` for file events. The frontend `RequestLogTimeline` parses these shapes, so keep them consistent.
- `type_id` is resolved through `Sys_options::where('op_key', 'log-...')` — seed a `log-*` option for every new event type.

---

## 4. The wire contract (frontend → backend)

### 4.1 Save payload

Pages build a `FormData` envelope:

```
envelope.append('data', JSON.stringify(formData));
// per file field:
envelope.append('dynamicFile**...*-*<fieldname>', JSON.stringify(fileReference));  // temp-upload reference
// or legacy: envelope.append('dynamicFile**...*-*<fieldname>', <File object>);
```

`formData` (see `Form.vue`):

```json
{
  "typeKey": "op-doc-request",
  "dynamicF": {
    "op-doc-request-form**<connId|new-<timestamp>>": {
      "entities": { "req_no": "...", "target_type": "...", "note": "..." },
      "tag": "op-doc-request-form"
    }
  },
  "files": { "dynamicFile**<fileId>**<connId>*-*<fieldName>": { "reference": { "success": true, "reference_id": 123, "encrypted_name": "...", "original_name": "..." } } },
  "removedData": [ { "id": <connId>, "key": "<entity_tag>", "table_tag": "document_files" } ]
}
```

- `dynamicF` keys are `{tag}**{connId}`. `connId` = `sys_con_ops.id` when editing an existing row, or `new-<epoch>` for a new row.
- `removedData` rows tell the backend which entities (or whole form rows) were deleted in the UI. For file rows, `key` is the entity tag, `id` is the `sys_con_ops.id` the file hangs off.
- Persons (user) forms add an extra `alldata` field with `{ permissions, removedData }` (see `UForm.vue`).

### 4.2 GET read response

`GET /api/v1/document/{qnid}` returns:

```json
{
  "success": true,
  "data": {
    "document": { "op_key": "op-doc-request", "document_status": "1", "title": "...", "status": [ { "op_key": "doc_trans_created", "op_title": "...", "note": "...", "created_at": "...", "name": "..." } ] },
    "formFormat": {
      "op-doc-request-form": {
        "<connId>": { "entities": { "req_no": "...", "target_type": "..." }, "files": {} }
      }
    }
  }
}
```

- `document.status` is a **JSON array of status transactions** (aggregated via `json_agg`), newest last.
- File fields inside `entities` come back as JSON objects (see §6.3 for shape).

---

## 5. Backend write flow: `registerContent($id, $requestData, $files)`

Location: `DocumentServiceProvider::registerContent()` — wrapped in a **DB transaction** (rollback on any exception).

> **PUT parsing note:** for edits (PUT), the `multipart/form-data` envelope is parsed by the **global** `ParsePutMultipart` middleware (registered in `bootstrap/app.php`; PHP doesn't populate `$_FILES` for PUT) into `$request` + `UploadedFile`s. If that ever fails, `DocumentController` falls back to the `parsePut()` helper reading `php://input` directly. `registerContent` itself is agnostic to which path produced the files.

### 5.1 Steps

1. **Resolve document** — `$id == 0`/numeric → create new `Documents` with `type_id` from `typeKey`. Non-numeric (`$id` is a qnid) → load existing (update mode), snapshot `before` state for the audit log.
2. **Write `main_*` fields** — only `title`, `starting_at`, `ending_at` are writable through the generic payload (`GENERIC_WRITABLE_MAIN_FIELDS`). **status, id, qnid, type_id, person_id, grp_code, parent_*** are hard-locked; status changes go through dedicated endpoints. (This is a deliberate hardening — older code used to trust `main_*` more broadly.)
3. **Creator stamping** — on create only, `person_id = session('person_id')`.
4. **`Transactions::create`** — `doc_trans_created` on new documents.
5. **Removed-data processing** — for each `removedData` row: find the entity by `conn_id` + `entity_tag`. If it's a `document_files` link, only act when the linked file is **active** (`status = 1`); then `file->status = 0` and delete the entity row. (Older version rows are ignored — they're history, not the live link.)
6. **Dynamic fields loop** — for each `dynamicF` entry:
   - Resolve `typeId` from tag via `sys_options`, create/update the `sys_con_ops` row (`conn_id = 0`).
   - **Server-side field authority**: 
     - `op-doc-client`: `clicode` is **forced server-side** (new → `document->qnid`; update → client value ignored entirely). Prevents code forgery.
     - `op-doc-request` / `op-doc-offer`: `req_no` set on create (document count), `rev_date` set to today on update.
   - Upsert each entity: find existing by `conn_id + entity_tag + table_tag='sys_con_ops'`, else insert. `strip_tags()` applied to all values.
   - **`target_type` special case** (request/offer): writes `grp_code` = uppercased, Turkish-transliterated `target_type` value. This is the multi-system partition key.
7. **File handling** — see `file-upload-versioning-mechanics.md` §3.
8. **Commit, then audit log** — `UserLog::create` with `description = { before, after }` (full before/after form data). `before` is omitted intentionally on some flows (offer cancel) — the frontend `RequestLogTimeline` treats absence of `before` as a *status change* entry.

### 5.2 Return contract

```php
[ 'success' => bool, 'id' => documents.id, 'data' => $document,
  'detail' => getFormData(...), 'qnid' => ..., 'before' => ..., 'after' => ... ]
```

---

## 6. Backend read flow: `getFormData($qnid)`

Single SQL that hydrates the whole document:

1. **Form rows**: selects `sys_con_ops` joined to `sys_options` filtered by `group_key = 'op-doc-forms'`, excluding the person-only form types (`op-doc-user-permission-form`, `op-doc-user-contact-form`, `op-doc-user-client-form`), `dco.conn_id = 0`, `dco.status = 1`.
   - For `table_tag = 'document_files'` entities, `entity_value` is replaced by a JSON object built from the file row + its **last file transaction** (`last_status`, `op_id = 1`, newest first) + the acting person's name.
   - **Key subtlety**: only file entities whose linked file is active (`status = 1`) are returned — replaced files vanish from the current form but stay in history.
2. **Document header**: `document` row + `status` array (transactions in group `'op-trans-' || op_key`).
3. Output shaped as `{ document, formFormat }`.

**Warning — raw SQL injection surface:** `$id` is interpolated into raw SQL (`d.qnid = '".$id."'`). Callers MUST validate UUID format (`required|uuid`) before reaching here — see `cancelOffer`/`reopenOffer` validators in `DocumentController`.

---

## 7. Listing engine: `Documents::tableList($obj)` — the "one query fits all" pattern

The generic list endpoint `POST /api/v1/table/{model}` (`SystemController::table`) maps model names to their static `tableList()`. Document tables are built with one raw SQL query using **dynamically injected column expressions**:

- `columns` map — each key maps to a SQL fragment (aliased with `as`). `status` is a correlated subquery pulling the latest `op-trans-<type>` transaction; `main_attr` is a `LEFT JOIN LATERAL` that aggregates all entities of the filter form type into JSON.
- `filter` array from client → `where` clauses via `switch($f['key'])`. Supported keys: `free`/`all` (search across columns), `attr` (entity tag `ilike`), `transactions` (latest status =/is null), `status-not`, `status-null`, `monthly`, `month-period`, `showExpired`, `today-ended`, `is-rodevans`, `form-type`, `with-cancelled`, plus default `{key, value, type: '=' | 'like'}`.
- **Row-level scoping is baked into the query** for reseller sessions: when `session('currentStatus')['clientQnidList']` is non-empty, offers are filtered to rows whose `cliid` entity is in the client list; clients to own qnids; requests to started/ended ones. A reseller without a client list gets **zero rows** (fails closed).
- **Multi-tenant filtering**: non-admin sessions see only rows whose `grp_code` matches `GLOBALS['SYS_CODE']` (or "her ikisi" for requests).
- `noInject()` sanitization runs on every client filter value before interpolation — legacy anti-SQL-injection scrubber (in `PermissionHelpers.php` and `DocumentHelpers.php`).

**Reuse tip:** to add a new document type, you mostly add `sys_options` rows + a form definition in `Form.vue` + (optionally) filter cases. The engine, list, permission map and file machinery are agnostic.

---

## 8. Permission mapping for document types

`docPermCheck($type, $job)` in `app/Helpers/PermissionHelpers.php`:

```php
'op-doc-request' => ['edit' => 'per-05-02', 'read' => 'per-05-01', 'status' => 'per-05-02'],
'op-doc-client'  => ['edit' => 'per-06-02', 'read' => 'per-06-01', 'status' => 'per-06-02'],
'op-doc-offer'   => ['edit' => 'per-08-02', 'read' => 'per-08-01', 'status' => 'per-05-02'],
```

Enforcement points in `DocumentController::index` (every method):

1. `docPermCheck($type, read|edit)` → 403.
2. **Reseller override** — clients may edit/read their own client document (`op-doc-client` + `op-pert-reseller` + qnid ∈ `currentStatus.clientQnidList`).
3. **Offer response gate** — `op-doc-offer` requires `currentStatus.canResponse` (files approved).
4. **Offer ownership** — `offerOwnershipCheck($qnid)`: admins pass; resellers must have the offer's `cliid` in their client list; anything undeterminable fails closed.
5. **Offer editing state machine** (PUT): cancelled offers (`document_status = 0`) are untouchable for everyone; resellers may only edit offers whose last status is in `['doc_trans_offer_revision','doc_trans_created','doc_trans_offer_draft']`.
6. **DELETE** on offers is rejected outright ("cancel, don't delete") — use `/v1/trans/cancel-offer`.

Client-side equivalents live in `authStore.permissions.includes(...)` and route guards.

---

## 9. Status machine mechanics

- `POST /v1/trans/set-status` (`DocumentController::setStatus`) — validates `id` + `op_key`, permission via `docPermCheck($type, 'status')` (reseller may self-send offers with `doc_trans_offer_sended`). Writes a `UserLog` (`log-document-status-update`) + a `Transactions` row. Offer status changes trigger `EmailServiceProvider::sendOfferStatus`.
- **Offer special rules**:
  - `cancelOffer` → `documents.status = 0` (conditional write, race-safe), keeps all rows/files visible, logs with **no `before`** so the timeline classifies it as status change.
  - `reopenOffer` → `documents.status = 1` again; the previous status transaction remains the current status (cancel isn't a transaction).
  - `setStatus` on a cancelled offer **revives** it (`status = 1`) then writes the new status — cancellation is not terminal, only admins can do this (per-05-02 gate).
- Status auto-behaviors: when a reseller PUTs an offer whose last status is `doc_trans_offer_revision`, the system auto-writes `doc_trans_offer_revised` and emails.
- **Cron status transitions** (`app/Console/Kernel.php`):
  - `request:autoclose` — daily 01:00. Runs `Documents::tableList` with the `today-ended` filter (requests whose `contract_end_date` = today) and sets `doc_trans_request_end` ("Talep Süresi Bitti") on the first match whose status isn't already ended. Note: the current implementation processes only the first row (`$data[0]`) — TODO-flavored code; a new system should loop all matches.
  - `active-sessions:clean` — daily 02:00 (see `session-and-login-mechanics.md` §8).
  - `cleanupTempFiles()` — daily 03:00 (see `file-upload-versioning-mechanics.md` §8).

---

## 10. Person forms (the sibling engine)

`PersonsServiceProvider::setPerson($id, $data, $files, $fileGroup, $allData)` mirrors the document engine:

- `persons` table schema: `id, status, type_id, email_approved, approved, parent_id, spec_code, title, name, surname, phone, address, grp_code, qnid, balance, timestamps`. `type_id` → `sys_options` (`op-pert-*`); `qnid` is the public id (UUID, model boot); `spec_code` is the business code (used in exports/reports).
- Payload keys are prefixed differently:
  - `main_name`, `main_surname`, `main_status` → person columns (prefix `main_` stripped).
  - `user_*` → the login account (`User` model): `user_status`, `user_role`, `user_password`, `user_username`, `user_needs_refresh`.
  - `type_key` → resolves to `persons.type_id`.
  - `**userfacilitygroup**` → contact block entities (`contphone**userfacilitygroup**main-0`), grouped by the suffix after `**`.
  - `**userclientgroup**` → client bindings (`cliid/clicode/clititle`).
  - `permissions` → JSON array stored in the permission entity (`{id}**userpermissiongroup**{id}`).
- If no explicit permissions are sent, they are **derived from the assigned role template** (`SysRoleTemplate` by `op_key` = `users.role`).
- User status/role changes trigger `forceLogoutPerson` (kick the user out).
- After save: `PermissionService->refreshUserPermissionCache()` + `bumpUserPermissionVersion()` so live sessions pick up changes (see `session-and-login-mechanics.md`).
- `getPerson()` returns person + `contacts`, `permissions`, `clients` as JSON arrays (PostgreSQL `json_agg`).

---

## 11. Frontend rendering contract (`Form.vue`)

- `this.forms[tag]` holds **field definitions** per form tag: array of field objects `{ name, label, type, required, options, hidden, readOnly, mask, ... }` — see `op-doc-request-form`, `op-doc-client-form`, `op-doc-offer-form`, `op-doc-user-form`, `op-doc-flat-form` blocks. This is the closest thing to a form schema.
- `buildDynamicFForm(tag, dynamicId, data)` renders one row per `sys_con_ops` id; `data` comes from the GET response (`formFormat[tag][connId]`).
- On every input, `submitDynamicChanges(el)` writes into `formData.dynamicF[tag+'**'+rowId].entities[name]`; dates are normalized to `Y-m-d`, checkboxes to `1/0`, money masks swap `,`→`.`.
- **Files**: selecting a file triggers an **immediate** `POST /api/v1/temp-upload` (see file doc). The returned reference JSON is stored in `formData.files[key]` and later appended to the save envelope. Failed uploads are retried once at save time; pending uploads are awaited before submit.
- `removedData` is pushed when rows/entities are removed in UI (row delete → `{ key: 'row', id: connId }`; field delete → `{ id: connId, key: entityTag }`).
- On load, existing data is injected via `formDataStore.setData(response.data.formFormat)`; the store also carries `rawData` for pages that need the full document.

---

## 12. Gotchas / sharp edges

1. **`ctitle` magic** — `Sys_options::where(['ctitle' => ...])` is a fragile lookup. Keep `ctitle` values (`type_id`, `sub_type_id`) and `op_key` values (`form-main`, `form-file`) consistent when seeding a new system.
2. **Entity upsert for plain fields** (`table_tag = 'sys_con_ops'`) — updates in place, so plain fields have **no history**. Only files keep versions (via new rows).
3. **File entity activeness is derived** — `getFormData` filters inactive files out, and removed-data processing ignores inactive rows. Never trust `entity_value` alone; always join the file status.
4. **No `ORDER BY` in `getFormData`** — `cliid` may sit on any form row; loops that need it must scan (see `offerOwnershipCheck`).
5. **`main_*` whitelist** — new document types can't add generic columns through the form payload; add real columns to `GENERIC_WRITABLE_MAIN_FIELDS` deliberately.
6. **`before` in audit logs** is used by the UI to distinguish edits from status changes — keep the convention (omit `before` for status-only events).
7. **SQL injection discipline** — qnid-style params reaching `getFormData`/`tableList` must be UUID-validated; filters go through `noInject()` + `strip_tags()`.
8. **Transactions are the status** — always read latest `op-trans-<type>` per target; the `documents.status` column is only active/passive/cancelled.

---

## 13. Adding a new document type (blueprint for reuse)

1. Seed `sys_options`: `op-doc-{type}` (document type), `op-doc-{type}-form` (form tag), `op-trans-op-doc-{type}` statuses (`doc_trans_*`), `op-{fieldfile}` file type keys (`op-...` used for file-type titles).
2. Add a form definition block in `Form.vue` (`this.forms['op-doc-{type}-form']`).
3. Add a page pair `{Type}Form.vue` / `{Type}List.vue` (copy `RForm`/`RList` patterns).
4. Add `docPermCheck` map entries + permission codes (`per-XX-01` read, `per-XX-02` edit) + catalog rows via `php artisan permission:create`.
5. If the type needs client scoping, add the `switch($formType)` case in `Documents::tableList` and the ownership check in the controller.
6. If it has files, nothing extra is needed — file machinery is type-agnostic.