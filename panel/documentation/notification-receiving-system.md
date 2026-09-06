# Notification Receiving System Analysis

## 1. System Overview

The notification system is a multi-layer architecture that receives notifications from the backend and displays them to the user in the Header component. It integrates two data sources and processes multiple notification types.

### Key Components
- **Frontend**: Header.vue (notification display), Navigation Store (data management)
- **Backend**: SystemController.php (API endpoint), ReportServiceProvider.php (notification logic)
- **Data Stores**: navigationStore (Pinia), authStore (Pinia)

---

## 2. Frontend Architecture

### 2.1 Header Component (`Header.vue`)

**Location**: `/resources/js/components/coalparts/Header.vue`

#### Data Flow
```
mounted() → loadNotifications()
    ↓
    ├─ Fetch from authStore.currentStatus.rejectedFiles
    │   └─ Map to notification objects with onclick handlers
    │
    └─ Call navigationStore.getNotifications()
        └─ Async fetch from /api/v1/notifications
```

#### Key Methods

**`loadNotifications()`** (lines 46-64)
```javascript
loadNotifications() {
  // 1. Create notifications from rejected files in authStore
  this.notifications = (this.authStore.currentStatus?.rejectedFiles || []).map((fl) => {
    return {
      title: 'Reddedilen Dosya',
      message: `${fl.title} reddedildi.`,
      time: `${fl.rejected_by} tarafından`,
      type: 'clientFile',
      onclick: () => {
        this.$router.push({ name: 'CForm', params: { id: fl.cli_id } });
      },
    };
  });
  
  // 2. Fetch notifications from navigationStore (API call)
  this.navigationStore.getNotifications();
}
```

**Issues & Notes:**
- `this.notifications` array is populated with rejected files
- Comment shows intentional merge disabled: `//this.notifications = [...this.notifications, ...this.navigationStore.notifications || []];`
- **Problem**: Notifications are loaded but only rejected files are shown; navigationStore notifications are fetched but not merged into display

**`showNotifications()`** (lines 72-145)
```javascript
showNotifications() {
  let list = [];
  
  // Iterate through addNotifications (from navigationStore)
  for(let key in this.addNotifications){
    switch (key) {
      case 'awaitingUsers':        // New user registrations
      case 'clientChanges':         // Client file updates
      case 'offerRevisionRequests': // Offer revision requests
      case 'newOffer':              // New offers from clients
      case 'offerChanges':          // Offer updates
        // Map each category to notification objects
        break;
    }
  }
  
  // Add rejected files from this.notifications
  list = [...list,...this.notifications];
  
  // Display in SweetAlert modal
  Swal.fire({ ... })
}
```

**Notification Types Handled:**
1. **awaitingUsers** - New user registrations
   - Title: "Yeni Kullanıcı Kaydı"
   - Routes to: UForm with user ID
   - From: `navigationStore.notifications.awaitingUsers[]`

2. **clientChanges** - Client file updates
   - Title: "Müşteri Dosya Bilgisi Girdi ({title})"
   - Routes to: CForm with client ID
   - From: `navigationStore.notifications.clientChanges[]`

3. **newOffer** - New offers from clients
   - Title: "Yeni Teklif"
   - Message: "{client_title} müşterisi yeni bir teklif girdi."
   - Routes to: OForm with offer ID
   - From: `navigationStore.notifications.newOffer[]`

4. **offerRevisionRequests** - Offer revision requests
   - Title: "Teklif Revizyon Talebi"
   - Message: "Revizyon talep edildi."
   - Routes to: OForm with offer ID
   - From: `navigationStore.notifications.offerRevisionRequests[]`

5. **offerChanges** - Offer updates
   - Title: "Teklif Güncellemesi"
   - Message: "{client_title} müşterisi teklifini güncelledi."
   - Routes to: OForm with offer ID
   - From: `navigationStore.notifications.offerChanges[]`

6. **clientFile** (rejectedFiles) - Rejected files
   - Title: "Reddedilen Dosya"
   - Message: "{file_title} reddedildi."
   - Routes to: CForm with client ID
   - From: `authStore.currentStatus.rejectedFiles[]`

#### Computed Properties

**`addNotifications`** (line 37)
```javascript
addNotifications() {
  return this.navigationStore?.notifications || {};
}
```
- Returns notification object from navigationStore
- Default: empty object if not available

**`headerBgStyle`** (line 28)
- Dynamic background based on system code (CATES, YATAGAN)

#### UI Elements

**Notification Bell Icon** (line 242)
```vue
<button @click="showNotifications" class="btn btn-icon btn-header position-relative">
  <i class="ki-outline ki-notification-bing fs-1"></i>
  <span :hidden="addNotifications?.blink !== 1"
    class="bullet bullet-dot bg-success h-6px w-6px animation-blink"></span>
</button>
```

- Bell icon triggers `showNotifications()` modal
- Animated red dot appears when `addNotifications.blink === 1`

---

### 2.2 Navigation Store (`stores/navigation.js`)

**Location**: `/resources/js/stores/navigation.js`

#### State
```javascript
state: () => ({
  active: false,
  currentTitle: '',
  breadcrumps: [],
  breadbuttons: [],
  routeParams: {},
  lastUpdated: 0,
  notifications: [],      // ← Notification array
  sys_code: '...'
})
```

#### Actions

**`getNotifications()`** (lines 58-65)
```javascript
async getNotifications(){
  const rsp = await (new Plib).request({
    url: '/api/v1/notifications',
    method: 'GET',
  }, null);
  
  this.notifications = rsp;
}
```

- Makes HTTP GET to `/api/v1/notifications`
- Assigns response directly to `this.notifications`
- No error handling visible
- Plib library handles HTTP request (likely Axios wrapper)

---

### 2.3 Auth Store (`stores/auth.js`)

**Location**: `/resources/js/stores/auth.js`

#### State
```javascript
state: () => ({
  data: {},
  permissions: null,
  currentStatus: null,    // ← Contains rejectedFiles
  typeKey: null,
  personId: null,
  userName: null,
})
```

#### Actions

**`getPermissions()`** (lines 17-25)
```javascript
async getPermissions(){
  const rsp = await (new Plib).request({
    url: '/api/v1/getpermissions',
    method: 'GET',
  }, null);
  
  this.permissions = rsp.permissions;
  this.currentStatus = rsp.currentStatus;  // Includes rejectedFiles
  this.typeKey = rsp.typeKey;
  this.personId = rsp.personId;
  this.userName = rsp.userName ?? null;
}
```

- Called at app initialization (app.js line 38)
- `currentStatus` contains client account data including `rejectedFiles`

---

## 3. Backend Architecture

### 3.1 SystemController (`app/Http/Controllers/SystemController.php`)

**Location**: `/app/Http/Controllers/SystemController.php`

#### API Endpoint: `/api/v1/notifications`

**Method**: `getNotifications()` (lines 78-115) — **TEDARIK 7 + Read Tracking**
```php
public function getNotifications(){
  $response = ['blink' => 0, 'unreadTotal' => 0];
  $provider = new ReportServiceProvider();
  $limit = 10; // show only 10 unread per category
  // Each: $r = $provider->getAdminNotifications('tedarik-0X', $limit);
  // $response['category'] = $r['data']; // max 10 unread
  // $response['unreadTotal'] += $r['total']; // all unread count
  // if($r['total'] > 0) $response['blink'] = 1;
  return $response;
}
```

**Response Structure** (with read tracking):
```json
{
  "blink": 1,
  "unreadTotal": 8,
  "orderImported": [...],  // max 10 unread per category
  "orderSent": [...],
  "pendingFiles": [...],
  "fileApproved": [...],
  "fileRejected": [...],
  "orderApproved": [...],
  "orderRejected": [...]
}
```

#### API Endpoint: `/api/v1/notifications/read`
**Method**: `markNotificationRead()` — marks single notification as read
```php
POST /api/v1/notifications/read
{ "op_key": "tedarik-01", "target_qnid": "order-or-file-qnid" }
→ inserts into notification_reads table (user_id, op_key, target_qnid)
```

#### API Endpoint: `/api/v1/notifications/read-all`
**Method**: `markAllNotificationsRead()` — marks all current notifications as read
```php
POST /api/v1/notifications/read-all
→ fetches all unread for each category, inserts into notification_reads
```

**Notification Code Mappings (TEDARIK):**
- `tedarik-01`: Sipariş Sisteme Geldi (SAP Üzerinden) — `doc_trans_order_created` (SyncOrdersCommand, later cron)
- `tedarik-02`: Sipariş Onaya Gönderildi — `doc_trans_order_transfer_sent` (tedarikçi parçaladı/tümden)
- `tedarik-03`: İnceleme Bekleyen Dosyalar Mevcut — `doc_file_waiting` (order/item files)
- `tedarik-04`: Sipariş Dosyası Onaylandı — `doc_file_accepted`
- `tedarik-05`: Sipariş Dosyası Yeniden Talep Edildi — `doc_file_rejected`
- `tedarik-06`: Sipariş Kalite Onayı Verildi — `doc_trans_order_approved`
- `tedarik-07`: Sipariş Reddedildi — `doc_trans_order_rejected` (+ fallback `files_rejected`)

**Blink Indicator:**
- `blink: 1` = Show animated red dot on bell icon
- Set when ANY notification category is not empty

#### API Endpoint: `/api/v1/getpermissions`

**Related to**: Rejected files in `currentStatus`
- Populates `authStore.currentStatus.rejectedFiles`
- Contains rejected file data for clients/users

---

### 3.2 ReportServiceProvider (`app/Providers/ReportServiceProvider.php`)

**Location**: `/app/Providers/ReportServiceProvider.php`

#### Key Methods

**`getAdminNotifications($notifKey)`** (lines 18+) — **TEDARIK 7**
```php
public function getAdminNotifications($notifKey){
  $permittedUsers = (new PersonsServiceProvider())->getNotificationUsers($notifKey, session('person_id'));
  if(empty($permittedUsers)) return [];
  switch ($notifKey) {
    case 'tedarik-01': $data = $this->getTedarikOrders('doc_trans_order_created'); break;
    case 'tedarik-02': $data = $this->getTedarikOrders('doc_trans_order_transfer_sent'); break;
    case 'tedarik-03': $data = $this->getTedarikFilesByStatus('doc_file_waiting'); break;
    case 'tedarik-04': $data = $this->getTedarikFilesByStatus('doc_file_accepted'); break;
    case 'tedarik-05': $data = $this->getTedarikFilesByStatus('doc_file_rejected'); break;
    case 'tedarik-06': $data = $this->getTedarikOrders('doc_trans_order_approved'); break;
    case 'tedarik-07': $data = $this->getTedarikOrders('doc_trans_order_rejected'); break;
  }
  return $data;
}
public function getUserNotifications($notifKey){ // reseller-scoped mirror tedarik-04..07 via LIFNR
  if(session('type_key')!=='op-pert-reseller') return [];
  // same fetchers, Documents/Document_files tableList auto-fails-closed via clientQnidList→LIFNR
}
```

**Process:**
1. Validates user membership in notification group (`op-doc-user-notification-form` `entity_value LIKE %opKey%`)
2. Fetches TEDARIK data via `getTedarikOrders()` / `getTedarikFilesByStatus()` (scope via `Documents::tableList` / `Document_files::tableList` LIFNR)
3. Returns array; `SystemController` sets `blink=1` if any non-empty

#### Notification Data Fetchers (TEDARIK)

**`getTedarikOrders($statusKey)`**
- `Documents::tableList` with `transactions=$statusKey` + `type=op-doc-order` + `form-type=op-doc-order-form`
- Reseller scoping: `spec_code IN (lifnrs from clientQnidList)` `ReportServiceProvider.php:514` — fails closed if no client

**`getTedarikFilesByStatus($fileStatusKey)`**
- `Document_files::tableList` with `file_status=$fileStatusKey` (`doc_file_waiting/accepted/rejected`)
- Reseller scoping via `Document_files.php:152` LIFNR → same fails-closed

Legacy `getAwaitingUserRequests()` / `getAwaitingClientFiles()` / `getOffers()` kept for reference but no longer wired.

---

## 4. Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         APPLICATION INIT                         │
├─────────────────────────────────────────────────────────────────┤
│  app.js → authStore.getPermissions()                            │
│           ↓                                                       │
│           Calls: GET /api/v1/getpermissions                     │
│           Sets: currentStatus (includes rejectedFiles)          │
│           Sets: permissions, typeKey, personId                  │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│                    HEADER.VUE MOUNTED                             │
├─────────────────────────────────────────────────────────────────┤
│  mounted() → loadNotifications()                                │
│  ├─ Map authStore.currentStatus.rejectedFiles                  │
│  │   └─ → this.notifications array (clientFile type)           │
│  │                                                               │
│  └─ Call navigationStore.getNotifications()                    │
│      ↓                                                           │
│      Calls: GET /api/v1/notifications (SystemController)       │
│      ↓                                                           │
  │      Response:                                                   │
│      {                                                           │
│        blink: 1,                                                 │
│        orderImported: [...], // tedarik-01                      │
│        orderSent: [...],     // tedarik-02                      │
│        pendingFiles: [...],  // tedarik-03                      │
│        fileApproved: [...],  // tedarik-04                      │
│        fileRejected: [...],  // tedarik-05                      │
│        orderApproved: [...], // tedarik-06                      │
│        orderRejected: [...], // tedarik-07                      │
│      }                                                           │
│      └─ → navigationStore.notifications (stored in Pinia)      │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│              USER CLICKS NOTIFICATION BELL                       │
├─────────────────────────────────────────────────────────────────┤
│  @click="showNotifications"                                     │
│  ↓                                                               │
│  Builds list from addNotifications (navigationStore)            │
│  ├─ Maps each category (awaitingUsers, clientChanges, etc.)    │
│  ├─ Adds formatted notification objects                        │
│  ├─ Concatenates with this.notifications (rejected files)      │
│  │                                                               │
│  └─ Displays in SweetAlert modal with:                         │
│      ├─ Title, Message, Time for each notification             │
│      ├─ Click handlers with router.push() to details          │
│      └─ Animated blink on bell icon when unread               │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. Notification Categories & Data Structure — TEDARIK 7

### tedarik-01 — Sipariş Sisteme Geldi (SAP Üzerinden)
```javascript
{
  title: 'Sipariş Sisteme Geldi (SAP)',
  message: `${u.order_no} — ${u.ctitle} (${u.spec_code})`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik01',
  onclick: () => router.push({ name: isTedarik ? 'TedarikOrderForm' : 'OrderForm', params: { id: u.qnid } })
}
```
**Backend**: `ReportServiceProvider.getTedarikOrders('doc_trans_order_created')` — `Documents::tableList` `op-doc-order` `created`; later wired to `SyncOrdersCommand` dispatch `tedarikOrderImported`

### tedarik-02 — Sipariş Onaya Gönderildi
```javascript
{
  title: 'Sipariş Onaya Gönderildi',
  message: `${u.order_no} onaya gönderildi (${u.transfer_mode})`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik02',
  onclick: () => router.push({ name: 'DForm', params: { id: u.file_qnid } }) // or OrderForm
}
```
**Backend**: `getTedarikOrders('doc_trans_order_transfer_sent')` — `processOrderTransfer()` `DocumentServiceProvider.php:754`

### tedarik-03 — İnceleme Bekleyen Dosyalar Mevcut
```javascript
{
  title: 'İnceleme Bekleyen Dosyalar Mevcut',
  message: `${u.file_type} — ${u.group_key}`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik03',
  onclick: () => router.push({ name: 'DForm', params: { id: u.qnid } })
}
```
**Backend**: `getTedarikFilesByStatus('doc_file_waiting')` — `Document_files::tableList` `file_status=waiting` `Document_files.php:296`

### tedarik-04 — Sipariş Dosyası Onaylandı
```javascript
{
  title: 'Sipariş Dosyası Onaylandı',
  message: `${u.file_type} onaylandı — ${u.group_key}`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik04',
  onclick: () => router.push({ name: 'DForm', params: { id: u.qnid } })
}
```
**Backend**: `getTedarikFilesByStatus('doc_file_accepted')` — `documentFileStatus(doc_file_accepted)` `DocumentServiceProvider.php:1178`

### tedarik-05 — Sipariş Dosyası Yeniden Talep Edildi
```javascript
{
  title: 'Sipariş Dosyası Yeniden Talep Edildi',
  message: `${u.file_type} reddedildi — ${u.note}`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik05',
  onclick: () => router.push({ name: isTedarik ? 'TedarikDForm' : 'DForm', params: { id: u.qnid } })
}
```
**Backend**: `getTedarikFilesByStatus('doc_file_rejected')` + `syncOrderStatusFromFiles()` auto `files_rejected` `tedarik-system-process.md:256`

### tedarik-06 — Sipariş Kalite Onayı Verildi
```javascript
{
  title: 'Sipariş Kalite Onayı Verildi',
  message: `${u.order_no} kalite onayı verildi`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik06',
  onclick: () => router.push({ name: 'OrderForm', params: { id: u.qnid } })
}
```
**Backend**: `getTedarikOrders('doc_trans_order_approved')` — `acceptAllOrderFiles()` `per-05-03 ONLY`

### tedarik-07 — Sipariş Reddedildi
```javascript
{
  title: 'Sipariş Reddedildi',
  message: `${u.order_no} reddedildi`,
  time: `Kayıt: ${u.created_at}`,
  type: 'tedarik07',
  onclick: () => router.push({ name: 'OrderForm', params: { id: u.qnid } })
}
```
**Backend**: `getTedarikOrders('doc_trans_order_rejected')` — `cancelOrder()` `per-05-04` + fallback `files_rejected`

### Legacy clientFile (rejectedFiles) — kept via `authStore.currentStatus.rejectedFiles` but now covered by tedarik-05

---

## 6. Current Issues & Observations

### Issue 1: Commented-Out Notification Merge
**Location**: Header.vue line 62
```javascript
//this.notifications = [...this.notifications, ...this.navigationStore.notifications || []];
```

**Impact**: 
- Rejected files are loaded but API notifications are only displayed when clicking the bell
- No merged view exists
- Rejected files in `this.notifications` are separate from `addNotifications`

**Status**: Intentional (based on comment structure)

### Issue 2: Navigation Store Notifications Not Reactive
**Location**: Header.vue computed property `addNotifications`
```javascript
addNotifications() {
  return this.navigationStore?.notifications || {};
}
```

**Issue**: 
- navigationStore is updated asynchronously in `getNotifications()`
- No reactivity hook/watcher to trigger re-render when `notifications` changes
- Possible race condition: bell icon may not show blink initially

**Recommendation**: Add watch or ensure component reactivity updates

### Issue 3: No Error Handling
**Locations**:
- navigation.js `getNotifications()` - No try/catch
- Header.vue `loadNotifications()` - No error states
- SystemController - No validation

**Risk**: Silent failures, notifications not loading without user awareness

### Issue 4: Permission Filtering Not Visible
**Backend**: `ReportServiceProvider.getNotificationUsers()` validates permissions
- Frontend has no knowledge of which notifications user is permitted to see
- All validation is server-side (good security-wise)

### Issue 5: Blink Indicator Timing
- Blink set when API responds with `blink: 1`
- No persistence or tracking of "read" status
- Bell keeps blinking until page refresh or new API call

---

## 7. Configuration & Routes

### API Routes
- `GET /api/v1/notifications` → SystemController.getNotifications()
- `GET /api/v1/getpermissions` → AuthController.getPermissions()

### Frontend Routes (Notification Actions)
- User Registration: `{ name: 'UForm', params: { id: u.id } }`
- Client Form: `{ name: 'CForm', params: { id: u.cli_id } }`
- Offer Form: `{ name: 'OForm', params: { id: u.offer_id } }`

### Permission Keys
- `per-00-01`: Notification settings access (Sidebar, NSettings.vue)

---

## 8. Related Components & Pages

### Settings
- **NSettings.vue**: Notification group configuration
- **Location**: `/resources/js/pages/coalsystem/Notifications/NSettings.vue`
- **Features**: Assign users to notification groups, manage notification recipients
- **Layout**: Admin-style dark header (`#0f172a → #1e293b` gradient), 3-column equal-height grid (`align-items:stretch`, `flex:1` table `height:100%` fills card), save button in header (green → amber when unsaved changes detected)
- **Table**: Admin order list style (light `#f8fafc` header, `0.72rem` uppercase labels, `0.86rem` body, clean row borders). **Fix 2026-09-06 night:** `PickleTable` has no `destroy()` → `onRoleChange:128` previously leaked DOM → duplicate `İSİM/KULLANICI ADI` headers. Fixed to clear `#div_table.innerHTML=''` + `table=null` before `new PickleTable` (guard also in `buildTestTable:206`)
- **Unsaved Changes**: `isDirty` computed tracks `touchedUserIds.length > 0`, amber pulsing `nset-save-btn--dirty` + animated `nset-unsaved-banner` "Kaydedilmemiş X değişiklik var"
- **No FAB**: Save button integrated into header, `AppFab` component removed from this page
- **BUKRS gating (tedarik-01/02/03 + tedarik-04/`05`/`06`/`07` dual):** `ReportServiceProvider:58` `filterOrdersByBukrsForCurrentUser` + `83` `filterFilesByBukrsForCurrentUser` (files: `document_files.qnid IN (...) → d → ord CASE WHEN d.parent_id!=0 THEN parent ELSE d` → `COALESCE(MAX(se.sys_code),MAX(ord.grp_code),MAX(d.grp_code))`) use `COALESCE(MAX(se.entity_value),MAX(d.grp_code)) GROUP BY qnid` (entity `sys_code` fallback to `documents.grp_code`), `bukrsToSystem()` (`4000/GDZ`, `5000/ADM`, `BOTH`), `getCurrentUserSystemCode()` (`auth()->user()->grp_code` → `persons.grp_code` → `SYS_CODE`). `tedarik-01` (`doc_trans_order_created`) + `tedarik-02` (`doc_trans_order_transfer_sent`) filter orders; `tedarik-03` (`doc_file_waiting`) filters files via their order. `tedarik-04` (`doc_file_accepted`) + `tedarik-05` (`doc_file_rejected`) + `tedarik-06` (`doc_trans_order_approved`) + `tedarik-07` (`doc_trans_order_rejected/files_rejected`) dual: reseller bypasses `getNotificationUsers` check — LIFNR-scoped `getTedarikFilesByStatus` + `filterFilesByBukrs` even if not assigned; non-reseller assigned `BOTH/==order sys_code`. Feeds `SystemController:82,85,88,90,92` blink + `DashboardNotifications/TedarikHeader` + `TedarikActivity/Bilgilendirmeler`. `tedarik-02`/`tedarik-03` trigger together (same `processOrderTransfer` moment) but to independent groups.

### Logs
- **NList.vue**: Notification delivery logs
- **Location**: `/resources/js/pages/coalsystem/NotificationLogs/NList.vue`
- **Features**: Track sent notifications, retry failed deliveries

### Database Entities
- `SysNotificationType`: Notification type configuration
- `NotificationLog`: Delivery history (email/SMS sends)
- `NotificationRead`: Read tracking per user (`user_id`, `op_key`, `target_qnid`, `read_at`) — `2026_09_07_000001_create_notification_reads_table.php`
- `PersonNotificationGroup`: User-group assignments

---

### Bell & Bilgilendirmeler Fixes 2026-09-06 night
- `PersonsServiceProvider:667` numeric/qnid mismatch (`session person_id` `c055...` vs `p.qnid='1'`) → now `p.id OR p.qnid` → `tedarik-01` 0→8
- `ReportServiceProvider:942` `tedarikActivity` filtered only `doc_trans_*` → `log-order-update` hidden → added `OR log-order-update` with `EXISTS op-doc-order` guard → admin 0→8, supplier 0→1, added `order_no/spec_code/qnid` subselects + `TedarikActivity.vue:10` detail line + `goDetail` `markRead`+`router.push TedarikOrderForm`
- `TedarikHeader.vue:8` `ki-bell` missing → SVG `M15 17h5…` + `44px` `1.5px #ffedd5` + `12px` `tdk-badge-pulse` + `has-notif` orange border + click→`markRead`+`navigate`
- `SystemController:128` `Plib` `FormData` not in `input()` → fallback `all()['op_key']` → `POST /api/v1/notifications/read` `success true`

---

## 10. Notification Read Tracking System (2026-09-07)

### Overview
Notifications are ephemeral (computed from live order/file status). To avoid showing the same notification repeatedly, we track which notifications each user has "read" (clicked/viewed).

### Schema: `notification_reads`
```sql
notification_reads (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,          -- users.id
  op_key VARCHAR(20) NOT NULL,   -- 'tedarik-01' through 'tedarik-07'
  target_qnid VARCHAR(36) NOT NULL, -- order or file qnid
  read_at TIMESTAMP,
  UNIQUE(user_id, op_key, target_qnid)
)
```

### Backend Flow
1. `ReportServiceProvider::getAdminNotifications($notifKey, $limit)` — fetches all matching notifications → filters out those in `notification_reads` for current user → returns `['data' => [...limited], 'total' => N]`
2. `SystemController::getNotifications()` — calls `getAdminNotifications` with `$limit=10` for each of 7 categories, accumulates `unreadTotal`, sets `blink=1` if any unread
3. `POST /v1/notifications/read` — `markNotificationRead(op_key, target_qnid)` → `NotificationRead::updateOrCreate()`
4. `POST /v1/notifications/read-all` — `markAllNotificationsRead()` → fetches all unread for each category, marks all as read

### Frontend Flow
1. `navigationStore.getNotifications()` — fetches `GET /v1/notifications` → stores response (includes `unreadTotal`)
2. Bell badge shows `unreadTotal` count
3. On notification click → `navigationStore.markNotificationRead(opKey, targetQnid)` → `POST /v1/notifications/read` → removes from local state → badge updates
4. Bilgilendirmeler items also call `markNotificationRead` on click via `goDetail()`

### Key Files
- `panel/database/migrations/2026_09_07_000001_create_notification_reads_table.php` — migration
- `panel/app/Models/NotificationRead.php` — model
- `panel/app/Providers/ReportServiceProvider.php:118` — `getAdminNotifications()` with read filtering + limit
- `panel/app/Http/Controllers/SystemController.php:78` — `getNotifications()` with `unreadTotal`
- `panel/app/Http/Controllers/SystemController.php:107` — `markNotificationRead()`
- `panel/app/Http/Controllers/SystemController.php:125` — `markAllNotificationsRead()`
- `panel/routes/api.php:47-49` — routes
- `panel/resources/js/stores/navigation.js` — `markNotificationRead()`, `markAllNotificationsRead()`
- `panel/resources/js/components/Dashboard/Admin/DashboardHeader.vue` — bell + modal click → mark read
- `panel/resources/js/components/Dashboard/Tedarik/TedarikHeader.vue` — bell + modal click → mark read
- `panel/resources/js/components/Dashboard/Tedarik/TedarikActivity.vue` — `goDetail()` → mark read

## 9. Recommended Improvements

### 1. ✅ Implement Notification Read Status (DONE 2026-09-07)
- `notification_reads` table tracks `user_id + op_key + target_qnid`
- `getAdminNotifications()` filters out read items, returns `{data, total}`
- `GET /v1/notifications` returns `unreadTotal` + 10 items per category
- `POST /v1/notifications/read` marks single notification as read
- `POST /v1/notifications/read-all` marks all current as read
- Frontend bell shows `unreadTotal`, click on notification → mark read → remove from local state

### 2. Add Error Handling
```javascript
async getNotifications(){
  try {
    const rsp = await (new Plib).request({...});
    this.notifications = rsp;
  } catch(error) {
    console.error('Failed to load notifications:', error);
  }
}
```

### 3. Separate Notification Streams
- Real-time notifications (WebSocket/SSE)
- Polling for updates on interval
- Differentiate between critical and informational notifications

### 4. Add Notification Badge Count
```vue
<span v-if="totalNotificationCount > 0" class="badge">
  {{ totalNotificationCount }}
</span>
```

### 5. Separate Notification Streams
- Real-time notifications (WebSocket/SSE)
- Polling for updates on interval
- Differentiate between critical and informational notifications

---

## 10. Testing Checklist

- [ ] Verify rejectedFiles load on app init
- [ ] Verify API notifications load on bell click
- [ ] Check blink indicator appears when notifications exist
- [ ] Test all notification type routing
- [ ] Verify permission filtering works
- [ ] Test error scenarios (API timeout, network error)
- [ ] Check mobile responsiveness of notification modal
- [ ] Verify notification cleanup after action taken
