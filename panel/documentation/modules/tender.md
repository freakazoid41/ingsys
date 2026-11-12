# İhale (Tender) — What Is Built + Pipeline

> **Status 2026-11-12:** closed-beta complete — tender state machine, native supplier shell, shared client library, offer/file statuses, admin review, winner + snapshot, deadline enforcement, server-side offer/file validation, per-file acceptance, and tender notifications. **Still out of scope:** invites/participants, prices, order handoff, multi-lot, auto-disqualification cron.
> **Spec source:** `memory/idea.md` ("İhale sistemi" section).
> **Canonical EAV mechanics:** `panel/documentation/form-system-mechanics.md`. Product catalog: `modules/products.md`.

---

## 1. Pipeline — built flow (2026-11-11)

```
── 1. CREATE ─────────────────────────────────────────────────────────────────
WHO: Admin / İş Birimi                          PERM: per-041-03
İhaleler → Yeni İhale                            /coalpanel/ihaleler/form
İhale Kodu (required) · Teknik Şartname file (required) · product rows (picker + qty/unit)
→ documents(op-doc-ihale) + EAV → transactions: tender-created
state: tender-created → offers BLOCKED (server-enforced)

── 2. ACTIVATE ───────────────────────────────────────────────────────────────
WHO: Admin / İş Birimi                          PERM: per-041-03
İhaleler → 🚀 Aktif Et → POST /v1/tenders/activate
→ transactions: tender-activated → teklif alımı açık

── 3. OFFER (supplier) ───────────────────────────────────────────────────────
WHO: client-bound user (Tedarikçi)              PERM: per-041-03-01
İhaleler → Teklif Ver (row icon)                 /coalpanel/ihaleler/teklif?ihale=<qnid>
                                                or /tedarikpanel/ihaleler/teklif?ihale=<qnid>
Ürün Listesi (server-side, card rows) → product → matrix
per file upload:
  temp-upload (valid_until via flatpickr, optional)
  → POST /v1/client-docs/slot
  → shared library doc (client + product code + requirement + kVA)
  → transactions(op_id=1): doc_file_waiting | doc_file_refreshed
Teklifi Kaydet → POST/PUT /v1/document  (op-doc-ihale-offer)
→ offer doc + header conn + N item conns → transactions: offer-sended
server checks: active tender · deadline not passed · every tender product exactly once
               · quantity match · imalatçı + marka · duplicate supplier+tender blocked
expired file = save blocked · tender must be activated

── 4. REVIEW (admin) ─────────────────────────────────────────────────────────
WHO: Admin / İş Birimi                          PERM: per-041-03
Teklifler queue                                  /coalpanel/ihaleler/teklifler
✔ Kabul Et  → POST /v1/offers/accept
              → offer-accepted + winner entities + snapshot every offer + tender-complated
✖ Reddet    → POST /v1/offers/reject {note}      (reason required) → offer-rejected
Dosyayı Reddet (cell modal) → POST /v1/offers/file-reject {note}
              → file transaction: offer-file-rejected
              → offer auto-rejected: "Dosya reddedildi: <note>"
Dosyayı Kabul Et (cell modal) → POST /v1/offers/file-accept {file_qnid|file_id}
              → file transaction: offer-file-accepted (global shared-library flag)

── 5. REVISE (supplier) ──────────────────────────────────────────────────────
WHO: client-bound user (Tedarikçi)              PERM: per-041-03-01
fix + Teklifi Kaydet on a rejected offer
→ registerContent sees last offer status = offer-rejected
→ transactions: offer-sended   (rejection stays in history)

── 6. COMPLETE (admin) ───────────────────────────────────────────────────────
WHO: Admin / İş Birimi                          PERM: per-041-03
İhaleler → ✔ Tamamla → winner modal (only suppliers who submitted)
→ POST /v1/tenders/complete {winner_supplier_qnid, winner_supplier_title}
→ winner_supplier_qnid / winner_supplier_title / winner_offer_qnid on tender
→ snapshot JSON on every offer (files frozen at that moment)
→ transactions: tender-complated
state: read-only — later library replacements do NOT rewrite this tender

── PLANNED ───────────────────────────────────────────────────────────────────
- invites/participants + deadline auto-disqualification cron
- prices (online vs offline), multi-winner / lot-based tenders
- order handoff after award (Tedarik)
- deeper per-offer file acceptance scoping (today shared-library/global)
```

---

## 1.1 Actors, roles & permissions — who can do what

### Permission catalog (İhale + Ürün)

| Code | Title | Scope |
|---|---|---|
| `per-041-03` | **İhale Oluşturma / Yönetme** | create tender, edit until completed, activate, complete + lock with winner, review queue, accept/reject offers, reject offer files |
| `per-041-03-01` | **Teklif Oluşturma** | prepare/save **own** offers, read tenders, read/write own document library (needs a bound client) |
| `per-09-01` | Ürün Listeleme | read products / categories / kVA |
| `per-09-02` | **Ürün Oluşturma / Düzenleme** | product create/edit/delete + kVA definitions |
| `per-09-03` | **Kategori Oluşturma / Düzenleme** | product category create/edit/delete + file-definition templates |

`per-041-03-02` (Teklif İnceleme) is **retired** — management (`per-041-03`) covers review; do not reintroduce it.

### Roles (live `sys_role_templates`)

| Role | op_key | İhale | Ürün | Notes |
|---|---|---|---|---|
| Admin | `immutable-admin` | `03`, `03-01` | `09-01/02/03` | everything; DEV_ADMIN backdoor mirrors this list |
| İş Birimi | `immutable-satınalma-keyuser` | `03` | — | tender management + review |
| Tedarikçi | `immutable-reseller` | `03-01` | — | offers only; must have a bound client; also seeds `041-01` + `041-02` for shell access |
| Ürün Kategori Yöneticisi | `immutable-product-manager` | — | `09-01/02/03` | catalogue only |
| İB. Görüntüleme | (viewer) | — | — | coalpanel shell only |

Everyone needs **`per-041-01`** to enter `/coalpanel`; the İhale module tile and menu are enabled for `03`/`03-01`.

### Job → permission matrix

| Job | Screen / endpoint | Permission | Admin | İş Birimi | Tedarikçi | Ürün Yön. |
|---|---|---|---|---|---|---|
| See tender list | `/coalpanel/ihaleler` | `03` or `03-01` | ✅ | ✅ | ✅ | — |
| Create / edit tender | `IhForm`, `POST/PUT /v1/document` (`op-doc-ihale`) | `03` | ✅ | ✅ | ❌ | — |
| Activate tender | `POST /v1/tenders/activate` | `03` | ✅ | ✅ | ❌ | — |
| Complete + pick winner | `POST /v1/tenders/complete` | `03` | ✅ | ✅ | ❌ | — |
| Open Teklif Ver | row icon on active tender → `IhOffer` | `03-01` | ✅ | ❌ | ✅ | — |
| Save own offer | `POST/PUT /v1/document` (`op-doc-ihale-offer`) | `03-01` + ownership | ✅ | ❌ | ✅ own | — |
| Review queue | `/coalpanel/ihaleler/teklifler` | `03` | ✅ | ✅ | ❌ | — |
| Accept / reject offer | `/v1/offers/accept`, `/v1/offers/reject` | `03` | ✅ | ✅ | ❌ | — |
| Reject one file | `/v1/offers/file-reject` | `03` | ✅ | ✅ | ❌ | — |
| Read own library | `GET /v1/client-docs/{supplier}` | `03-01` + own client | ✅ | ✅ | ✅ own | — |
| Upload/replace own library file | `POST /v1/client-docs/slot` | `03-01` + own client | ✅ | ✅ | ✅ own | — |
| Tender product rows | `POST /v1/tender-products` | `03` or `03-01` | ✅ | ✅ | ✅ | — |
| Products CRUD | `/coalpanel/products` | `09-02` | ✅ | — | — | ✅ |
| Categories CRUD | `/coalpanel/product-categories` | `09-03` | ✅ | — | — | ✅ |

> Admin holds **both** İhale codes (DEV_ADMIN mirror) but has no client binding — create offers with a supplier account (`Tedarikçi` + bound client). İş Birimi has `03` only, so it manages/reviews but cannot create offers.

### Enforcement layers

1. **Shell:** `coalAuth` requires `per-041-01`.
2. **Sidebar:** İhale section visible for `03 || 03-01`; **Yeni İhale** + **Teklifler** require `03`; row **Teklif Ver** requires `03-01` and a `tender-activated` tender.
3. **Router guards:** `/coalpanel/ihaleler*` + `/tedarikpanel/ihaleler*` → `03 || 03-01`; `/ihaleler/teklifler` → `03` (no tedarik review queue); `/ihaleler/teklif` → `03-01 || 03`.
4. **Server doc permissions:** `docPermCheck` — tender edit/status `03`, read `03,03-01`; offer/item edit/status `03-01`, read `03,03-01`; category edit `09-03`; product/kVA edit `09-02`.
5. **Controller endpoints:** activate/complete `03`; accept/reject/file-reject `03`; client-docs (supplier: own supplier + `03-01`, office: `03`); tender-products `03 || 03-01`.
6. **Ownership:** `assertOfferOwnership` — a reseller can only touch offers whose `supplier_qnid` is in their bound client list (payload on write, stored doc on read). Role detection falls back to `users.role` for Bearer-token requests; client list falls back to the person's `cliid` entities. Fail-closed.
7. **State guards (not permissions):** offers blocked unless the tender is `tender-activated`; completed tender is read-only; `offer-accepted` is immutable; expired files block offer save.

### "Required user" for a supplier

- `users.role = immutable-reseller` (Tedarikçi)
- `per-041-03-01` granted (from role template)
- at least one connected client (`op-doc-user-client-form` → `cliid` entities) — otherwise `IhOffer` shows "bağlı carisi olan hesap" notice and server rejects library access
- `per-041-01` to enter coalpanel (currently granted to resellers in the live DB)

---

## 2. What Lives Where

| Piece | File | Notes |
|---|---|---|
| Menu "İhale Yönetimi" | `resources/js/components/coalparts/Sidebar.vue:397` | section `03 || 03-01`; children **İhaleler** (both), **Yeni İhale** (`03`), **Teklifler** (`03`). Per-row **Teklif Ver** icon in `IhList` (`03-01`, active tender) → `IhOffer?ihale=<qnid>`. No standalone Teklif Ver menu entry |
| Routes | `resources/js/router/index.js` | `/coalpanel/ihaleler` → `IhList`, `/ihaleler/teklifler` → `IhOfferList`, `/ihaleler/form/:id?` → `IhForm`, `/ihaleler/teklif/:id?` → `IhOffer`; tedarik equivalents `/tedarikpanel/ihaleler` → `TedarikIhList`, `/tedarikpanel/ihaleler/teklif/:id?` → `TedarikIhOffer` |
| Tender list | `resources/js/pages/coalsystem/Ihale/IhList.vue` | ajax PickleTable, `all` search, product count from `main_attr` row keys, spec pill, edit/offers/delete |
| Offers list | `resources/js/pages/coalsystem/Ihale/IhOfferList.vue` | admin queue; `?ihale=<qnid>` filters one tender (`attr` filter), `row_count` from server |
| Page + product picker | `resources/js/pages/coalsystem/Ihale/IhForm.vue` | modal table, multi-row products, qty/unit; save → back to `IhList` |
| Form schema | `resources/js/components/coalparts/Form.vue:1305` | `op-doc-ihale-form` (see §4) |
| Dictionary seeder | `database/seeders/IhaleSeeder.php` | idempotent, run once per env (see §3) |
| Permission map | `app/Helpers/PermissionHelpers.php` | `op-doc-ihale` edit/status `per-041-03`, read `03,03-01`; offer/item `03-01`; category `09-03`; product/kVA `09-02` — full matrix in **§1.1** |
| List counts | `app/Models/Documents.php` `tableList` | conditional `row_count` for `op-doc-ihale-form` (distinct product rows) + `op-doc-ihale-offer-form` (item form conns) |

---

## 3. Dictionary (seeded — `IhaleSeeder.php`, local DB done)

| op_key | group_key | Purpose |
|---|---|---|
| `op-doc-ihale` | `op-doc` | İhale header |
| `op-doc-ihale-form` | `op-doc-forms` | Header form |
| `op-ihale_spec_file` | `op-file-types` | File-type title for the spec file |
| `op-doc-ihale-offer` | `op-doc` | Offer header |
| `op-doc-ihale-offer-form` | `op-doc-forms` | Offer header form |
| `op-doc-ihale-offer-item-form` | `op-doc-forms` | One offer item per tender product |
| `op-doc-client-docs` / `-form` | `op-doc` / `op-doc-forms` | Shared supplier document library |
| `tender-created` / `tender-activated` / `tender-complated` | `op-trans-op-doc-ihale` | Tender state chain |
| `offer-sended` / `offer-rejected` / `offer-accepted` / `offer-file-rejected` / `offer-file-accepted` | `op-trans-op-doc-ihale-offer` | Offer/file state chain |

Run: `php artisan db:seed --class=IhaleSeeder` + `php artisan db:seed --class=ClientDocsSeeder`. No migration — EAV only (+ `document_files.valid_until`).

---

## 4. Form Schema (`op-doc-ihale-form`)

- **İhale Kodu** (`ihale_kodu`, text, required, half width) + **Teklif Son Tarihi** (`teklif_son_tarihi`, date, optional, half width). When set, the server rejects offer writes after that date.
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
- After first save, returns to the tender list (`IhList`).

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

Verified: `tableList` `row_count` and UI card count both return 3 / 4 / 2. All four tenders are at **`tender-created`** (offers blocked) until an admin uses 🚀 Aktif Et. No offers exist in the local DB (smoke data cleaned).

> The table above is the demo seed snapshot. The current local DB has **4 tenders at `tender-created` and 0 offers** (re-checked 2026-11-12); §9's “empty” line is stale.

---

## 8. How To Verify

1. Hard refresh → coalpanel → **İhale Yönetimi → İhaleler**.
2. **Yeni İhale** → İhale Kodu, attach a spec file (required), click Ürün → modal lists the 9 `TRF-33-*` products → Seç → unit (`ST`) fills → Miktar → + Ekle → Kaydet (returns to the list).
3. `IH-2026-001` row shows `3 kalem` + Şartname pill + **Oluşturuldu**; actions are ✏️ 🚀 (activate) 📄 🗑.
4. 🚀 → **İhale Aktif**; login as `kbbozat`-type reseller → row shows **Teklif Ver**; open matrix, upload a file (expiry optional), save → offer `Gönderildi`.
5. Admin → **Teklifler** queue → ✔ Kabul Et (completes + winner) or ✖ Reddet with reason; a cell's Do­sya İşlemleri → **Dosyayı Reddet** auto-rejects the offer.

---

---

## 9. Teklif (Offer) — Built 2026-11-11, Verified Over HTTP

Supplier screen (`IhOffer.vue`, routes `/coalpanel/ihaleler/teklif/:id?` **and** `/tedarikpanel/ihaleler/teklif/:id?`, entry from the row **Teklif Ver** icon or the offers list **Aç**). Custom UI (no Form engine — matrix too complex), same data mechanic: one POST/PUT envelope (`typeKey` + `dynamicF` + `dynamicFile*` refs) through `/v1/document` → `registerContent`. Versioning, logs and permissions unchanged.

**Server-side offer validation (2026-11-12):** `DocumentController::validateOfferPayload()` rejects a tampered offer before `registerContent`: tender must exist, every tender product must appear exactly once, product qty must match, `imalatci_firma_adi` + `urun_markasi` are required, duplicate supplier+tender offers are blocked. `validateOfferFiles()` checks every category file-template cell against the supplier's shared library and rejects missing/expired versions. Tender creation also server-validates code + product + spec file. Deadline (`teklif_son_tarihi`) is checked server-side; expired tenders reject offer writes. Temp-upload references are tagged with `document_files.uploaded_by`; foreign references are rejected (legacy rows with `uploaded_by=0` tolerated). Optional TEDAŞ / Genel Görüş rows remain UI-guided.

**Rule:** creating an offer requires a connected client (non-empty `clientQnidList`) and `per-041-03-01`; otherwise the screen shows a notice. Office users (`per-041-03`) can open an existing offer read-only for review.

**Dictionary** (`IhaleSeeder.php`, seeded): `op-doc-ihale-offer` (header), `op-doc-ihale-offer-form` (header row: `ihale_qnid`, `ihale_kodu`, `supplier_qnid`, `supplier_title`), `op-doc-ihale-offer-item-form` (one row per product: product link + qty/unit + `imalatci_firma_adi`, `urun_markasi`, `tedas_raporu`, `genel_gorus`). Item rows also carry `ihale_qnid` + `supplier_qnid` (denormalized, for scoping).

**Permissions (2026-11-11 — two-code model)**
| Code | Title | Grants |
|---|---|---|
| `per-041-03` | **İhale Oluşturma / Yönetme** | create/edit tender (until completed), activate, complete + winner, review queue, offer accept/reject, per-file reject |
| `per-041-03-01` | **Teklif Oluşturma** | create/read **own** offers (requires a bound client), read tenders, read/write own document library |
| ~~`per-041-03-02`~~ | retired | removed from catalog + role templates; management covers review |

- `docPermCheck`: `op-doc-ihale` edit/status `per-041-03`, read `per-041-03,per-041-03-01`; offer + item edit/status `per-041-03-01`, read `per-041-03,per-041-03-01`.
- **Ownership** (`assertOfferOwnership`): resellers only touch offers whose `supplier_qnid` is in their bound client list (payload on write, stored doc on read). Role detection falls back to `users.role` for Bearer-token requests (no session), and the client list falls back to the person's `cliid` entities. Fail-closed.
- Frontend: sidebar section `03 || 03-01`; **Yeni İhale** + **Teklifler** `03`; row **Teklif Ver** `03-01` and only on `tender-activated`; router guards mirror this. `IhList` manage buttons require `03`, `IhOfferList` review requires `03`, `IhOffer` save requires `03-01`.
- Roles: **Admin** all; **Tedarikçi** `03-01` only; **İş Birimi** `03`; **Ürün Kategori Yöneticisi** `09*`. Module tile for İhale is now enabled.

**Matrix mechanic (the image, sections A/B/C):**
- Header table A per product: ihale kodu (ro), imalatçı* (fill), tedarikçi (ro, from binding), marka* (fill), malzeme grubu (ro), spec (ro), tedaş/görüş (optional).
- Templates come from the product category's `category_files` rows (grouped by nameTag). `file_group == 'Diğer Belgeler'` (case-insensitive) → section C, else B.
- Columns = union of template `kva` lists in that section (numeric sort). Template with empty `kva` → ONE spanning cell (covers all powers); else one cell per listed kVA.
- Cell file tag: `{cellKey}**offer_cells**{rowKey}`, exactly 2× `**` = single-slot versioning; first segment unique per cell (`oc_{prod8}_{reqShort}_{kva}`) so the prefix search never collides. On edit the new row reuses the old tag (backend line 401) — history preserved.
- TEDAŞ row is multi-file (append-only): tags `tedas_file**tedas_docs**{rowKey}**doc-{uniq}` (3× `**` → exact-match, never replaces). Verified live: 2 uploads → 2 active rows (36350, 36351).
- GENEL DEĞERLENDİRME GÖRÜŞÜ is a single admin-only file: tag `gorus_{prod8}**gorus_docs**{rowKey}` (2× `**` → replace on re-upload). Suppliers see it readonly ("admin dolduracak"); admins get upload/replace/remove. Server-side `stripResellerGorus` drops forged görüş files + removedData rows before `registerContent`. Verified: supplier forgery → 200 but 0 entities stored; admin upload → stored + read back.
- Uploads go through immediate `/v1/temp-upload` (reference at save); validation mirrors backend (pdf/xls/xlsx/jpg/png, 42 MB). Save requires imalatçı + marka + every cell filled (pending or saved).
- Existing-offer lookup: `POST /v1/table/documents` + `attr` filter on `supplier_qnid`, client-side match on `ihale_qnid` → redirects to edit instead of duplicating.

**Demo data (local):** TEMİZLENDİ 2026-11-11 — eski `DEMO-001` ihalesi + test teklifi + tüm demo ürün/kategoriler silindi. Yerine `TransformerCatalogSeeder` ile 1 kategori (`TRF-33KV`, 14 dosya şartı) + 9 ürün (`TRF-33-050 … TRF-33-1600`) geldi. **2026-11-12 check:** DB has 4 tenders at `tender-created`, 0 offers (`tender.md §7`).

**Verified live (curl + real 2FA sessions):**
- POST offer as kbbozat → 200, header + item + active file stored; GET readback matches screen contract (conn ids, file JSON).
- GET + PUT same offer as picklecanme (bound HASÇELİK) → 403 `Bu teklif sizin firmanıza ait değil.` (guard message, not docPermCheck).
- PUT replacement as owner → 200; old file status 0, new `replaced_id` → old, tag reused.
- `syncOrderStatusFromFiles` verified no-op for offer docs (early return unless `op-doc-order`).

## 10. Paylaşımlı Evrak Kütüphanesi — Shared Client Files (2026-11-11)

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
- **Matrix cells (`IhFileCell.vue`):** empty → outlined **Yükle** button; filled → **icon-only tile** (no text): **green check** = uploaded/valid, **red alert** = rejected, **amber clock** = expired (`title` tooltip carries date/status). Clicking the tile opens **Dosya İşlemleri** modal: status pill, yüklenme/geçerlilik/kaynak/sürüm rows, **Not / Ret nedeni** box, and buttons **Önizle · Geçmiş (n) · Yenile · Dosyayı Reddet** (admin only, hidden on a locked tender). `Geçmiş` opens the version-history modal; `Yenile` re-opens the flatpickr expiry + upload flow.
- On file pick: `Geçerlilik Tarihi` prompt — **flatpickr** (Turkish locale, `d/m/Y`, typed or picked, optional) → temp upload with `valid_until` → slot POST → cell becomes `saved` + `Paylaşımlı`, expiry shown, `Geçmiş (n)` opens the version history modal.
- `itemMissing` / `missingList` treat expired files as missing → save is blocked with "süresi doldu".
- `IhList` gained a **Durum** pill (Açık / İhale Tamamlandı) and the completion action.

**Permissions:** supplier may read/write own library with `per-041-03-01` (client qnid must be one of their bound clients — session list, or the person's `cliid` entities on token requests); office side needs `per-041-03`. Fail-closed. See **§1.1**.

**Note:** `reseller` seeds `per-041-01` + `per-041-03-01`; suppliers can use `/tedarikpanel/ihaleler*` (native shell) or the coalpanel route. The Tedarik sidebar now exposes **İhaleler** and the module tile is enabled for `03-01`.

**Tender status chain (own state machine, `op-trans-op-doc-ihale`)**
| Status | Meaning | Who |
|---|---|---|
| `tender-created` | birth on save (EAV) — offers **not** accepted | automatic (`registerContent` birth map) |
| `tender-activated` | teklif alımı açık; offers can be prepared/saved | Admin / İş Birimi (`03`) → 🚀 **Aktif Et** |
| `tender-complated` | ended, **winner selected**, offers frozen to snapshot, read-only | Admin / İş Birimi (`03`) → ✔ **Tamamla** (winner modal) |

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
- Offers list (`IhOfferList`) is the review queue: **Durum** pill (Gönderildi / Kabul Edildi / Reddedildi + reason), actions **Aç · ✔ Kabul Et · ✖ Reddet**. Management-only (`per-041-03`); suppliers no longer see other suppliers' offers.
- Supplier screen (`IhOffer`) shows a red banner with the reject reason (editable) or a green "kabul edildi" banner.
- **File-level rejection:** from a cell's **Dosya İşlemleri** modal an admin gets **Dosyayı Reddet** → reason required → writes `offer-file-rejected` as an `op_id=1` transaction on that `document_files` row **and auto-rejects the offer** with `Dosya reddedildi: <note>` (`POST /v1/offers/file-reject` `{id, file_qnid|file_id, note}`). The cell tile turns red, the reason appears in the modal, and the supplier can replace the file to clear it. Because files live in the shared client library, the rejection marks that document version globally (all tenders see it red) — replace = new version, clean.
- `ClientDocsService::listing()` now returns `file.last_status` (latest `op_id=1` transaction) so tiles/modal can render rejected state without extra calls. **Per-file acceptance:** the same modal has **Dosyayı Kabul Et** (`POST /v1/offers/file-accept`, `offer-file-accepted`); it is global for the shared-library version, same caveat as rejection.
- **Notifications:** `ihale-01` (tender activated), `ihale-02` (tender completed), `offer-01` (new offer), `offer-02` (offer rejected), `offer-03` (offer accepted) are seeded and wired into bell + mail (`ReportServiceProvider`, `SystemController`, `EmailServiceProvider`, `SendNotificationMailJob`). Mail recipient fallback: `per-041-03-01` for tender events, `per-041-03` for new offers; reject/accept go to the supplier's `contmail` contact.
- **Tender documents stay out of `/coalpanel/documents`:** `Document_files::tableList` excludes parent types `op-doc-ihale`, `op-doc-ihale-offer`, `op-doc-ihale-offer-item`, `op-doc-client-docs` (tender spec, offer files, shared library). They are only visible inside the tender offer details screen — the list and its Excel export follow automatically.

---

## 11. Open Items (Master decides)

### Done (2026-11-12)
- Tender list (`IhList`), offers list (`IhOfferList`), supplier offer screen (`IhOffer`), shared client library, expiry + history, tender + offer + file status machines, winner selection, snapshot lock, permission split, DList exclusion.
- Tender module tile enabled (`AuthController::getAvailableModules`); `per-041-03-02` retired.
- **2026-11-12 hardening:** reseller role seeds `per-041-01` (fresh environments can reach `/coalpanel`), cross-shell module navigation fixed, generic `/v1/trans/set-status` blocked for tender/offer types, stored-state terminal lock on completed tenders/accepted offers, offer status chain enforced, rejected offers cannot win, table endpoint permission + reseller offer scoping, and admin/İB offer inspection fixed.
- **2026-11-12 closeout:** native `/tedarikpanel/ihaleler*` routes + İhaleler menu/tile, server-side offer item/quantity/manufacturer validation, tender creation validation, deadline (`teklif_son_tarihi`) + server enforcement, per-file acceptance (`offer-file-accepted` + `POST /v1/offers/file-accept`), tender bell/mail notifications (`ihale-01/02`, `offer-01/02/03`), and `TenderPipelineTest`.

### Open
- **Optional file slots** — category file-template cells are now server-validated against the shared library (missing/expired blocks save). Optional TEDAŞ / Genel Görüş rows remain UI-guided.
- **Library management** — no delete (replace only); replaced physical versions stay on disk (same policy as orders).
- **Tender scope** — no participant/invite model, no lot-based or multi-winner tenders. Deadline blocks new offer writes but there is no auto-disqualification cron.
- **Prices / award handoff** — no price capture, no order creation from the winning offer. **Intentional module boundary for now:** Tender ends at `tender-complated`; Tedarik starts from `op-doc-order` (SAP/manual). Do not create/read Tedarik orders inside the tender flow until a product decision explicitly opens that bridge. A future bridge must be an isolated `AwardToOrderService` / explicit action, not cross-module reads.
- **İş Birimi scope** — `03` currently grants full tender management; if business units should only *view*, split a read-only code later.
- **Table endpoint scoping** — tender/offer document types are now permission-gated and reseller offers are scoped in `Documents::tableList`; other document types still rely on UI + doc endpoints.
- **File acceptance scope** — `offer-file-accepted` is global because files live in the shared library; per-offer acceptance would need an offer-item flag.
