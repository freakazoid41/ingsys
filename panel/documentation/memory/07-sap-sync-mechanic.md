# 07 — SAP Sync Mechanic

> **Updated:** 2026-09-06
> **Read after:** `tedarik-system-process.md`

## Sync Flow

1. SAP payload → `/tmp/sap_payload.json`
2. Fresh copy → `cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json`
3. Run: `php artisan orders:sync --fresh`
4. Result: 8 orders, 21 items, 8 clients, 7 files, 0 serials, 37 transitions (`grp_code=GDZ`)

## Payload Shape

```json
{
  "EBELN": "3510004500",
  "EBELP": "0010",
  "MATNR": "...",
  "TXZ01": "...",
  "MENGE": "10",
  "MEINS": "ST",
  "LIFNR": "...",
  "NAME1": "...",
  "BEDAT": "2026-01-15",
  "EINDT": "2026-02-01"
}
```

## Key Tables

| Table | Purpose |
|---|---|
| `documents` | Main document rows (order, client, offer, file) |
| `document_items` | Line items per order |
| `document_serials` | Serial numbers per item |
| `document_transitions` | Status change history |
| `document_files` | File attachments |

## Backend Endpoints

| Endpoint | Purpose |
|---|---|
| `POST /api/v1/table/documents` | List documents (PickleTable) |
| `POST /api/v1/trans/set-status` | Change order status |
| `POST /api/v1/trans/set-file-status` | Change file status |
| `POST /api/v1/orders/rename` | Rename partitioned order |
