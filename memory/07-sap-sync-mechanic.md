# SAP Sync Mechanic — How To Create Fresh Data

> **⚠️ USE THIS ALWAYS: `php artisan orders:reset` — one-shot wipe + fresh (no manual search)**
> **→ `panel/app/Console/Commands/ResetOrdersCommand.php` (2026-09-07) wraps `orders:sync --fresh` + wipes serials/files/notification_reads/storage/cache**
> **Command:** `php artisan orders:sync --json=/path/to/payload.json` + **`php artisan orders:reset` (preferred)**
> **File:** `panel/app/Console/Commands/SyncOrdersCommand.php` + `ResetOrdersCommand.php`
> **Payload:** `/tmp/sap_fresh_payload.json` (last used, 21 rows → 8 EBELN)

## 1. The Command — USE `orders:reset` ALWAYS (no manual search)

```bash
cd panel

# ONE-SHOT — wipe everything + fresh SAP examples (PREFERRED — use this always)
php artisan orders:reset
php artisan orders:reset --json=/tmp/sap_fresh_payload.json  # explicit payload

# What it does (inside ResetOrdersCommand.php:30):
#  1. Wipe serials (op-doc-order-serial docs + EAV + trans)
#  2. Wipe files (document_files + op_id=1 trans + sys_con_entities table_tag=document_files + storage/app/public/documents + temp)
#  3. Wipe notification_reads + Cache::flush()
#  4. Call orders:sync --fresh (which wipes orders+items internally)
#  → Before: X orders, Y items, Z serials, W files → After: 8 orders, 21 items, 0 serials, 0 files, ~39 trans, clean slate
#  → No manual tinker, no rm -rf, no cache search — just one command

# Low-level (if you need dry-run or append-only)
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --dry-run   # preview
php artisan orders:sync --json=/tmp/sap_fresh_payload.json             # idempotent append
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh     # wipe orders+items only (NOT serials/files — use orders:reset for full)
```

**`orders:reset` flags:**
- `--json=` — payload path, default `/tmp/sap_fresh_payload.json` (21 rows → 8 EBELN, keep leading zeros in LIFNR)
- `--keep-clients` — reserved (clients are kept by default, as in sync)

**`orders:sync` flags:**
- `--json=` — path to SAP JSON payload (required)
- `--dry-run` — show stats without writing
- `--fresh` — wipe all `op-doc-order` + `op-doc-order-item` docs before sync (use `orders:reset` for FULL wipe)

**Idempotency:** Checks `sys_con_entities` for `entity_tag=order_no` matching `EBELN`. If exists, skips that order entirely.

## 2. Payload Format

Flat array of SAP rows. Each row = one order item (SAP sends items, we group by EBELN).

```json
[
  {
    "BUKRS": "4000",        // Company code → sys_code
    "LIFNR": "0000300184",  // Vendor → spec_code (Cari Kodu), keep leading zeros
    "EBELN": "3510004200",  // Purchase order number → order_no
    "EBELP": "00010",       // Item number → appended to prod_code as MATNR**EBELP
    "MCOD1": "DEMİR ÇELİK A.Ş.", // Company name → ctitle
    "MATNR": "40.1.2.001",  // Material number → prod_code (first part)
    "TXZ01": "Premium Kok 1. Sınıf", // Description → title
    "MENGE": "3500",        // Quantity → quantity
    "MEINS": "ST",          // Unit → unit
    "BEDAT": "22/08/2026",  // Date → created_at (d/m/Y format)
    "SUBMI": "SAP-2026-001",// Submitter → buying_no
    "NETPR": "0",           // Price — NOT stored in form (kept out of UI)
    "WEMNG": "0"            // Received qty — NOT stored
  }
]
```

**Field mapping (SAP → EAV entity_tag):**
- Order fields: `order_no`, `buying_no`, `spec_code`, `sys_code`, `ctitle`, `created_at`
- Item fields: `prod_code` (= `MATNR**EBELP`), `title`, `quantity`, `unit`

## 3. What The Command Does

For each group of rows sharing `EBELN`:

1. **Skip check** — if `order_no` entity already exists, skip
2. **Create/find client** — match by `lifnr` entity in `op-doc-client`, create if not found
3. **Create order document** — `Documents type_id=op-doc-order`, `person_id='system'`
4. **Birth transaction** — `doc_trans_order_created`
5. **Create EAV fields** — `sys_con_ops` (form type `op-doc-order-form`) + `sys_con_entities` for each field
6. **Create items** — for each row: `Documents type_id=op-doc-order-item`, `parent_id=order.id`, same EAV pattern with `op-doc-order-item-form`

## 4. How To Prepare A New Payload

**From SAP directly:** Export to JSON matching the field names above.

**Manual:** Create a JSON file at `/tmp/sap_fresh_payload.json`:
```bash
cat > /tmp/sap_fresh_payload.json << 'EOF'
[
  {"BUKRS":"4000","LIFNR":"0000300190","EBELN":"3510005000","EBELP":"00010","MCOD1":"NEW CLIENT","MATNR":"99.9.9.001","TXZ01":"Test Product","MENGE":"1000","MEINS":"ST","BEDAT":"28/08/2026","SUBMI":"TEST-001","NETPR":"0","WEMNG":"0"}
]
EOF
php artisan orders:sync --json=/tmp/sap_fresh_payload.json
```

**From existing test data:** The file at `/tmp/sap_payload.json` (copied to `/tmp/sap_fresh_payload.json` for sync) has **8 orders, 21 items, 8 clients** (3510004200, 3510004300×2, 3510004400×4, 3510004500×2, 3510004600×3, 3510001793×2, 3510002100×3, 3510003500×4). Reuse or modify.

## 5. Current Live Data

**Authoritative snapshot lives in `memory/05-order-system-state.md` §8.** As of the last fresh sync (2026-09-07 12:47, `php artisan orders:reset` → same payload `/tmp/sap_fresh_payload.json` 21 rows → 8 EBELN): **8 orders, 21 items, 8 clients, 0 files, 0 serials, 39 transactions** — all ST < 300, mixed ST/KG/M, clean slate. **USE `orders:reset` ALWAYS — no manual `cp`/`tinker`/`rm -rf` needed.** Prev 2026-09-01 12:25 was `cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json && php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` + manual wipe.

## 6. Important Notes

- **No auth needed** — command runs as CLI, `person_id='system'` for all created docs
- **No file uploads** — only creates order headers + item rows. Files (transfer_kabul/cins/item_test/images) are uploaded via the UI
- **No grp_code set** — `target_type` entity not in payload, so `grp_code` stays null. If tenant filtering needed, add `target_type` to payload entities
- **Transactions total: 37** (birth: 8 orders + 21 items + 8 clients)
- **Wipe clean:** `orders:reset` wipes ALL: orders+items (via `--fresh` inside) + serials + files + file trans + file entities + storage `documents/*` + `temp/*` + `notification_reads` + `Cache::flush()`. `orders:sync --fresh` alone only wipes orders+items (use `reset` for full). Clients are NOT deleted. **Always use `orders:reset` — no manual steps.**
- **Old coal data** no longer exists — wiped 2026-09-01. Use `orders:reset` to remove if unwanted after new tests
