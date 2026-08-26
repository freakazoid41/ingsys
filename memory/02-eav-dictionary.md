# EAV & Universal Dictionary — The Core Abstraction

> **Tables:** `sys_options`, `documents`, `sys_con_ops`, `sys_con_entities` (+ `persons` mirror)  
> **Seeders:** `panel/database/seeders/SysSeeder.php:689`, `SysRoleTemplateSeeder.php:168`  
> **Migrations:** `2022_12_05_*_sys_options`, `2024_04_04_*_documents`+`sys_con_ops`, `2024_05_06_*_sys_con_entities`, `2022_12_05_*_persons`

---

## 1. sys_options — The Dictionary

Every enumerated value in the system is a row here. No enums in code.

| Column | Meaning |
|--------|---------|
| `id` | PK, referenced as `type_id` everywhere |
| `op_key` | machine key, e.g. `op-doc-request`, `doc_trans_offer_approved`, `per-05-01`, `log-tender-update` |
| `group_key` | bucket, e.g. `op-doc`, `op-doc-forms`, `op-trans-op-doc-offer`, `op-per-types`, `op-pert`, `trans`, `op-logs` |
| `title` | human label (TR) |
| `ctitle` | which column it classifies (`type_id`, `sub_type_id`, `group_key` etc) |
| `ttitle` | which table it belongs to (`documents`, `persons`) |
| `status` | 1 active |

### Important group_keys (grep `group_key` in SysSeeder)

| group_key | Contents | Used as |
|-----------|----------|---------|
| `op-doc` | document types: `op-doc-request|offer|client|flat` + `op-doc-client-main` **+ NOW `op-doc-order|order-item|transfer` (`OrderSystemSeeder.php:06`)** | `documents.type_id` |
| `op-doc-forms` | form definitions: `op-doc-request-form|offer-form|client-form|user-form|flat-form|user-contact-form|user-client-form|user-permission-form|user-notification-form` **+ `op-doc-order-form|order-item-form|transfer-form`** | `sys_con_ops.type_id` |
| `op-per-types` / `op-apt-types` | old flat/apt types (legacy) | — |
| `op-pert` | person types: `op-pert-admin|reseller` | `persons.type_id` |
| `op-file-types` | file categories: 5 entries | `sys_options title for file UI` |
| `trans` | generic trans types (19) | `transactions.type_id` when `op_id=0` |
| `op-trans-op-doc-request` / `op-trans-op-doc-offer` | status machines per doc type | `transactions.type_id` (dynamic group `op-trans-{docOpKey}`) |
| `op-trans-op-doc-order` / `op-trans-op-doc-transfer` | **NEW** Sipariş/Transfer status: `doc_trans_order_created/transfer_sent/approved/rejected/files_rejected` + `doc_trans_transfer_created/sent/approved/rejected` (`files_rejected` = "Reddedilen Dosyalar Mevcut", auto-set by `syncOrderStatusFromFiles` when any order/item file is rejected) | `transactions.type_id` |
| `op-file-types` extended | **+ `op-transfer_kabul_file` (Malzeme Kabul), `op-transfer_cins_file` (Cins-Miktar), `op-item_test_file`, `op-item_images_file`** | `sys_options title for file UI` |
| `op-logs` | log kinds: 24 `log-*` | `user_logs.type_id` |
| `op-cur-types` | currencies TRY/USD/EUR/GBP | `currencies` |
| `op-con-ops` | sub_type_id values: `form-main|form-file|personnel-main` | `sys_con_ops.sub_type_id` |

Quick seed introspection:
```sql
SELECT group_key, op_key, title FROM sys_options ORDER BY group_key, op_key;
-- filter: where group_key='op-doc' → document types
--         where group_key='op-doc-forms' → form schemas
--         where group_key LIKE 'op-trans-%' → status values per doc type
```

Adding a new doc type = 1 row in `op-doc` + 1 row in `op-doc-forms` (+ optionally rows in `op-trans-{newKey}` for its state machine).

---

## 2. documents — The Entity

```sql
-- panel/database/migrations/2024_04_04_132901_create_documents_table.php
id          bigserial PK
qnid        varchar(256) unique — external UUID, the only id the frontend sees
type_id     bigint → sys_options.id (op-doc-* )
title       varchar — only GENERIC_WRITABLE_MAIN_FIELDS; most data is in EAV, not here
starting_at / ending_at  date — writable via main_*
person_id   text — actually persons.qnid of creator! (not integer, misnamed)
grp_code    varchar — tenant tag YATAGAN/CATES/Her İkisi normalized from target_type entity
parent_id   bigint — unused? (legacy apt)
status      int default 1 — 1 active, 0 cancelled/passivated (offer cancel vs remove)
created_at / updated_at
```

Key behaviors:
- `qnid` generated in model `creating` hook (uuid)
- `grp_code` auto-set from `target_type` entity value in `registerContent:184-193` with TR-char normalization
- `title/starting_at/ending_at` are the ONLY generic-writable `main_*` fields (`DocumentServiceProvider::GENERIC_WRITABLE_MAIN_FIELDS`). Everything else is EAV.
- `status=0` for offers = cancelled but still visible (see `cancelOffer` vs `removeContent` distinction)

---

## 3. sys_con_ops — The Section Instance

One row = one form instance attached to one entity.

```sql
id           bigserial PK
main_id      bigint → documents.id OR persons.id
conn_id      bigint default 0 — parent ops id (hierarchical, but always 0 in this app)
type_id      bigint → sys_options.id where op_key = form key (e.g. op-doc-request-form)
sub_type_id  bigint → sys_options.id where op_key = form-main (documents) or personnel-main (persons)
status       int default 1
created_at / updated_at
```

- `type_id` is the form discriminator (`op-doc-request-form` vs `op-doc-client-form`)
- `sub_type_id` is `form-main` for EAV values, `form-file` for file containers (but file EAV still uses `form-main` conn + `table_tag=document_files` — confusing)
- `main_id + type_id + sub_type_id` should be unique per entity, but not enforced (DB allows multiples, code `updateOrCreate` handles dup)
- `conn_id=0` always — tree was designed but never used

---

## 4. sys_con_entities — The Value

One row = one field value.

```sql
id           bigserial PK
conn_id      bigint → sys_con_ops.id
entity_tag   varchar — "{field}**{group}**{key}" composite key
entity_value text — value or file id (encrypted filename lives in document_files, not here)
table_tag    varchar — 'sys_con_ops' for scalar values, 'document_files' for files, 'user_con_ops' for persons
status       int default 1
created_at / updated_at
```

Examples:
```
conn_id=123, entity_tag='title**op-doc-client-form**new-1',     entity_value='Acme Ltd',      table_tag='sys_con_ops'
conn_id=123, entity_tag='cont_imza_file**clientimzasirku**new-1', entity_value='45',             table_tag='document_files'  (45 = document_files.id)
conn_id=456, entity_tag='cliid**userclientgroup**0',            entity_value='a1b2-c3d4-qnid',  table_tag='user_con_ops'
conn_id=456, entity_tag='456**userpermissiongroup**456',        entity_value='["per-05-01"]',   table_tag='user_con_ops'    (permissions JSON array)
```

Query pattern (all raw SQL, no indexes on `conn_id`+`entity_tag` — perf risk):
```sql
SELECT * FROM sys_con_entities WHERE conn_id = ? AND entity_tag = ? AND table_tag = 'sys_con_ops';
```

---

## 5. Persons Mirror — Same EAV, Different Ops

```
persons         — id, qnid, name, surname, type_id→sys_options(op-pert-*), status, balance
users           — id, person_id→persons.id, email, password (bcrypt), role (op_key string, NO FK), needs_refresh, status, current_team_id, bg_image
sys_con_ops     — main_id=persons.id, type_id∈{op-doc-user-contact-form, op-doc-user-client-form, op-doc-user-permission-form, op-doc-user-notification-form}
sys_con_entities — field values per connection
```

Per-person EAV connections (one `sys_con_ops` per form type per person, via `upsertConnectionEntity`):
| form op_key | entity_tags stored | table_tag |
|-------------|-------------------|-----------|
| `op-doc-user-contact-form` | `contmail**userfacilitygroup**k`, `contphone**` etc | `user_con_ops` |
| `op-doc-user-client-form` | `cliid**userclientgroup**k`, `clicode**`, `clititle**` | `user_con_ops` |
| `op-doc-user-permission-form` | `{personId}**userpermissiongroup**{personId}` → JSON array | `user_con_ops` |
| `op-doc-user-notification-form` | `{personId}**usernotificationgroup**{personId}` → JSON array | `user_con_ops` |

`persons.qnid` is the external id; `users.person_id` is the integer FK.

---

## 6. Transactions & UserLogs — State Machine & Audit

```
transactions — id, qnid, op_id(0=document,1=file), type_id→sys_options(doc_trans_*|doc_file_*), target_id(documents.id or document_files.id), log_id→user_logs.id, amount/cur_id/rel_id/sign/period/note, description JSON, created_at
user_logs    — id, user_id→users.id, sys_code, relation(documents|persons), relation_id, type_id→sys_options(log-*), description JSON {before, after, desc, note}, created_at
```

- Every `registerContent` creates `UserLog log-tender-update` with `before/after = getFormData()` JSON
- Every `setStatus` / `documentFileStatus` creates `UserLog` + `Transactions` (transactions.type_id resolved from `op_key`)
- File transactions have `op_id=1` — dashboard queries filter `op_id=1` for file status history
- `documents.status` vs `transactions` — `documents.status` is binary (active/cancelled), the rich history is in `transactions` aggregated as `status JSON` in `getFormData:373`.

---

## 7. Other Supporting Tables

| Table | Purpose |
|-------|---------|
| `document_files` | `id, qnid, description (encrypted filename), status, relation('documents'), relation_id, replaced_id (version chain)` |
| `active_sessions` | `user_id, token_id, session_id, ip, ua, current_status JSON, permission_version, force_logout, last_seen` |
| `sys_role_templates` | `op_key unique, title, permissions JSON, immutable bool` + `sys_role_template_audit` |
| `sys_permission_catalogs` | `code per-XX-YY unique, title, group, metadata JSON {parent_code}` — tree via metadata |
| `sys_notification_types` | `op_key notif-*` |
| `notification_logs` | `type, to, subject, body, status pending/sent/error, detail JSON, attempts` |
| `currencies` | `main_cur, target_cur, amount decimal(15,3)` — truncated daily from TCMB XML |
| `sessions/cache/jobs` | standard Laravel, driver=database |
| `personal_access_tokens` | Sanctum tokens |
| `teams/team_user/team_invitations` | Jetstream dead (unused) |

---

## 8. Read/Write Summary

**Write:** `DocumentServiceProvider::registerContent` (documents) / `PersonsServiceProvider::setPerson` (persons) — both wrap in `DB::transaction`, handle `dynamicF→sys_con_ops/entities`, `dynamicFile→document_files`, `removedData` deletions, `target_type→grp_code`, `clicode` immutability, `Transactions/UserLogs`. See `memory/01-form-engine.md:3`.

**Read:** `getFormData(qnid)` / `getPerson(qnid)` — raw SQL LEFT JOINs `sys_con_ops→sys_con_entities` + lateral `json_build_object` for files + `json_agg` for transactions. Returns nested `{document, formFormat: {formKey: {rowId: {entities, files}}}}`. No Eloquent relations, no FKs.

**Delete:** `removeContent` soft-sets `documents.status=0` (+ deactivates `users` if client). `cancelOffer` conditional `update status=0 where status=1` + `UserLog`. `Document_files.status=0` for file version replacement.

**Permissions:** `sys_con_entities where conn→sys_con_ops→sys_options op_key='op-doc-user-permission-form'` → JSON array → `PermissionService` cache+session.

---

## 9. Adding New Data — Checklist

- [ ] Add `sys_options` rows: doc type (`op-doc-{new}` / `op-doc` group) + form type (`op-doc-{new}-form` / `op-doc-forms`) + optional status keys (`doc_trans_{new}_*` / `op-trans-op-doc-{new}`)
- [ ] If new transaction types, add `group_key='op-trans-op-doc-{new}'` rows
- [ ] Add `Form.vue` schema in `forms['op-doc-{new}-form']`
- [ ] Optionally add `sys_permission_catalogs` entries `per-{NN}-*` + wire `docPermCheck` map in `PermissionHelpers.php`
- [ ] No migration needed for fields — but add indexes on `sys_con_entities(conn_id, entity_tag)` if performance suffers (currently missing)
