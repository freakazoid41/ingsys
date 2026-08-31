# File Upload & Versioning Mechanics

## 1. Overview

Files are stored on the **`public` disk** (`storage/app/public/{documents,temp}/`), referenced from the DB through the EAV engine (see `form-system-mechanics.md`), with a **linked-list versioning model**: every new upload of the same field creates a new `document_files` row whose `replaced_id` points at the version it replaced. Old versions keep their file on disk, are marked `status = 0`, and are served through the entity history (`old_versions`).

File identity in the DB is an **encrypted filename** (`document_files.description`) — never the real path. Access is via short UUID links that decrypt server-side.

**Key files:**
- `app/Helpers/DocumentHelpers.php` — `tempUploadFile`, `finalizeTempFile`, `uploadFile`, `addFileToDb`, `removeFile`, `cleanupTempFiles`, `decryptFile`, `signDocument`
- `app/Providers/DocumentServiceProvider.php` — file-entity wiring inside `registerContent` + `documentFileStatus`
- `app/Models/Document_files.php` — model + `tableList()` (includes `old_versions`, `last_status`)
- `app/Http/Controllers/DocumentController.php` — `tempUpload`, `setFileStatus`, `setFileStatusAll`, `disableDocument`
- `app/Providers/EncryptionProvider.php` — AES-128-CBC + PBKDF2 file-name encryption
- `app/Console/Commands/PurgeDocuments.php` — hard-delete cascade
- `resources/js/components/coalparts/Form.vue` — immediate temp upload on file select

---

## 2. Storage & naming

- **Temp area**: `storage/app/public/temp/` — files land here the moment the user selects them, before the form is saved.
- **Permanent area**: `storage/app/public/documents/` — files live here once linked to a document.
- **Filename rule**: `{unixTimestamp}{5 random chars}{slugified original name}.{ext}` (e.g. `1735689600aB3xYcustomer-signed.pdf`). `slugify()` transliterates Turkish chars and lowercases.
- **Accepted**: max **40 MB** (`42000000` bytes), extensions `jpg, png, jpeg, pdf, xls, xlsx` (enforced in both `tempUploadFile` and `uploadFile`).
- **Encryption**: the filename is encrypted with `EncryptionProvider` (`AES-128-CBC`, PBKDF2-SHA512 key derivation, 999 iterations, key = `'pickle'`, URL-safe base64 compact format) before being stored in `document_files.description`. The plaintext path is only ever reconstructed server-side inside `decryptFile`.

---

## 3. The two upload paths

### 3.1 Path A — Temp upload (modern, always used by the SPA)

**Frontend** (`Form.vue`): on `input` of a file field, immediately `POST /api/v1/temp-upload` with the raw file.

**Backend** `tempUploadFile($file)`:
1. Validates size/extension.
2. Writes to `temp/` with the generated name.
3. Creates a `document_files` row: `relation = 'temp'`, `relation_id = 0`, `status = 1`, `description = encrypted name`.
4. Returns `{ success, reference_id, encrypted_name, original_name }` — the **reference** is later embedded in the save envelope.

**Finalization** — inside `registerContent()` when a form saves:
- The envelope's `dynamicFile**...` string fields (JSON references) are merged into `$files` by `DocumentController` (both POST and PUT paths).
- For each reference with `reference_id > 0`: `finalizeTempFile($referenceId, $documentId, 'form-file', $existingFileId)`.
- `finalizeTempFile`:
  1. Loads the temp row (`relation = 'temp'`), decrypts the name.
  2. `rename()`s the physical file `temp/` → `documents/` (same name; cheap, no re-encryption needed).
  3. **Replacement case** (`existingFileId > 0`, i.e. a file was already linked to this field):
     - Old file `status = 0`.
     - Temp row promoted: `relation = 'documents'`, `relation_id = $documentId`, `replaced_id = $oldFile->id`.
     - Logs `UserLog(log-file-added)` + `Transactions(doc_file_refreshed)` targeting the **new** file.
     - **Copies all `document_files` entities** from the old file's `conn_id` to the new file's id.
  4. **First-upload case**: promotes the row, logs `Transactions(doc_file_waiting)`.
  5. Returns `{ success, file_id }`.

### 3.2 Path B — Direct multipart upload (legacy fallback)

`addFileToDb($f, $tag, $rowId, $relation, $relationId, $logMessage)`:
1. `uploadFile()` — validates, writes to `documents/`, returns encrypted name.
2. Creates `document_files` row (`relation`, `relation_id`, `type_id` from `$tag`).
3. `Transactions(doc_file_waiting)` + `UserLog(log-file-added)`.
4. If `$rowId != 0` (replacing an existing file id): old file `status = 0`, new file `replaced_id = old id`, `Transactions(doc_file_refreshed)` targeting the **old** file id, entity copy from old `conn_id` to new file.

> Both paths converge on the same versioning semantics. `finalizeTempFile` is the canonical one; `addFileToDb` is retained for compatibility.

---

## 4. The link between form fields and files

After a file is stored, `registerContent` creates a **new `sys_con_entities` row**:

```
table_tag   = 'document_files'
conn_id     = <the form block's sys_con_ops.id>
entity_tag  = <field name, e.g. 'cont_imza_file**...' or 'offer_file**...'>
entity_value= <document_files.id as string>
```

**Versioning rule**: every upload appends a new entity row — the entity is never updated in place. The **active** version is the newest entity whose linked file has `status = 1`. `getFormData` returns only active files for the current form, while `Document_files::tableList` exposes `old_versions` (a `json_agg` of all entity rows sharing the same `entity_tag`, with each version's description/qnid/created_at).

### Replacement detection (critical logic)

`registerContent` locates the existing active file *before* inserting:

```php
$oldFileEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => $fileName, 'table_tag' => 'document_files'])
    ->whereIn('entity_value', function ($q) {
        $q->selectRaw('id::text')->from('document_files')->where('status', 1);
    })
    ->orderByDesc('id')->first();
```

Only `status = 1` files count as the "live" version — stale rows are history.

### Removal (removedData)

When the UI removes a file, `registerContent`'s removed-data step finds the entity by `conn_id` + `entity_tag`, **verifies the linked file is still active**, then: file `status = 0` + entity row deleted. Physical file stays on disk (it's still reachable via old-version history / direct URL until purged).

---

## 5. Version chain & history

```
document_files rows (same entity_tag, e.g. "signed_contract"):
  id=10  status=1  replaced_id=9    ← current (active)
  id=9   status=0  replaced_id=7
  id=7   status=0  replaced_id=0    ← original
```

- Chain direction: `replaced_id` points **backwards** (new → old).
- `status=1` marks exactly one live version per field (enforced by replacement logic, not by constraint).
- `old_versions` JSON in `Document_files::tableList` returns all versions in one aggregate for the UI timeline.
- File-level status transitions (`doc_file_waiting → doc_file_accepted / doc_file_rejected / ...`) are `Transactions` rows with `op_id = 1` targeting the file id; `last_status` subquery in both `getFormData` and `tableList` reads the newest one.

---

## 6. Status machine for files

### 6.1 `documentFileStatus($id, $statusKey, $note)` — `POST /v1/trans/set-file-status`

Gated by `per-07-02`. Steps:
1. Load file by `qnid`; resolve `$statusKey` → `sys_options` (e.g. `doc_file_accepted`).
2. Find the entity pointing at this file (`table_tag = 'document_files'`, `entity_value = file id`).
3. `UserLog` (`type_id = status type`) + `Transactions(op_id = 1, type_id = status, target_id = file.id, note)`.
4. Returns success + `connections` (all entities on the same `conn_id` — used to grab the client's contact info for notification).

### 6.2 `setFileStatusAll` — bulk approve/reject per document

Reuses `getDocumentFiles($documentId)` (files of a client document with their last transaction join) then loops `documentFileStatus`.

### 6.3 Supporting file queries (`DocumentServiceProvider`)

- `getDocumentFiles($qnid)` — active files of a client document joined with their latest file transaction + who acted (used by `setFileStatusAll`).
- `getRejectedClientFiles($list)` — active files whose **last** file transaction is `doc_file_rejected`, joined with rejecter name (feeds `currentStatus.rejectedFiles` → the notification bell's "Reddedilen Dosya" items).
- `getAwaitingClientFiles($list)` — client-document files whose last status is `doc_file_waiting` (feeds the `notif-01` admin notifications, see `notification-receiving-system.md`).

### 6.3 Notifications after status change

Both endpoints collect `cont_email*`/`cont_phone*` entities from the file's connection block and fire `EmailServiceProvider::sendClientFileStatus(...)`. They also call `refreshAllUserPermissions()` (bumps the permission version for every user) so all live sessions re-evaluate `currentStatus.canResponse`.

### 6.4 `disableDocument($qnid)`

Gated by `per-07`. Hard-sets a file `status = 0` (used to unlink without a version chain).

---

## 7. Read/download path

### 7.1 Web route

`GET /order-file/{doc}` (`web.php`, under auth+middleware) → `decryptFile($doc, 'view')`:

1. If `$doc` looks like a UUID → resolve `document_files.qnid` to its `description` (short-link support).
2. Decrypt the filename → `storage/app/public/documents/{name}`.
3. 404 if missing; returns the file with correct MIME headers (`download` variant sets `Content-Disposition: attachment`).
4. `signDocument()` (PDF stamping / image watermarking with "approved by X on date" text) exists but is **commented out** in the current flow.

> **Security note — no per-file authorization:** the per-file permission checks in `decryptFile` are commented out. Any authenticated session that knows a `document_files.qnid` (or encrypted description) can download that file via `/order-file/{qnid}`. The route only requires the generic `auth:sanctum` + `CheckPermissionVersion` middleware. If the new system needs access control per file (owner/reseller/admin), restore/enforce checks here.

### 7.2 File listing

`POST /v1/table/document_files` (gated by `per-07` + `per-07-01` in `SystemController::table`) → `Document_files::tableList()`:
- Returns `file` (encrypted name — rendered client-side via the `/order-file` route), `type_title`, `relation`, `relation_qnid`, `entity_tag`, `last_status`, `old_versions`.
- Filters: `free`/`all` search across columns (excluding raw `file`), default `{key,value,type}` filters.
- Base scope: `status = 1`, `grp_code = SYS_CODE`, parent document active, and excludes `op-offer_otherdocs_file`.

---

## 8. Cleanup, purging & maintenance

| Job | When | What it does |
|-----|------|--------------|
| `cleanupTempFiles()` (helper) | **daily 03:00** (scheduled in `app/Console/Kernel.php`) | Deletes `temp/` files older than 24h + deletes orphaned `relation = 'temp'` rows older than 24h |
| `php artisan documents:purge` (`PurgeDocuments`) | manual | **Hard-deletes** request/offer documents with full cascade: entities, conns, transactions (op 0 + file ops), user_logs, document_files rows, then physical files via `removeFile()`. Skips `op-doc-client` documents & users. `--dry-run` reports; `--force` required in production. Physical deletion happens **after** the DB transaction commits (filesystem can't roll back) |
| `php artisan files:reencrypt-descriptions` (`ReencryptFileDescriptions`) | one-off / migration | Migrates legacy **JSON-format** encrypted filenames (`document_files.description`) to the **compact** URL-safe format. Skips non-JSON rows; writes a backup JSON to `storage/app/reencrypt-backup-{ts}.json` before updating (no backup = no update); verifies each decrypt/encrypt round-trip; supports `--dry-run` and `--rollback=<backupPath>` |

`removeFile($fileId)` deletes the physical file (`unlink`) and returns the row — used by purge, previously used by old `removeContent`.

---

## 9. Gotchas / sharp edges

1. **Physical files are never deleted on normal versioning** — replaced files stay on disk. Only `PurgeDocuments` or explicit `removeFile` calls remove them. Disk growth is expected; plan a retention policy.
2. **Entity table is the version store** — never delete old entity rows when "removing" a file; deactivate the file instead (removed-data step does delete the *active* link entity, but history rows stay).
3. **`getFormData` only shows active files** — don't rely on it for version history; use `Document_files::tableList` `old_versions`.
4. **Temp rows are 1-time**: `finalizeTempFile` requires `relation = 'temp'`; a reference used twice fails the second time. A form saved twice with the same pending reference will error on the second save — pages should clear `formData.files` after a successful submit.
5. **File-type titles** resolve via `Sys_options` with key `'op-' || explode('**', entity_tag)[0]` — the entity tag's first segment is the *file type op_key* (e.g. `cont_imza_file` → `op-cont_imza_file`). New file field types need a matching `sys_options` row for proper titles in logs/notifications.
6. **40 MB cap is enforced in two places** (temp + direct) — keep in sync. `php.ini upload_max_filesize` must allow it too.
7. **Encryption key is a literal** `'pickle'` in `EncryptionProvider` calls — move to env/config for a new system, and note the legacy JSON-format decrypt path (old records) is still supported.
8. **`addFileToDb` and `finalizeTempFile` duplicate replacement logic** — if you change one, mirror it in the other, or refactor to a single service.
9. **`/order-file` is unauthenticated per-file** (see §7.1) — a known qnid is enough to fetch any file; audit/scope it in the new system.
10. **Legacy JSON-format descriptions** are still decryptable (dual-format support in `EncryptionProvider`); new writes use the compact format. `files:reencrypt-descriptions` converts the old rows.

---

## 10. Reuse blueprint

1. Keep the `document_files` + EAV link model — it gives you versioning "for free" (new row per upload, `replaced_id` chain, status flag).
2. Keep the temp-upload-then-finalize UX: upload at selection time (fast feedback, large files don't block form submit), finalize atomically inside the document transaction.
3. Keep the encrypted-filename pattern for the description column; store paths nowhere else.
4. Keep file status as transactions (`op_id = 1`) so the timeline/audit stays uniform with document statuses.
5. Add a config-driven accepted-extension/size list instead of the hardcoded array.
6. If the new system needs pre-signed/streamed downloads, wrap `decryptFile` behind a signed-URL controller instead of the session-only `/order-file` route.