# Form Engine Deep Dive — `coalparts/Form.vue`

> **Single file does everything:** `panel/resources/js/components/coalparts/Form.vue:~3300` lines, imperative DOM, schema-driven.  
> **Backend mirror:** `DocumentServiceProvider::registerContent` + `PersonsServiceProvider::setPerson`  
> **Transport:** `pickle.js` + page savecallbacks

## 1. Mental Model — No Migrations, Only Schemas
INGSYS: `sys_options entry → Form.vue schema → EAV rows`

## 2. Form.vue Schema Structure
```js
data().forms = {
  'op-doc-order-form': { showRemoveButton, oncreated, fields: [...] },
  'op-doc-order-item-form': { ... },
  'op-doc-client-form': { ... },
  // ...
}
```

## 3. Field Types
text/email/password/number/textarea, select, file, switch, yesno, tree, button, section, sub, multiple

## 4. Naming Convention — CRITICAL
```
entity_tag = "{fieldName}**{group_key}**{rowId}"
file input name = "dynamicFile**{group_key}**{fileId}*-*{tag}**{group_key}**{rowId}"
```

## 5. `readonlyFields` Support
- `Form.vue:2385` — handles compound names `field**group**id` via `startsWith(rf+'**')`
- `Form.vue:2995` — textarea same logic + opacity
- Used for `order_desc/imalatci_firma_adi` + files `transfer_kabul/cins` when locked

## 6. File Handling
- `tempUpload` flow: `POST /v1/temp-upload` → `reference_id` → sent as JSON → `finalizeTempFile`
- **FIXED:** `finalizeTempFile` now handles replacement with `$existingFileId` param
- File replacement versioning: both paths create version chains. See `panel/docs/file-replacement-fix.md`.

## 7. Single-File Slots
- `transfer_kabul` and `transfer_cins` use `type:'multiple'` with `hideAdd:true` + `removable:false`
- Exactly one row, one file input. Re-upload replaces in same slot.
