# File Replacement Fix — Temp Upload Path (2026-08-28)

> **Problem:** `finalizeTempFile()` silently destroyed file version history when replacing files via the temp upload path (`/v1/temp-upload` → `pickle.js` temp flow). The old file was deleted from disk with no version chain, no audit log, and no `doc_file_refreshed` transaction.

## The Bug

### Two file upload paths in `registerContent`

```
$isReference = is_string($file) && is_json($file);
if($isReference)  → finalizeTempFile()   ← temp path (modern browsers via pickle.js)
else              → addFileToDb()        ← traditional path (direct File upload)
```

### What `addFileToDb` does correctly (traditional path)

1. Creates a **NEW** `document_files` record (new id, new encrypted name)
2. If `$rowId != 0` (replacement):
   - Old file: `status=0, replaced_id=newId` → **VERSION CHAIN**
   - Copies entities from old to new
   - Transaction: `doc_file_refreshed` → **AUDIT TRAIL**
3. Returns new file ID

### What `finalizeTempFile` did wrong (temp path)

1. Found the **EXISTING** `document_files` record (the temp one from `/v1/temp-upload`)
2. Moved file on disk: `temp/` → `documents/` (old file **deleted forever**)
3. Updated **SAME** record: `relation_id=docId, relation='documents'`
4. Transaction: `doc_file_waiting` → **NOT** `doc_file_refreshed`
5. Returned **SAME** file ID → entity stayed unchanged

**Result:** On replacement, the old file was gone from disk, no version chain existed, no audit log recorded the change. `syncOrderStatusFromFiles` didn't even notice because `entity_value` was unchanged.

## The Fix

### Files Changed

| File | Change |
|------|--------|
| `panel/app/Helpers/DocumentHelpers.php:529` | `finalizeTempFile` — new `$existingFileId` param (default 0). When > 0: deactivates old file (`status=0, replaced_id=newId`), promotes temp record to permanent, logs `doc_file_refreshed`, copies entities from old to new. First upload (`$existingFileId=0`) unchanged. |
| `panel/app/Providers/DocumentServiceProvider.php:209-229` | `registerContent` — looks up existing file entity (`table_tag='document_files'`) BEFORE processing. Extracts `$existingFileId` from old `entity_value`. Passes it to `finalizeTempFile`. |

### Flow After Fix

```
User picks new file via temp upload
→ pickle.js: POST /v1/temp-upload → creates document_files (relation='temp', id=45)
→ On save: registerContent receives reference_id=45
→ registerContent looks up old entity: conn_id=X, entity_tag='transfer_kabul_file**transfer_kabul**id'
  → finds existing entity with entity_value=32 (old file id)
  → $existingFileId = 32
→ finalizeTempFile(45, documentId, 'form-file', existingFileId=32)
  → moves temp file to documents/
  → finds old file #32: status=0, replaced_id=45
  → promotes temp record #45: relation='documents', relation_id=documentId
  → Transaction: doc_file_refreshed (NOT doc_file_waiting)
  → copies entities from old #32 to new #45
  → returns file_id=45
→ registerContent: entity.entity_value = 45
```

### sys_options Required

| op_key | Purpose | Already exists? |
|--------|---------|-----------------|
| `doc_file_refreshed` | Transaction type for file replacement | ✅ id=51 |
| `doc_file_waiting` | Transaction type for first upload | ✅ id=49 |
| `log-file-added` | UserLog type for file operations | ✅ id=20 |

### Migration (if new repo)

```sql
-- These should already exist from SysSeeder/OrderSystemSeeder
INSERT INTO sys_options (op_key, group_key, title, ttitle, ctitle, status)
VALUES
  ('doc_file_refreshed', 'op-trans-op-doc', 'Doküman Onay Yenilendi', 'transactions', 'type_id', 1),
  ('doc_file_waiting', 'op-trans-op-doc', 'Doküman Beklemede', 'transactions', 'type_id', 1),
  ('log-file-added', 'op-logs', 'Dosya Eklendi', 'user_logs', 'type_id', 1);
```

### document_files Table Columns Required

```sql
-- replaced_id must exist for version chaining
ALTER TABLE document_files ADD COLUMN IF NOT EXISTS replaced_id bigint DEFAULT NULL;
```

## How to Port to Another Repo

1. Copy `finalizeTempFile` from `DocumentHelpers.php:516-644` (the full function with `$existingFileId` param)
2. In `DocumentServiceProvider::registerContent`, add the entity lookup before file processing:
   ```php
   $oldFileEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => $fileName, 'table_tag' => 'document_files'])->first();
   $existingFileId = 0;
   if($oldFileEntity && is_numeric($oldFileEntity->entity_value)){
       $existingFileId = (int) $oldFileEntity->entity_value;
   }
   ```
3. Pass `$existingFileId` to `finalizeTempFile`:
   ```php
   $fileResponse = finalizeTempFile($referenceId, $document->id, 'form-file', $existingFileId);
   ```
4. Ensure `doc_file_refreshed` exists in `sys_options`
5. Ensure `document_files.replaced_id` column exists
