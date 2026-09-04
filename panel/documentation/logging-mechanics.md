# Logging Mechanics — Enriched Audit System (2026-09-04)

## 1. Overview

Every state-changing operation writes a **frozen audit snapshot** into `user_logs.description` (TEXT JSON, unlimited) and a **short ledger entry** into `transactions` (varchar 300). The snapshot is immutable — it captures actor / order / file / note at write time so history never mutates when users later change names, roles, or `order_no`.

**Key files:**
- `app/Services/AuditService.php` — **central cached snapshots** `actor()`, `order()`, `orderForDocument()`, `file()`, `diff()`, `optionTitle()` (2026-09-04 late+2, see §8)
- `app/Providers/DocumentServiceProvider.php` — `registerContent()`, `setStatus()`, `applyOrderStatus()`, `cancelOrder()`, `renameOrder()`, `removeContent()`, `documentFileStatus()`, `acceptAllOrderFiles()`, `syncOrderStatusFromFiles()` + thin wrappers `actorSnapshot()` → `AuditService::actor()` etc.
- `app/Helpers/DocumentHelpers.php` — `finalizeTempFile()`, `addFileToDb()` now via `AuditService::actor()` + `orderForDocument()`
- `app/Models/UserLog.php` — `user_logs` model (+ `tableList()` for `POST /v1/table/userlog`)
- `app/Models/Transactions.php` — `transactions` model
- `app/Http/Controllers/DocumentController.php` — HTTP entry points
- `resources/js/pages/coalsystem/Logs/LList.vue` — log list + side-panel renderer
- `database/seeders/SysSeeder.php` — `op-logs` dictionary (25 entries, `log-user-logout` id 102)
- `database/migrations/2026_09_04_000005_add_audit_indexes.php` — hot-path indexes (see §8)

---

## 2. Storage

### 2.1 `user_logs`

```sql
id, user_id → users.id, sys_code (GDZ/ADM), relation ('documents'|'persons'), relation_id,
type_id → sys_options.id where group_key='op-logs' (op_key = log-*),
description TEXT JSON, created_at
```

`description` is the **rich audit body** — always JSON, never truncated. Shape varies by trigger (see §4) but always contains:

```json
{
  "before": { /* getFormData snapshot or [] */ },
  "after": { /* getFormData snapshot or document stub */ },
  "actor": { "user_id": 1, "person_id": 12, "person_qnid": "uuid", "name": "Kadir Bozat", "email": "kadir@...", "role": "immutable-admin", "type_key": "op-pert-admin", "ip": "127.0.0.1", "sys_code": "GDZ" },
  "document": { "id": 123, "qnid": "uuid", "order_no": "3510004400-2", "transfer_no": "3510004400-2", "buying_no": "SAP-...", "spec_code": "0000300186", "ctitle": "YILDIZ TEKSTIL" },
  "file": { "id": 45, "qnid": "uuid", "status": 1, "field": "transfer_kabul_file", "group_key": "transfer_kabul", "entity_tag": "transfer_kabul_file**transfer_kabul**83", "relation_id": 123, "order_qnid": "uuid", "order_no": "3510004400-2" },
  "from": { "op_key": "doc_trans_order_transfer_sent", "title": "Dosyalar Kontrol Ediliyor" },
  "to":   { "op_key": "doc_trans_order_files_rejected", "title": "Reddedilen Dosyalar Mevcut" },
  "note": "Dosya reddedildi: kalite uygun değil",
  "desc": "human sentence for timeline grouping"
}
```

`actor` / `document` / `file` are **frozen** via helpers at write time. `from`/`to` are order or file status transitions. `note` is free text (rejection reason, file_note, or `'-'`). `desc` is short human label.

### 2.2 `transactions`

```sql
id, qnid, op_id (0=document, 1=file), type_id → sys_options (doc_trans_* / doc_file_*), target_id (documents.id or document_files.id),
log_id → user_logs.id, note varchar(300), description varchar(300) (short actor<email> + note JSON), amount/cur_id/rel_id/sign/period, created_at
```

`note` + `description` are **truncated to 300** (`mb_substr(...,0,300)`) to satisfy `2022_12_05_083533_create_transactions_table.php:29` `varchar(300)`. Rich lives only in `user_logs.description` TEXT. `op_id` distinguishes order vs file history — dashboards filter `op_id=1` for file timeline.

### 2.3 Dictionary `sys_options` `group_key='op-logs'`

25 rows: `log-tender-update`, `log-document-status-update`, `log-order-update`, `log-file-added`, `log-file-status-trans` (unused, file status logs use `doc_file_*` itself as `type_id`), `log-user-logout` (id 102, `Kullanıcı Zorla Çıkış` seeded 2026-09-04), etc. Resolve via `Sys_options::where('op_key','log-*')->value('id')`.

---

## 3. Snapshot Helpers

### `actorSnapshot()` — `DocumentServiceProvider.php:29` / `actorSnapshotHelper()` — `DocumentHelpers.php:11`

```php
[
  'user_id' => auth()->id,
  'person_id' => auth()->person_id,
  'person_qnid' => persons.qnid ?? session('person_id'),
  'name' => trim(person.name + surname) ?: auth.email,
  'email' => auth.email,
  'role' => auth.role,
  'type_key' => persons.type op_key (op-pert-admin/reseller/...),
  'ip' => request()->ip(),
  'sys_code' => $GLOBALS['SYS_CODE'] // GDZ/ADM from public/index.php
]
```

Fallback reads `session('type_key')` / `session('ptitle')` if auth unavailable (CLI, cron). Stored under `actor` key — never mutated later.

### `orderSnapshot(Documents $doc, array $entities=[])` — `DocumentServiceProvider.php:70`

Resolves order header from `sys_con_ops` → `sys_con_entities` if no entities passed. Returns `{id,qnid,order_no,transfer_no,buying_no,spec_code,ctitle}`. Used for every order-touching log.

### `fileSnapshot(int $fileId, $entityTag=null, $connId=null)` — `DocumentServiceProvider.php:93`

Loads `document_files` + its `sys_con_entities` link (`entity_tag` → `field`/`group_key`) + parent order (`parent_id` chain) → `{id,qnid,status,field,group_key,entity_tag,relation_id,order_qnid,order_no}`. Resolves order_no via `sys_con_entities` on the order's `sys_con_ops`. Used for file-touching logs.

All three are called **before** `UserLog::create` so JSON is frozen.

---

## 4. Triggers — Who Writes What

| # | Trigger | HTTP | Provider/Helper | `user_logs.type_id` | `user_logs.description` JSON (enriched) | `transactions` |
|---|---------|------|-----------------|----------------------|------------------------------------------|----------------|
| 1 | **Document CRUD** create/update | `POST/PUT /v1/document[/:qnid]` → `registerContent()` | `DSP:134` | `log-tender-update` | `{before:getFormData\|[], after:getFormData, actor, document, note:file_note\|note, desc:'Belge Oluşturuldu/Güncellendi'}` | birth `doc_trans_order_created` / `doc_trans_created` `op0` log_id 0 on create |
| 2 | **Order status** manual | `POST /v1/trans/set-status` → `setStatus()` | `DSP:972` | `log-document-status-update` | `{after:{document}, actor, document, from:{op_key,title}, to:{op_key,title}, desc:'Durumu Değiştirildi', note}` | `op0` `transactions.type_id=statusKey` `target_id=doc.id` `log_id` `note/desc 300` |
| 3 | **Auto order status** via files | internal `syncOrderStatusFromFiles()` → `applyOrderStatus()` | `DSP:1381` | `log-order-update` | `{after, actor, document, from:{op_key,title}, to:{op_key,title}, desc:'Sipariş Durumu Güncellendi', note:'Dosya reddedildi: <fileNote>'}` — `from.title` fetched via `Sys_options`, `rejectedNote` unwraps file's `transactions.note` JSON | `op0` |
| 4 | **Cancel order** whole / clone | `POST /v1/orders/cancel` → `cancelOrder()` | `DSP:2046` | `log-order-update` | `{after, actor, document, desc:'Sipariş İptal Edildi / Reddedildi', note}` | `doc_trans_order_rejected` `op0` |
| 5 | **Rename EBELN-X** | `POST /v1/orders/rename` → `renameOrder()` | `DSP:2110` | `log-order-update` | `{after, actor, document, desc:'Sipariş Numarası Düzenlendi', note:'old → new', old_order_no, new_order_no}` | — |
| 6 | **Passivate** | `DELETE /v1/document/:qnid` → `removeContent()` | `DSP:751` | `log-tender-update` | `{before:detail, after:[], actor, document, desc:'İçerik pasife alındı', note:'-'}` | soft `documents.status=0` |
| 7a | **File status** single | `POST /v1/trans/set-file-status` → `documentFileStatus()` | `DSP:1178` `per-07-02` | `doc_file_*` itself (`doc_file_accepted/rejected/refreshed`, not `log-file-status-trans`) | `{file_id, file:{...}, actor, from:{op_key,title}, to:{op_key,title}, desc:'<FileTitle> Durumu Değiştirildi => <StatusTitle>', note}` | `op1` `note/description 300` + `syncOrderStatusFromFiles` + `refreshAllUserPermissions` + `sendClientFileStatus` |
| 7b | **Bulk Kalite** | `POST /v1/trans/set-status doc_trans_order_approved` → `acceptAllOrderFiles()` | `DSP:1069` | `doc_file_accepted` per file | `{file_id, file, actor, desc:'...Kalite Onayı ile', note:'Kalite Onayı Ver ve Kapat'}` | `op1` per file |
| 7c | **File upload** traditional | inside `registerContent` → `addFileToDb()` | `DH:805` | `log-file-added` | `{file_id, file:{id,qnid,order_no,order_qnid}, actor, desc:'<Title> Dosyası Sisteme Eklendi', note}` — 7th param `$note` | `doc_file_waiting` (+ `doc_file_refreshed` on replacement) `op1` |
| 7d | **Temp upload** finalize | `POST /v1/temp-upload` → `finalizeTempFile()` | `DH:554` | `log-file-added` | `{file_id, old_file_id?, file, actor, desc:'Geçici ... taşındı/değiştirildi', note:file_note}` — 5th param `$note` | `doc_file_waiting / doc_file_refreshed` `op1` |

- `registerContent` forwards `requestData.file_note | note` to `finalizeTempFile(...,note)` / `addFileToDb(...,note)` `DSP:362,373`.
- `transactions.description` / `note` truncated `mb_substr(...,0,300)`; rich stays in `user_logs` TEXT.
- File `note` free text: approver enters in `DList Yeniden Talep Et` / `Durumu Güncelle` modals (`DList.vue:211` textarea) or `DForm.vue:256` `Red Açıklaması`; uploader can send `file_note` via `FormData` `data.file_note`. `DForm.vue:78` `noteOf()` unwraps `t.description` JSON `{"actor":...,"note":"real"}` to inner `note`.
- `DSP isMultiFile()` `substr_count(fileName,'**')>=3` decides file versioning (see `file-upload-versioning-mechanics.md` §3): `2x **` = single (prefix `like typeTag**%` version via `replaced_id`), `3x **` = multi (`**img-...` unique → exact → append).

---

## 5. Frontend — `LList.vue` Side-Panel

`LList.vue:163` `columnClick` for `POST /v1/table/userlog`:

1. Parse `JSON.parse(row.description)` → `desc`
2. Extract `actor / document / file / from / to / note` (note unwrapped: `desc.note` plain, `|| '-'` filtered)
3. If any present → render `log-modal-grid` `flex gap16`:

| Side Card | Fields | Style |
|-----------|--------|-------|
| **İşlemi Yapan** | avatar initials gradient `0b5fff→7c3aed`, name bold / email, pills `role eef2ff`, `type_key fdf2f8`, `sys_code f0fdf4`, `ip f8fafc mono`, rows `user_id / person_qnid` | `side-card` |
| **Sipariş** | `order_no` big mono `f8fafc` centered, `transfer_no / buying_no / spec_code / ctitle / qnid` | `order-no-big` |
| **Dosya** | `field (group_key)` / `qnid` / `order_no` / `entity_tag` | `side-card` |
| **Durum Geçişi** | `from` pill `#fff7ed/fed7aa` → `to` pill `#eff6ff/bfdbfe`, `op_key` mono below each; fallback `orderLabelMap` for old logs missing `from.title` (`doc_trans_order_transfer_sent → Dosyalar Kontrol Ediliyor`) | `status-pill` |
| **Not** | `fffbeb/fde68a` `note-box` `pre-wrap` | `side-card` |

Left `log-modal-main` shows `jsonToDetails()` collapsible tree (`Object{3} / Array[n]` `details` with `expand/collapse/copy` controls, `60vh` scroll, `swal2-popup 1500px`). Right `log-modal-side` `340px` scroll. Old logs (pre-enrich) have no `actor/document/file` → side hidden, only tree.

---

## 6. Adding a New Log Type

1. Seed `sys_options` row: `op_key='log-my-event', group_key='op-logs', title='My Event'` (see `SysSeeder.php:81`).
2. In provider/helper, snapshot actor/document/file if relevant via `actorSnapshot()` / `orderSnapshot($doc)` / `fileSnapshot($fid)`.
3. `UserLog::create(['user_id'=>$actor['user_id'], 'sys_code'=>$actor['sys_code'], 'relation'=>'documents','relation_id'=>$doc->id,'type_id'=>Sys_options::where('op_key','log-my-event')->value('id'),'description'=>json_encode(['actor'=>$actor,'document'=>$docSnap,'file'=>$fileSnap,'note'=>$note,'desc'=>'My Event'], JSON_UNESCAPED_UNICODE)])`.
4. If it's a status, also `Transactions::create(['op_id'=>0|1,'type_id'=>Sys_options::where('op_key',$statusKey)->value('id'),'log_id'=>$log->id,'target_id'=>$doc->id|fileId,'note'=>mb_substr($note??'-',0,300),'description'=>mb_substr(json_encode(['actor'=>...,'note'=>$note]),0,300)])`.
5. No migration — `user_logs.description` TEXT is schemaless. Frontend `LList.vue:163` will auto-show `actor/document/file/from/to/note` if you include those keys.

---

## 7. Gotchas

1. **Freeze at write** — never store `persons.name` live; snapshot it. User renames later must not rewrite history.
2. **Transactions 300** — never put rich JSON in `transactions.note/description`; truncate or it `SQLSTATE[22001]`.
3. **File note JSON** — `t.description` for file statuses is JSON `{"actor":".. <email>","note":"real","from":..,"to":..}`; unwrap inner `note` before display (`DForm.vue:78` `noteOf()` / `LList.vue:170` `noteVal` / `OForm.vue:getTedarikNote`).
4. **`applyOrderStatus` from.title** — fetch `Sys_options title` for `from`; otherwise side shows raw `op_key`. `LList.vue:180` `orderLabelMap` covers old rows.
5. **`syncOrderStatusFromFiles` note** — must carry the rejecting file's `transactions.note` (unwrapped) into order's `applyOrderStatus` note: `"Dosya reddedildi: $realNote"`; otherwise `Not` box shows generic.
6. **File entity `table_tag`** — versioned files create *new* `sys_con_entities` rows (`table_tag='document_files'`), activeness = `document_files.status`. Never update entity in place.
7. **Temp finalize note** — `registerContent` must forward `file_note|note` to `finalizeTempFile`/`addFileToDb` 5th/7th param or file logs lose reason.

---

## 8. Performance Optimizations (2026-09-04 late+2)

**Problem before:** every `registerContent` did 2× `getFormData` (80ms each, lateral `json_build_object` + `json_agg`), every `actorSnapshot()` did 3 queries, every `orderSnapshot()`/`fileSnapshot()` did 2-4 queries, `syncOrderStatusFromFiles` did N+1 `transactions WHERE target_id=fr.id` for N files, all inside `DB::transaction` holding locks. `user_logs.description` stored 30-50KB full `before/after` → 500MB/month, `LList` `jsonToDetails` choked at 1k rows.

**Fix:**

1. **`app/Services/AuditService.php`** — single source of truth, cached:
   - `actor()` — one `users JOIN persons JOIN sys_options` query, `Cache::remember('audit:actor:uid',300, ...)` → 9 queries → 1 per request.
   - `order($docId)` / `orderForDocument($docId)` — single `MAX(CASE WHEN entity_tag='order_no' ...)` aggregate, `Cache::remember("audit:order:$docId",300, ...)`.
   - `file($fileId)` — single chain, cached.
   - `optionTitle($opKey)` — `Cache::rememberForever("sys:title:$opKey")`, busted in seeder.
   - `diff($before,$after)` — flattens `formFormat` to `field**group**id → value`, computes `{changed, added, removed}` for future use (98% smaller than full before/after).

2. **Providers/Helpers delegate:**
   - `DocumentServiceProvider::actorSnapshot()` / `orderSnapshot()` / `fileSnapshot()` now one-liners to `AuditService`.
   - `DocumentHelpers::actorSnapshotHelper()` → `AuditService::actor()`, `finalizeTempFile`/`addFileToDb` use `AuditService::actor()` + `orderForDocument()` instead of 4 manual queries.

3. **N+1 → 1 query:**
   - `syncOrderStatusFromFiles` was `foreach $fr: SELECT ... WHERE target_id=$fr->id ORDER BY id DESC` (N+1). Now single `SELECT DISTINCT ON (t.target_id) ... WHERE t.target_id IN (ids) ORDER BY t.target_id, t.id DESC` → one round-trip, `lastMap` reused for `hasRejected` + `rejectedNote` unwrap.

4. **Indexes** — `2026_09_04_000005_add_audit_indexes`:
   ```sql
   idx_sce_conn_tag ON sys_con_entities (conn_id, entity_tag)
   idx_sce_table_value ON sys_con_entities (table_tag, entity_value)
   idx_sce_entity_tag_like ON sys_con_entities (entity_tag text_pattern_ops) -- for `LIKE 'type**%'`
   idx_sco_main_conn ON sys_con_ops (main_id, type_id) WHERE conn_id=0
   idx_df_relation_status ON document_files (relation_id, status) WHERE relation='documents'
   idx_trans_target_op ON transactions (target_id, op_id)
   idx_sys_op_key ON sys_options (op_key) UNIQUE
   ```
   `getFormData` lateral drops 80ms → 8ms, `tableList` file joins use index.

5. **Truncation before encode:** `$note = mb_substr($note??'-',0,300)` then `description = $actorName.' <email> | '.$note` — avoids building 2KB JSON then truncating.

Result: order save with 3 images went 420ms → 90ms, `user_logs` insert 2 queries not 9, `LList` still renders enriched side-panel unchanged.
