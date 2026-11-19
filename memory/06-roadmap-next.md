# Roadmap — Next Sessions

> **Completed-work log removed (was duplicate of `05 §4`). History lives in `git log`. Mechanics live in `panel/documentation/*`.**

## 1. Immediate Next (pick 1)
- **Category variant mechanic:** `category_files` rows carry `variants` (explicit scope-type codes from the seeded `op-doc-scope-value` dictionary) + category `scope_kind/headers/value/variants` metadata (real TEDAŞ catalog, 2026-11-19). Extend the tender/offer matrix to scope cells by variant (hücre birimleri, TİP-A/B, HARİCİ/DAHİLİ) like kVA: `IhOffer.vue buildItems/loadTemplates` expansion, `validateOfferFiles` + `ClientDocsService::matchKey` variant key, product `variant` entity. Bible: `products.md §14`.
- **Sevk Way-1 follow-ups:** SAS depo pull, ambar doluluk takvim API, sevk mail `sevk-01`, per-05-06/07 split (now reuses 05-03/07-02). Mechanics: `panel/documentation/modules/transfer-approve.md`.
- **Fabrika Kabul Way-2:** specced later, guard slot reserved (`ready → fabrika_started`).
- **Tedarik polish:** list LIFNR filter wiring verify, detail readOnly + transfer + serials + files via shared `OForm`, `files_rejected` re-upload loop — mostly DONE, verify both panels parity.
- **Dashboard extend:** add Sevk states to `tedarikStats/adminStats` breakdown (colors/labels done) + Stat→List `?status=` already wired.
- **Branding:** DONE (`ADM/GDZ`). Only per-tenant CSS tweaks left (logo-adm rule).
- **Front panel skin:** waits on `memory/idea.md` design from Master — crown jewel, not started.

## 2. Tech Debt (active only)
- `Documents::tableList parent_type_id=0` — items visible in main lists
- Security: `DEV_ADMIN 111111`, public `resetusercradentals`, CSRF off, `/order-file` IDOR, `pickle` hardcoded, relay password logged
- `B2X` stopped container on 5431 — remove when confirmed
- `permission_version file` store — `refreshAllUserPermissions` flaky, relogin fallback

## 3. How To Resume
1. Read `memory/05` + this file + `panel/documentation/tedarik-system-process.md:12` test plan.
2. `docker ps | grep tedarikNewApp`, `panel/.env`, `php artisan migrate:status`.
3. Key methods: `DocumentServiceProvider::processOrderTransfer / duplicateOrderItem / decrementItemQuantity / createSerialEntries / setHasSerialsFlag`, `ReportServiceProvider::getResellerLifnrs / tedarikStats / tedarikRecentOrders / tedarikStatusBreakdown`.
4. After any `Form.vue/router/Sidebar/OForm/OList/OrderItemTable` edit → `npm run build` → test both panels.
5. Update `memory/05 §1` after major change. Do NOT re-log history here.

## 4. Decision Tree
- "Front design ready" → build client front-panel skin
- "Dashboard stats" → extend `tedarikStats`
- "Dashboard polish" → animations / realtime / chart types
- "New doc type" → `memory/03-app-factory-guide.md` (sys_options + Form.vue + pages + perms)
