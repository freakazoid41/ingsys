# App Factory Guide — How To Create Different Apps From This Core

> **Core thesis:** This is NOT a coal app. It's a **generic EAV document engine** wearing coal clothes.  
> **Reuse vector:** Duplicate the pattern `Document Type + Form Schema + Pages + Permissions + Status Machine` to make any doc-centric app.

---

## 1. What Is Actually Reusable (The Real Core)

| Layer | Files | Reusable? | Notes |
|-------|-------|-----------|-------|
| **EAV Engine** | `DocumentServiceProvider`, `PersonsServiceProvider`, `sys_options`, `documents`, `sys_con_ops/entities` | ✅ 100% generic | Zero coal knowledge |
| **Auth & RBAC** | `AuthController`, `PermissionService`, `CheckPermissionVersion`, `ActiveSession`, `RoleTemplateService`, `sys_role_templates`+`permission_catalogs` | ✅ generic | Just rename roles/permissions |
| **File Service** | `DocumentHelpers`, `EncryptionProvider`, `document_files`, `/order-file`, `temp-upload` | ✅ generic | 42MB, encrypted, versioned (both upload paths) |
| **Notifications** | `EmailServiceProvider`, `MailService`, `SmsService`, `notification_logs`, Jobs | ✅ generic | recipient = `notif-*` groups |
| **Dashboard/Export** | `ReportServiceProvider`, `ExportService`+`ExportController` | ⚠️ semi-generic | Queries are coal-specific, pattern reusable |
| **Frontend Shell** | `pickle.js`, `stores/*`, `layouts/CoalPanel`, `router`, `Form.vue` (engine), `AppFab`, `Sidebar`, `Header` | ✅ generic | Skin/theme is swappable |
| **Coal Specific** | `Form.vue` schemas (`op-doc-request/offer/client`), `RSummary`/`OfferSummary`, `coal_specs`, `calory_settings`, `TCMB/Currencies` | ❌ replace | This is what you swap |

**Bottom line:** Keep rows 1+2+3+4+6. Replace row 5+7.

---

## 2. The Reusable Pattern — 5 Steps To Any New App

### Pattern
```
New Document Type → New Form Schema → New Pages → New Permissions → New Status Machine
```

All 5 are **config + Form.vue + Vue pages** — no new tables.

### Example new apps you can build tomorrow:

| New App | New Doc Type(s) | What Changes |
|---------|----------------|--------------|
| **HR Leave Management** | `op-doc-leave-request`, `op-doc-leave-approval` | Form: employee, dates, type; Status: requested→approved/rejected |
| **Maintenance Tickets** | `op-doc-ticket`, `op-doc-workorder` | Form: asset, priority, description + files; Status: open→in_progress→done |
| **Real Estate CRM** | `op-doc-property`, `op-doc-visit`, `op-doc-offer` | Reuse offer flow but for houses |
| **School Admissions** | `op-doc-application`, `op-doc-evaluation` | Form: student, grades, docs; Status: applied→review→accepted/waitlist |
| **Inventory** | `op-doc-purchase-order`, `op-doc-goods-receipt` | Form: items (multiple), supplier, amounts |

---

## 3. Step-by-Step — Creating `op-doc-leave-request` App

### Step 1 — Seed Dictionary (1 SQL file or seeder)
```php
// In a new seeder or artisan tinker
Sys_options::create(['op_key'=>'op-doc-leave', 'group_key'=>'op-doc', 'title'=>'Leave Request', 'ttitle'=>'documents', 'ctitle'=>'type_id']);
Sys_options::create(['op_key'=>'op-doc-leave-form', 'group_key'=>'op-doc-forms', 'title'=>'Leave Form']);
Sys_options::create(['op_key'=>'doc_trans_leave_pending', 'group_key'=>'op-trans-op-doc-leave', 'title'=>'Pending']);
Sys_options::create(['op_key'=>'doc_trans_leave_approved', 'group_key'=>'op-trans-op-doc-leave', 'title'=>'Approved']);
Sys_options::create(['op_key'=>'doc_trans_leave_rejected', 'group_key'=>'op-trans-op-doc-leave', 'title'=>'Rejected']);
```

### Step 2 — Add Form Schema to `Form.vue`
```js
// panel/resources/js/components/coalparts/Form.vue — add to data().forms
'op-doc-leave-form': {
  showRemoveButton: false,
  oncreated: () => {},
  fields: [
    {
      type: 'sub', name: 'sub_1', label: 'Leave Details', subs: [
        { type: 'text', name: 'title', label: 'Subject', col:6, required:true, oninput: e=>this.submitDynamicChanges(e.target) },
        { type: 'select', name: 'leave_type', label: 'Type', col:3, required:true,
          options: [{text:'Annual',value:'Annual'},{text:'Sick',value:'Sick'},{text:'Unpaid',value:'Unpaid'}],
          oninput: e=>this.submitDynamicChanges(e.target) },
        { type: 'text', name: 'days', label: 'Days', col:3, isMasked:true, mask:'money', oninput: e=>this.submitDynamicChanges(e.target) },
      ]
    },
    {
      type: 'sub', name: 'sub_2', label: 'Dates', subs: [
        { type: 'text', name: 'starting_at', label: 'From', col:4, isDate:true, required:true, oninput: e=>this.submitDynamicChanges(e.target) },
        { type: 'text', name: 'ending_at', label: 'To', col:4, isDate:true, required:true, oninput: e=>this.submitDynamicChanges(e.target) },
        { type: 'text', name: 'target_type', hidden:true, defaultValue: 'CATES', oninput: e=>this.submitDynamicChanges(e.target) }, // tenant-required
      ]
    },
    {
      type: 'textarea', name: 'desc', label: 'Notes', col:12, oninput: e=>this.submitDynamicChanges(e.target)
    },
    {
      type: 'multiple', name: 'docs', label: 'Attachments', group_key:'leave_docs', subs: [
        { type: 'file', name: 'leave_file', accept:'.pdf,.jpg,.png', col:12, label:'File', oninput: e=>this.submitDynamicChanges(e.target) }
      ]
    }
  ]
}
```
> **Key rule:** Any field named `starting_at`/`ending_at`/`title` with `isDate` or `main_*` prefix convention — but most fields go to EAV, not `documents` columns. Only `target_type` is mandatory for tenant (`grp_code`) routing if you use multi-tenant. Include it hidden if needed.

### Step 3 — Create Pages (copy RForm template)
```bash
mkdir -p panel/resources/js/pages/coalsystem/Leave
# Copy these as templates:
#   RForm.vue → Leave/LForm.vue  (edit form + timeline)
#   Request/RList.vue → Leave/LList.vue (PickleTable list)
```
Minimal `LForm.vue`:
```vue
<script>
import Plib from '@/lib/pickle'; import { useRoute } from 'vue-router'
import { useNavigationStore } from '@/stores/navigation'; import { useFormDataStore } from '@/stores/formdata';
import Form from '@/components/coalparts/Form.vue';
export default {
  breadcrumbs:{ list:[{title:'Leaves',path:'/coalpanel/leave'}], title:'Leave Form' },
  components:{ Form },
  setup(){ return { useNavigationStore, useFormDataStore, Plib, useRoute } },
  data(){ return { loadForm:false, plib:new Plib(), navigationStore:useNavigationStore(), formDataStore:useFormDataStore(), id:useRoute().params.id } },
  mounted(){
    const check = async ()=> this.id ? await this.plib.request({url:'/api/v1/document/'+this.id,method:'GET'}) : {success:false};
    check().then(r=>{ this.formDataStore.setData(r?.data?.formFormat); this.formDataStore.rawData=r?.data||{}; this.loadForm=true; this.navigationStore.toggle(false); });
    this.navigationStore.toggle(true);
  },
  methods:{
    async submitForm(formData){
      formData.typeKey='op-doc-leave';
      const chk=this.plib.checkForm('.form-item'); if(!chk.valid){ this.plib.toast(Swal,'info','Fill required fields'); return }
      const env=new FormData(); env.append('data',JSON.stringify(formData));
      for(let k in formData.files){ const f=formData.files[k]; env.append(k, f.reference?JSON.stringify(f.reference):f.file); }
      const rsp=await this.plib.request({url:'/api/v1/document'+(this.id?'/'+this.id:''),method:this.id?'PUT':'POST'},null,env);
      this.plib.toast(Swal,rsp.success?'success':'error',rsp.msg||'Done',()=>this.$router.push({name:'LList'}));
    }
  }
}
</script>
<template><Form v-if="loadForm" formtypes="op-doc-leave-form" savebtntitle="Submit" :savecallback="submitForm" /></template>
```

Minimal `LList.vue` — copy `Request/RList.vue` and change filters:
```js
// inside LList.vue — PickleTable config
initialFilter: [
  { key:'form-type', type:'=', value:'op-doc-leave-form' },
  { key:'type', type:'=', value:'op-doc-leave' }
]
```

### Step 4 — Permissions
```php
// Seed new permission codes
SysPermissionCatalog::create(['code'=>'per-09-01', 'title'=>'Leaves View', 'group'=>'leaves']);
SysPermissionCatalog::create(['code'=>'per-09-02', 'title'=>'Leaves Manage', 'group'=>'leaves']);
// Attach to roles
$role = SysRoleTemplate::where('op_key','op-pert-admin')->first();
$role->permissions = array_merge($role->permissions, ['per-09-01','per-09-02']); $role->save();

// Wire to helper: panel/app/Helpers/PermissionHelpers.php — add to docPermCheck map:
'op-doc-leave' => ['read'=>'per-09-01', 'edit'=>'per-09-02', 'status'=>'per-09-02']
```

Frontend gate in `Sidebar.vue`:
```vue
<MenuItem v-if="authStore.permissions.includes('per-09-01')" title="Leaves" to="/coalpanel/leave" />
```

### Step 5 — Optional Status Machine & Notifications
- If you want approve/reject, call `POST /v1/trans/set-status` with `op_key: doc_trans_leave_approved` (same as offer status flow in `DocumentController::setStatus`)
- For notifications, add `notif-04` type in `sys_notification_types` + assign users in `NSettings.vue`, then `EmailServiceProvider::send*` on transitions (copy `sendOfferStatus` pattern)

### Step 6 — Routes + Menu
```js
// resources/js/router/index.js
{ path:'/coalpanel/leave', name:'LList', component:()=>import('@/pages/coalsystem/Leave/LList.vue') },
{ path:'/coalpanel/leave/form/:id?', name:'LForm', component:()=>import('@/pages/coalsystem/Leave/LForm.vue') }
```
Build: `npm run build` (or `dev`). Backend needs no route changes — `ANY /v1/document` already handles any `typeKey`.

---

## 4. Creating a Completely Different App (Full Clone)

For a totally new domain (e.g. Real Estate), you **fork `panel/`**:

1. **Rename & retheme:**
   - `APP_NAME` in `.env`, `coalapp.blade.php` title, `CoalPanel.vue` layout, `coaltheme/` assets → your theme
   - `SYS_CODE` logic in `public/index.php` → your tenant logic (or remove multi-tenant, hardcode one)
   - `Form.vue` — delete all 5 `op-doc-*` schemas, add yours (see step 2 above)
   - `Sidebar.vue` + `Header.vue` — replace menu/brand

2. **Wipe seed data:**
   ```bash
   php artisan migrate:fresh
   php artisan db:seed --class=SysRoleTemplateSeeder  # keep role infra
   php artisan db:seed --class=UserSeeder            # your admins
   php artisan db:seed --class=SysSeeder             # re-seed with your sys_options (edit SysSeeder.php or write new seeder)
   ```

3. **Clean dead code:**
   - Delete `resources/js/pages/coalsystem/Request|Offer|Client` + `components/coalparts/RSummary|OfferSummary` etc if not needed
   - Delete `ReportServiceProvider::dashboardInfo` coal queries, rewrite for your entities
   - Delete `Currencies/TCMB.php`, `FlatForm` demo if irrelevant
   - Keep `DocumentServiceProvider`, `PersonsServiceProvider`, `PermissionService`, `pickle.js` — they are generic

4. **Database stays identical** — no migrations for new fields. Only `sys_options` rows change.

### What NOT to do
- ❌ Do NOT add new columns to `documents`/`persons` for business fields — use EAV or you'll break the engine
- ❌ Do NOT bypass `registerContent` / `setPerson` — they handle transactions, logs, file encryption, permissions
- ❌ Do NOT add FK constraints to `sys_con_entities.conn_id` — EAV is polymorphic
- ❌ Do NOT write raw `documents` inserts without `Transactions` + `UserLog` — you lose audit trail & status aggregation

---

## 5. Field Type Cookbook — Pick What You Need

| Need | Use | Snippet |
|------|-----|---------|
| Text input | `type:'text'` | `{name:'title', label:'Title', required:true}` |
| Long text | `type:'textarea'` | `{name:'desc', col:12}` |
| Number (money) | `type:'text' + isMasked` | `{name:'amount', mask:'money', hasIcon:true, icon:'TL'}` |
| Date | `isDate:true` | `{name:'contract_start_date', isDate:true, required:true}` |
| Dropdown | `select` | `{type:'select', options:[{text:'A',value:'A'}]}` |
| Async dropdown | `setOptions` | `{setOptions: async()=> (await store.fetch()).map(x=>({text:x.name,value:x.id}))}` |
| Toggle | `switch` | `{type:'switch', name:'her_ikisi', desc:'Visible both systems'}` |
| Repeating rows | `multiple + group_key` | `{type:'multiple', group_key:'my_group', subs:[{name:'field1'},...]}` |
| File upload | `file` | `{type:'file', accept:'.pdf,.jpg', name:'my_file'}` |
| Permission tree | `tree` | `{type:'tree', name:'permissions', list: permissionStore.list, oncheck: e=>...}` |
| Conditional visibility | `order_radius oninput` | Hide/show via `document.querySelector("[data-tag*='group_key']")?.parentNode.hidden = true` + `getClientRects` skips hidden validation |

---

## 6. Quick Start Checklist For Any New Doc App

- [ ] `sys_options`: doc type + form type + status keys + permissions
- [ ] `Form.vue`: add `forms['op-doc-{new}-form']` schema (copy a similar one)
- [ ] Pages: `{New}/LList.vue` (table) + `{New}/LForm.vue` (form), copy `RForm/RList`
- [ ] Router: add 2 routes in `router/index.js`
- [ ] Sidebar: add menu entry gated by `per-XX`
- [ ] Permissions: `PermissionHelpers::docPermCheck` map + `sys_permission_catalogs`
- [ ] (Optional) Dashboard: extend `ReportServiceProvider` or add custom cards
- [ ] (Optional) Notifications: `sys_notification_types` + `EmailServiceProvider.send*`
- [ ] `npm run build` + test `POST /v1/document` with `typeKey=op-doc-{new}`

**Total new backend code for a simple doc app: ~0 lines. Total frontend: ~2 new Vue pages + ~30-line Form.vue schema + 2 router lines.**

