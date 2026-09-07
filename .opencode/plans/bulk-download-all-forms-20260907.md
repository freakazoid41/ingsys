# Bütün Formları İndir — Bulk ZIP Download (incl. rejected) — Plan

**Date:** 2026-09-07  
**Owner:** RedV  
**Status:** Plan — awaiting Master build approval  
**Request:** On order detail (both `/coalpanel/orders/form/:id` admin and `/tedarikpanel/orders/form/:id` tedarik) add **Bütün Formları İndir** orange button next to `Malzeme Kabul Formu` / `Malzeme Cinsi Kabul Formu`. Click → ZIP of **all entered files for that order, including `status 0` rejected/old versions**, with inspector/status context. Current history buttons already show `file_type + status pill + personName + note + date` via `DForm` / `OrderItemTable`.

---

## 1) Current Mechanics (where button lives)

- **Order detail dual template** `panel/resources/js/pages/coalsystem/Order/OForm.vue` `isTedarik` (`~1360 lines`): header + `OrderItemTable` + **Step 5 orange cards** (`998-1011` `printMalzemeKabul` / `printMalzemeCinsMiktarKabul` → `POST /v1/export/malzeme-kabul` `ExportController:443` `PDF::loadView` single PDF `download()`). Per-item files live in `OrderItemTable.vue:1281` (`fetchItemFiles` → `GET /v1/document/{item.qnid}`).
- **File history + inspector** `panel/resources/js/pages/coalsystem/Documents/DForm.vue:210` `isTedarik` — renders `files[]` from `GET /v1/file-detail/:qnid` `DocumentController:783`:
  ```sql
  SELECT i.qnid file_qnid, i.status file_status (1 current, 0 old/rejected), i.description file_desc (encrypted), sf.title file_type, se.entity_tag, d.qnid relation_qnid,
         (SELECT json_build_object('op_key',sot.op_key,'title',sot.title,'name',p.name,'note',t.description,'created_at',t.created_at)
          FROM transactions t JOIN sys_options sot ... WHERE t.target_id=i.id AND op_id=1 ORDER BY t.id DESC) last_status
  FROM document_files i JOIN sys_con_entities se ON se.entity_value=i.id::text
  JOIN documents d ON d.id=i.relation_id::int
  WHERE (d.id=:orderId OR d.parent_id=:orderId) AND se.entity_tag NOT LIKE '%item_images_file%' AND i.description!=''
  ORDER BY i.created_at DESC  -- NO i.status=1 filter → includes rejected
  ```
  `statusCls` → `is-success #00a651 / is-fail #e30613`, `personName(f)=last_status.name`, `noteOf`, `fmtDate(last_status.created_at)`; `openFile → /order-file/{qnid}` via `DocumentHelpers:83 decryptFile` (`EncryptionProvider AES-128-CBC PBKDF2 pickle`).

- **Single file route** `routes/web.php:72` `GET /order-file/{doc}` → `decryptFile($doc,'view'|'download')` `File::get(storage/app/public/documents/plain)`.

- **Bulk ZIP precedent** `panel/app/Http/Controllers/ExportController.php:370 offerPdf` — only ZIP in repo:
  ```php
  $zipPath=sys_get_temp_dir().'/offer_bundle_'.$qnid.'_'.time().'.zip';
  $zip=new \ZipArchive(); $zip->open($zipPath,CREATE|OVERWRITE);
  $pdf=$pdf->output(); $zip->addFromString('offer-'.$qnid.'.pdf',$pdf);
  foreach($formData as k=>v) if(strpos($k,'offer_otherdocs_file')!==false){ $p=storage_path('app/public/documents/'.$enc->decrypt(json_decode($v,true)['description'])); if(file_exists($p)) $zip->addFile($p,basename($p)); }
  $zip->close(); return response()->download($zipPath,'offer-'.$qnid.'.zip')->deleteFileAfterSend(true);
  ```
  No existing “download all order files as zip”.

---

## 2) Design — `Bütün Formları İndir`

### 2.1 UX & Placement (both panels, same OForm)

**Location** `OForm.vue:998` row currently:
```
[Malzeme Kabul Formu] [Malzeme Cinsi Kabul Formu]   (orange #FF5A1F)
```
Add leftmost:
```
[Bütün Formları İndir — ⬇ icon] [Malzeme Kabul Formu 👁] [Malzeme Cinsi Kabul Formu 👁]   // all #FF5A1F 44px pill, same tedarik-orange
```
- Show **iff** `files.length >0` from `fileDetail` (order has at least one `document_files` row). Otherwise disabled `opacity .55` + tooltip `Henüz form yok`.
- Same in **admin** (`!isTedarik`) and **tedarik** (`isTedarik`) — shared template, no fork.
- Loading state `downloadingAll` → `ki-loading spin` + `İndiriliyor…` + `Swal.fire` `İndiriliyor...` covering zip build (like `printingKabul`).
- Click → `GET /api/v1/order/{qnid}/download-all` (or `POST /v1/export/order-files-zip`) → blob → `URL.createObjectURL` + anchor `download="order-3510004400-forms.zip"`; on error `Swal error` with `404 Dosya bulunamadı`.

### 2.2 Backend — new endpoint (reuse fileDetail query)

**Route** `panel/routes/api.php:41` (before wildcard `POST /v1/export/{model}`):
```php
Route::get('/v1/order/{qnid}/download-all', [DocumentController::class, 'downloadAllOrderFiles']);
Route::post('/v1/export/order-files-zip', [DocumentController::class, 'downloadAllOrderFiles']); // alias for FormData
```

**Controller** `panel/app/Http/Controllers/DocumentController.php` new method `downloadAllOrderFiles(Request $r, $qnid = null)`:
1. Resolve `orderQnid = $qnid ?? $r->input('qnid')` validate `uuid`.
2. **Perm + scope** — `docPermCheck('op-doc-order','read')` or `per-05-01`/`per-07-01`; for `op-pert-reseller` enforce `LIFNR+SYSTEM` gate: `Documents::tableList` scoping already, but explicit check: `spec_code == client.lifnr AND sys_code == client_system` (same composite as late5). If not owned → `403`.
3. **Find orderId** — `Documents where qnid = orderQnid` + type `op-doc-order`.
4. **Fetch ALL files** — copy `fileDetail:869` query **without** `se.entity_tag NOT LIKE '%item_images_file%'` filter? Product images are `item_images_file` — exclude (they are not “forms”). Keep filter, but **include `i.status IN (0,1)`** already (no status filter) so rejected included. Select `i.qnid, i.description, sf.title file_type, se.entity_tag, i.created_at, last_status`.
5. If `files` empty → `404 {success:false, msg:'İndirilecek form bulunamadı'}`.
6. **Zip build**:
   ```php
   $enc = new EncryptionProvider();
   $zipName = 'order-'.$orderNo.'-forms-'.date('Ymd-His').'.zip';
   $zipPath = sys_get_temp_dir().'/'.$zipName;
   $zip = new \ZipArchive(); $zip->open($zipPath, ZipArchive::CREATE|ZipArchive::OVERWRITE);
   $usedNames = [];
   foreach($files as $f){
     $plain = $enc->decrypt($f->file_desc); // handles both compact + legacy JSON
     $path = storage_path('app/public/documents/'.$plain);
     if(!file_exists($path)) continue; // skip missing, log warning
     $ext = pathinfo($plain, PATHINFO_EXTENSION) ?: 'pdf';
     $base = ($f->file_type ?: 'form').'-'.substr($f->file_qnid,0,8).'.'.$ext; // e.g. transfer_kabul_file-a1b2c3d4.pdf
     // de-dupe: if same file_type repeated, suffix counter
     $name = $base; $i=1; while(isset($usedNames[$name])) $name = pathinfo($base,PATHINFO_FILENAME).'-'.$i++.'.'.$ext;
     $usedNames[$name]=true;
     $zip->addFile($path, $name);
     // optional: add _info.txt per file with inspector/status? Keep zip clean for now; info lives in DForm UI.
   }
   // If only one file, still zip for uniform UX (or could directly download single). Keep zip.
   $zip->close();
   return response()->download($zipPath, 'order-'.$orderNo.'-tum-formlar.zip')->deleteFileAfterSend(true);
   ```
   Use `storage_path('app/public/documents/')` + `decrypt` (handles `salt:iv:ct` compact and legacy JSON). Add `Content-Type: application/zip`.

7. **Error handling** — `try/catch` log `Log::warning('downloadAll failed', ...)` → `500`.

**Permissions:**reuse existing `per-07-01` (document list) or `per-05-01` (order view) — both panels already gate order detail via `isTedarik` + `docPermCheck`. No new perm needed.

### 2.3 Frontend wiring `OForm.vue`

- Add `data: downloadingAll:false`
- Add method `downloadAllForms()`:
  ```js
  async downloadAllForms(){
    if(this.downloadingAll) return;
    this.downloadingAll=true;
    Swal.fire({title:'İndiriliyor…', text:'Formlar hazırlanıyor', allowOutsideClick:false, didOpen:()=>Swal.showLoading()});
    try{
      const res = await fetch(`/api/v1/order/${this.$route.params.id}/download-all`, {headers:{Authorization:`Bearer ${localStorage.getItem('token')}`}});
      if(!res.ok){ const j=await res.json().catch(()=>({msg:'İndirme başarısız'})); throw new Error(j.msg||`HTTP ${res.status}`); }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a=document.createElement('a'); a.href=url; a.download=`order-${this.orderNo||this.$route.params.id}-tum-formlar.zip`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
      Swal.close(); this.plib.toast(Swal,'success','İndirildi');
    }catch(e){ Swal.fire({icon:'error',title:'İndirilemedi',text:e.message}) }
    finally{ this.downloadingAll=false }
  }
  ```
- Template add button:
  ```vue
  <button class="tedarik-orange-btn" :disabled="downloadingAll || !hasAnyFile" @click="downloadAllForms">
    <i :class="downloadingAll?'ki-outline ki-loading':'ki-outline ki-archive'" :style="downloadingAll?'animation:spin 1s linear infinite':''"></i>
    {{downloadingAll?'İndiriliyor…':'Bütün Formları İndir'}}
  </button>
  ```
  `hasAnyFile` computed from `tedarikExistingKabul|Cins|OrderItemTable existingFiles`.

- Keep existing `Malzeme Kabul/Cinsi` generate-PDF buttons as-is (they generate, not list).

### 2.4 Alternative considered & rejected

- **Reuse `fileDetail` + client-side zip (JSZip):** would require fetching each file as blob via `/order-file` + JS `zip.js` — heavier, no server decrypt reuse, N requests. Server zip is one round-trip.
- **Include product images:** excluded via `NOT LIKE '%item_images_file%'` — keep “forms” semantics (transfer_kabul/cins + item_test). If Master later wants images too, just remove that filter.

---

## 3) Files to Touch (est. 3)

| File | Change |
|------|--------|
| `panel/routes/api.php:41` | add `GET /v1/order/{qnid}/download-all` before wildcard |
| `panel/app/Http/Controllers/DocumentController.php:783` | add `downloadAllOrderFiles` (60 lines, reuse fileDetail query + ZipArchive + LIFNR+SYSTEM scope) |
| `panel/resources/js/pages/coalsystem/Order/OForm.vue:998` | add `Bütün Formları İndir` button + `downloadingAll` + `downloadAllForms()` + `hasAnyFile` computed; shared `isTedarik` |

No migration, no new `sys_options`, no `Documents` model change (reuse existing `fileDetail` SQL).

---

## 4) Risks & Edge

- **Missing file on disk:** `file_exists` skip + count skipped; if all missing → `404`. Log `warning` with `file_qnid`.
- **Large zip:** max ~ 2× `transfer_kabul` + `transfer_cins` + N× `item_test` per order (≤ 10 files, each ≤ 42 MB, but typical 1 MB). Zip of 10×5 MB = 50 MB ok. Use `sys_get_temp_dir` + `deleteFileAfterSend`.
- **Old versions:** `i.status=0` rejected old versions are separate rows with same `entity_tag` — both will be in zip as `transfer_kabul_file-a1b2-...pdf` vs `transfer_kabul_file-b2c3-...pdf` (deduped by `substr(qnid,0,8)`). This satisfies “including rejected ones”.
- **Reseller scope:** must enforce same `LIFNR+SYSTEM` as order list — reuse `Documents::tableList` check or inline `clientQnidList→lifnr+sys` before zip. Admin sees all.
- **IDOR:** `/order-file/{qnid}` currently IDOR (any auth qnid can fetch) — new zip adds same check, so not worsening.

---

## 5) Testing

1. **Admin** `/coalpanel/orders/form/7a15...` (YILDIZ) with `transfer_kabul` + `transfer_cins` + 1× `item_test` (3 files) → click `Bütün Formları İndir` → zip `order-3510004400-tum-formlar.zip` with 3 PDFs, names `transfer_kabul_file-xxxx.pdf` etc, open OK.
2. **Reject one** `DForm` → `Reddet` with note, then **supplier** `/tedarikpanel/orders/form/7a15...` sees `Reddedildi` pill, **Bütün Formları İndir** still shows **4** files (3 current + 1 rejected `status 0` old version) — verify count in zip = `SELECT count(*) FROM document_files WHERE relation_id IN (orderId, itemIds)`.
3. **Reseller scoping** `kbbozat` (GDZ) → cannot download `ADM` order `5e5e...` → `403`.
4. **Empty order** `3510001793` (no files yet) → button disabled + tooltip `Henüz form yok`, `GET` returns `404` JSON.
5. **Large** upload 5× 10 MB → zip streams, `deleteFileAfterSend` cleans temp.

---

## 6) Rollback

- Remove route + controller method + button — no DB change, no data loss. Zip temp files auto-deleted.

**Next:** If Master says “build it”, implement §2.2 + §2.3 in one go, `php -l` + `npm run build` + manual zip test on `3510004400`.
