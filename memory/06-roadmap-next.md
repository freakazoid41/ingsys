# Roadmap — Plans For Next Sessions

> **Front panel is the crown jewel — design awaited.**

## 1. Immediate Next (Choose 1)

### Option A: Front Panel Design → Build Client Flow
- Route & Layout: `/front/orders` + `/front/orders/:id`
- Order Detail Client View: backend already handles, just render skin
- File upload: reuse `Form.vue` schemas + `pickle.js` temp-upload
- Rejected files: `files_rejected` keeps files editable

### Option B: SAP Dummy Ingest + Cron
- `POST /api/v1/orders/dummy-ingest` (admin only)
- Group by EBELN, create order + items
- Cron: `Kernel.php` schedule `orders:sync`

## 2. After Client Flow

1. **Dökümanlar file approve** — DONE (`set-file-status`, `syncOrderStatusFromFiles`)
2. **Transfer approve/reject** — DONE (`/v1/trans/set-status`, `/v1/orders/cancel`)
3. **Dashboard rebuild** — PENDING (coal queries still)
4. **Skin** — replace branding

## 3. Tech Debt

- **File replacement FIXED 2026-08-28.** Both upload paths version correctly. Docs: `panel/docs/file-replacement-fix.md`.
- Parent link: `Documents::tableList` still shows all items
- Security: `DEV_ADMIN`, CSRF off, IDOR, hardcoded keys
- Old containers: `B2X` still exists

## 4. How Future LLM Should Resume

1. Read `memory/00-core-overview.md` + `05-order-system-state.md` + `06-roadmap-next.md`
2. Check docker, .env, migrate:status
3. Admin transfer flow already built
4. After Form.vue/router/Sidebar/OForm/OList/OrderItemTable edit → `npm run build`
5. Always update `memory/05` after major change

## 5. Decision Tree

- **"Front design ready"** → build client front-panel skin
- **"Dummy first"** → build `POST /api/v1/orders/dummy-ingest` + cron
- **"Dashboard first"** → rebuild `ReportServiceProvider` order stats
- **"Skin it"** → rebrand coal → tedarik
