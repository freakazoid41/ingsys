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

**Method**: `getNotifications()` (lines 77-96)
```php
public function getNotifications(){
  $response = ['blink' => 0];
  $provider = new ReportServiceProvider();
  
  // Get admin-targeted notifications
  $response['awaitingUsers'] = $provider->getAdminNotifications('notif-00');
  if(!empty($response['awaitingUsers'])) $response['blink'] = 1;

  $response['clientChanges'] = $provider->getAdminNotifications('notif-01');
  if(!empty($response['clientChanges'])) $response['blink'] = 1;

  $response['newOffer'] = $provider->getAdminNotifications('notif-02');
  if(!empty($response['newOffer'])) $response['blink'] = 1;

  $response['offerRevisionRequests'] = $provider->getUserNotifications('offer-revision-request');
  if(!empty($response['offerRevisionRequests'])) $response['blink'] = 1;

  $response['offerChanges'] = $provider->getAdminNotifications('notif-03');
  if(!empty($response['offerChanges'])) $response['blink'] = 1;

  return $response;
}
```

**Response Structure**:
```json
{
  "blink": 1,
  "awaitingUsers": [...],
  "clientChanges": [...],
  "newOffer": [...],
  "offerRevisionRequests": [...],
  "offerChanges": [...]
}
```

**Notification Code Mappings:**
- `notif-00`: Awaiting users (new registrations)
- `notif-01`: Client changes (file updates)
- `notif-02`: New offers
- `notif-03`: Offer changes
- `offer-revision-request`: Offer revision requests

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

**`getAdminNotifications($notifKey)`** (lines 20+)
```php
public function getAdminNotifications($notifKey){
  $personsProvider = new PersonsServiceProvider();
  $data = [];
  
  // Check if user is in notification group
  $permittedUsers = $personsProvider->getNotificationUsers($notifKey, session('person_id'));
  if(empty($permittedUsers)) return [];
  
  switch ($notifKey) {
    case 'notif-00':  // Awaiting users
      $data = $this->getAwaitingUserRequests();
      break;
    case 'notif-01':  // Client file uploads
      $data = $this->getAwaitingClientFiles();
      break;
    // ... more cases
  }
  
  return $data;
}
```

**Process:**
1. Validates user has permission for notification type
2. Fetches relevant data based on notification code
3. Returns array of notification items

#### Notification Data Fetchers

**`getAwaitingUserRequests()`**
- Returns pending user registration requests
- Fields: `id`, `username`, `created_at`

**`getAwaitingClientFiles()`**
- Returns client file uploads awaiting action
- Fields: `cli_id`, `title`, `inserted_by`, `created_at`

**Additional Data Sources** (inferred from showNotifications mapping):
- Offer data: `id`, `created_at`, `main_attr` (JSON with client title)
- Revision requests: Same structure as newOffer

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
│        awaitingUsers: [...],                                     │
│        clientChanges: [...],                                     │
│        newOffer: [...],                                          │
│        offerRevisionRequests: [...],                             │
│        offerChanges: [...]                                       │
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

## 5. Notification Categories & Data Structure

### Category: awaitingUsers (notif-00)
```javascript
{
  title: 'Yeni Kullanıcı Kaydı',
  message: `${u.username} adlı kullanıcı kayıt bekliyor.`,
  time: `Kayıt tarihi: ${u.created_at}`,
  type: 'awaitingUser',
  onclick: () => router.push({ name: 'UForm', params: { id: u.id } })
}
```
**Backend Source**: `ReportServiceProvider.getAwaitingUserRequests()`

### Category: clientChanges (notif-01)
```javascript
{
  title: 'Müşteri Dosya Bilgisi Girdi ('+u.title+')',
  message: `${u.inserted_by} kullanıcısı dosya bilgisi girdi.`,
  time: `Kayıt tarihi: ${u.created_at}`,
  type: 'awaitingUser',
  onclick: () => router.push({ name: 'CForm', params: { id: u.cli_id } })
}
```
**Backend Source**: `ReportServiceProvider.getAwaitingClientFiles()`

### Category: newOffer (notif-02)
```javascript
{
  title: 'Yeni Teklif',
  message: `${u.title} müşterisi yeni bir teklif girdi.`,
  time: `Kayıt tarihi: ${u.created_at}`,
  type: 'newOffer',
  onclick: () => router.push({ name: 'OForm', params: { id: u.offer_id } })
}
```
**Backend Source**: `ReportServiceProvider.getAdminNotifications('notif-02')`

### Category: offerRevisionRequests
```javascript
{
  title: 'Teklif Revizyon Talebi',
  message: `${u.created_at} tarihli teklif için revizyon talep edildi.`,
  time: `Kayıt tarihi: ${u.created_at}`,
  type: 'newOffer',
  onclick: () => router.push({ name: 'OForm', params: { id: u.offer_id } })
}
```
**Backend Source**: `ReportServiceProvider.getUserNotifications('offer-revision-request')`

### Category: offerChanges (notif-03)
```javascript
{
  title: 'Teklif Güncellemesi',
  message: `${u.title} müşterisi teklifini güncelledi.`,
  time: `Kayıt tarihi: ${u.created_at}`,
  type: 'newOffer',
  onclick: () => router.push({ name: 'OForm', params: { id: u.offer_id } })
}
```
**Backend Source**: `ReportServiceProvider.getAdminNotifications('notif-03')`

### Category: clientFile (rejectedFiles)
```javascript
{
  title: 'Reddedilen Dosya',
  message: `${fl.title} reddedildi.`,
  time: `${fl.rejected_by} tarafından`,
  type: 'clientFile',
  onclick: () => router.push({ name: 'CForm', params: { id: fl.cli_id } })
}
```
**Frontend Source**: `authStore.currentStatus.rejectedFiles`

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

### Logs
- **NList.vue**: Notification delivery logs
- **Location**: `/resources/js/pages/coalsystem/NotificationLogs/NList.vue`
- **Features**: Track sent notifications, retry failed deliveries

### Database Entities
- `SysNotificationType`: Notification type configuration
- `NotificationLog`: Delivery history
- `PersonNotificationGroup`: User-group assignments

---

## 9. Recommended Improvements

### 1. Add Reactive Watcher
```javascript
watch: {
  'navigationStore.notifications': {
    handler() {
      // Trigger UI update when notifications change
    },
    deep: true
  }
}
```

### 2. Add Error Handling
```javascript
async getNotifications(){
  try {
    const rsp = await (new Plib).request({...});
    this.notifications = rsp;
  } catch(error) {
    console.error('Failed to load notifications:', error);
    // Show user-friendly error
  }
}
```

### 3. Implement Notification Read Status
- Track which notifications have been seen
- Disable blink when all notifications are read
- Persist state in localStorage or backend

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
