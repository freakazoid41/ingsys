# Dashboard & Reporting Mechanics

## 1. Overview

The dashboard is a dual-mode reporting UI backed by `ReportServiceProvider::dashboardInfo()`. Admins see system-wide stats, charts and calendars; resellers (`op-pert-reseller`) see their own offer stats. All endpoints live under `auth:sanctum + CheckPermissionVersion`.

**Key files:**
- `app/Providers/ReportServiceProvider.php` — `dashboardInfo`, `dashboardTopInfo`, `dashboardMonthlyOffers`, `dashboardMonthlyDistribution`, `dashboardImportantInfo`, `getOffers`, `getAwaitingUserRequests`
- `app/Http/Controllers/ReportController.php:16-18` — `dashboard($type,$period=null)` thin JSON wrapper
- `routes/api.php:65` — `ANY /v1/dashboard/{type}/{period?}`
- `resources/js/pages/coalsystem/Dashboard.vue` — Admin vs Client switch
- `resources/js/components/Dashboard/Admin.vue`, `Client.vue`, `Default.vue` (legacy)
- `resources/js/components/Dashboard/Admin/*.vue`, `Client/*.vue`
- `resources/js/stores/events.js` — dead `getOngoingTasks/monthlyEvents` callers
- `resources/js/lib/offerStatus.js` — `WITH_CANCELLED_FILTER`, `offerStatus()`, `isOfferCancelled()`

## 2. Backend dispatch

`ReportController::dashboard($type,$period)` → `ReportServiceProvider::dashboardInfo($type,$addional)` (`ReportServiceProvider.php:93-109`):

- `topstats` → `dashboardTopInfo()` (ignores period)
- `monthlyoffers` → `dashboardMonthlyOffers($addional ?? true)`
- `monthlydistribution` → `dashboardMonthlyDistribution()` (ignores period)
- `importantinfo` → `dashboardImportantInfo()` (ignores period)
- default → `abort(404, Unknown dashboard type)`

Only `monthlyoffers` consumes `{period}`. Strict `=== true` check (`ReportServiceProvider.php:251`): no period (`null ?? true`) = monthly-filtered; any string (e.g. `client`) = all-time.

## 3. Endpoint details

### 3.1 `topstats` — `dashboardTopInfo():182-241`

```json
{ "totalRequests": int, "totalOffers": int, "approvedOffers": int, "awaitingOffers": int, "todaysOffers": int, "allClients": int }
```

- requests: `type=op-doc-request, form-type=op-doc-request-form, monthly=-MM-`
- offers: same + `type=op-doc-offer, with-cancelled=1`, monthly (cancelled included as historical total)
- approved: `document_status!=0 && status contains doc_trans_offer_approved`
- todays: `created_at contains Y-m-d`
- awaiting: `type=op-doc-offer, status-null like %%` (all-time, no monthly filter)
- clients: `type=op-doc-client` (all-time)

### 3.2 `monthlyoffers` — `dashboardMonthlyOffers($isMonthly=true):243-365`

```json
[{ "label": string, "value": int, "key": "doc_trans_offer_sended|review|revision|revised|approved|rejected|cancelled", "color": "#hex" }]
```

- Base: `type=op-doc-offer, form-type=op-doc-offer-form, with-cancelled=1`
- Groups seeded from `Sys_options where op_key like doc_trans_offer_%`; `doc_trans_offer_draft` skipped, merged into `sended`
- `colorMap:264-271`: sended `#0d6efd`, review `#ffc107`, revision `#ff9800`, revised `#17a2b8`, approved `#198754`, rejected `#e74c3c`, cancelled `#6c757d`
- `cancelled` synthesised from `document_status==0` (overrides transaction), not from `sys_options`
- Empty/draft status → `sended` fallback

Admin calls `GET /v1/dashboard/monthlyoffers` (monthly). Client calls `GET /v1/dashboard/monthlyoffers/client` (all-time) from `ClientStats.vue:70`.

### 3.3 `monthlydistribution` — `dashboardMonthlyDistribution():116-180`

Associative object keyed by santral, not array:

```json
{ "Çates": {"name":"Çates","totalRequests":int,"totalOffers":int}, "Yatağan": {...}, "Her İkisi": {...}, "-": {...} }
```

- Both requests + offers monthly, `with-cancelled=1` for offers
- Key via `mb_stripos(main_attr,'Çates'|'Yatağan'|'Her İkisi')`, default `-`
- Docblock claims `approvedOffers` but code only sets `totalRequests/totalOffers`
- Frontend `DashboardDistribution.vue:85-102` normalises object → array

### 3.4 `importantinfo` — `dashboardImportantInfo():367-477`

```json
[{ "text": "Teklif|Talep #docNo — title — Sözleşme Bitişi|...", "date": "Y-m-d", "type": "contract_end_date|...", "event": "op-doc-request|op-doc-offer", "doc_id": int }]
```

Sorted date asc nulls last. 7 queries: requests ×4 attrs + offers ×3 attrs, filter `attr value /MM/`. `main_attr` parsed as JSON then `unserialize` fallback; extracts `title` from `Key==title`, `docNo` from `req_no|offer_no`.

## 4. Notification helpers (same provider)

- `getAdminNotifications($notifKey):17` — gated by `getNotificationUsers($notifKey, session person_id)`; `notif-00`→`getAwaitingUserRequests` (`User::tableList user_status=-1`), `notif-01`→`getAwaitingClientFiles`, `notif-02`→`getOffers()`, `notif-03`→`getOffers('doc_trans_offer_revised')`
- `getUserNotifications($notifKey):51` — only `offer-revision-request` + reseller, calls `getOffers('doc_trans_offer_revision')`
- `getOffers($type='null'):81` — `Documents::tableList(transactions=$type, type=op-doc-offer)`

## 5. Frontend switch

`Dashboard.vue:71-72`:

```html
<Admin v-if="authStore.typeKey !== 'op-pert-reseller'" />
<Client v-if="authStore.typeKey === 'op-pert-reseller'" />
```

`typeKey` from `GET /v1/getpermissions`. `Default.vue` imported but never rendered (legacy PickleTable demo).

Admin (`Admin.vue`): `DashboardHeader` (greeting + notifications) + `DashboardStats` (`GET topstats`, `approvedOffers` fetched but not displayed, clicks → RList/OList/CList) + `Distribution` + `QuickActions` (Yeni Talep, Kullanıcı Ekle) + `ProcessChart` (doughnut, `GET monthlyoffers`) + `Notifications` (uses `/v1/notifications`, not dashboard API) + `Calendar` (`GET importantinfo`, click → OForm/RequestForm) + `RequestTables` (two PickleTables on `/v1/table/documents`, rodevans split by `sysCode==='CATES'`, status via `offerStatus()`).

Client (`Client.vue`): `ClientHeader` + `ClientStats` (`GET monthlyoffers/client`, maps approved/review/revision/rejected only) + `ClientQuickOps` + `ClientInfoSection` (notifications + request PickleTable) + `ClientOfferTable` (`with-cancelled` filter, eye → RequestForm).

## 6. Dead code

- `stores/events.js:14-75` calls `GET /v1/dashboard/getOngoingTasks` and `/monthlyEvents/YYYY-MM` — neither exists in `dashboardInfo` switch → 404. No imports in Dashboard components.
- `Default.vue:1-423` — legacy hero + offer PickleTable, not rendered.
- `offerStatus.js:11-63` shared: `WITH_CANCELLED_FILTER={key:'with-cancelled',value:'1'}`, `offerStatus(row)` terminal-cancelled override.

## 7. Gotchas

1. Period is sentinel, not date — any non-true string disables monthly filter.
2. `topstats.awaitingOffers/allClients` are all-time while siblings are monthly — don't compare directly.
3. `monthlydistribution` returns object, not array — frontend must normalise.
4. `cancelled` is `document_status==0`, never a transaction — chart slice is synthesised.
5. `approvedOffers` in topstats response unused in Admin UI (fetched but hidden).
6. All dashboard routes require auth — no public caching; add cache if polling frequently.
