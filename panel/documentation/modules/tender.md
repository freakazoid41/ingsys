# İhale (Tender) — What Is Built + Pipeline

> **Status 2026-11-11:** full lifecycle built — tender state machine (`tender-created → tender-activated → tender-complated`), supplier offer screen over the shared client document library, offer + file statuses, admin review, winner selection and snapshot locking. **Missing:** notifications, invite/participant model, deadlines, prices, order handoff.
> **Spec source:** `memory/idea.md` ("İhale sistemi" section).
> **Canonical EAV mechanics:** `panel/documentation/form-system-mechanics.md`. Product catalog: `modules/products.md`.

---

## 1. Pipeline — built flow (2026-11-11)

```
── 1. CREATE ─────────────────────────────────────────────────────────────────
[admin]  İhaleler → Yeni İhale            /coalpanel/ihaleler/form
         İhale Kodu (required) · Teknik Şartname file (required) · product rows (picker + qty/unit)
         → documents(op-doc-ihale) + EAV
         → transactions: tender-created
         state: tender-created              offers BLOCKED (server-enforced)

── 2. ACTIVATE ───────────────────────────────────────────────────────────────
[admin]  İhaleler → 🚀 Aktif Et
         → POST /v1/tenders/activate
         → transactions: tender-activated
         state: tender-activated             teklif alımı açık

── 3. OFFER (supplier) ───────────────────────────────────────────────────────
[supplier] İhaleler → Teklif Ver           /coalpanel/ihaleler/teklif?ihale=<qnid>
           Ürün Listesi (server-side, card rows) → product → matrix
           per file upload:
             temp-upload (valid_until via flatpickr, optional)
             → POST /v1/client-docs/slot
             → shared library doc (client + product code + requirement + kVA)
             → transactions(op_id=1): doc_file_waiting | doc_file_refreshed
           Teklifi Kaydet → POST/PUT /v1/document  (op-doc-ihale-offer)
           → offer doc + header conn + N item conns
           → transactions: offer-sended
           expired file = save blocked

── 4. REVIEW (admin) ─────────────────────────────────────────────────────────
[admin]  Teklifler queue                   /coalpanel/ihaleler/teklifler
         ✔ Kabul Et   → POST /v1/offers/accept
                        → transactions: offer-accepted
                        → winner entities + snapshot every offer + tender-complated
         ✖ Reddet     → POST /v1/offers/reject {note}
                        → transactions: offer-rejected (reason required)
         cell modal   → Dosyayı Reddet
                        → POST /v1/offers/file-reject {note}
                        → file transaction: offer-file-rejected
                        → offer auto-rejected: "Dosya reddedildi: <note>"

── 5. REVISE (supplier) ──────────────────────────────────────────────────────
[supplier] fix + Teklifi Kaydet on a rejected offer
           → registerContent sees last offer status = offer-rejected
           → transactions: offer-sended   (rejection stays in history)

── 6. COMPLETE (admin) ───────────────────────────────────────────────────────
[admin]  İhaleler → ✔ Tamamla → winner modal (only suppliers who submitted)
         → POST /v1/tenders/complete {winner_supplier_qnid, winner_supplier_title}
         → winner_supplier_qnid / winner_supplier_title / winner_offer_qnid on tender
         → snapshot JSON on every offer (files frozen at that moment)
         → transactions: tender-complated
         state: read-only — later library replacements do NOT rewrite this tender

── PLANNED ───────────────────────────────────────────────────────────────────
- invites/participants + deadline auto-disqualification
- notifications: ihale-*/offer-* mail + bell (none exist yet)
- prices (online vs offline), multi-winner / lot-based tenders
- order handoff after award (Tedarik)
- per-file acceptance/approval (only rejection exists today)
- supplier portal under /tedarikpanel (today entry is coalpanel)
```


---

## 2. What Lives Where

| Piece | File | Notes |
|---|---|---|
| Menu "İhale Yönetimi" | `resources/js/components/coalparts/Sidebar.vue:397` | gated `per-041-03`; children: İhaleler · Yeni İhale · Teklifler. **Teklif Ver menu entry removed 2026-11-11**, but the per-row **Teklif Ver** icon in `IhList` stays (gated `per-041-03-01`) → opens `IhOffer?ihale=<qnid>` |
| Routes | `resources/js/router/index.js` | `/coalpanel/ihaleler` → `IhList`, `/ihaleler/teklifler` → `IhOfferList`, `/ihaleler/form/:id?` → `IhForm`, `/ihaleler/teklif/:id?` → `IhOffer` |
| Tender list | `resources/js/pages/coalsystem/Ihale/IhList.vue` | ajax PickleTable, `all` search, product count from `main_attr` row keys, spec pill, edit/offers/delete |
| Offers list | `resources/js/pages/coalsystem/Ihale/IhOfferList.vue` | admin queue; `?ihale=<qnid>` filters one tender (`attr` filter), `row_count` from server |
| Page + product picker | `resources/js/pages/coalsystem/Ihale/IhForm.vue` | modal table, multi-row products, qty/unit; save → back to `IhList` |
| Form schema | `resources/js/components/coalparts/Form.vue:1305` | `op-doc-ihale-form` (see §4) |
| Dictionary seeder | `database/seeders/IhaleSeeder.php` | idempotent, run once per env (see §3) |
| Permission map | `app/Helpers/PermissionHelpers.php:89` | `op-doc-ihale` edit/read/status → `per-041-03` |
| List counts | `app/Models/Documents.php` `tableList` | conditional `row_count` for `op-doc-ihale-form` (distinct product rows) + `op-doc-ihale-offer-form` (item form conns) |

---

## 3. Dictionary (seeded — `IhaleSeeder.php`, local DB done)

| op_key | group_key | Purpose |
|---|---|---|
| `op-doc-ihale` | `op-doc` | İhale header |
| `op-doc-ihale-form` | `op-doc-forms` | Header form |
| `op-ihale_spec_file` | `op-file-types` | File-type title for the spec file |

Run: `php artisan db:seed --class=IhaleSeeder`. No migration — EAV only.

---

## 4. Form Schema (`op-doc-ihale-form`)

- **İhale Kodu** (`ihale_kodu`, text, required, full width).
- **İlgili Teknik Şartname** (`multiple`, `group_key='ihale_spec'`, single row `hideAdd`, not removable): `spec_title` (text, optional) + `ihale_spec_file` (file: pdf/xls/xlsx/jpg/png, **required** since 2026-11-11). Versions like a single slot (2× `**`).
  - **Do not put `required: true` on the file input itself:** file inputs always report `value === ''`, so `pickle.checkForm` would mark them invalid forever and block every save. Required-ness lives in `IhForm.submitForm` → `hasSpecFile()` (saved entity in `formDataStore.rawData.formFormat` OR a pending `ihale_spec_file` key in `formData.files`). The label carries a visual `*`.
- **Ürünler** (`multiple`, `group_key='ihale_product_rows'`, `removable`, first row required): per row `product_qnid` (hidden) + `product_title` (readonly, tap-to-pick) + `quantity` (text, required) + `unit` (readonly, auto-filled ST/KG/M).
- Engine rule that bit us: the row trash icon renders on the **last** sub only (`Form.vue` multiple builder) — so the hidden field goes first, visible fields last.

---

## 5. Product Picker (`IhForm.vue`)

PForm category-picker pattern, adapted for multi-row:
- Clicking a row's readonly Ürün field (delegated document click, survives Form re-renders) opens **Ürün Seç** modal.
- Modal = ajax PickleTable, server-paginated (10/page), search by code/name, **Seç** per row. Big-data safe.
- Pick writes `product_qnid` + `product_title` + `unit` into that row and fires `input` so the engine captures it; duplicates across rows blocked with a notice.
- `syncProductsToForm()` re-injects all row values into `dynamicF` at submit (detached-write guard, PForm pattern).
- Save validation: at least one picked product + engine `checkForm` (code, title, quantity).
- After first save, redirects to edit mode (`/coalpanel/ihaleler/form/{qnid}`).

---

## 6. Category Template Split (same session)

`category_files` row (`op-doc-product-category-form`): the old combined **Standart / Belge Adı** (`file_title`) is now two fields — **Standart** (`standard`, optional, col 2) + **Belge Adı** (`file_title`, required, kept storage so old rows keep working). Row layout: Evrak No 2 + Standart 2 + Belge Adı 3 + Dosya Grubu 3 + Dosya Tipleri 2. Doc: `modules/products.md:47`.

---

## 7. Demo Data (local DB)

**2026-11-11 reset:** all previous demo catalogue + tender rows purged (hard delete: documents, `sys_con_ops`, `sys_con_entities`, linked `document_files`, transactions, `user_logs`). Old 6 categories / 12 products / `DEMO-001` / test offer are gone.

New catalogue = `TransformerCatalogSeeder.php` (idempotent by category code, direct DB inserts so audit-free like `KvaSeeder`):

| Category | Code | Products |
|---|---|---|
| 33 kV Hermetik Dağıtım Transformatörü | `TRF-33KV` | `TRF-33-050 / 100 / 160 / 250 / 400 / 630 / 1000 / 1250 / 1600` — short name `33 kV Hermetik Dağıtım Trafosu <n> kVA`, unit ST, linked via `category_qnid` + denormalized `category_title` |

Local category qnid (2026-11-11 re-seed): `edd5210c-123a-49c5-9bfd-e0296caeddc0` → `/coalpanel/product-categories/form/edd5210c-123a-49c5-9bfd-e0296caeddc0`.

Category file template = the reference image 1:1 — 14 rows, Evrak No kept exactly `0–6` (Tip Test Raporları) and `8–14` (Diğer Belgeler), `7` intentionally absent like the image. Rows `9`, `10`, `12` span all powers in the image → stored `kva=[]`; the rest list all nine kVA codes explicitly. `file_types`: `pdf` for reports/certs, `pdf_img` for catalogue/drawings.

Picker query (`form-type=op-doc-product-form` + `type=op-doc-product`) returns **9**; categories query returns **1**.

Tender examples = `TenderDemoSeeder.php` (idempotent by `ihale_kodu`, created through `registerContent` so transactions/audit logs exist). Each tender carries multiple product rows (unique row-key prefix per row — see `products.md` gotcha #8):

| İhale Kodu | Ürünler (adet) | Kalem |
|---|---|---|
| `IH-2026-001` | TRF-33-630 ×2, TRF-33-1000 ×1, TRF-33-1250 ×3 | 3 |
| `IH-2026-002` | TRF-33-050 ×6, TRF-33-100 ×4, TRF-33-160 ×2, TRF-33-250 ×3 | 4 |
| `IH-2026-003` | TRF-33-400 ×5, TRF-33-1600 ×1 | 2 |
| `IH-2026-004` | TRF-33-100 ×2, TRF-33-630 ×1 | 2 |

`IH-2026-004` intentionally repeats **TRF-33-100** with `IH-2026-002` so the shared client-document library can be seen across two tenders.

Verified: `tableList` `row_count` and UI card count both return 3 / 4 / 2. Offer tables still empty (supplier flow has no UI door yet).

---

## 8. How To Verify

1. Hard refresh → coalpanel → **İhale Yönetimi → İhale**.
2. Fill İhale Kodu, attach a spec file, click Ürün → modal lists the 9 `TRF-33-*` transformer products → Seç → unit (`ST`) fills → type Miktar → + Ekle for more rows → trash removes a row.
3. Kaydet → success toast → URL becomes `/coalpanel/ihaleler/form/{qnid}`.
4. Reopen: code, spec, products, quantities, units all read back.

---

---

## 10. Teklif (Offer) — Built 2026-11-11, Verified Over HTTP

Supplier-only screen (`IhOffer.vue`, route `/coalpanel/ihaleler/teklif/:id?`, menu **Teklif Ver**). Custom UI (no Form engine — matrix too complex), same data mechanic: one POST/PUT envelope (`typeKey` + `dynamicF` + `dynamicFile*` refs) through `/v1/document` → `registerContent`. Versioning, logs, perms unchanged.

**Rule:** offer requires an account with a connected client (non-empty `clientQnidList`); otherwise the screen shows a notice. Admin review UI is a later step.

**Dictionary** (`IhaleSeeder.php`, seeded): `op-doc-ihale-offer` (header), `op-doc-ihale-offer-form` (header row: `ihale_qnid`, `ihale_kodu`, `supplier_qnid`, `supplier_title`), `op-doc-ihale-offer-item-form` (one row per product: product link + qty/unit + `imalatci_firma_adi`, `urun_markasi`, `tedas_raporu`, `genel_gorus`). Item rows also carry `ihale_qnid` + `supplier_qnid` (denormalized, for scoping).

**Permissions:** `docPermCheck` map (`PermissionHelpers.php`): offer + item → edit `per-041-03-01` (Teklif Oluşturma), read `per-041-03-01,per-041-03-02` (İnceleme). Menu **Teklif Ver** + route gate on create (inspect-only opens the screen readonly: inputs disabled, actions + save hidden). Seeded via `role_details.json` + `coal_roles_templates.json` → `SysRoleTemplateSeeder` (admin: both codes; reseller: 03-01); existing user entities migrated by role-template union + cache bump (24 users). DEV_ADMIN list updated. **Ownership** (`DocumentController::assertOfferOwnership`, after `docPermCheck`): resellers may only touch offers whose `supplier_qnid` is in their own `clientQnidList` (checked in payload for POST/PUT, in stored doc for GET/DELETE). Admins bypass. Fail-closed. Note: kbbozat/picklecanme user entities were missing `per-041-03` (stale vs role template) — granted + cache bumped.

**Matrix mechanic (the image, sections A/B/C):**
- Header table A per product: ihale kodu (ro), imalatçı* (fill), tedarikçi (ro, from binding), marka* (fill), malzeme grubu (ro), spec (ro), tedaş/görüş (optional).
- Templates come from the product category's `category_files` rows (grouped by nameTag). `file_group == 'Diğer Belgeler'` (case-insensitive) → section C, else B.
- Columns = union of template `kva` lists in that section (numeric sort). Template with empty `kva` → ONE spanning cell (covers all powers); else one cell per listed kVA.
- Cell file tag: `{cellKey}**offer_cells**{rowKey}`, exactly 2× `**` = single-slot versioning; first segment unique per cell (`oc_{prod8}_{reqShort}_{kva}`) so the prefix search never collides. On edit the new row reuses the old tag (backend line 401) — history preserved.
- TEDAŞ row is multi-file (append-only): tags `tedas_file**tedas_docs**{rowKey}**doc-{uniq}` (3× `**` → exact-match, never replaces). Verified live: 2 uploads → 2 active rows (36350, 36351).
- GENEL DEĞERLENDİRME GÖRÜŞÜ is a single admin-only file: tag `gorus_{prod8}**gorus_docs**{rowKey}` (2× `**` → replace on re-upload). Suppliers see it readonly ("admin dolduracak"); admins get upload/replace/remove. Server-side `stripResellerGorus` drops forged görüş files + removedData rows before `registerContent`. Verified: supplier forgery → 200 but 0 entities stored; admin upload → stored + read back.
- Uploads go through immediate `/v1/temp-upload` (reference at save); validation mirrors backend (pdf/xls/xlsx/jpg/png, 42 MB). Save requires imalatçı + marka + every cell filled (pending or saved).
- Existing-offer lookup: `POST /v1/table/documents` + `attr` filter on `supplier_qnid`, client-side match on `ihale_qnid` → redirects to edit instead of duplicating.

**Demo data (local):** TEMİZLENDİ 2026-11-11 — eski `DEMO-001` ihalesi + test teklifi + tüm demo ürün/kategoriler silindi. Yerine `TransformerCatalogSeeder` ile 1 kategori (`TRF-33KV`, 14 dosya şartı) + 9 ürün (`TRF-33-050 … TRF-33-1600`) geldi. İhale/teklif tabloları şu an boş (`tender.md §7`).

**Verified live (curl + real 2FA sessions):**
- POST offer as kbbozat → 200, header + item + active file stored; GET readback matches screen contract (conn ids, file JSON).
- GET + PUT same offer as picklecanme (bound HASÇELİK) → 403 `Bu teklif sizin firmanıza ait değil.` (guard message, not docPermCheck).
- PUT replacement as owner → 200; old file status 0, new `replaced_id` → old, tag reused.
- `syncOrderStatusFromFiles` verified no-op for offer docs (early return unless `op-doc-order`).

## 11. Paylaşımlı Evrak Kütüphanesi — Shared Client Files (2026-11-11)

> **Rule:** a file belongs to **client + product code + requirement + kVA**, never to a tender. Same client + same product code + same requirement sees the same file in every tender.

**Identity / storage**
- Key (front + back, exact string): `product_code||standard||file_title||kva` — `kva = all` for a spanning template row.
- Registry = one `op-doc-client-docs` document per supplier (`supplier_qnid` header entity).
- Slot tag: `cdf_<sha1(product|standard|title|kva) 24>**client_docs**slot_<same hash>` — unique first segment so the engine's single-slot prefix replacement touches only that slot (never another cell).
- Versioning is the stock file mechanic: one `sys_con_entities` row per upload, active = `document_files.status=1`, `replaced_id` chains backwards. History = all entity rows for the slot.
- Expiry: `document_files.valid_until` (migration `2026_11_11_120000`) — set at temp upload, survives finalize. Expired = red pill, still viewable, **blocks offer save**.

**Endpoints** (`DocumentController`)
- `GET /v1/client-docs/{supplierQnid}` → `{ qnid, slots:[{match_key, product_code, standard, file_title, kva, evrak_no, file, expired, versions[]}] }`
- `POST /v1/client-docs/slot` (multipart) → `supplier_qnid, supplier_title, product_code, standard, file_title, kva, evrak_no, reference(JSON)`; finalizes the temp file into the registry via `registerContent`.
- `POST /v1/tenders/complete` `{ id: ihaleQnid }` → snapshots every offer of that tender, then writes `tender-complated`.
- `POST /v1/tender-products` (`tableReq` JSON: `qnid`, `scale`, `filter`) → server-side paginated tender product rows enriched with `prod_code`, `category_qnid`, `category_title`; search matches title + product code (never qnid).

**Tender completion lock**
- Tender status chain `tender-created → tender-activated → tender-complated` (`op-trans-op-doc-ihale`); activation by 🚀, completion by ✔ (admin, `per-041-03`).
- Completion stores one JSON `snapshot` entity on each offer header (`{snapshot_at, tender_qnid, slots[]}`).
- While a tender is open, offers resolve files **live** from the registry (replacements show everywhere).
- After completion the offer reads the **snapshot** — later registry replacements do not rewrite a closed tender. Offer UI becomes read-only and the upload/change buttons disappear.

**UI (IhOffer.vue)**
- **Product overview first:** opening an offer lands on a **server-side paginated table** (`PickleTable` ajax → `POST /v1/tender-products`, 10/page, search by name/code) rendered as **card rows** (rounded row cards, left accent stripe = state). Columns: Ürün (title + `prod_code` + qty/unit) · Kategori · Evrak (`ready/total` + mini bar) · Eksik (missing + expired) · Durum pill (**Dikkat Bekliyor / Devam Ediyor / Tamamlandı**) · Doldur/Aç. Header chips summarise `N ürün dikkat bekliyor` / `N tamam` / total. Stats come from the built items (`itemStats`), so they refresh on return from the matrix. Clicking a row opens that product's matrix (`viewMode='form'`); `← Ürün Listesi` returns and rebuilds the table.
- **TEDAŞ / Genel Görüş rows** use attachment cards (`.iho-attach-*`): doc icon, ellipsised file name, status pill, right-aligned Önizle / Değiştir / Kaldır; pending uploads render as dashed amber cards; empty state = dashed **+ Dosya Ekle/Yükle** button. TEDAŞ stays multi-file (append), görüş stays single (replace).
- **Matrix cells (`IhFileCell.vue`):** empty → outlined **Yükle**; filled → **icon-only tile** (no text): **green check** = uploaded/valid, **red alert** = rejected, **amber clock** = expired (`title` tooltip carries the date/status). Clicking the tile opens **Dosya İşlemleri** modal: status pill, yüklenme/geçerlilik/kaynak/sürüm rows, **Not / Ret nedeni** box (unwrapped from `last_status.note` JSON), and buttons **Önizle · Geçmiş (n) · Yenile** (Yenile hidden on a locked/completed tender). `Geçmiş` hands off to the version-history modal; `Yenile` re-opens the same flatpickr expiry + upload flow. `Geçmiş` hands off to the version-history modal; `Yenile` re-opens the same flatpickr expiry + upload flow.
- On file pick: `Geçerlilik Tarihi` prompt — **flatpickr** (Turkish locale, `d/m/Y`, typed or picked, optional) → temp upload with `valid_until` → slot POST → cell becomes `saved` + `Paylaşımlı`, expiry shown, `Geçmiş (n)` opens the version history modal.
- `itemMissing` / `missingList` treat expired files as missing → save is blocked with "süresi doldu".
- `IhList` gained a **Durum** pill (Açık / İhale Tamamlandı) and the completion action.

**Permissions:** supplier may read own library and write with `per-041-03-01` (client qnid must be in `currentStatus.clientQnidList`); office side needs `per-041-03`. Fail-closed.

**Note:** `reseller` in the live DB has `per-041-01`, so suppliers reach `/coalpanel/ihaleler/teklif` today; the tedarik shell's ihale tile is still `disabled` (see §12).

**Tender status chain (own state machine, `op-trans-op-doc-ihale`)**
| Status | Meaning | Who |
|---|---|---|
| `tender-created` | birth on save (EAV) — offers **not** accepted | automatic (`registerContent` birth map) |
| `tender-activated` | teklif alımı açık; offers can be prepared/saved | admin `IhList` → 🚀 **Aktif Et** |
| `tender-complated` | ended, **winner selected**, offers frozen to snapshot, read-only | admin `IhList` → ✔ **Tamamla** (winner modal) |

- Transition guard in `DocumentServiceProvider::setStatus`: `created → activated → complated`, terminal after completed.
- Offer writes (`registerContent`, `op-doc-ihale-offer`) are **server-blocked** unless the tender is `tender-activated`; completed = locked.
- `IhOffer` reads the tender status: inactive shows a blue notice and disables uploads/save; completed shows the lock banner + snapshot files.
- Winner is stored on the tender header: `winner_supplier_qnid`, `winner_supplier_title`, `winner_offer_qnid`; list shows 🏆 under the Durum pill.
- `POST /v1/tenders/activate` `{id}` and `POST /v1/tenders/complete` `{id, winner_supplier_qnid, winner_supplier_title}`.
- Completion validates the winner actually has an offer on that tender, freezes every offer (`snapshot` JSON), then writes `tender-complated`.

**Offer status chain (`op-trans-op-doc-ihale-offer`)**
| Status | Meaning | Trigger |
|---|---|---|
| `offer-sended` | supplier submitted (also birth) | offer save/create |
| `offer-rejected` | admin rejected **with reason**; supplier may fix and resend | `POST /v1/offers/reject` `{id, note}` (note required) |
| `offer-accepted` | admin accepted — **automatically completes the tender** with that supplier as winner | `POST /v1/offers/accept` `{id}` |

- Rejected → supplier edits and saves → `registerContent` flips it back to **`offer-sended`** automatically (rejection stays in the audit history).
- Accepting requires the tender to be `tender-activated`; it runs the same `completeTenderWithWinner()` core (winner entities + snapshots + `tender-complated`).
- Offers list (`IhOfferList`) is the review queue: **Durum** pill (Gönderildi / Kabul Edildi / Reddedildi + reason), actions **Aç · ✔ Kabul Et · ✖ Reddet**. Admin-only (`per-041-03-02`); suppliers no longer see other suppliers' offers.
- Supplier screen (`IhOffer`) shows a red banner with the reject reason (editable) or a green "kabul edildi" banner.
- **File-level rejection:** from a cell's **Dosya İşlemleri** modal an admin gets **Dosyayı Reddet** → reason required → writes `offer-file-rejected` as an `op_id=1` transaction on that `document_files` row **and auto-rejects the offer** with `Dosya reddedildi: <note>` (`POST /v1/offers/file-reject` `{id, file_qnid|file_id, note}`). The cell tile turns red, the reason appears in the modal, and the supplier can replace the file to clear it. Because files live in the shared client library, the rejection marks that document version globally (all tenders see it red) — replace = new version, clean.
- `ClientDocsService::listing()` now returns `file.last_status` (latest `op_id=1` transaction) so tiles/modal can render rejected state without extra calls.

---

## 12. Open Items (Master decides)

- ~~Offers list page~~ **DONE 2026-11-11** — `IhOfferList` at `/coalpanel/ihaleler/teklifler` (admin review queue; supplier filter via `?ihale=<qnid>`).
- ~~Ihale list page~~ **DONE 2026-11-11** — `IhList` at `/coalpanel/ihaleler` (search, product count, spec pill, edit/offers/delete).
- **Supplier shell still has no tender route** — `IhForm/IhOffer` live under `/coalpanel/*`. Live DB shows `immutable-reseller` **has** `per-041-01`, so suppliers can reach the offer screen via coalpanel today; `TedarikPanel.vue` / `PlanlamaPanel.vue` still show the ihale tile as `disabled`. Needs `/tedarikpanel/ihaleler*` + menu wiring eventually.
- ~~Offer status machine~~ **DONE 2026-11-11** — `offer-sended → offer-rejected (reason) → offer-sended (resend) → offer-accepted (completes tender + winner)`.
- ~~Tender status machine~~ **DONE 2026-11-11** — `tender-created → tender-activated → tender-complated`.
- Per-**file** review: rejection **DONE** (`offer-file-rejected`, auto-rejects offer); per-file acceptance/approval not implemented (offer-level accept only).
- Notifications (`ihale-*` types don't exist yet) — accept/reject sends no mail/bell.
- Lib files are not admin-reviewed yet (no per-file accept/reject on registry slots).
- File deletion from the library (only replace exists); retention policy for replaced versions (same as orders — physical files stay).
- Prices online vs offline; order handoff after award; deadline auto-disqualification.
- `per-041-03-01/02` sub-codes DO exist (seeded + DEV_ADMIN list) — admin has both, reseller only `03-01`, keyuser only `03`.
- Module-select tile still `disabled` (`AuthController::getAvailableModules`).
- Participant model (invite/disqualify) and deadline job; supplier visibility scope rules.
