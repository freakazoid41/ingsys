# Backend & Frontend Patterns

## 1. Request Flow
Browser → Laravel middleware → Controller → Provider → Model/raw SQL → DB

## 2. Key Backend Patterns

### File & Crypto
- `uploadFile`: 42MB, whitelist, encrypted filename, `Document_files`
- `addFileToDb`: creates NEW record, handles replacement (`status=0, replaced_id=newId`, copy entities, `doc_file_refreshed`)
- `finalizeTempFile`: **FIXED** — now handles replacement with `$existingFileId`. Mirrors `addFileToDb` behavior. See `panel/docs/file-replacement-fix.md`.
- `GET /order-file/{qnid|encrypted}` → decryptFile (IDOR)

### Status Machine
`POST /v1/trans/set-status` → `DocumentServiceProvider::setStatus` → `Transactions` + `UserLog`

### Order Status
`created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`

## 3. Key Frontend Patterns

### Central Client — `pickle.js`
`request(rqs, file, formData)` — CSRF + Bearer, 401 handlers, temp upload flow

### Pinia Stores
`auth`, `permissiondata`, `navigation`, `events`, `formdata`

### Router
Routes under `/coalpanel`, no auth guard (server `web.php` is the gate)

### OrderItemTable
Row list (not PickleTable), `POST /v1/table/documents` fetch, max-h 420px scroll, selectable for partial transfer
