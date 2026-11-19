# Ürün Yönetimi (Products) — Mechanics Bible

> **Scope:** product categories, products, kVA definitions, category file-requirement templates.
> **Read after:** `form-system-mechanics.md` (EAV engine), `memory/03-app-factory-guide.md` (new doc-type recipe).
> **No new tables.** Everything below is `sys_options` + EAV (`documents` + `sys_con_ops` + `sys_con_entities`) + existing permission/file machinery.

---

## 1. What Lives Here

| Concept | Doc type | Form | Purpose |
|---|---|---|---|
| Ürün Kategorisi | `op-doc-product-category` | `op-doc-product-category-form` | Category (Başlık + Kod) + its file-requirement templates |
| Ürün | `op-doc-product` | `op-doc-product-form` | Product (Kod + Kısa Ad + Birim + category link) |
| Güç Tanımı (kVA) | `op-doc-kva` | `op-doc-kva-form` | System-wide kVA values users manage (Başlık + Değer) |

Categories own **file definition templates** (what documents a future module will demand per product). Products link to one category. kVA rows are referenced by file definitions (which power ratings each required file applies to).

---

## 2. Dictionary (`sys_options`)

Seeders (all idempotent `firstOrCreate` by `op_key`):
- `ProductCategorySeeder.php` — `op-doc-product-category` (`op-doc`), `op-doc-product-category-form` (`op-doc-forms`)
- `ProductSeeder.php` — `op-doc-product` (`op-doc`), `op-doc-product-form` (`op-doc-forms`)
- `KvaSeeder.php` — `op-doc-kva` (`op-doc`), `op-doc-kva-form` (`op-doc-forms`) + 9 standard kVA docs (`50..1600`, see §5 — stored as *documents*, not options)

Run: `php artisan db:seed --class=ProductCategorySeeder|ProductSeeder|KvaSeeder`.

---

## 3. Permissions & Role

- `per-09` (Ürün Sistemi) + `per-09-01` (Listeleme) + `per-09-02` (Ürün Oluşturma / Düzenleme) + `per-09-03` (Kategori Oluşturma / Düzenleme), seeded via `storage/entities/role_details.json` → `php artisan db:seed --class=SysRoleTemplateSeeder` (clears file caches).
- `docPermCheck` map (`PermissionHelpers.php`): `op-doc-product` + `op-doc-kva` → `read: per-09-01`, `edit/status: per-09-02`; **`op-doc-product-category` → `edit/status: per-09-03`** (separate category permission).
- New role `immutable-product-manager` (Ürün Kategori Yöneticisi): `per-041-01` (coalpanel access) + `per-09*`. Seeded from `coal_roles_templates.json`. Admin assigns it in user form; person type must be `op-pert-admin` (office), **not** reseller — otherwise reseller scoping hides everything (see §8).
- `immutable-admin` carries all three per-09 codes.
- **Gotcha:** `AuthController::getPermissions()` hardcodes the DEV_ADMIN full-permission list — every new `per-*` must be added there too or the god-mode account silently loses the UI.

---

## 4. EAV Shapes

One `sys_con_ops` per form (`conn_id=0`, `sub_type_id=form-main`), scalar entities under `table_tag='sys_con_ops'`.

**`op-doc-product-category-form`** (conn per category doc):
- `title`, `code` (plain text)
- File-def rows: `multiple`, `group_key='category_files'`, one `sys_con_ops` per row set. Entities per row (tag suffix = `**category_files**{rowId}`):
  - `evrak_no` (optional text)
  - `standard` (optional text — Standart, e.g. `TS EN 60076`)
  - `file_title` (required text — Belge Adı; kept its storage so old rows keep working)
  - `file_group` (required text — e.g. `Tip Test Raporları`, `Diğer Belgeler`)
  - `file_types` (required select — upload-kind preset: `pdf | pdf_img | img | xls | all`)
  - `kva` (JSON array string of kVA **codes**, e.g. `["250","1000"]`; `[]` or missing = applies to ALL powers — see §6)

**`op-doc-product-form`** (conn per product doc):
- `prod_code`, `short_name`, `unit` (`ST|KG|M` select)
- `category_title` (readonly display, **required** — label "Malzeme Grubu (listeden seçin)"), `category_qnid` (hidden link to category doc). Server refuses product creation without `category_qnid` (`DocumentController::handleStore`); the tender offer matrix resolves required file templates through this category.

**`op-doc-kva-form`** (conn per kVA doc):
- `title` (display label, convention: `KVA`), `code` (numeric value as text, e.g. `1600`)

Readback: `GET /v1/document/{qnid}` → `formFormat['op-doc-*-form'][rowId].entities` (see `form-system-mechanics.md:6`).

---

## 5. kVA Definitions (System Settings)

- kVA values are **documents** (`op-doc-kva`), managed in UI at `/coalpanel/kva` (`KvList/KvForm`, Ürün submenu, per-09 gated).
- Seeded standards: `50 100 160 250 400 630 1000 1250 1600` (title `KVA`, code = number). Users add more (e.g. `2000`) from the UI — no seeder re-run needed.
- Display/exchange value is always `code` (stable); `title` is the human label.
- Why documents not `sys_options`: user-managed at runtime with zero backend code (list/form/export/perm all generic).

---

## 6. Category File Templates (Pointer Definitions, NOT Files)

Each `category_files` row declares one document the future collection module will demand:
`{evrak_no?, file_title, file_group, file_types, kva[], variants[]}`.
- **No `document_files` rows are created** — these never touch storage. They are requirement templates only.
- The future module reads them via `getFormData(categoryQnid)` (or raw `sys_con_entities` by `group_key='category_files'`) and will create real file links + `document_files` rows with that relation when collecting from suppliers.
- `kva: []` = required for all power ratings. Non-empty = only the listed ratings (matched against product variant codes).
- `variants: []` = required for all text scopes. Non-empty = only the listed scope-type codes (functional units, TİP-A/B, HARİCİ/DAHİLİ) — the sheet's "aranmaz" semantics.

---

## 7. Product Form Mechanics (`PForm.vue` + `PCForm.vue` pattern)

- Standard `Form.vue` scaffold (`formtypes="op-doc-product-form"`, `savecallback=submitForm`). See `memory/01-form-engine.md:4`.
- **Category link is page-owned, not form-owned:** the picker lives outside `<Form>`. On pick, `pushCategoryToForm()` writes the hidden `category_qnid` + readonly `category_title` DOM inputs and dispatches `input` so the engine's `submitDynamicChanges` captures them. `submitForm` **also injects both keys into every `dynamicF` row defensively** — never rely on DOM state alone (see `tedarik-system-process.md:164` detached-`v-model` trap).
- Validation: category required is enforced in `submitForm` (`selectedCategoryQnid` empty → toast, no save), not via `required` on the hidden field.
- Edit mode pre-selects from `formDataStore.rawData.formFormat['op-doc-product-form']` first-row entities.
- Category column in `PList` shows denormalized `category_title` (same pattern as client `clititle` — avoids a join per row).

---

## 8. Category Picker Modal (Server-Side Paginated)

`PForm.vue` → `Kategori Seç` button (and clicking the readonly category field — delegated document click handler, survives Form re-renders) opens a modal with an **ajax PickleTable**:
- `POST /v1/table/documents`, `initialFilter: form-type=op-doc-product-category-form + type=op-doc-product-category`, `pageLimit: 10` — server paginates, safe for big lists.
- Search box → `setFilter([{key:'all',...}])` (main_attr free-text). Reset clears.
- Per-row `Seç` button (columnFormatter closure) → `pickCategory(qnid, row)` → closes modal, updates banner + hidden inputs.
- Table instance destroyed + container cleared on close (PickleTable has no `destroy()` — manual `innerHTML=''` like `NSettings.vue`).

---

## 9. Tagbox Engine Field (`type: 'checks'` in `Form.vue`)

Generic tag-input used today for per-row kVA selection and per-row scope-type selection (`variants`); reusable for any multi-pick:
- Schema: `{type:'checks', name, col, label, emptyText?, setOptions: async () => [{text, value}] }`. Loads options via `setOptions` (kVA: `POST /v1/table/documents` op-doc-kva, text `KVA 1600`, value `1600`).
- Renders a tag box: textbox filters dropdown (TR-aware lowercase), Enter/click adds pill, ×/Backspace removes, Esc closes.
- Storage: **JSON array string** in one entity (e.g. `["250","1000"]`). Change handler submits a synthetic element to `submitDynamicChanges` (real checkboxes can't represent multi-value).
- Seeded on build (`pushCurrent()` immediately) so untouched-on-edit rows still save.
- Validation: intentionally no `required` handling — empty means "all", enforced by convention not the engine.

---

## 10. Listing & Export

- `PList` (`/coalpanel/products`, Ürün submenu, per-09-01 gate; create/delete per-09-02): `POST /v1/table/documents` with `form-type` + `type` filters, `rowFormatter` flattens `main_attr` → `prod_code/short_name/unit/category_title`. `PCList` uses **per-09-03** for create/delete, `KvList` per-09-02.
- No status pills — products/categories have no state machine. Seeder-inserted rows bypass `registerContent`, so they may have no birth transaction; UI-created rows go through `registerContent` and get `doc_trans_created`.
- Export: `POST /v1/export/products|productcategories|kvas` (`ExportController` branches mirror the clients pattern; `rowCallback` formats dates).

---

## 11. Routes & Menu

```js
// router/index.js (under /coalpanel)
{ path:'/coalpanel/product-categories',      name:'PCList' } + { path:'/coalpanel/product-categories/form/:id?', name:'PCForm' }
{ path:'/coalpanel/products',                name:'PList'  } + { path:'/coalpanel/products/form/:id?',               name:'PForm'  }
{ path:'/coalpanel/kva',                     name:'KvList' } + { path:'/coalpanel/kva/form/:id?',                     name:'KvForm' }
```
Sidebar: Ürün section (gated `per-09-01`) → Ürün Kategorileri, Güç Tanımları (kVA), Ürünler.

---

## 12. How To Test (E2E Scripts in `panel/tests/e2e/`)

Users: admin `redv@kontent.com.tr` (all 37 perms incl. per-09) for create/edit; reseller `kbbozat41@hotmail.com` must get **403** on `POST /v1/document` with `typeKey: op-doc-product*`.
- `pc.run.js` — category create + list readback.
- `p.run.mjs` — product create via modal picker (search + Seç) + list readback shows `category_title`.
- Category file defs + kVA picks: edit category → add rows (evrak_no/title/group/types) → pick kVAs from tagbox → save → `GET /v1/document/{qnid}` → `formFormat['op-doc-product-category-form']` entities contain `kva**category_files**{rowId} = ["250",...]`.
- Reseller gating: `POST /v1/document {typeKey:'op-doc-product'}` → 403 (supplier has no per-09).

---

## 14. Real TEDAŞ Catalog (2026-11-19, rebuilt)

`catalog:purge --force` wipes the catalog domain (categories, products, tenders, offers, supplier evrak registry `op-doc-client-docs`) with EAV/files/transactions/logs; add `--definitions` to also wipe the kVA + scope-value dictionaries for a full fresh reset. `TedarikRealCatalogSeeder` then seeds the **5 official TEDAŞ categories** from the tanzim tables with their full file-requirement templates (117 rows total) and **one example product per category**. Idempotent — refreshes rows in place, matched by `code` entity; self-calls `ProductCategorySeeder → ProductSeeder → KvaSeeder → ScopeValueSeeder`.

| Code | Title | FRM | Şartname | Scope | Example product |
|---|---|---|---|---|---|
| `TRF-33KV` | 33 kV Hermetik Dağıtım Transformatörü | 847 | TEDAŞ-MLZ/99-032.E | 9 kVA (50–1600) | `TRF-33-630` |
| `GY-HUCRE` | Metal Mahfazalı Gaz Yalıtımlı YG Hücre | 843 | TEDAŞ-MLZ/2006-002.D | 7 fonksiyonel birim | `GYH-630` |
| `HY-HUCRE` | Metal Mahfazalı Hava Yalıtımlı YG Hücre | 846 | TEDAŞ-MLZ/95-007.E | 9 fonksiyonel birim | `HYH-630` |
| `AG-PANO` | AG Dağıtım Panosu | 865 | TEDAS-MLZ-2003-006 B | 7 combined (`100 kVA Harici`, `160/250/400 kVA Harici+Dahili`) + plain `630/1000/1250/1600 kVA` | `AGP-630` |
| `SDK` | Saha Dağıtım Kutusu | 869 | TEDAŞ-MLZ/2004-046.B | TİP-A / TİP-B | `SDK-A` |

- **Scope values are first-class documents**, exactly like kVA: `ScopeValueSeeder` adds `op-doc-scope-value(-form)` + **24 text values** (15 hücre birimleri, `TİP-A/B`, 7 pano combined values). Requirement rows connect by `code` — `variants` JSON lists every applicable code explicitly (`[]` = no text dimension). Verified zero orphans against both dictionaries.
- **Subcategories are flattened, never split** (2026-11-19 rule from Master): a definition like `50 kVA (Harici, Dahili)` is stored as two plain values — `50 kVA Harici`, `50 kVA Dahili`. `HARİCİ`/`DAHİLİ` standalone values are retired; `ScopeValueSeeder` prunes them. **Exact FRM.865 mapping:** `100 kVA Harici`; `160 kVA Harici + Dahili`; `250 kVA Harici + Dahili`; `400 kVA Harici + Dahili`; `630/1000/1250/1600` plain kVA (no subcategory). PANO rows: `variants` = 7 combined codes, `kva: ["630","1000","1250","1600"]`, `scope_kind: kva_variant`.
- **Excel cross-check (ALL GREEN, 2026-11-19):** `tests/e2e/excel-crosscheck.php` parses the 5 real tanzim xlsx files (merged cells included) and compares every requirement row (117), title, standard, group and scope mapping against the DB. Latest run: **0 mismatches**. Fixes it surfaced: PANO variant mapping corrected (160/250/400 both variants; 630/1000/1250/1600 plain), HY row 24 now `YA. TRF KOR + GER. TRF`, and titles/standards aligned to the sheet wording (slash spacing, trailing comma, parenthesized SDK standard).
- **kVA definitions are self-describing:** `KvaSeeder` sets each title to `50 kVA` … `1600 kVA` (code stays numeric), and the Güç tab shows one name column (no duplicate value column).
- Row entities: `evrak_no, standard, file_title, file_group, file_types, kva, variants` (`**category_files**{rowKey}` suffix, one conn per category). `kva` keeps the engine convention: explicit array = per-rating; `[]` = all powers (spanning rows).
- Category metadata entities (main conn): `sartname_kodu`, `sartname_adi`, `scope_kind` (`kva|variant|kva_variant`), `scope_headers` (column labels JSON), `scope_values` (every connected scope code JSON), `scope_variants` (kva→HARİCİ/DAHİLİ map, AG-PANO only), `row_count`.
- The tender/offer matrix still consumes only `kva` — text `variants` are seeded and connected but **not yet expanded into cells (pending mechanic)**.
- **UI:** Ürün → **Güç & Kapsam Tanımları** (`/coalpanel/kva`) is a two-tab page — **Güç (kVA)** + **Kapsam Tipleri** (scope values). Scope create/edit/delete via `SForm` route `/coalpanel/kva/scope-form/:id?` (`op-doc-scope-value-form` def in `forms/defs/scope-value.js`), gated `per-09-01` read / `per-09-02` edit, Excel export model `scopevalues`. Verified live: tabs, 24 rows, create → list → search. Row polish: one name column, `dd.mm.yyyy HH:ii` dates, 34px tinted action buttons, hover row highlight. **No group concept** — definitions are a flat list (Başlık + Kod only); `scope_group` was removed from list/filter/export/form and pruned from the DB.
- **Product lists row polish (2026-11-19):** `/coalpanel/products` (PList) — `prod_code` indigo monospace pills, bold short names, unit chips (ST/KG/M), category chips (teal), formatted dates, tinted 34px action buttons, hover rows. `/coalpanel/product-categories` (PCList) — bold category titles, `code` pills, formatted dates, same row styling. `/coalpanel/client` (CList) — bold titles, `clicode` pills, system chips (ADM orange / GDZ blue), `lifnr` cari pills, same action buttons; mobile card view kept. `/coalpanel/users` (UList) — status chips (Aktif green / Pasif red / Onay Bekliyor amber + Şifre Yenileme warn chip), bold names, Tip teal chip, Rol indigo chip, Sistem chips, monospace usernames with active-dot icon, amber/teal/red 34px action buttons (reset/edit/delete). The shared design lives in `public/coaltheme/css/custom.css` (FINAL OVERRIDE block; bundled via Vite — **rebuild required after edits**): header 14px 18px / 11px 800 uppercase, rows 15px 18px, hover `#f0fbfc` with rounded edges. Applies to all `.rlist-card` admin tables. Verified live: client/products/categories/kva/users all read 15px 18px + hover `rgb(240,251,252)`; tenant chips consistent (GDZ rgb(239,246,255) / ADM rgb(255,247,237)).
- **Category form now exposes the connections:** each `category_files` row renders two tagboxes — `Geçerli Güçler` (kVA docs) and `Geçerli Tipler` (scope-value docs, **bare codes, no group prefix**). Both load existing pills and re-seed `dynamicF` on build so untouched rows keep their links. Verified live: TRF 11 rows × 9 kVA (`50 kVA` … `1600 kVA`); GY 7 units (row 13 → KES. ÇIK, row 26 → 3 units); HY 9 units (row 24 → YA. TRF KOR + GER. TRF); PANO 4 plain kVA pills (630/1000/1250/1600) + 7 combined type pills; SDK TİP-A/TİP-B every row. Save-without-change keeps rows, metadata and single-conn shape intact.
- Live build: `prjBuildLive` runs `ProductCategorySeeder → ProductSeeder → KvaSeeder → ScopeValueSeeder → TedarikRealCatalogSeeder`. On an already-populated DB: `php artisan catalog:purge --force && php artisan db:seed --class=TedarikRealCatalogSeeder`.
- Open data questions (Master): FRM.865 evrak no 1 missing in source; 1600 kVA HARİCİ/DAHİLİ tag absent; FRM.869 row 13 standard reads "61439-1 Madde 10.2.101" (kept literal, likely a 61439-5 typo).

## 15. Gotchas

1. **Readonly/hidden link fields are invisible to validation** — category link is enforced in `submitForm`, not via `required`.
2. **Always inject page-owned values into `formData.dynamicF` at submit time** — DOM writes alone can be lost on re-render (Form rebuilds inputs from store).
3. **`setOptions` runs unbound** — use raw `fetch` with `localStorage.token`, not `this.plib` (no component context).
4. **PickleTable has no `destroy()`** — clear `innerHTML` manually when closing modals or tables duplicate.
5. **New `per-*` codes must also be added to the hardcoded DEV_ADMIN list** in `AuthController::getPermissions()` or the god-mode account silently loses the UI.
6. **kVA `code` is the contract value** (matched later against product variants); `title` is display-only. Keep codes numeric strings.
7. **Test data hygiene** — passivate (`status=0`) E2E rows after runs; live list queries only show `status=1`.
8. **`category_files` row keys must be unique in their first dash segment** (`rowKey.split('-')[0]`). `Form.vue`'s `multiple` builder de-dupes cards with `keyLock` on that token, so seeding `seed-1 … seed-14` (or `new-tr-0 … new-tr-13`) collapses all rows into **one** visible card. Use frontend-shaped keys like `1700000000000-0`, `1700000000001-1`, … (unique prefix per row). `TransformerCatalogSeeder` follows this.
