# SAP Sync Mechanic — How To Create Fresh Data

> **Command:** `php artisan orders:sync --json=/path/to/payload.json`
> **File:** `panel/app/Console/Commands/SyncOrdersCommand.php`
> **Payload:** `/tmp/sap_fresh_payload.json` (last used)

## 1. The Command

```bash
cd panel

# Preview what would be created
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --dry-run

# Create fresh data (idempotent — skips existing EBELNs)
php artisan orders:sync --json=/tmp/sap_fresh_payload.json

# Wipe all existing orders + items, then recreate
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh
```

**Flags:**
- `--json=` — path to SAP JSON payload (required)
- `--dry-run` — show stats without writing
- `--fresh` — wipe all `op-doc-order` + `op-doc-order-item` docs before sync

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

**From existing test data:** The file at `/tmp/sap_fresh_payload.json` has 5 orders (3510004200-3510004600), 14 items, 5 clients. Reuse or modify.

## 5. Current Live Data (2026-08-28 after sync)

| EBELN | Müşteri | LIFNR | Items | Status |
|-------|---------|-------|-------|--------|
| 3510001793 | PANORAMA TEKSTİL | 0000300181 | 2 | doc_trans_order_created |
| 3510002100 | HASÇELİK KABLO | 0000300182 | 3 | doc_trans_order_created |
| 3510003500 | HES HACILAR ELEKTRİK | 0000300183 | 4 | doc_trans_order_created |
| 3510004200 | DEMİR ÇELİK A.Ş. | 0000300184 | 3 | doc_trans_order_created |
| 3510004300 | AKSA ENERJİ LTD. | 0000300185 | 2 | doc_trans_order_created |
| 3510004400 | YILDIZ TEKSTİL | 0000300186 | 4 | doc_trans_order_created |
| 3510004500 | BORA MADENCİLİK | 0000300187 | 2 | doc_trans_order_created |
| 3510004600 | GÜNEŞ ELEKTRİK | 0000300188 | 3 | doc_trans_order_created |

**Total: 8 orders, 23 items, 8 clients** (3 original from memory/05 §3 + 5 new from sync command)

## 6. Important Notes

- **No auth needed** — command runs as CLI, `person_id='system'` for all created docs
- **No file uploads** — only creates order headers + item rows. Files (transfer_kabul/cins/item_test/images) are uploaded via the UI
- **No grp_code set** — `target_type` entity not in payload, so `grp_code` stays null. If tenant filtering needed, add `target_type` to payload entities
- **Transactions total: 104** (birth transactions for all docs)
- **Wipe clean:** `--fresh` deletes all orders + items + their EAV + transactions. Clients are NOT deleted
- **Old coal data** (3510001793 etc) still exists from previous sessions. Use `--fresh` to remove if unwanted
