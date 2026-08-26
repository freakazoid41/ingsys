# Frontend Çekirdek (resources/js) — Dosya Haritası

> Kapsam: 13 dosya · Tamamı okundu.
> Alt yapı: Vue 3 SPA + Pinia + vue-router 4 + laravel-vue-i18n + @unhead/vue + SweetAlert2. HTTP katmanı özel `Plib` sınıfı (fetch tabanlı), axios devre dışı (kodda yorum satırı).

## `resources/js/app.js` (~71 satır)
- **Amaç:** SPA giriş noktası. Vue uygulamasını, Pinia'yı, router'ı, i18n'i, unhead'i ve breadcrumbs plugin'ini kurar; uygulama açılmadan önce izin/rol verilerini prefetch eder.
- **Semboller:** `initApp()` (async bootstrap), `pinia`, `app`.
- **İlişkiler:** `stores/auth` (getPermissions, startHeartbeat, typeKey/currentStatus), `stores/permissiondata` (fetchRoleTemplates, fetchRoleItems), `router/index`, `layouts/App.vue`, `plugins/breadcrumbs`, `laravel-vue-i18n` (lang JSON'ları `../../lang/*.json`'dan eager glob ile).
- **Bootstrap akışı:** 1) `authStore.getPermissions()` → 2) paralel `fetchRoleTemplates` + `fetchRoleItems` → 3) `typeKey == 'op-pert-reseller' && !currentStatus.canProceed` ise `/coalpanel/client/form/{clientQnid}`'e redirect → 4) 30sn heartbeat başlat → 5) `app.mount('#app')`.
- **Bulgular:**
  - `initApp()` çağrısından SONRA senkron `authStore.setData({type:'admin'})` çağrılıyor — async init ile yarışır, muhtemelen debug/artık kod.
  - `getPermissions()` başarısız olursa catch'lenip sadece console'a yazılıyor; uygulama yine mount ediliyor (izinsiz açılış riski sunucu tarafında kapatılmış olmalı).
  - Geniş axios yapılandırması ve eski `attempt_user` akışı yorum satırı olarak duruyor (ölü kod).

## `resources/js/coal-swal.js` (~2 satır)
- **Amaç:** SweetAlert2'yi `window.Swal` global'ine bağlar.
- **İlişkiler:** SPA'da KULLANILMIYOR; sadece `resources/views/auth/{coallogin,loginSms,passwordReset,register}.blade.php` sayfaları `@vite` ile yükler. SPA tarafında `pickle.js` kendi `import Swal` yapar.
- **Bulgular:** —

## `resources/js/router/index.js` (~103 satır)
- **Amaç:** Tüm SPA rotalarını tanımlar; auth guard YOKTUR (yorum satırında), sadece Metronic `KTDrawer` aside menüsünü kapatan navigation guard'ları vardır.
- **Semboller:** `router`, `closeAsideDrawer()`, `beforeEach`, `afterEach`.
- **Route tablosu** (hepsi `/coalpanel` altında, parent layout `CoalPanel.vue`):

  | Path | Name | Component |
  |---|---|---|
  | `/coalpanel` | `CIndex` | `pages/coalsystem/Dashboard.vue` |
  | `/coalpanel/notifications/settings` | `NSettings` | `Notifications/NSettings.vue` |
  | `/coalpanel/treeexample` | `TreeExample` | `treeTest.vue` |
  | `/coalpanel/example` | `ExampleList` | `Example/FlatList.vue` |
  | `/coalpanel/example/form/:id?` | `ExampleForm` | `Example/FlatForm.vue` |
  | `/coalpanel/request` | `RequestList` | `Request/RList.vue` |
  | `/coalpanel/request/form/:id?` | `RequestForm` | `Request/RForm.vue` |
  | `/coalpanel/client` | `CList` | `Client/CList.vue` |
  | `/coalpanel/client/form/:id?` | `CForm` | `Client/CForm.vue` |
  | `/coalpanel/users` | `UList` | `Users/UList.vue` |
  | `/coalpanel/users/form/:id?` | `UForm` | `Users/UForm.vue` |
  | `/coalpanel/roles` | `Roles` | `Roles/Roles.vue` |
  | `/coalpanel/documents` | `DList` | `Documents/DList.vue` |
  | `/coalpanel/offer` | `OList` | `Offer/OList.vue` |
  | `/coalpanel/offer/form/:id?` | `OForm` | `Offer/OForm.vue` |
  | `/coalpanel/sistem-loglari` | `LList` | `Logs/LList.vue` |
  | `/coalpanel/notifikasyon-loglari` | `NList` | `NotificationLogs/NList.vue` |

- **Guard mantığı:**
  - `beforeEach`: `closeAsideDrawer()` çalıştırıp `next()` — auth kontrolü YOK. `closeAsideDrawer` `#kt_aside`'dan `drawer-on` sınıfını kaldırır, `KTDrawer.getInstance().hide()` dener, body attribute'larını ve `.drawer-overlay` elementlerini temizler.
  - `afterEach`: aynı temizliği + 50ms ve 300ms gecikmeli iki ek denemeyle tekrarlar (mobil drawer kalıntılarını gidermek için).
  - Yorum satırında: `requiresAuth`/`isGuest` meta tabanlı guard, `/panel/auth/login` rotası ve 404 catch-all rotası (hepsi devre dışı).
- **Bulgular:**
  - **Kritik:** Route guard'larında kimlik/izin denetimi yok; tüm yetkilendirme sunucu tarafı API middleware'ine ve app.js'teki reseller redirect'ine emanet. Login'e yönlendiren bir 404/catch-all da yok — bilinmeyen path boş sayfa verir.
  - `createWebHistory()` kullanılıyor → sunucuda SPA fallback (örn. `/coalpanel/{any}` → aynı blade) zorunlu.

## `resources/js/stores/auth.js` (~42 satır)
- **Amaç:** Oturum/izin durumu + periyodik izin heartbeat'i.
- **State:** `data`, `permissions`, `currentStatus`, `typeKey`, `personId`, `userName`, `_heartbeat`.
- **Aksiyonlar:**
  - `setData(data)` — manuel state yazma (app.js'teki şüpheli çağrı bunu kullanıyor).
  - `getPermissions()` → **GET `/api/v1/getpermissions`** → `permissions`, `currentStatus`, `typeKey`, `personId`, `userName` alanlarını doldurur.
  - `startHeartbeat()` / `stopHeartbeat()` — 30 sn'de bir `getPermissions()` tekrarlar (izin değişikliklerini yakalamak için; pickle.js 401 `permission_changed` akışıyla birlikte çalışır).
- **Bulgular:** `logout` aksiyonu yok (App.vue'daki interceptor yorum satırında referans veriyor); heartbeat'te hata yönetimi yok.

## `resources/js/stores/events.js` (~76 satır)
- **Amaç:** Dashboard için devam eden görevler + bu ayın/yarının etkinlikleri.
- **State:** `tasks`, `events`.
- **Aksiyonlar:**
  - `setTaskData()` → **GET `/api/v1/dashboard/getOngoingTasks`** → her kaydın `main_attr` JSON'unu Key/Value olarak açıp id bazlı map'e dizer.
  - `setEventData()` → **GET `/api/v1/dashboard/monthlyEvents/{YYYY}-{MM}`** (UTC bazlı) → `main_attr` açılır, sadece `start_date` veya `end_date`'i bugün/yarın olanlar (`isValid` lokal fonksiyonu) `events`'e alınır.
- **Bulgular:** `.then(rsp => {return rsp})` gereksiz no-op; UTC ay/yıl ile lokal `isValid` karşılaştırması saat dilimi kenar durumlarında tutarsız olabilir; `Object.values(list)` zaten array olan `list` üzerinde anlamsız.

## `resources/js/stores/formdata.js` (~16 satır)
- **Amaç:** Liste → form sayfası geçişinde satır verisini taşıyan basit paylaşım store'u.
- **State:** `rawData`, `formData`, `addional` (typo: "additional").
- **Aksiyonlar:** `setData(data, addional)`, `getData()`. API çağrısı yok.
- **Bulgular:** `rawData` hiç yazılmıyor (ölü state); `addional` yazım hatası API yüzeyine yayılmış.

## `resources/js/stores/navigation.js` (~81 satır)
- **Amaç:** Breadcrumb/başlık/buton barı durumu + header bildirimleri; `sessionStorage`'a persist eder.
- **State:** `active` (preloader), `currentTitle`, `breadcrumps` (typo: breadcrumbs), `breadbuttons`, `routeParams`, `lastUpdated`, `notifications`, `notificationError`, `sys_code` (DOM'daki `input[name="SYS_CODE"]`'tan okunur).
- **Aksiyonlar:**
  - `toggle(status)`, `setBread(list,title)`, `setButtons(list)`, `setRouteParams(params)` — hepsi `$state` ataması + `$patch` fallback + `sessionStorage['nav.state']` yazımı yapar.
  - `getNotifications()` → **GET `/api/v1/notifications`** → hata durumunda `{blink:0}`'a sıfırlar.
  - `clearNotifications()` — blink + `awaitingUsers/clientChanges/newOffer/offerRevisionRequests/offerChanges` listelerini sıfırlar.
- **Bulgular:** `sys_code` state ilk yüklenmede DOM'dan okunuyor — element yoksa (örn. SPA dışı sayfa) `null.value` hatası fırlatır; `notificationError` state'i tanımlı ama hiç yazılmıyor (ölü); üç setter'da tekrarlanan persist mantığı yardımcı fonksiyona çıkarılabilir.

## `resources/js/stores/permissiondata.js` (~67 satır)
- **Amaç:** Rol şablonları + rol/yetki item listesi (Roles sayfasının veri kaynağı).
- **State:** `items`, `roleTemplates`. **Getter'lar:** `asJson`, `list`, `byOpKey(opKey)`, `roleList`.
- **Aksiyonlar:**
  - `fetchRoleTemplates()` → **GET `/api/v1/roles/templates`**
  - `fetchRoleItems()` → **GET `/api/v1/roles/items`**
  - `setList`, `setRoleList`, `addItem`, `remove(opKey)`, `loadFromJson` — lokal manipülasyon.
- **Bulgular:** Hata durumunda sessizce boş array'e düşer (UI'da "hata" ile "boş veri" ayırt edilemez).

## `resources/js/lib/pickle.js` (~814 satır)
- **Amaç:** Projenin çekirdek yardımcı sınıfı `Plib`. Tablo kütüphanesi wrapper'ı **DEĞİL**; fetch tabanlı HTTP istemcisi + form doğrulama/toplaması + UI yardımcıları (toast, loader, para formatı, base64, görsel sıkıştırma vb.) içeren genel amaçlı utility/toolkit sınıfı. Tüm store'lar ve sayfalar API'ye bunun `request()` metoduyla çıkar.
- **Semboller (ana metotlar):**
  - `request(rqs, file, formData)` — merkezi HTTP katmanı: CSRF token'ı `meta[name="csrf-token"]`'dan, Bearer token'ı `localStorage.token`'dan okur; DELETE → urlencoded, POST/PUT → FormData; JSON parse edemezse ham hatayı Swal modalında gösterir. **Özel 401 akışları:** `message === 'permission_changed'` → toast + `/api/v1/getpermissions` refresh + orijinal isteği 1 kez retry; `message === 'force_logout'` → token sil + uyarı + `/`'ye yönlendir.
  - `transaction(type, model, data)` — legacy CRUD kısayolu: `/api/{auth|request|query|upload}/...` şemasına istek üretir (mevcut v1 rotalarıyla uyuşmuyor; eski sistemden kalma).
  - Form: `checkForm(selector)` (required doğrulama, çoklu dil `data-lang` alanlarını JSON'a toplama, checkbox/multy-select özel işleme, `err-{name}` konteynerleri, nice-select kenarlıkları, tinymce entegrasyonu), `clearElements(selector)`, `validatePassword(input)`, `fileInfo(evt)` (jpg/jpeg/png/pdf, ≤~40MB).
  - UI: `toast`, `processLoading`, `prompt`, `setLoader`, `compressImage`, `createFile`, `openTab` (POST ile yeni sekme), `formatMoney`, `seoTitle`, `getMonths` (TR ay adları), `getPerms`, `getUrlParam`, `getNumberOfDays`, `getDaysArray`, `sleep`, `getLang`, `clearString`, `createElm`.
  - Kripto: `_utf8_encode/_utf8_decode`, `crypFunc()` (el yapımı base64 encode/decode — güvenlik değil, obfuscation).
  - `checkMail(email)` → **POST `/api/auth/checkmail`** (legacy endpoint).
- **İlişkiler:** Tüm store'lar (auth/events/navigation/permissiondata) ve sayfalar tarafından import edilir; Swal'a bağımlı.
- **Bulgular:**
  - `request()` sonunda erişilemez `return rsp;` (tanımsız `rsp`, dead code).
  - Header'lara yanlışlıkla `'credentials': 'include'` header'ı ekleniyor (geçersiz header; asıl ayar fetch option'da doğru verilmiş).
  - CSRF meta etiketi olmayan sayfada tüm istekler exception fırlatır (guard yok).
  - `transaction()` ve `checkMail()` mevcut `/api/v1` şemasına uymayan legacy uçlara işaret ediyor — kullanılmıyorsa ölü kod.
  - JSON parse başarısız olunca sunucunun HTML hata sayfasını `html` olarak Swal içine basıyor (XSS yüzeyi: sunucu çıktısı escape edilmeden enjekte ediliyor).

## `resources/js/lib/treeModal.js` (~409 satır)
- **Amaç:** Bağımsız (vanilla JS, Vue'suz) checkbox'lı ağaç seçim bileşeni. İki mod: `show(options)` → Promise dönen modal (confirm: seçili node objeleri, cancel: `null`); `render(options)` → verilen hedef elementin içine inline ağaç çizer ve `{getChecked, setChecked, setSelected, destroy}` API'si döner.
- **Semboller:** `buildTreeFromFlat`, `normalizeItems` (flat→ağaç, eksik id'lere `auto-N` atar), `normalizeCheckedInput` (string/JSON/object/array her formatta defaultChecked kabulü), `createNodeElement`, `setChildrenChecked`, `updateAncestorState` (indeterminate dahil üç durum), `getCheckedValues`, `attachHandlers`, `injectStyles`, `render`, `show`.
- **Davranış:** Parent seçimi tüm çocukları seçer; çocuk durumları parent'ı checked/indeterminate yapar. `op_key` alanını id olarak da destekler (Roles/izin ağaçları için tasarlanmış). Anahtarlar özelleştirilebilir: `idKey` (vars. `id`), `parentKey` (vars. `parent_id`), `childrenKey` (vars. `childs`), `labelKey`.
- **İlişkiler:** Rol/izin atama ekranları (`Roles.vue`, `treeTest.vue`) tarafından kullanılır. Node stillerini kendisi inject eder; ancak `show()` modalının `tm-overlay/tm-modal/tm-header/tm-footer/tm-btn` stilleri `public/coaltheme/css/treeModal.css`'ten gelir (harici bağımlılık).
- **Bulgular:** `injectStyles` sadece node stillerini basar — modal görünümü theme CSS'i yüklenmeden bozuk; `attachHandlers`'a `onChange` geçilmeden çağrılan `show()` içinde onChange hiç bağlanmıyor (sadece OK anında topluyor — tasarım gereği olabilir).

## `resources/js/plugins/breadcrumbs.js` (~28 satır)
- **Amaç:** Vue plugin'i: `this.$setBreadcrumbs(list, title)` global helper'ı + bileşen `breadcrumbs` option'ını (array veya `{list,title}`) mount'ta navigation store'a yazan global mixin; unmount'ta temizler.
- **İlişkiler:** `stores/navigation` (`setBread`); `app.js`'te `.use(breadcrumbsPlugin)` ile kurulur.
- **Bulgular:** Her bileşene global mixin ekler (tüm component'lerde mounted/beforeUnmount hook'u koşar — küçük ama geniş etki).

## `resources/js/layouts/App.vue` (~35 satır)
- **Amaç:** Kök bileşen; sadece `<router-view :key="$route.path">` render eder. `:key` sayesinde aynı component farklı path'te tam remount olur.
- **İlişkiler:** `router`, `stores/auth` (yorum satırındaki logout/interceptor kodu referans veriyor).
- **Bulgular:** Tüm mounted interceptor mantığı yorum satırı (ölü kod); 401/419/403/423 yönlendirmeleri şu an aktif değil.

## `resources/js/layouts/CoalPanel.vue` (~82 satır)
- **Amaç:** `/coalpanel` altındaki tüm sayfaların ana kabuğu: sol `Sidebar` + üst `Header` + Simplebar scroll içinde `<router-view :key="$route.path">`. `navigationStore.active` true iken SVG ripple animasyonlu preloader overlay gösterir.
- **Semboller:** components: `Sidebar`, `Header`, `Simplebar`; mounted'ta `localStorage['sa-theme']`'i `body[data-sa-theme]`'e uygular ve `window.KTDrawer.createInstances('[data-kt-drawer="true"]')` ile Metronic drawer'ı başlatır.
- **İlişkiler:** `components/coalparts/Sidebar.vue`, `components/coalparts/Header.vue`, `stores/navigation`, `@unhead/vue` (setup'ta expose ediliyor ama kullanılmıyor), Metronic `KTDrawer` (global theme JS).
- **Bulgular:** `useHead` import edilip setup'tan dönülüyor ama template'te/hook'ta kullanılmıyor (ölü); boş `beforeMount` arrow fonksiyonu; tema okuma `localStorage` null dönerse `data-sa-theme="null"` yazılır.

## Alan Özeti
- Bu dilim SPA'nın iskeletidir: `app.js` bootstrap → `App.vue` kök → `CoalPanel.vue` kabuk → router child sayfaları. Tüm sayfalar bu çekirdeğin store'ları ve `Plib` istemcisi üzerinden API'ye çıkar.
- **İstek akışı:** Sayfa/Store → `new Plib().request({url, method, data})` → fetch (CSRF meta + Bearer localStorage) → 401 `permission_changed` ise otomatik refresh+retry, `force_logout` ise zorla çıkış → JSON response.
- **Store → endpoint eşleşmesi:** auth → `GET /api/v1/getpermissions` (30sn heartbeat); events → `GET /api/v1/dashboard/getOngoingTasks`, `GET /api/v1/dashboard/monthlyEvents/{YYYY-MM}`; navigation → `GET /api/v1/notifications`; permissiondata → `GET /api/v1/roles/templates`, `GET /api/v1/roles/items`; formdata → API yok (sayfalar arası veri taşıyıcı).
- **pickle.js rolü:** tablo wrapper'ı değil; HTTP istemcisi + form toolkit + UI yardımcılarından oluşan "İsviçre çakısı" sınıf. 814 satırda en az 4 sorumluluk taşıyor — teknik borcun merkezinde.
- **Kritik bulgular:** (1) Router'da auth guard yok — yetkilendirme tamamen sunucuya emanet; (2) app.js'te async init sonrası senkron `setData({type:'admin'})` yarış durumu; (3) pickle.js `transaction()`/`checkMail()` legacy endpoint'leri muhtemelen ölü; (4) navigation store `sys_code` DOM bağımlılığı kırılgan; (5) pickle.js parse-hatası Swal'inde escape'siz HTML enjeksiyonu.
