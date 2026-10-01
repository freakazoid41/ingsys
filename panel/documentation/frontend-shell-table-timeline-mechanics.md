# Frontend Shell, Table & Timeline Mechanics

## 1. Overview

All list pages share one PickleTable pattern over `POST /v1/table/{model}` plus a common shell (navigation store, Sidebar, Header, AppFab, pickle.js resilience, Form dynamics) and two log-timeline components.

**Key files:**
- `app/Http/Controllers/SystemController.php:19-54` — `table($model)`
- `app/Models/Documents.php:48`, `UserLog.php:41`, `User.php:81`, `Document_files.php:61`, `NotificationLog.php:35`
- `resources/js/lib/pickle.js` — `request:42`, `transaction:559`, `openTab:615`, `401:105-154`
- `resources/js/lib/treeModal.js`, `password.js`, `tooltip.js`, `offerStatus.js`
- `resources/js/stores/navigation.js`, `auth.js`, `permissiondata.js`, `formdata.js`, `events.js`
- `resources/js/components/coalparts/Sidebar.vue`, `Header.vue`, `AppFab.vue`, `Form.vue`, `RequestLogTimeline.vue`
- `resources/js/components/Offer/OfferLogTimeline.vue`, `OfferTable.vue`
- `resources/js/pages/coalsystem/*/*List.vue`, `Dashboard.vue`, `Logs/LList.vue`
- `resources/js/router/index.js`, `layouts/CoalPanel.vue`, `app.js:37-55`

## 2. Table endpoint contract

`POST /v1/table/{model}` (`api.php:40`, `auth:sanctum+CheckPermissionVersion`). `SystemController::table`:

- Query-param shim: `page→scale.page, size→scale.limit, sort→order`.
- If no `tableReq`, uses whole request body. PickleTable sends `FormData{tableReq:JSON{scale,filter,order}}`.
- Gate only `user` (`per-04 && per-04-01`) and `document_files` (`per-07 && per-07-01`), else open. Returns `json(['message'=>Unauthorized],403)` body (not HTTP status — check body).
- `ucfirst($model)` + `Userlog→UserLog`, `Notificationlog→NotificationLog`, then `Model::tableList(decoded)`.

`tableList($obj)` standard: in `{scale:{page,limit}, filter:[{key,type,value}], order:{key,style}}`, out `{data[],pageCount,totalCount,filteredCount,last_page}`. Pagination `OFFSET=(page*limit)-limit`. Ordering `order by col style` default `id desc`. Filters: `free|all` OR over columns, `doc_qnid` via `description->after->document->qnid`, default `key/type(like|=)` upper-trim.

`Documents::tableList:53-106`: extracts `formType` from `filter[type]` + `with-cancelled` opt-in; null → empty. Columns `main_id,id(qnid),type,created_at,status(latest op-trans-<formType> as op_key**title**note),document_status,main_attr,addional`. Default `status='1'`; offers + `with-cancelled` → `IN ('0','1')`. Reseller scoping via `session currentStatus.clientQnidList`.

## 3. PickleTable pattern

Canonical `LList.vue:241-272`:

```js
new PickleTable({container:#div_table, headers:[{title,key,order,width,type,colAlign,headAlign,search,columnClick,columnFormatter}], pageLimit:10, height:70vh, type:ajax, columnSearch:true, paginationType:number|scroll, ajax:{url:/v1/table/userlog,data:{}}, initialFilter:[], rowFormatter:(elm,data)=>data})
```

- Headers: `LList.vue:117-238` 8 cols, `#` magnifier `columnClick` → `JSON.parse(description)` → Swal 1500px `jsonToDetails()` recursive `<details>` + copy.
- `Form.vue:2851-2893` client picker: `height:30vh`, `rowClick→clickEvent`, `rowFormatter` expands `main_attr` JSON to `data[Key]=Value`.
- Runtime: `setFilter([{key:all,value:search}])`, `setFilter([])` reset, `plib.openTab('POST','/v1/export/userlogs',table.currentFilter)` for Excel, `getData/changePage/updateRow/deleteRow`.
- Mobile: card fallback (`formatRequestCard/formatOfferCard`), hide `thead`.
- Toolbar: `mainSearch→all`, `resetSearch`, `toggleExpired→showExpired`, `DList` status selects, `RList/OList` export via `openTab`.

## 4. AppFab (`AppFab.vue:252-403`)

Props `fabType,btntype,callback,rejectcallback,acceptcallback,savebtntitle,cancelcallback`. `status:await|loading` lock + 500ms reset.

- `bar:367-374` fixed bottom center: `[İptal v-if cancel][savebtntitle][Bütün Onayla v-if type==options][Bütün Reddet v-if type==options]`.
- `leftIcon:375-402` floating wheel: `saveBtn` single icon + spinner, `options` 3-dots → `fab-action-1 execute, -2 accept, -3 reject`.
- Usage `Form.vue:2922`: `v-if="admin||request-form"`, callbacks `formCallback/reject/accept/cancel→router.go(-1)`.

## 5. pickle.js resilience + auth heartbeat

`request:42-55` builds `fetch credentials:include, X-Requested-With, X-CSRF-TOKEN, Authorization:Bearer localStorage.token`. `DELETE` urlencoded, `PUT|POST` FormData.

- `permission_changed:105-139`: toast, `GET /getpermissions`, retry once, else `return false`.
- `force_logout:141-154`: `removeItem(token)`, Swal `reason`, `willClose: /=/`, return parsed.
- `transaction:559-605`: `login→auth/post, get→request/get, ask→query/post, add→request/post, update→request/put, delete→request/delete, upload→upload/post`; `url=/api/url/model/id?`.
- `openTab:615-632`: hidden `<form method action target>` + `<textarea name=JSON>` per key, submit+remove. Used for exports/PDF.

`auth.js:18-41`: `getPermissions→{permissions,currentStatus,typeKey,personId}`, `startHeartbeat 30s`, `stopHeartbeat`. Boot `app.js:39-53`: `await getPermissions`, `fetchRoleTemplates/Items`, reseller `!canProceed→/client/form/qnid`, `startHeartbeat` before mount. `Sidebar.vue:20` re-fetches on mount.

`navigation.js:9-80`: persisted `currentTitle,breadcrumps,breadbuttons,routeParams,lastUpdated` in `sessionStorage:nav.state`; `getNotifications→GET /v1/notifications`; `clearNotifications` resets blink+5 arrays. `Header.vue:27-35` watches notifications deep → `$forceUpdate`; `totalNotificationCount` sums 5 keys + rejectedFiles; `showNotifications:115-251` maps to Swal → `UForm/CForm/OForm`.

`Sidebar.vue:36-121`: `userName/roleLabel/initials`, `markActiveRoute` URL match, `bindAccordion`, Turkish-lowercase search filter; menu gated `permissions.includes(per-05-01,08-01,07-01,06-01,04-01...)`.

`router/index.js`: `closeAsideDrawer` on before/afterEach. `CoalPanel.vue`: preloader `v-show=navigation.active`.

## 6. Timeline diff

Shared: `FIELD_LABELS` 27 keys, `labelFor=base.split('**')[0]`, `formatValue` (request_type 1→Rodevans, offer_type split `**`), `diffEntities(before,after)` union keys skip `qnid,rev_date[,request_id,cliid]`, trim-compare → `{key,label,before,after}`.

- `RequestLogTimeline.vue:64-121` prop `requestQnid`: `POST table/userlog {scale:1/50, filter:doc_qnid}`, `desc=JSON.parse(description)`, `before/after=Object.values(formFormat[op-doc-request-form])[0].entities`, `isCreate=every !before`. Icon `log-tender-update:pencil, create:plus, lock:lock`. Template: green dot if create, `Talep Oluşturuldu|title·name·created_at`, `N değişiklik` toggle, grid `label|before red|→|after green`.
- `OfferLogTimeline.vue:71-161` prop `documentQnid`, limit 100, 3 branches: `desc&&!before→status (STATUS_LABEL approved/rejected/revision/review/revised/sended/draft + note)`, `file_id!=null→file (desc.desc)`, else `edit` via `op-doc-offer-form`. Dot amber/indigo/green.

## 7. Form dynamics (`Form.vue:1912-2832`)

`submitDynamicChanges(el,isDatalist,key)`: datalist→`formData[key]`; else `tag=dataset.tag, name=split('*-*')[0], rowId`, init `dynamicF[tag**rowId]`, money unmask, `file→files[tag**dynamicFile**id**rowId]` immediate `uploadTempFile POST /temp-upload`, checkbox `1/0`, date `dd/mm/yyyy→yyyy-mm-dd`. `formCallback:1874-1911` waits pending uploads, retries failed, then `savecallback`. `buildDynamicFForm` renders `sub|multiple|section|yesno|textarea|button|tree|select|switch` via `createInput/createSelect/addElements`, flatpickr TR, VMasker money/phone, password toggle. `order_radius (Dahil/Hariç/Sadece)` hides `fuel_price/calory/coal_specs`, `target_type ÇATES` shows `request_type`. Hidden-field validation skipped via `getClientRects`. `createpss→password.js:generatePassword()→confirm Swal`.

Shared libs: `formatMoney`, `fileInfo(40MB jpg/jpeg/png/pdf)`, `compressImage`, `setLoader`, `crypFunc base64`; `password.js PASSWORD_PATTERN(≥8 lower+upper+digit+[=!-@._*]) + crypto.getRandomValues`; `tooltip.js attach/hide` body-fixed (PickleTable overflow workaround).

## 8. Gotchas

1. Table 403 is body, not HTTP — check `message==Unauthorized`.
2. `Documents.tableList` needs `type` filter or returns empty.
3. `permission_changed` retry is once — backend now refreshes transparently, rare.
4. Timeline `before` missing = status/file, not edit — keep convention.
5. `openTab` uses `textarea` — large filters okay, files not.
