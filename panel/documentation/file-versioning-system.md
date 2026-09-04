# EAV File Versioning System — Complete Reference

> **Source:** INGSYS Order Management (`tedarikPanel` branch) — verified in production
> **Purpose:** Portable reference for implementing file replacement + version history on any EAV-based project (clients, offers, orders, forms, etc.)

---

## 1. Overview

Files are stored on the **`public` disk** (`storage/app/public/{documents,temp}/`), referenced from the DB through the EAV engine, with a **linked-list versioning model**: every new upload of the same field creates a new `document_files` row whose `replaced_id` points at the version it replaced. Old versions keep their file on disk, are marked `status = 0`, and are served through the entity history (`old_versions`).

File identity in the DB is an **encrypted filename** (`document_files.description`) — never the real path. Access is via short UUID links that decrypt server-side.

### 1.1 Architecture — How It Works

```
document_files     — physical file record (id, qnid, description=encrypted name, relation, relation_id, status, replaced_id)
sys_con_entities   — form link (conn_id → sys_con_ops, entity_tag = {field}**{group}**{row}, entity_value = document_files.id, table_tag='document_files')
```

### 1.2 Version History = Entity Row Accumulation (CRITICAL RULE)

**Every file upload adds a NEW row to `sys_con_entities`.** Over time, multiple rows accumulate for the same slot (same `entity_tag`):

```
entity_tag = 'transfer_cins_file**transfer_cins**new-1'

row 1: entity_value = 110  → document_files 110 (status=0, old)
row 2: entity_value = 111  → document_files 111 (status=0, old)
row 3: entity_value = 112  → document_files 112 (status=1, CURRENT)
```

- **Active = `document_files.status`** (1 = current version). Entity rows have no status column.
- `old_versions` (version history list) finds all rows by `entity_tag` match.
- Form reading (`getFormData`) shows only the **active file**.

### 1.3 Version Chain = `replaced_id` (Backward)

`replaced_id` sits on the **new file** and points to the **previous version**:

```
110 (original)  → 111 (replaced_id=110)  →  112 (replaced_id=111)
```

So: `newFile.replaced_id = oldFile.id`. (Old code did `oldFile.replaced_id = new` — wrong direction.)

### 1.4 Key Files

| File | Responsibility |
|------|----------------|
| `app/Helpers/DocumentHelpers.php` | `tempUploadFile`, `finalizeTempFile`, `uploadFile`, `addFileToDb`, `removeFile`, `cleanupTempFiles`, `decryptFile`, `signDocument` |
| `app/Providers/DocumentServiceProvider.php` | File-entity wiring inside `registerContent` + `documentFileStatus` |
| `app/Models/Document_files.php` | Model + `tableList()` (includes `old_versions`, `last_status`) |
| `app/Http/Controllers/DocumentController.php` | `tempUpload`, `setFileStatus`, `setFileStatusAll`, `disableDocument` |
| `app/Providers/EncryptionProvider.php` | AES-128-CBC + PBKDF2 file-name encryption |
| `app/Console/Commands/PurgeDocuments.php` | Hard-delete cascade |
| `resources/js/components/coalparts/Form.vue` | Immediate temp upload on file select |

---

## 2. Database Schema

```sql
document_files (
    id           bigserial PK,
    qnid         uuid (external id),
    description  text  (encrypted file name),
    relation     varchar(20) default '-',   -- 'documents' | 'temp' | '-'
    relation_id  int    default 0,          -- documents.id (0 if temp)
    type_id      int,                        -- sys_options (form-file etc)
    status       smallint default 1,         -- 1 active, 0 passive (version history)
    replaced_id  int    default 0,           -- NEW file: previous version's id
    created_at / updated_at
)

sys_con_entities (
    id           bigserial PK,
    conn_id      int  (→ sys_con_ops.id),
    table_tag    varchar(100),  -- 'document_files' (files) | 'sys_con_ops' (scalar)
    entity_tag   varchar(100),  -- '{field}**{group}**{row}'
    entity_value text,          -- file: document_files.id (text)
    qnid / created_at / updated_at
)
```

> **NOTE:** Do NOT add a status column to `sys_con_entities`. Active status is always derived from `document_files.status`.

---

## 3. Storage & Naming

- **Temp area**: `storage/app/public/temp/` — files land here the moment the user selects them, before the form is saved.
- **Permanent area**: `storage/app/public/documents/` — files live here once linked to a document.
- **Filename rule**: `{unixTimestamp}{5 random chars}{slugified original name}.{ext}` (e.g. `1735689600aB3xYcustomer-signed.pdf`). `slugify()` (defined in `DocumentHelpers.php`) transliterates Turkish chars and lowercases.
- **Accepted**: max **42 MB** (`42000000` bytes), extensions `jpg, png, jpeg, pdf, xls, xlsx` (enforced in both `tempUploadFile` and `uploadFile`).
- **Encryption**: the filename is encrypted with `EncryptionProvider` (`AES-128-CBC`, PBKDF2-SHA512 key derivation, 999 iterations, 128-byte key length, key = `'pickle'`, URL-safe base64 compact format) before being stored in `document_files.description`. The plaintext path is only ever reconstructed server-side inside `decryptFile`.

---

## 4. The Two Upload Paths

### 4.1 Path A — Temp Upload (Modern, SPA Default)

**Frontend** (`Form.vue`): on `input` of a file field, immediately `POST /api/v1/temp-upload` with the raw file.

**Backend** `tempUploadFile($file)`:
1. Validates size/extension.
2. Writes to `temp/` with the generated name.
3. Creates a `document_files` row: `relation = 'temp'`, `relation_id = 0`, `status = 1`, `description = encrypted name`.
4. Returns `{ success, reference_id, encrypted_name, original_name }` — the **reference** is later embedded in the save envelope.

**Finalization** — inside `registerContent()` when a form saves:
- The envelope's `dynamicFile**...` string fields (JSON references) are merged into `$files` by `DocumentController` (both POST and PUT paths).
- For each reference with `reference_id > 0`: `finalizeTempFile($referenceId, $documentId, 'form-file', $existingFileId, $note)`.
- `finalizeTempFile($referenceId, $documentId, $tag = 'form-file', $existingFileId = 0, $note = null)`:
  1. Loads the temp row (`relation = 'temp'`), decrypts the name.
  2. `rename()`s the physical file `temp/` → `documents/` (same name; cheap, no re-encryption needed).
  3. **Replacement case** (`existingFileId > 0`, i.e. a file was already linked to this field):
     - Old file `status = 0`.
     - Temp row promoted: `relation = 'documents'`, `relation_id = $documentId`, `replaced_id = $oldFile->id`.
     - Logs `UserLog(log-file-added)` + `Transactions(doc_file_refreshed)` targeting the **new** file.
     - **Copies all `document_files` entities** from the old file's id (via `conn_id = $fileOld->id`) to the new file.
  4. **First-upload case**: promotes the row, logs `Transactions(doc_file_waiting)`.
  5. Returns `{ success, file_id }`.

### 4.2 Path B — Direct Multipart Upload (Legacy Fallback)

`addFileToDb($f, $tag, $rowId = 0, $relation = '-', $relation_id = '0', $logMessage = '', $note = null)`:
1. `uploadFile()` — validates, writes to `documents/`, returns encrypted name.
2. Creates `document_files` row (`relation`, `relation_id`, `type_id` from `$tag`).
3. `Transactions(doc_file_waiting)` + `UserLog(log-file-added)`.
4. If `$rowId != 0` (replacing an existing file id): old file `status = 0`, new file `replaced_id = old id`, `Transactions(doc_file_refreshed)` targeting the **old** file id, entity copy from old `conn_id` to new file.

> **Note:** Variable names in code are misspelled as `$reletion` / `$reletion_id` (not `$relation` / `$relation_id`).

> **Transactions target inconsistency:** `finalizeTempFile` replacement targets the **new** file (`target_id => $docFile->id`), while `addFileToDb` replacement targets the **old** file (`target_id => $fileOld->id`). Both work but the semantics differ — if changing one, mirror the behavior in the other.

> Both paths converge on the same versioning semantics. `finalizeTempFile` is the canonical one; `addFileToDb` is retained for compatibility.

---

## 5. Correct Code Patterns

### 5.1 Upload — New Entity Row Per Upload

`registerContent()` (or your EAV saver) inside, after file processing:

```php
// Every upload creates a NEW entity row → version history lives in entity rows.
// Active status derived from document_files.status (status=1 = current version).
$entity = new Sys_con_entities;
$entity->table_tag   = 'document_files';
$entity->conn_id     = $conn->id;           // form link (sys_con_ops.id)
$entity->entity_tag  = $fileName;           // {field}**{group}**{row}
$entity->entity_value = (string) $fileId;   // document_files.id
$entity->save();
```

> **Single-slot replacement nuance:** On replacement, the code reuses the **old entity's `entity_tag`** (not `$fileName`) when the old entity exists. This avoids timestamp-based `entity_tag` mismatches between tedarik and admin panels. For multi-file fields (`**` count >= 3), always uses the incoming `$fileName`.

### 5.2 Replacement Detection — Find ACTIVE Entity

To deactivate the old file, locate the entity pointing at the **active file**. There are **two patterns** depending on slot type:

#### Multi-file slots (`**` count >= 3) — exact match

```php
$oldFileEntity = Sys_con_entities::where([
        'conn_id'    => $conn->id,
        'entity_tag' => $fileName,
        'table_tag'  => 'document_files',
    ])
    ->whereIn('entity_value', function ($q) {
        $q->selectRaw('id::text')->from('document_files')->where('status', 1);
    })
    ->orderByDesc('id')->first();
```

#### Single-file slots — prefix match

```php
$typeTag = explode('**', $fileName)[0]; // e.g. 'transfer_kabul_file'
$oldFileEntity = Sys_con_entities::where([
        'conn_id'    => $conn->id,
        'table_tag'  => 'document_files',
    ])
    ->where('entity_tag', 'like', $typeTag . '**%')
    ->whereIn('entity_value', function ($q) {
        $q->selectRaw('id::text')->from('document_files')->where('status', 1);
    })
    ->orderByDesc('id')->first();
```

> The prefix match handles single-file slots where the `entity_tag` may contain different timestamps between tedarik and admin panels.

#### Common — extract file id

```php
$existingFileId = 0;
if ($oldFileEntity && is_numeric($oldFileEntity->entity_value)) {
    $existingFileId = (int) $oldFileEntity->entity_value;
}
```

**Critical:** `whereIn(...)` + `orderByDesc('id')` is MANDATORY. Otherwise:
- Unsorted `first()` → OLDEST row → `existingFileId` always = FIRST file → every replacement deactivates the first file.

### 5.3 Deactivate Old + Chain (`finalizeTempFile` / `addFileToDb`)

```php
// Temp upload path (finalizeTempFile) — REPLACEMENT branch:
if ($existingFileId > 0) {
    $fileOld = Document_files::find($existingFileId);
    if ($fileOld) {
        $fileOld->status = 0;              // old version passive
        $fileOld->save();

        // New file promoted to permanent; replaced_id points to previous version
        $docFile->relation_id   = $documentId;
        $docFile->relation      = 'documents';
        $docFile->replaced_id   = $fileOld->id;   // BACKWARD chain
        $docFile->save();
        // ... doc_file_refreshed transaction + UserLog ...
    }
}

// Direct upload path (addFileToDb) — REPLACEMENT branch:
if ($rowId != 0) {
    $fileOld = Document_files::find($rowId);
    $fileOld->status = 0;
    $fileOld->save();

    $file->replaced_id = $fileOld->id;     // BACKWARD chain
    $file->save();
    // ... doc_file_refreshed transaction ...
}
```

### 5.4 Form Reading — Show Only Active File (`getFormData`)

```sql
-- Entity join filters file entities to only those with active files
left join sys_con_entities sce
       on sce.conn_id = dco.id
      and (sce.table_tag <> 'document_files'
           or exists (
               select 1 from document_files dfe
               where dfe.id = sce.entity_value::int
                 and dfe.status = 1
           ))
```

Without this filter, old versions would appear/clash in the form for the same slot.

> **Additional exclusions in `getFormData`:** The query also excludes `op-doc-user-permission-form`, `op-doc-user-contact-form`, and `op-doc-user-client-form` from the results (person-related EAV forms not relevant to document display).

### 5.5 Version History List (`old_versions`)

Scalar subquery in `tableList` — **entity-tag match** (accumulated rows give all versions):

```sql
'old_versions' => "(select json_agg(json_build_object(
                        'description', df2.description,
                        'qnid',        df2.qnid,
                        'created_at',  df2.created_at
                    ))
                    from sys_con_entities se2
                        inner join document_files as df2 on df2.id = se2.entity_value::int
                    where se2.entity_tag = se.entity_tag) as old_versions"
```

> Alternative (more robust): recursive CTE walking the `replaced_id` chain. Works even if entity rows are deleted. Both produce the same result.

### 5.6 File Removal (`removedData`)

Entity to delete must be **active**:

```php
$check = Sys_con_entities::where([
        'conn_id'    => $row['id'],
        'entity_tag' => $row['key'],
    ])->orderByDesc('id')->first();

if (! empty($check) && $check->table_tag == 'document_files') {
    $fileStatus = Document_files::where('id', (int) $check->entity_value)->value('status');
    if ($fileStatus != 1) {
        $check = null;   // passive version row — history, don't touch
    }
}
// $check not null: file status=0 + entity deleted
```

---

## 6. Version Chain & History

```
document_files rows (same entity_tag, e.g. "signed_contract"):
  id=10  status=1  replaced_id=9    ← current (active)
  id=9   status=0  replaced_id=7
  id=7   status=0  replaced_id=0    ← original
```

- **Chain direction**: `replaced_id` points **backwards** (new → old).
- `status=1` marks exactly one live version per field (enforced by replacement logic, not by constraint).
- `old_versions` JSON in `Document_files::tableList` returns all versions in one aggregate for the UI timeline.
- File-level status transitions (`doc_file_waiting → doc_file_accepted / doc_file_rejected / ...`) are `Transactions` rows with `op_id = 1` targeting the file id; `last_status` subquery in both `getFormData` and `tableList` reads the newest one.

---

## 7. Status Machine for Files

### 7.1 `documentFileStatus($id, $statusKey, $note)` — `POST /v1/trans/set-file-status`

Permission check (`per-07-02`) is in the **controller** (`DocumentController::setFileStatus`), not inside this function. Steps:
1. Load file by `qnid`; resolve `$statusKey` → `sys_options` (e.g. `doc_file_accepted`).
2. Find the entity pointing at this file (`table_tag = 'document_files'`, `entity_value = file id`).
3. `UserLog` (`type_id = status type`) + `Transactions(op_id = 1, type_id = status, target_id = file.id, note)`.
4. Returns success + `connections` (all entities on the same `conn_id` — used to grab the client's contact info for notification).

### 7.2 `setFileStatusAll` — Bulk Approve/Reject per Document

Reuses `getDocumentFiles($documentId)` (files of a client document with their last transaction join) then loops `documentFileStatus`.

### 7.3 Supporting File Queries (`DocumentServiceProvider`)

- `getDocumentFiles($qnid)` — active files of a client document joined with their latest file transaction + who acted (used by `setFileStatusAll`).
- `getRejectedClientFiles($list)` — active files whose **last** file transaction is `doc_file_rejected`, joined with rejecter name (feeds `currentStatus.rejectedFiles` → the notification bell's "Reddedilen Dosya" items).
- `getAwaitingClientFiles($list)` — client-document files whose last status is `doc_file_waiting` (feeds the `notif-01` admin notifications). Also filters by `sod.op_key = 'op-doc-client'` (only client documents).

### 7.4 Notifications After Status Change

Both endpoints collect `cont_email*`/`cont_phone*` entities from the file's connection block and fire `EmailServiceProvider::sendClientFileStatus(...)`. They also call `refreshAllUserPermissions()` (bumps the permission version for every user) so all live sessions re-evaluate `currentStatus.canResponse`.

### 7.5 `disableDocument($qnid)`

Gated by `per-07`. Hard-sets a file `status = 0` (used to unlink without a version chain).

---

## 8. Read/Download Path

### 8.1 Web Route

`GET /order-file/{doc}` (`web.php`, under auth+middleware) → `decryptFile($doc, 'view')`:

1. If `$doc` looks like a UUID → resolve `document_files.qnid` to its `description` (short-link support).
2. Decrypt the filename → `storage/app/public/documents/{name}`.
3. 404 if missing; returns the file with correct MIME headers (`download` variant sets `Content-Disposition: attachment`).
4. `signDocument()` (PDF stamping / image watermarking with "approved by X on date" text) exists but is **commented out** in the current flow.

> **Security note — no per-file authorization:** the per-file permission checks in `decryptFile` are commented out. Any authenticated session that knows a `document_files.qnid` (or encrypted description) can download that file via `/order-file/{qnid}`. The route only requires the generic `auth:sanctum` + `CheckPermissionVersion` middleware. If the new system needs access control per file (owner/reseller/admin), restore/enforce checks here.

### 8.2 File Listing

`POST /v1/table/document_files` (gated by `per-07` + `per-07-01` in `SystemController::table`) → `Document_files::tableList()`:
- Returns `file` (encrypted name — rendered client-side via the `/order-file` route), `type_title`, `relation`, `relation_qnid`, `entity_tag`, `last_status`, `old_versions`, `group_key`, `product_name`, `relation_detail`, `file_type`, `file_type_key`, `relation_type`, `title`.
- Filters: `free`/`all` search across columns (excluding raw `file`), default `{key,value,type}` filters.
- Base scope: `status = 1`, `grp_code = SYS_CODE`, parent document active, excludes `op-offer_otherdocs_file`, and excludes `entity_tag` containing `item_images_file` (per-item product images not actionable for admin review).

---

## 9. Cleanup, Purging & Maintenance

| Job | When | What it does |
|-----|------|--------------|
| `cleanupTempFiles()` (helper) | **daily 03:00** (scheduled in `app/Console/Kernel.php`) | Deletes `temp/` files older than 24h + deletes orphaned `relation = 'temp'` rows older than 24h |
| `php artisan documents:purge` (`PurgeDocuments`) | manual | **Hard-deletes** request/offer documents with full cascade: entities, conns, transactions (op 0 + file ops), user_logs, document_files rows, then physical files via `removeFile()`. Skips `op-doc-client` documents & users. `--type=all|request|offer` (default `all`); `--dry-run` reports; `--force` required in production. Physical deletion happens **after** the DB transaction commits (filesystem can't roll back) |
| `php artisan files:reencrypt-descriptions` (`ReencryptFileDescriptions`) | one-off / migration | Migrates legacy **JSON-format** encrypted filenames (`document_files.description`) to the **compact** URL-safe format. Skips non-JSON rows; writes a backup JSON to `storage/app/reencrypt-backup-{ts}.json` before updating (no backup = no update); verifies each decrypt/encrypt round-trip; supports `--dry-run` and `--rollback=<backupPath>` |

`removeFile($fileId)` deletes the physical file (`unlink`) and returns the row — used by purge, previously used by old `removeContent`.

---

## 10. Historical Bugs (Lessons Learned)

### Bug 1: `$check` searched wrong `table_tag`

```php
// WRONG — file entities are stored under 'document_files' tag
$check = Sys_con_entities::where([... 'table_tag' => 'sys_con_ops'])->first();
```

No match ever found → every upload created a new row (which was actually correct for version history, unintended side effect).

### Bug 2: Replacement detection found oldest row

```php
// WRONG — unsorted first() returns the OLDEST entity row
$oldFileEntity = Sys_con_entities::where([...])->first();
```

As accumulated rows grew, `existingFileId` always = FIRST file → every replacement deactivated the first file, last uploaded stayed active.

**Fix:** `whereIn(entity_value, active file ids)` + `orderByDesc('id')`.

### Bug 3: `replaced_id` direction reversed

```php
// WRONG (forward chain)
$fileOld->replaced_id = $docFile->id;
// CORRECT (backward — "previous version")
$docFile->replaced_id = $fileOld->id;
```

### Bug 4: `old_versions` depended on entity rows

When single-row update was tried (to fix Bug 2 — "update entity in place"), `old_versions` broke — because history entity rows no longer existed.

**Lesson:** Never update/deduplicate entity rows for version history; each version gets a new row, active determined by `document_files.status`.

### Bug 5: `relation='-'` ghost records

`tempUploadFile` sets `relation='temp'`; `finalizeTempFile` changes to `'documents'`. If `relation='-'` remains, the upload flow never completed (or another code path). `relation_id=0` + `relation='-'` = orphan → clean up.

---

## 11. Gotchas / Sharp Edges

1. **Physical files are never deleted on normal versioning** — replaced files stay on disk. Only `PurgeDocuments` or explicit `removeFile` calls remove them. Disk growth is expected; plan a retention policy.
2. **Entity table is the version store** — never delete old entity rows when "removing" a file; deactivate the file instead (removed-data step does delete the *active* link entity, but history rows stay).
3. **`getFormData` only shows active files** — don't rely on it for version history; use `Document_files::tableList` `old_versions`.
4. **Temp rows are 1-time**: `finalizeTempFile` requires `relation = 'temp'`; a reference used twice fails the second time. A form saved twice with the same pending reference will error on the second save — pages should clear `formData.files` after a successful submit.
5. **File-type titles** resolve via `Sys_options` with key `'op-' || explode('**', entity_tag)[0]` — the entity tag's first segment is the *file type op_key* (e.g. `cont_imza_file` → `op-cont_imza_file`). New file field types need a matching `sys_options` row for proper titles in logs/notifications.
6. **42 MB cap is enforced in two places** (temp + direct) — keep in sync. `php.ini upload_max_filesize` must allow it too.
7. **Encryption key is a literal** `'pickle'` in `EncryptionProvider` calls — move to env/config for a new system, and note the legacy JSON-format decrypt path (old records) is still supported.
8. **`addFileToDb` and `finalizeTempFile` duplicate replacement logic** — if you change one, mirror it in the other, or refactor to a single service.
9. **`/order-file` is unauthenticated per-file** (see §8.1) — a known qnid is enough to fetch any file; audit/scope it in the new system.
10. **Legacy JSON-format descriptions** are still decryptable (dual-format support in `EncryptionProvider`); new writes use the compact format. `files:reencrypt-descriptions` converts the old rows.

---

## 12. Migration Checklist for New Projects

- [ ] `document_files` schema: `status`, `replaced_id`, `relation`, `relation_id` exist
- [ ] Upload code creates **new** `sys_con_entities` row each time (`table_tag='document_files'`)
- [ ] Replacement detection: `whereIn(entity_value, active files)` + `orderByDesc('id')`
- [ ] `finalizeTempFile` / `addFileToDb`: old `status=0`, new `replaced_id = old id`
- [ ] `getFormData`: join filter `EXISTS(document_files status=1)`
- [ ] `old_versions`: entity-tag match scalar subquery (or replaced_id CTE)
- [ ] `removedData`: only touches active file entity
- [ ] `php -l` + real replacement test (3 uploads → 3 entity rows, 1 active, old_versions=3)
- [ ] `relation='temp'` → `'documents'` conversion correct; no `'-'` orphans left
- [ ] Config-driven accepted extensions/sizes (don't hardcode)
- [ ] Encryption key in env/config (not literal `'pickle'`)

---

## 13. Verification Queries

```sql
-- Entity rows per slot + file active status
SELECT sce.id, sce.entity_tag, sce.entity_value,
       df.status AS file_status, df.replaced_id
FROM sys_con_entities sce
JOIN document_files df ON df.id = sce.entity_value::int
WHERE sce.table_tag = 'document_files'
ORDER BY sce.id;

-- Chain check (new → old)
SELECT id, status, replaced_id, created_at
FROM document_files
WHERE relation = 'documents'
ORDER BY id;

-- Version count per entity_tag
SELECT count(*)
FROM sys_con_entities se2
JOIN document_files df2 ON df2.id = se2.entity_value::int
WHERE se2.entity_tag = 'alan**grup**satir';
```

Expected: N entity rows per slot for N uploads; only last file `status=1`; `replaced_id` chain continuous.

---

## 14. Reuse Blueprint

1. Keep the `document_files` + EAV link model — it gives you versioning "for free" (new row per upload, `replaced_id` chain, status flag).
2. Keep the temp-upload-then-finalize UX: upload at selection time (fast feedback, large files don't block form submit), finalize atomically inside the document transaction.
3. Keep the encrypted-filename pattern for the description column; store paths nowhere else.
4. Keep file status as transactions (`op_id = 1`) so the timeline/audit stays uniform with document statuses.
5. Add a config-driven accepted-extension/size list instead of the hardcoded array.
6. If the new system needs pre-signed/streamed downloads, wrap `decryptFile` behind a signed-URL controller instead of the session-only `/order-file` route.
