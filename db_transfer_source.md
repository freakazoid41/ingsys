# DB Source Spec - For Target Session (Read This To Know What To Take From Where)

Source: TedarikApp Laravel 9. Current `config/database.php:18 default=sqlsrv`. Also supports `pgsql` via branches. No FK constraints in DB - all joins are logical.

## 1. Connections - where to connect + what each is for

### 1a. DB server connections (`config/database.php:36-96`, `.env: DB_CONNECTION,DB_HOST,DB_PORT,DB_DATABASE,DB_USERNAME,DB_PASSWORD`)

- `sqlsrv` (`database.php:81-95`): CODE DEFAULT (`database.php:18 default=env(DB_CONNECTION,sqlsrv)`). `trust_server_certificate=true`. All `TOP 1 / convert(varchar) / STUFF..FOR XML / offset fetch` branches are for this. NOTE: current `.env:19 DB_CONNECTION=pgsql` OVERRIDES default at runtime - check live DB with `SELECT @@VERSION` vs `SELECT version()` before dump.
- `pgsql` (`database.php:66-79`): secondary supported + CURRENT `.env` value. Code branches on `env(DB_CONNECTION)==pgsql` for `LIMIT/OFFSET, TO_CHAR, ::integer/cast, string_agg/json_agg, description::json->>` (`Documents.php:142-163`, `Persons.php:77-84`, `User_logs.php:97-141`, migration `2022_12_05_080953` generated cols). Use if target is Postgres.
- `mysql` (`database.php:46-64`): defined but NOT supported - no `mysql` branch in any `tableList()`. Target mysql requires rewriting all raw SQL. Flag as risky.
- `sqlite` (`database.php:38-44`): only for `phpunit.xml` (`CACHE_DRIVER=array, QUEUE_CONNECTION=sync`), not prod.
- `redis` (`database.php:123-150`): cache/queue config present but `QUEUE_CONNECTION=sync` + custom `Excel_queue` table used instead. No Redis data to migrate.
- `SYS_CODE` tenancy (`public/index.php:6`): `$GLOBALS['SYS_CODE']=str_contains(HOST,gdzelektrik)?GDZ:ADM`. Every query filters `sys_code 4000/5000`. Target must preserve this col or drop filter.

### 1b. Table list
- Table list (17 files = 16 creates + 1 alter `add_ip_and_user_agent`, so 16 tables from migrations + 1 missing): `users, password_resets, failed_jobs, personal_access_tokens, sys_options, persons, user_logs, products, product_con_op, transactions, document_details, documents, user_con_ops, message_contents, person_con_ops, document_files` + `excel_queue (model Excel_queue.php:13 table=excel_queue singular, NO migration file - check prod DB directly. Note bug: TestGdzInfoMail.php:243 queries `excel_queues` plural with fallback comment)`

Export order to keep IDs: `sys_options -> persons -> users -> user_con_ops/person_con_ops -> products/product_con_op -> documents -> document_details -> document_files -> transactions -> message_contents -> user_logs -> personal_access_tokens -> excel_queue`

## 1c. Which table holds WHAT (one-liner for target session)

- `sys_options`: holds ALL dropdown/status words. `op_key` e.g. `per-30, status-transfer-ended, file-check-wait`. No business data, only meanings.
- `persons`: holds people/companies. `spec_code`=SAP LIFNR, `sys_code`=4000/5000, `parent_id`=sub-supplier link.
- `users`: holds logins. `person_id` links to persons, `email+password` for auth.
- `user_con_ops / person_con_ops`: hold permissions. Just 3 ints: who + which `op_key`.
- `products / product_con_op`: hold catalog (rarely used).
- `documents`: holds ORDER HEADER. One row per `order_no` (EBELN). No lines, no files here.
- `document_details`: holds ORDER LINES + SERIALS. `document_id`=which order, `parent_id`=0 main line else child serial row, `prod_code/srl_code/quantity/deal_price`.
- `document_files`: holds FILE POINTERS, NOT file bytes. `description`=encrypted filename on disk, `order_id`=always header id, `document_id`=scoped id (see §3), `op_id/type_id`=what kind of form.
- `transactions`: holds STATUS HISTORY. No current-status column anywhere else - latest `transactions` row IS the status. `target_id 0=order 1=line 2=file`.
- `message_contents`: holds email/SMS templates (`op-step2` etc).
- `user_logs`: holds AUDIT. Who (`user_id`) did what (`log_id`) with JSON `description={request:{ref_id,target_id}}`.
- `personal_access_tokens / password_resets / failed_jobs`: hold sessions/reset tokens/failed jobs. Skip unless needed.
- `excel_queue`: holds async jobs (report/mail). `description` JSON + `status 0/1/3`.
- DISK (not DB): holds real bytes - `public/documents/` SINGLE folder (verified `filesystems.php:42 root=public_path()`) + `storage/app/public/awaiting_mails.json` (retry queue, not docs).

## 2. Full schema (from `database/migrations/*.php`)

### `sys_options` - DICTIONARY, MUST COPY FIRST
`id, parent_id int default 0, title(50), ttitle(50), ctitle(50), op_key(50) default '-', timestamps`
Seed in `2022_12_05_073600`: `op-supp/op-off (Persons.type_id), op-deps/dep-01/02/03 (Person_con_ops), op-perm/per-01..04,07..32 (NOTE: per-05,06 MISSING, not seeded) + per-25 log view, log-login/logout/post/put/delete (User_logs.log_id), option (Product_con_op), op-doc/op-prd-doc/op-ord-doc/op-testdoc/op-imgdoc/op-accmetform/op-accform/op-factory-form (Document_files.op_id/type_id), status-wait/file-check/file-rejected/factory-check-wait/transfer-ended (NOTE: status-successful/unsuccessful/transfer are COMMENTED OUT lines 392-410, NOT seeded), t-file-waiting/file-inspect-*/detail-process-*/od-success, file-check-wait/okey/failed/refreshed, trans-one/multiple-piece, supp-factory/transfer, op-sms/op-mail + op-step1/2/3/4/7/8/5/6/forgot-pass (NOTE duplicate op-step7 at 566+596)`. Map by `op_key`, NOT by `id` - IDs differ per env.

### `persons` - companies/people
`id, type_id smallint (0 unknown, 1=supplier 2=office VERIFIED: AuthController.php:114 `type_id==1` => load child suppliers, QueueProvider.php:389 `where type_id 2` => office mail list), parent_id int (0 root, else -> persons.id sub-supplier, AuthController.php:115), sys_code int default 4000 (4000=GDZ 5000=ADM - FILTER ALL QUERIES BY THIS), spec_code(50) LIFNR `ltrim 0`, title(50), name(50) required, surname(50), phone(50), address(250), qnid uuid, timestamps`
Take: `SELECT * FROM persons` - keep `id, spec_code, sys_code, parent_id`.

### `users` - logins
`id, status bool default true, person_id int -> persons.id (0 = orphan, blocked by PermCheck), name, email unique, email_verified_at, password(60) bcrypt, qnid uuid, remember_token, timestamps` + seed `admin@admin`
Take: `users.id, person_id, email, status`.

### `user_con_ops / person_con_ops` - perms
`id, op_id int -> sys_options.id, type_id int -> sys_options.id, user_id/person_id int, timestamps`
Example: `user_con_ops.type_id = (SELECT id FROM sys_options WHERE op_key='per-30')` = can receive mail.
Take whole tables, remap `op_id/type_id` via `op_key` lookup on target.

### `products / product_con_op`
`products: id, title(50), prod_code(50), timestamps` | `product_con_op: id, op_id, type_id (-> sys_options option), product_id -> products.id`
Low priority, catalog only.

### `documents` - order header (CORE)
`id, transfer_id int 0, suppliment_id int 0, order_no(50)=EBELN, buying_no(50)=SUBMI, ctitle(150)=supplier name MCOD1, sys_code(10) 4000/5000 string!, spec_code(50) LIFNR, form_code/form_supp(50), form_desc(500), suppliment_address(300), qnid uuid, inserted_at, created_at/updated_at`
Source: SAP `ZMM_FM_MTP_GET_PO_INFO*` via `DocumentsProvider::processSapOrder()`. Dedupe by `order_no`.
Take: all, keep `id, order_no, sys_code, spec_code`.

### `document_details` - order lines (CORE)
`id, document_id int -> documents.id, parent_id int 0 (0=main line, >0=serial child of another detail), prod_id int 0, suppliment_id int, quantity float(15,3), main_quantity float, deal_price float, received_price float, unit(5), title(50), prod_code(50)=MATNR**EBELP, srl_code(100) serial, srl_date timestamp, met_code/met_title/grp_code/grp_title/brd_title(50), qnid, suppliment_address(300), timestamps`
Take: all, keep `id, document_id, parent_id, prod_code, srl_code, quantity`.

### `document_files` - uploads (CORE) + FILE LOCATIONS
`id, type_id int -> sys_options.id (op-testdoc/op-imgdoc/op-accmetform/op-accform/op-factory-form), op_id int -> sys_options.id, document_id int, order_id int -> documents.id (always order header), status smallint 1, description(1000)=ENCRYPTED filename (AES-128-CBC pickle, PermissionHelpers.php:25-27, decrypt via EncryptionProvider), qnid, timestamps`
Rule verified in `OrderController.php:186-199 vs 292-302`:
- `op_id = op-prd-doc` => ORDER ITEM file: `document_id -> document_details.id` (productId), `order_id -> documents.id`. Types: `op-testdoc` (test doc, OrderController.php:188), `op-imgdoc` (image, OrderController.php:232).
- `op_id = op-ord-doc` => ORDER file: `document_id -> documents.id`, `order_id -> documents.id` (same). Types: `op-accform` mkf (OrderController.php:294), `op-accmetform` mcmf (OrderController.php:328).
PHYSICAL LOCATION (single, VERIFIED): `config/filesystems.php:42 public disk root=public_path()` + `PermissionHelpers.php:19 Storage::disk(public)->putFileAs(documents,...)` + `DocumentHelpers.php:100 public_path()/documents/+decrypt(doc)` + delete `Documents.php:104 / Document_files.php:53 disk(public)->delete(documents/...)` + attach `QueueProvider.php:413 public_path(documents/)`. ALL = `public/documents/<decrypted filename>`. Filename format: `time()+random5+first10chars(original)` (PermissionHelpers.php:17-18), allowed `jpg/png/jpeg/pdf <=42MB`. NO files in `storage/app/*` for docs (only `storage/app/*.txt` OTP/lock files + `storage/app/public/awaiting_mails.json`).
Resolution for target import:
- Order file bytes: `SELECT d.order_no, f.description FROM document_files f JOIN documents d ON d.id=f.order_id WHERE f.op_id=(SELECT id FROM sys_options WHERE op_key='op-ord-doc')` -> file `public/documents/<decrypt(description)>`.
- Item file bytes: `SELECT d.order_no, dt.prod_code, f.description FROM document_files f JOIN document_details dt ON dt.id=f.document_id JOIN documents d ON d.id=f.order_id WHERE f.op_id=(SELECT id FROM sys_options WHERE op_key='op-prd-doc')` -> same folder.
Take: all rows + copy WHOLE `public/documents/` folder. Must decrypt via `EncryptionProvider` if target needs clear names.

### `transactions` - status history (CORE - DO NOT MISS)
`id, target_id smallint: 0=order(documents.id) 1=detail(document_details.id) 2=file(document_files.id), type_id int -> sys_options.id (status-*, file-check-*, t-file-*, etc), ref_id int (id in target table), description(300) reason, note(300), timestamps`
Current status = `SELECT so.op_key FROM transactions JOIN sys_options ON id=type_id WHERE ref_id=X AND target_id=Y ORDER BY id DESC LIMIT 1`. See `Document_files.php:97-108`, `Documents.php:164-170`.
Take: all rows, preserve `ref_id/target_id/type_id(op_key)`.

### `message_contents` - mail templates
`id, op_id int -> sys_options.id (op-mail/op-sms), type_id int -> sys_options.id (op-step1..8), content text, timestamps`
Take: all, remap via `op_key`.

### `user_logs` - audit (PGSQL-SPECIFIC!)
`id, user_id int -> users.id, log_id int -> sys_options.id (log-*), description text JSON `{request:{ref_id,target_id,...}}`, timestamps` + GENERATED columns (only on pgsql!): `target_id varchar GENERATED ALWAYS AS (description::json->'request'->>'target_id')`, `ref_id varchar GENERATED ...` from `2022_12_05_080953`. This `DB::statement` breaks on mysql/sqlsrv. Target must parse JSON manually or drop generated cols.
Take: `id, user_id, log_id(op_key), description, created_at`. Filter by `sys_code/spec_code` via joins in `User_logs.php:97-141` if needed.

### `personal_access_tokens, password_resets, failed_jobs`
`personal_access_tokens: id, tokenable morphs, name, token(64) unique sha256, abilities, last_used_at, expires_at + ip_address/user_agent (2024_08_08 migration)` - sessions, can skip unless need live login. `password_resets: email, token, created_at` - skip. `failed_jobs: uuid, connection, queue, payload, exception` - unused (`QUEUE_CONNECTION=sync`), skip.

### `excel_queue` - no migration! VERIFIED
`Model Excel_queue.php:13 table=excel_queue singular, fillable user_id, description` but code uses extra `status` col (not fillable, set via `->status=` + `save()`). `QueueProvider.php:40,51-52,59-60,80-81,300-301,315`: `status 0=pending -> 1=progress -> delete() on success, 3=failed`. `description` JSON = `{job_type: order_created_mail|report, order_no, files[], query, header[], report_name: orden/mtkor/smtkor, attempts/max_attempts}`. `processOrderCreatedMailJob:344-347` loads `details WHERE parent_id=0 AND document_id=order.id`. Dump prod schema via `sp_help excel_queue` / `\d excel_queue`. Take pending rows only if migrating live queue, else skip.

## 3. How tables connect in pipeline + what each connection is FOR

No FK in DB (`grep foreign => 0 hits in database/migrations`). All integrity is in code. If you miss a link, orphan rows + wrong status.

```
persons.id <- users.person_id           // FOR: login -> person. users.person_id=0 = orphan, blocked by PermCheck. Cascade: Persons.php:45-56 deleting person deletes its Users + Person_con_ops + User_con_ops.
persons.id <- person_con_ops.person_id  // FOR: departments/offices per person. Join: Persons.php:78-84 person_con_ops + sys_options.
users.id <- user_con_ops.user_id        // FOR: permissions per login (sper-*, per-30 mail). Loaded to session in AuthController.php:127-134.
users.id <- user_logs.user_id           // FOR: who did what. Join: User_logs.php:22-24 persons via users.
sys_options.id -> ALL type/op/log cols // FOR: dictionary. transactions.type_id, document_files.op_id/type_id, user_logs.log_id, message_contents.op_id/type_id, user_con_ops/person_con_ops. ALWAYS remap by op_key, never id.
persons.spec_code ~ documents.spec_code // FOR: tenant supplier match. NOT FK, `LIKE %spec_code%` in DocumentsProvider.php:43-45, Persons spec_code=LIFNR ltrim 0. Keep both cols as strings.
documents.id <- document_details.document_id // FOR: order -> lines. Cascade: Documents.php:52-71 deleting document deletes its details + transactions(target 1) + files(op-prd-doc).
document_details.id <- document_details.parent_id // FOR: line -> serial children (srl_code C57684). Self-ref, 0=main, >0=child detail id (OrderController.php:282-284).
documents.id <- document_files.order_id (always)  // FOR: file -> order header. Always set (OrderController.php:199,302).
documents.id OR details.id <- document_files.document_id // FOR: file scope. op-ord-doc => documents.id, op-prd-doc => details.id (OrderController.php:186-199 vs 292-302). Check op_id to resolve.
document_files.id -> transactions(ref_id,target 2) // FOR: file status history (file-check-wait/okey/failed). Cascade: Document_files.php:46-54 + Documents.php:106-114 delete transactions + storage file on file delete.
documents.id -> transactions(ref_id,target 0)      // FOR: order status (status-file-check/transfer-ended). Cascade: Documents.php:75-84.
details.id -> transactions(ref_id,target 1)         // FOR: line status (file-inspect-*). Cascade: Documents.php:57-64.
transactions.type_id -> sys_options.id              // FOR: status meaning. Latest row = current status (Document_files.php:97-108, Documents.php:164-170).
message_contents.op_id/type_id -> sys_options.id    // FOR: mail template lookup (op-step2 etc). QueueProvider.php:350-351.
personal_access_tokens.tokenable -> users.id (morph) // FOR: API session. Skip unless live login needed.
```

Integrity cascades to replicate on target import/delete:
- Delete person => delete users + both *_con_ops (Persons.php:45-56).
- Delete document => delete details + transactions 0/1/2 + files + storage files (Documents.php:49-89,92-120).
- Delete file => delete transactions target 2 + storage file (Document_files.php:44-54).

### File vs Order connections - how to read them

ORDER chain (header -> lines -> serials):
```
documents.id (order_no=2010013739) 
  └─ document_details.document_id = documents.id (prod_code=MATNR**EBELP, quantity)
       └─ document_details.parent_id = document_details.id (srl_code=C57684, unit=PRNT) // child serial
```

FILE chain (pointer -> bytes + status):
```
document_files.order_id ALWAYS = documents.id // which order, for filtering by sys_code
document_files.document_id = 
  IF op_id=op-ord-doc THEN documents.id (order-level: mkf/mcmf factory forms, OrderController.php:302,336)
  IF op_id=op-prd-doc THEN document_details.id (line-level: testdoc/imgdoc, OrderController.php:198,243)
document_files.description = encrypted filename // decrypt via EncryptionProvider, real file in public/documents/ SINGLE folder
document_files.type_id = WHAT kind (op-testdoc/op-imgdoc/op-accform/op-accmetform/op-factory-form)
document_files.id -> transactions(ref_id=files.id,target_id=2) // file status: file-check-wait/okey/failed/refreshed
```

Example full path for one serial part:
`persons(spec_code=301966) ~ documents(spec_code=301966,order_no=X) -> details(document_id=X,prod_code=Y) -> details(parent_id=detail.id,srl_code=C57684) -> files(document_id=detail.id,op-prd-doc) -> transactions(target 2) + transactions(target 1 on detail) + transactions(target 0 on document)`

## 4. Data-integrity protection (target session MUST follow)

1. Order: `sys_options -> persons -> users -> *_con_ops -> products -> documents -> details -> files -> transactions -> message_contents -> user_logs`. Keeps IDs valid because no DB FKs to enforce, orphans are silent.
2. IDs: keep old `id` values on import (SET IDENTITY_INSERT ON for sqlsrv). If you re-auto-increment, rebuild `document_id/order_id/ref_id/parent_id` maps. Keep `qnid` uuid as-is (used in URLs `order-file/{doc}`).
3. Dictionary: remap ALL `*_id -> sys_options.id` via `op_key` string lookup, never copy numeric id blindly. Seed may differ per env.
4. Transactions: import in one DB transaction per order (`DocumentsProvider::processSapOrder` style). `transactions` rows without matching `ref_id/target_id` = lost status -> UI shows default `Beklemede/Inceleniyor`.
5. Files + DB sync: `document_files.description` without file on disk = broken download; file without row = orphan. Copy WHOLE `public/documents/` + verify count `SELECT count(*) FROM document_files` == file count (decrypt names via EncryptionProvider pickle key).
6. Types: `persons.sys_code int` vs `documents.sys_code varchar(10)` - cast, don't truncate. `quantity/deal_price float(15,3)`, `srl_date timestamp`, `password(60)` bcrypt - keep lengths.
7. Tenancy: keep `sys_code 4000/5000` + `spec_code`. Dropping them breaks `WHERE sys_code=` filters and `LIKE %spec_code%` supplier match.
8. pgsql-only: drop/rewrite `user_logs.target_id/ref_id` generated cols (`description::json`) if target is not pgsql, parse JSON in app instead.
9. Verify after import:
```sql
-- orphans must be 0
SELECT count(*) FROM users WHERE person_id NOT IN (SELECT id FROM persons) AND person_id<>0;
SELECT count(*) FROM document_details WHERE document_id NOT IN (SELECT id FROM documents);
SELECT count(*) FROM document_files WHERE order_id NOT IN (SELECT id FROM documents);
SELECT count(*) FROM transactions WHERE target_id=0 AND ref_id NOT IN (SELECT id FROM documents);
SELECT count(*) FROM transactions WHERE target_id=1 AND ref_id NOT IN (SELECT id FROM document_details);
SELECT count(*) FROM transactions WHERE target_id=2 AND ref_id NOT IN (SELECT id FROM document_files);
```

## 5. Gotchas for target session

1. Filter by `sys_code`: every list query adds `sys_code='4000' or '5000'` (`Documents.php:179`, `Document_files.php:144`). If target is single-tenant, keep column but default it.
2. Map `sys_options` by `op_key` string, never by numeric `id`.
3. `user_logs.target_id/ref_id` generated cols are pgsql-only (`::json`, `->>`). Rewrite for mysql/sqlsrv.
4. Raw SQL in `tableList()` uses `TOP 1` vs `LIMIT 1`, `convert(varchar,)` vs `TO_CHAR`, `::integer`, `string_agg` vs `STUFF/FOR XML` - see `Documents.php:142-163`, `Persons.php:77-84`. If target driver changes, rewrite those.
5. Files are NOT in DB: single folder `public/documents/<decrypted description>` (verified `filesystems.php:42 + PermissionHelpers.php:19 + DocumentHelpers.php:100`). Copy whole folder. Plus `storage/app/public/awaiting_mails.json` (retry queue) + `storage/app/*.txt` (OTP/locks, skip).
6. `qnid` uuid is public external ID, keep it if target uses it in URLs (`order-file/{doc}` decrypt).
7. Tenancy key: `$GLOBALS['SYS_CODE']` from `public/index.php:6` host check. Target must set `SYS_CODE` or remove filter.

## 6. Minimal export queries (run on source)

```sql
SELECT * FROM sys_options ORDER BY id;
SELECT * FROM persons ORDER BY id;
SELECT * FROM users ORDER BY id;
SELECT * FROM documents ORDER BY id;
SELECT * FROM document_details ORDER BY id;
SELECT * FROM document_files ORDER BY id;
SELECT * FROM transactions ORDER BY id;
SELECT * FROM message_contents ORDER BY id;
SELECT * FROM user_logs ORDER BY id;
SELECT * FROM user_con_ops ORDER BY id;
SELECT * FROM person_con_ops ORDER BY id;
```
