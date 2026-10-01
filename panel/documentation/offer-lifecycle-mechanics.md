# Offer Lifecycle Mechanics

## 1. Overview

Offers (`op-doc-offer`) have a two-axis state: latest `transactions` row (`doc_trans_offer_*`) plus `documents.status` (`1` active / `0` cancelled). Cancel is a flag, never a transaction. Delete is forbidden.

**Key files:**
- `app/Http/Controllers/DocumentController.php` — `index:19`, `transaction:225`, `setStatus:263`, `cancelOffer:305`, `reopenOffer:347`, `setFileStatus:372`
- `app/Providers/DocumentServiceProvider.php` — `registerContent:93-101`, `setStatus:743-786`, `cancel/reopen:425-533`, `removeContent:535-612`
- `app/Http/Controllers/ExportController.php` — `offerPdf:364`, dead `offerPdf1:314`, `index offers:109-162`
- `routes/api.php:33,58-60`, `routes/web.php:62-63`
- `resources/js/pages/coalsystem/Offer/OList.vue`, `OForm.vue`
- `resources/js/components/Offer/OfferTable.vue`, `OfferSummary.vue`, `OfferLogTimeline.vue`, `OfferRequestTable.vue`
- `resources/js/lib/offerStatus.js`, `resources/js/pages/coalsystem/Request/RForm.vue`

## 2. Status keys

Seeded in `database/seeders/SysSeeder.php:236-285`:

- `doc_trans_created` (generic, written on every `registerContent` create)
- `doc_trans_offer_draft` Taslak
- `doc_trans_offer_sended` Gönderildi
- `doc_trans_offer_review` İnceleniyor
- `doc_trans_offer_revision` Revizyon Bekleniyor
- `doc_trans_offer_revised` Revize Edildi (auto-written on reseller edit, see §5)
- `doc_trans_offer_approved` Onaylandı
- `doc_trans_offer_rejected` Reddedildi

Second axis `document_status`: `1` active, `0` `İptal Edildi`. Frontend virtual key `cancelled` in `offerStatus.js:11,40-48` overrides any transaction when `document_status==0`, `terminal:true`.

List `status` column format: `op_key**Title**note` (latest `op-trans-op-doc-offer` transaction, `Documents.php:83-86`).

## 3. Endpoints

Base `auth:sanctum + CheckPermissionVersion`.

### 3.1 CRUD — `ANY /v1/document/{id?}` → `index`

- `GET /v1/document/<uuid>` — load `{document,formFormat}`. Used by `OForm.vue:63-96`.
- `POST /v1/document` — `FormData{data:JSON{typeKey:op-doc-offer,dynamicF}, dynamicFile*:File|temp-ref}`. Backend merges string `dynamicFile*` into `$files` (`DocumentController.php:92-96`). On success fires `EmailServiceProvider::sendOfferGiven(type:offerGiven)`.
- `PUT /v1/document/<uuid>` — same envelope via `parsePut()` fallback. Revision auto-flow applies.
- `DELETE /v1/document/<uuid>` — always `403 Teklifler silinemez...` for offers (`DocumentController.php:203-211`). Others → `removeContent()` passivate.

Listing: `POST /v1/table/documents` with `[{form-type=op-doc-offer-form},{type=op-doc-offer},WITH_CANCELLED_FILTER]` (`OList.vue:634-646`).

### 3.2 `POST /v1/trans/set-status` → `setStatus:263-298`

`FormData{id:uuid, op_key:doc_trans_*, note?}`. Validates `id,op_key`. Resolves type via `getFormData`, gates `docPermCheck(type,status)` (=`per-05-02` for offers) except reseller may self-send `doc_trans_offer_sended`. Writes `UserLog log-document-status-update` + `Transactions`. Unknown key → `422`. Offer → `sendOfferStatus(type:offerStatus)`. If doc was `status=0`, revives to `1` + `logOfferReopened` — cancel is not terminal for admins.

OList buttons: `review/revision/rejected/approved` only. Success patches row `status=op_key**title**note`.

### 3.3 `POST /v1/trans/cancel-offer` → `cancelOffer:305-340`

`FormData{id:qnid, note}`. Validates `id required|uuid` (raw SQL). Gates `docPermCheck(op-doc-offer,edit)=per-08-02` + `offerOwnershipCheck` first (anti-probing). Service: non-offer fail, already `0` → `Teklif zaten iptal`, else conditional `update where status=1` (race-safe). Logs `log-tender-update {desc:Teklif İptal Edildi, after:{document}, note}` — `before` omitted intentionally so timeline classifies as status. Keeps EAV + files, stays visible with `with-cancelled=1`.

### 3.4 `POST /v1/trans/reopen-offer` → `reopenOffer:347-370`

Same guards. Only `op-doc-offer`, conditional `update where status=0→1`. No status write — last transaction is already pre-cancel state, revive is automatic. Logs `Teklif Geri Açıldı`.

### 3.5 PDF / Excel

- `POST /export/offer` (web) → `offerPdf:364-436` — ZIP bundle via `plib.openTab('POST','/export/offer',{id})` (`OForm.vue:262-270`). Loads `getFormData`, `latestStatus` overridden `İptal Edildi` if cancelled. Non-file offers render `exports.offer` → `offer-<qnid>.pdf` + append `offer_otherdocs_file**` decrypted via `EncryptionProvider`; file-type offers ZIP files only. Downloads `offer-<qnid>.zip`.
- `offerPdf1:314-359` — single PDF download, no route (dead).
- `POST /api/v1/export/offers` → `index(model=offers)` — `teklifler.xlsx`, force `with-cancelled=1`, maps `status: 0→İptal Edildi, empty/draft→Taslak, else split('**')[1]`.

History: `POST /v1/table/userlog {model:userlogs, filter:[doc_qnid=offer.uuid]}` (`OForm.vue:328-339`).

## 4. Permission gates

`PermissionHelpers.php:31-48`: `op-doc-offer edit=per-08-02, read=per-08-01, status=per-05-02`.

- `index`: `docPermCheck(read|edit)` else 403 (except reseller own client doc). Offer requires `session currentStatus.canResponse` (all paths) + `offerOwnershipCheck` on GET/PUT.
- `offerOwnershipCheck($qnid):89-111`: admin true; non-reseller false (fail-closed); reseller needs `cliid` from any offer row ∈ `clientQnidList`.
- `setStatus`: `per-05-02`, reseller bypass only `doc_trans_offer_sended`.
- `cancel/reopen`: `per-08-02` + ownership.
- Listing scoping `Documents.php:112-133`: reseller offers `WHERE cliid IN (clientQnidList)`.

`canResponse` from `clientPermInfo` (see session doc): false if bound client missing `cont_imza_file**` or last file status ≠ `doc_file_accepted`.

## 5. Revision auto-flow (PUT `DocumentController.php:113-177`)

1. Load `getFormData`. If `document_status==0` → `422` for everyone incl. admin.
2. Reseller: last `status[].op_key` (default `created`) must ∈ `[revision,created,draft]` else `403`.
3. `registerContent` (status/type/person never writable).
4. If offer and lastStatus==`revision` → auto `setStatus(revised, Müşteri Revize Etti)` + `sendOfferGiven(type:offerRevision)`.
5. Admin side-effect `OForm.vue:144-159`: non-reseller + `per-08-02` opening `[created,draft,revised]` fires `set-status review / Yönetici İncelemeye Başladı`; skipped if cancelled.

## 6. Cancel vs delete

- DELETE blocked for offers. Cancel = `status 1→0` only, rows/files kept, visible with `with-cancelled=1` (`Documents.php:61-62,103-106` default `status='1'`; offers + flag → `IN ('0','1')`). Excel always includes cancelled.

## 7. Frontend guards (`offerStatus.js`, `OList.vue`, `OForm.vue`)

- `WITH_CANCELLED_FILTER={key:'with-cancelled',value:'1'}` always in offer lists.
- OList status pill clickable only if `per-05-02`; cancelled tooltip `Durum seçerek geri aç`.
- Edit disabled if `terminal`; non-`[revision,created,draft]` + non-admin → Swal `Sadece Revizyon`.
- `Teklif Ver` hidden for admin (`OList.vue:714`); picker `op-doc-offer-form**Teklif Formu | op-doc-offer-file**Dosya`.
- `OForm.vue:42-53`: `!canResponse && !viewMode` → Swal + back. `editable = !cancelled && status∈[revision,created,draft] && per-08-02 && reseller`; new (no id) → true. Non-editable → `OfferSummary` + `OfferLogTimeline` read-only; cancelled hides Edit/Revise/Approve/Reject.
- Reseller `Detay` pushes `OForm?id+?view=1`; `isViewMode` forces read-only.

## 8. Gotchas

1. Cancel has no transaction — read `document_status`, not `status`.
2. `set-status` on cancelled revives — intentional admin backdoor.
3. Reseller PUT limited to 3 statuses; admin PUT blocked only when cancelled.
4. `offerPdf1` dead — only `offerPdf` (ZIP) wired.
5. `WITH_CANCELLED_FILTER` required or cancelled vanishes from lists.
