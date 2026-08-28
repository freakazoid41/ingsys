# Form Engine Deep Dive — `coalparts/Form.vue`

> **Single file does everything:** `panel/resources/js/components/coalparts/Form.vue:2892` lines, imperative DOM, schema-driven.  
> **Backend mirror:** `panel/app/Providers/DocumentServiceProvider.php:27-288` (`registerContent`) + `panel/app/Providers/PersonsServiceProvider.php:73-316` (`setPerson`)  
> **Transport:** `panel/resources/js/lib/pickle.js:824` + page savecallbacks (e.g. `RForm.vue:85`, `OForm.vue`)

---

## 1. Mental Model — No Migrations, Only Schemas

Traditional: `migration → model → form`  
INGSYS: `sys_options entry → Form.vue schema → EAV rows`

Adding a field **never** touches the DB schema. You:
1. Add a row to `sys_options` (or reuse)
2. Add a field object to `Form.vue:data().forms[formKey].fields`
3. Backend auto-creates `sys_con_ops` + `sys_con_entities` rows.

---

## 2. Form.vue — Schema-Driven Imperative Engine

### 2.1 Where schemas live
```js
// panel/resources/js/components/coalparts/Form.vue:136-~2890
data() {
  return {
    ftypes: this.formtypes.split(','),
    forms: {
      'op-doc-flat-form':    { showRemoveButton, oncreated, fields: [...] },
      'op-doc-user-form':    { ... },
      'op-doc-request-form': { ... },
      'op-doc-client-form':  { ... },
      'op-doc-offer-form':   { ... },
    }
  }
}
```
Pages instantiate via prop:
```vue
<Form formtypes="op-doc-request-form" :savecallback="submitForm" savebtntitle="Kaydet" />
<!-- or multiple: formtypes="op-doc-user-form,op-doc-client-form" -->
```

### 2.2 Field types (rendered by `buildDynamicFForm`)
| type | Renders | Key props |
|------|---------|-----------|
| `text/email/password/number/textarea` | `<input>` / `<textarea>` | `name`, `label`, `placeholder`, `required`, `col`, `readOnly`, `hidden`, `showOnValue`, `isDate`, `isMasked/mask`, `hasIcon/icon`, `defaultValue`, `oninput` |
| `select` | `<select>` | `options: [{text,value}]`, `setOptions: async () => [...]` (supports async permission roles, facilities), `hasMultiple`, `disabled` |
| `file` | `<input type=file>` + preview/status | `accept`, `requiredIfFirst` |
| `switch` | checkbox toggle | `desc` |
| `yesno` | radio pair | — |
| `tree` | `TreeModal` permission tree | `list: permissionDataStore.list`, `oncheck` |
| `button` | `<button>` | `value`, `oninput` (used for "Şifre Üret", "Kullanıcı Talebinde Bulun") |
| `section` | `<hr>` | — |
| `sub` | horizontal group row (one `sys_con_ops`) | `group_key` (optional), `subs: [fields]` |
| `multiple` | repeatable row (adds "+ Ekle" button) | **Must have `group_key`**, `removable`, `requiredIfFirst`, `subs` |

`col: 1-12` → Bootstrap grid. `hidden: true` still in DOM but hidden. `showOnValue: true` reveals when value non-empty.

### 2.3 Special field props & behaviors
- `isDate: true` → flatpickr Turkish locale, `d/m/Y`, `monthSelect` for `isMonth`. `hasTime` adds time picker. Value auto-converted `d/m/Y → Y-m-d` in `submitDynamicChanges`
- `isMasked + mask: 'money|phone|email'` → `VMasker` (`Form.vue` + `pickle.js`). Money normalized `.`→removed `,`→`.` before save
- `setOptions: async` → called at build time to populate selects (e.g. roles from `permissionDataStore.fetchRoleTemplates()`, facilities from `formDataStore.setFacilitiesData()`)
- `oncreated(id,row)` → hook after row DOM built (used to pre-check permission tree, hide `fuel_price_impact_2` via `parentNode.parentNode...` traversal)
- `disabled: !authStore.permissions.includes('per-04-02')` — permission-gated fields directly in schema

### 2.4 The naming convention — CRITICAL
```
entity_tag = "{fieldName}**{group_key}**{rowId}"
file input name = "dynamicFile**{group_key}**{fileId}*-*{tag}**{group_key}**{rowId}"
```
Examples:
```
clicode**userclientgroup**20240501120000-0   (persons client code)
cont_imza_file**clientimzasirku**new-12345  (document file)
prime_unit_price**calory_settings**17461    (multiple row price)
```

`group_key` ties fields into one `sys_con_ops` row. `rowId` is `new-{timestamp}` for new rows, or DB `sys_con_ops.id` for existing rows (loaded via `formDataStore`).

### 2.5 Data collection — `submitDynamicChanges(target, isTree=false, fieldName)`
```js
// Every oninput calls this:
oninput: (e) => this.submitDynamicChanges(e.target)

// Inside:
this.formData.dynamicF[`${tag}**${rowId}`] = { tag, entities: { [fieldName]: value } }
this.formData.files[`dynamicFile**${group_key}**${fileId}*-*${fieldName}**${group_key}**${rowId}`] = fileObj
this.formData.removedData.push({ id: connId, key: entity_tag, group_key }) // for deleted multiple rows
```
- Type coercion: checkbox `1/0`, money stripped, date reformatted
- Files stored separately in `formData.files` with composite key; empty/deleted tracked in `removedData`
- `formDataStore.addional` can pre-populate (typo kept)

### 2.6 File handling quirks
- `fileInfo()` validates `jpg/jpeg/png/pdf` + ≤42MB
- Existing files show status badge (is-valid/is-invalid) + link to `/order-file/{qnid}`
- New file selection uses `DataTransfer` to inject `File` into hidden input
- `tempUpload` flow: immediate `POST /v1/temp-upload` → returns `reference_id` → sent as JSON string via `dynamicFile*` key (handled in `DocumentServiceProvider:212` `isReference` branch + `finalizeTempFile`)

### 2.7 Rendering flow (`mounted`)
```
load roles → for each ftype in ftypes:
  buildDynamicFForm(ftype, formDataStore.getData()?.[ftype] ?? { newRow })
→ clear formDataStore
→ beforeUnmount: destroy flatpickr instances + TreeModal
```
Template: one `.area-target[data-tag=ftype]` per form type + `<AppFab>` (visible only for admin or request forms).

### 2.8 Known debt
- ~2892 lines, Vue reactivity unused (raw `document.createElement`)
- Visibility via `parentNode.parentNode.parentNode...` (5 levels) — brittle
- `vergiKimlikDogrula` VKN 10-digit algo commented out
- `Math.random()` for password gen
- `keyLock` used as `this.keyLock = []` but not in `data()` (non-reactive, works by accident)
- `op-doc-per-kanaat` hardcoded in yesno block — dead logic from other project
- **Textarea `readonlyFields`** — added at Form.vue:2995/2385, applies `readOnly + disabled + opacity` to textareas/inputs matching `readonlyFields` prop. Handles compound names `field**group**id` via `startsWith(rf+'**')`. Used for `order_desc/imalatci_firma_adi` + files `transfer_kabul/cins` on locked orders (`OForm.vue:63` `isFilesLocked`). `transfer_kabul/cins` are single-file `multiple` with `hideAdd:true` (not repeatable).

---

## 3. Backend Mirror — How Data Is Stored

### 3.1 Save — `DocumentServiceProvider::registerContent($id, $requestData, $files)`
`panel/app/Providers/DocumentServiceProvider.php:26-288`

```php
// Payload from frontend:
$requestData = [
  'typeKey' => 'op-doc-request',
  'dynamicF' => [
    'op-doc-request-form**new-12345' => [
      'tag' => 'op-doc-request-form',
      'entities' => [ 'title' => 'Konu', 'target_type' => 'Yatağan', ... ]
    ],
    'op-doc-request-form**678' => [ // existing row
      'tag' => 'op-doc-request-form',
      'entities' => [ ... ]
    ]
  ],
  'removedData' => [ ['id'=>connId, 'key'=>entity_tag], ... ],
  'main_title' => '...',  // only GENERIC_WRITABLE_MAIN_FIELDS: title, starting_at, ending_at
]
$files = [ 'dynamicFile**group**id*-*field**group**row' => File|jsonReference ]
```

Flow (DB transaction):
1. Resolve `Documents` (`qnid` if update else new) → set `type_id` from `sys_options op_key=typeKey`
2. Whitelist write: only `main_title|starting_at|ending_at` from `main_*` keys → `documents` table. `status/qnid/type_id/person_id/grp_code` never writable here.
3. On create: `Transactions doc_trans_created` + `documents.person_id = session person_id`
4. For each `dynamicF` entry:
   - Decode `id` from `key.split('**')[1]` → `"new-..." ? new Sys_con_ops : find existing`
   - `Sys_con_ops: main_id=document.id, conn_id=0, type_id=sys_options where op_key=tag, sub_type_id=form-main|form-file`
   - Auto fields: `op-doc-client: clicode = document.qnid` (immutable on update, client cannot override), `op-doc-request/offer: req_no=count` on create, `rev_date=date d/m/Y` on update
   - For each `entities[key=>value]`: upsert `Sys_con_entities(conn_id, entity_tag, table_tag=sys_con_ops)` → `entity_value = strip_tags(value)`
   - If `entity_tag == 'target_type' && typeKey in [request,offer]` → `documents.grp_code = upper(TR-normalized value)` (Yatağan→YATAGAN, ÇATES→CATES)
   - For each `dynamicFile` matching `id`: if `is_string && is_json` → `finalizeTempFile(reference_id)` else `addFileToDb(file,...)` → creates `Document_files(status=1)` + `Transactions doc_file_waiting` + encrypted filename in `description` → upsert `Sys_con_entities(table_tag=document_files, entity_value=fileId)`
5. Process `removedData`: soft-delete `Document_files(status=0)` + delete `Sys_con_entities`
6. Commit → `getFormData(qnid)` for `after` → `UserLog create (before/after JSON, type log-tender-update)` → return `{success, id, data: document, qnid, detail: getFormData}`

File replacement versioning: same `entity_tag` new `File` → old `Document_files.status=0 + replaced_id` chain. **Both paths now version correctly:** `addFileToDb` (traditional) and `finalizeTempFile` (temp upload) both deactivate old, create new record, chain via `replaced_id`, copy entities, log `doc_file_refreshed`. See `panel/docs/file-replacement-fix.md`.

### 3.2 Persons mirror — `PersonsServiceProvider::setPerson($id, $data, $files, $fileGroup, $allData)`
`panel/app/Providers/PersonsServiceProvider.php:73-316`

Same pattern but for `persons`:
- `main_*` → `persons` columns + `type_id` from `type_key`
- Splits: `contact*` → JSON contacts, `*userfacilitygroup*` → `$facilities[tag][field]`, `*userclientgroup*` → `$clients`, `user_*` → `$user` (email/role/status/password)
- `permissions` JSON array stored as single entity `"{personId}**userpermissiongroup**{personId}"` with `type_id=op-doc-user-permission-form`
- `clients` each field as `"{field}**userclientgroup**{k}"`
- `facilities` similarly `"{field}**userfacilitygroup**{k}"`
- `upsertConnectionEntity(mainId, typeId, subTypeId, entityTag, value)` — `updateOrCreate` on `Sys_con_ops(main_id, type_id, sub_type_id)` + `Sys_con_entities(conn_id, entity_tag)`
- On password: `User::updateOrCreate(person_id, [password=>Hash::make, email, role, status, needs_refresh])`
- After commit: `PermissionService.refreshUserPermissionCache + bumpUserPermissionVersion` + `UserLog`

### 3.3 Read — `getFormData($qnid)` & `getPerson($qnid)`
`DocumentServiceProvider.php:290-398`, `PersonsServiceProvider.php:318-393`

Raw SQL (no Eloquent, note SQLi surface — qnid concatenated):
```sql
-- sys_con_ops + sys_con_entities joined, sys_options filter group_key='op-doc-forms' excluding permission forms
SELECT dco.id, so.op_key, sce.entity_tag,
  CASE WHEN sce.table_tag='document_files' THEN
    (SELECT json_build_object(description, qnid, id, status, last_status=>json_build_object(op_key, title, name, note) FROM transactions...) FROM document_files WHERE id=sce.entity_value::int)::text
  ELSE sce.entity_value END
FROM sys_con_ops dco JOIN sys_options so ON so.id=dco.type_id
LEFT JOIN sys_con_entities sce ON sce.conn_id=dco.id
JOIN documents d ON d.id=dco.main_id
WHERE so.group_key='op-doc-forms' AND dco.conn_id=0 AND dco.status=1 AND d.qnid='...'

-- documents + aggregated transactions (status history as JSON)
SELECT sp.op_key, d.*, d.status as document_status,
  (SELECT json_agg(json_build_object(op_key,title,note,created_at,name)) FROM transactions...) as status
FROM documents d JOIN sys_options sp ON sp.id=d.type_id WHERE d.qnid='...'
```
Grouped into:
```js
{
  document: { id, qnid, op_key, title, grp_code, document_status, status: JSON[], ... },
  formFormat: {
    'op-doc-request-form': {
      '123': { entities: { title: '...', target_type: 'YATAGAN', ... }, files: {} },
      '124': { entities: { ... } }
    }
  }
}
```
Frontend loads via: `GET /v1/document/{qnid}` → `DocumentController@index:77-83` → returns `{success, data: getFormData}` → `formDataStore.setData(data.formFormat)` → `Form.vue` populates.

`getPerson` similar but LEFT JOINs `persons → users → sys_options` + 3 lateral JSON_Agg for permissions/contacts/clients.

### 3.4 File helpers
`panel/app/Helpers/DocumentHelpers.php:777`
- `uploadFile(file)` — validates ≤42MB, whitelist `jpg/png/jpeg/pdf/xls/xlsx`, names `time()+random+slugify`, stores `storage/app/public/documents/`, encrypts name via `EncryptionProvider::encrypt` (AES-128-CBC + PBKDF2, base64url `salt:iv:ct`, hardcoded `pickle` key) → `Document_files(description=encrypted, status=1, relation='documents', relation_id=document.id, qnid=uuid)`
- `addFileToDb(file, group, fileId, relation, relationId, note)` — above + `Transactions doc_file_waiting` + `UserLog`
- `finalizeTempFile(referenceId, documentId, group)` — moves from temp staging to permanent + above
- `tempUploadFile(file)` — immediate staging, returns `{reference_id}`
- `decryptFile(doc, mode)` — route `GET /order-file/{qnid|encrypted}`: if qnid format → `Document_files where qnid`, else `EncryptionProvider::decrypt` → serves with mime. Authz commented out (IDOR).
- Version chain: `document_files.replaced_id` links old→new on same entity_tag replacement.

---

## 4. Frontend ↔ Backend Contract — Page Glue

Example `RForm.vue:85-118` submit:
```js
async submitForm(formData) {
  formData.typeKey = 'op-doc-request';
  const check = this.plib.checkForm('.form-item'); // validates required visible fields
  if (!check.valid) { toast('Eksik Alanları Doldurmalısınız'); return }
  const envelope = new FormData();
  envelope.append('data', JSON.stringify(formData));
  for (let key in formData.files) {
    const item = formData.files[key];
    if (item.reference) envelope.append(key, JSON.stringify(item.reference));
    else if (item.file) envelope.append(key, item.file);
  }
  const rsp = await this.plib.request({
    url: '/api/v1/document' + (this.id ? '/'+this.id : ''), // POST vs PUT
    method: this.id ? 'PUT' : 'POST'
  }, null, envelope);
  // on success → router.push({name: 'RequestList'})
}
```
- `PUT` multipart is broken on Apache — `bootstrap/app.php:ParsePutMultipart` is buggy (missing imports), fallback `parsePut()` helper in `DocumentController:141`
- `DocumentController:index` guards: `docPermCheck(typeKey, read|edit)` (map: `op-doc-request→per-05-*, client→per-06-*, offer→per-08-*`) + supplier `offerOwnershipCheck` + `canResponse`
- `Offer` PUT extra guard: cancelled `document_status==0` → 422; supplier only editable if last `transactions` op_key in `[revision, created, draft]`

---

## 5. How To Add A New Form (Quick Recipe)
1. Pick a `typeKey` e.g. `op-doc-invoice` and form key `op-doc-invoice-form`
2. Seed `sys_options`: `(op_key='op-doc-invoice', group_key='op-doc', ttitle='documents', ctitle='type_id')` and `(op_key='op-doc-invoice-form', group_key='op-doc-forms')`
3. In `Form.vue` add to `forms`:
   ```js
   'op-doc-invoice-form': {
     showRemoveButton: false,
     oncreated: () => {},
     fields: [
       { type:'sub', name:'sub_1', label:'Fatura Bilgileri', subs:[
         { type:'text', name:'title', label:'Başlık', col:6, required:true, oninput: e=>this.submitDynamicChanges(e.target) },
         { type:'number', name:'amount', label:'Tutar', col:3, oninput: e=>this.submitDynamicChanges(e.target) },
         { type:'select', name:'target_type', label:'Alıcı', col:3, required:true, options:[{text:'Yatağan',value:'Yatağan'},...], oninput: e=>this.submitDynamicChanges(e.target) },
       ]},
       { type:'multiple', name:'sub_2', label:'Ek Belgeler', group_key:'invoice_docs', removable:true, requiredIfFirst:false, subs:[
         { type:'file', name:'inv_file', accept:'.pdf,.jpg,.png', col:12, label:'Dosya', oninput: e=>this.submitDynamicChanges(e.target) }
       ]}
     ]
   }
   ```
4. Create page `resources/js/pages/coalsystem/Invoice/IForm.vue` (copy `RForm.vue`, change `formtypes="op-doc-invoice-form"`, `typeKey='op-doc-invoice'`, routes)
5. Add route in `resources/js/router/index.js`:
   ```js
   { path: '/coalpanel/invoice/form/:id?', name: 'IForm', component: () => import('@/pages/coalsystem/Invoice/IForm.vue') }
   ```
6. Add sidebar entry in `coalparts/Sidebar.vue` gated by new permission `per-09-*` (add to `sys_permission_catalogs` + role templates)
7. Done — no migration, no model change. `registerContent` + `getFormData` handle storage.
