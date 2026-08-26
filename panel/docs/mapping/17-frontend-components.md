# Frontend Components (resources/js/components/) — Dosya Haritası
> Kapsam: 25 dosya (Dashboard 3 + Admin alt 8 + Client alt 5 + 3 SUMMARY.md + Offer 4 + coalparts 6) · Tamamı okundu.

## Dashboard/ — Rol-bazlı ana sayfa bileşenleri

### `Dashboard/Admin.vue` (~249 satır)
- **Amaç:** Yönetici dashboard'unun layout kabuğu; 8 Admin alt bileşenini grid'e dizer.
- **Semboller:** `AdminDashboard` component; data `sysCode` (navigationStore.sys_code).
- **Props/Emits:** Yok. `<DashboardRequestTables :sysCode>` prop geçer.
- **Render:** DashboardHeader, DashboardStats, DashboardDistribution + DashboardQuickActions (2'li grid), DashboardProcessChart + DashboardNotifications + DashboardCalendar (3'lü grid), DashboardRequestTables.
- **Endpoint:** Kendisi çağırmaz; mounted'da `#kt_content_container`'dan `container-xxl` class'ını kaldırır (full-width), unmount'ta geri ekler.
- **Kullanım:** `pages/coalsystem/Dashboard.vue` tarafından rol admin ise render edilir.
- **Bulgular:** `:root` CSS değişkenleri scoped style içinde tanımlı (scoped'ta `:root` pratikte etkisiz/kaçak davranabilir). Template'de yorum "Row 3" gerçekte ilk sırada — sıralama yorumları güncel değil.

### `Dashboard/Client.vue` (~607 satır)
- **Amaç:** Tedarikçi (client) dashboard layout'u; 5 Client alt bileşenini dizer + bildirim durumunu hesaplar.
- **Semboller:** `ClientDashboard`; computed `hasNotifications()`; methods `openNotifications()` (ClientInfoSection ref'i üzerinden), `openProfile()` (UForm route).
- **Props/Emits:** Yok; ClientHeader'a `:has-notifications` prop + `@open-notifications`/`@open-profile` emit dinler.
- **Render:** ClientHeader, ClientStats, ClientQuickOps, ClientInfoSection, ClientOfferTable.
- **Endpoint:** Kendisi çağırmaz; `navigationStore.notifications` (awaitingUsers/clientChanges/newOffer/offerRevisionRequests/offerChanges) ve `authStore.currentStatus.rejectedFiles`'ı sayar.
- **Bulgular:** Büyük miktarda kullanılmayan CSS (stats-section, offers-table, action-btn vb. — alt bileşenlere taşınmış ama stiller burada kalmış, ölü CSS). `:host` seçici Vue SFC'de işlevsiz.

### `Dashboard/Default.vue` (~1227 satır)
- **Amaç:** Varsayılan/landing dashboard: hero banner + menü kartları + teklif tablosu (PickleTable).
- **Semboller:** data `menuItems` (5 kart: users/request/client/offer/documents), `offerTabs`, hardcoded `statusCards`/`activityItems`/`summaryCards`; methods `onOfferTabClick`, `onMenuClick`, `buildClientFilter` (Swal + PickleTable firma seçici), `buildOfferTable`.
- **Render:** Hero (banner-bg-new.webp + 5 menü kartı), "Tümü/Firma Seç" pill tab + #offer-table; `statusCards`/`activityItems`/`summaryCards` bölümleri `hidden` attribute ile gizli (eski tasarım).
- **Endpoint:** `GET /api/v1/table/documents` (PickleTable ajax; initialFilter `form-type=op-doc-offer-form` + `type=op-doc-offer`); firma seçicide aynı endpoint `op-doc-client-form` filtresiyle.
- **İlişkiler:** Plib, PickleTable, Swal, navigationStore/authStore. Satır tıklama → `/coalpanel/offer/form/:id`. Durum güncelleme bloğu (Swal + `/api/v1/trans/set-status`) yorum satırına alınmış.
- **Bulgular:** ~500 satır hardcoded mock data (statusCards, activityItems, summaryCards) + gizli template — ölü kod. `banner-bg-new.webp` relative path (public kökünden çözülmeli). `selectedMenu` yazılıyor ama kullanılmıyor.

### Dashboard/Admin/ — Yönetici dashboard parçaları

#### `Dashboard/Admin/DashboardHeader.vue` (~491 satır)
- **Amaç:** Karşılama başlığı + bildirim zili (Swal modal) + profil linki.
- **Semboller:** computed `hasNotifications`; methods `loadNotifications`, `mergeNotifications`, `showNotifications` (inline-HTML'li büyük Swal modal).
- **Props/Emits:** Yok.
- **Render:** "Hoş Geldiniz, {userName}" + Yönetici Paneli alt başlığı; zil ikonu (badge pulse), UForm profil router-link.
- **Endpoint:** `navigationStore.getNotifications()` tetikler (store içinde API'ye gider); kendisi doğrudan endpoint çağırmaz. Bildirim tıklamaları UForm/CForm/OForm route'larına gider.
- **Bulgular:** mergeNotifications mantığı DashboardNotifications ve ClientInfoSection ile 3 kez kopyalanmış (duplikasyon). Swal HTML'i template literal ile inline style dolu — XSS açısından `n.message` escape edilmeden HTML'e basılıyor (kullanıcı adı/firma ünvanı içerebilir).

#### `Dashboard/Admin/DashboardStats.vue` (~450 satır)
- **Amaç:** 5 istatistik kartı (Toplam Talep, Toplam Teklif, Aktif Firma, Bekleyen Teklifler, Bugünkü Teklifler).
- **Semboller:** data `topStats` (6 stat config; `approvedOffers` tanımlı ama `topStatsList`'te yok → render edilmez), `topStatsLoading`; methods `topDataLoad`, `handleStatAction` (route: RequestList/OList/CList).
- **Props/Emits:** Yok.
- **Endpoint:** `GET /api/v1/dashboard/topstats` (Plib).
- **Bulgular:** Hardcoded default değerler (42, 117, 58...) API başarısız olursa ekranda kalır — yanıltıcı. `approvedOffers` config'i ölü (listede değil). `iconPath` alanları kullanılmıyor. `secondaray-icon` typo (CSS'te de `.secondary-icon` diye düzeltilmiş ama data'da typo duruyor → renk uygulanmaz).

#### `Dashboard/Admin/DashboardDistribution.vue` (~230 satır)
- **Amaç:** Aylık santral bazlı talep/teklif dağılım kartları + toplam kartı.
- **Semboller:** computed `distributionTotals`; method `monthlyDistributionLoad` (3 farklı API response formatını normalize eder).
- **Props/Emits:** Yok.
- **Endpoint:** `GET /api/v1/dashboard/monthlydistribution`.
- **Bulgular:** `$forceUpdate()` kullanımı gereksiz (reaktif atama yeterli).

#### `Dashboard/Admin/DashboardQuickActions.vue` (~159 satır)
- **Amaç:** Hızlı işlem butonları (Yeni Talep Oluştur, Kullanıcı Ekle).
- **Semboller:** data `quickActions` (2 aktif; id 2/3 = AnnouncementCreate/ReportBuilder yorum/ölü); method `handleQuickAction` (RequestForm/UForm route).
- **Props/Emits:** Yok. **Endpoint:** Yok.
- **Bulgular:** id 2 ve 3 için route'lar switch'te duruyor ama buton listede yok — yarım bırakılmış özellik.

#### `Dashboard/Admin/DashboardProcessChart.vue` (~325 satır)
- **Amaç:** "Teklif Süreç Durumu (Aylık)" doughnut chart + özel legend + OList linki.
- **Semboller:** data `processData` (6 durum, hardcoded default); methods `monthlyOffersLoad`, `initCharts` (Chart.js + custom centerText plugin — ortada toplam).
- **Props/Emits:** Yok.
- **Endpoint:** `GET /api/v1/dashboard/monthlyoffers` (array/object/labels+data gibi 4 format normalize edilir).
- **İlişkiler:** chart.js/auto, Plib. Unmount'ta chart destroy edilir.
- **Bulgular:** Hardcoded fallback değerler API hatasında kalır.

#### `Dashboard/Admin/DashboardNotifications.vue` (~312 satır)
- **Amaç:** Bildirim kartı: navigationStore + rejectedFiles birleşik liste (ilk N adet).
- **Semboller:** `loadNotifications`, `mergeNotifications` (DashboardHeader ile neredeyse aynı kod).
- **Props/Emits:** Yok.
- **Render:** notification-item listesi + "Tüm Bildirimleri Görüntüle" linki (`href=""` — boş, işlevsiz).
- **Endpoint:** `navigationStore.getNotifications()` üzerinden.
- **Bulgular:** onclick tanımlı ama template'de tıklama bağlı değil (`@click` yok) — bildirimlere tıklanamıyor. Footer `<a href="">` sayfayı yeniler.

#### `Dashboard/Admin/DashboardCalendar.vue` (~228 satır)
- **Amaç:** Takvim / önemli tarihler kartı; tıklanınca ilgili belgeye gider.
- **Semboller:** methods `importantDatesLoad`, `openImportantDate` (event tipi 'offer' içeriyorsa OForm, değilse RequestForm; id yoksa Swal detay).
- **Props/Emits:** Yok.
- **Endpoint:** `GET /api/v1/dashboard/importantinfo`.
- **Bulgular:** Footer "Tüm Takvimi Görüntüle" `<a href="">` işlevsiz.

#### `Dashboard/Admin/DashboardRequestTables.vue` (~461 satır)
- **Amaç:** 3 PickleTable: Son Talepler, Son Talepler (Rödevans — sadece CATES), Son Teklifler.
- **Props:** `sysCode: String` (Admin.vue'dan).
- **Semboller:** `buildRequestTable` (is-rodevans=false filtresi; CATES ise ek tablo is-rodevans=true), `buildOfferTable`. Durum hücreleri `status.split('**')` → status-pill renklendirme.
- **Endpoint:** `GET /api/v1/table/documents` (initialFilter: op-doc-request-form/op-doc-request ve op-doc-offer-form/op-doc-offer).
- **Bulgular:** Tablolarda rowClick yok — satırlar tıklanamaz, sadece "Talep" kolonundaki göz butonu (RequestForm) çalışıyor. `addional` (sic) alan adı backend'den gelen typo'yu kullanıyor.

### Dashboard/Client/ — Tedarikçi dashboard parçaları

#### `Dashboard/Client/ClientHeader.vue` (~174 satır)
- **Amaç:** Client karşılama başlığı + zil + profil butonu (presentational).
- **Props:** `hasNotifications: Boolean`. **Emits:** `open-notifications`, `open-profile`.
- **Endpoint:** Yok (authStore.userName okur).

#### `Dashboard/Client/ClientStats.vue` (~390 satır)
- **Amaç:** 4 stat kartı: İncelenen / Reddedilen / Onaylanan Teklifler + Revize Bekleyen.
- **Semboller:** `loadStats` — response array'inde `doc_trans_offer_*` key'lerini stats objesine map eder.
- **Props/Emits:** Yok. Hepsi OList'e link verir.
- **Endpoint:** `GET /api/v1/dashboard/monthlyoffers/client`.

#### `Dashboard/Client/ClientQuickOps.vue` (~195 satır)
- **Amaç:** 3 hızlı işlem kartı: Talepler, Teklifler, Firmalar.
- **Semboller:** `handleQuickAction` → routeMap {offers: OList, companies: CList, requests: RequestList}.
- **Props/Emits:** Yok. **Endpoint:** Yok.

#### `Dashboard/Client/ClientInfoSection.vue` (~504 satır)
- **Amaç:** 2-3 kartlı bölüm: Bildirimler (ilk 5) + Talepler tablosu (+ CATES ise Rödevans tablosu).
- **Semboller:** `loadNotifications`, `mergeNotifications`, `showNotifications`/`openNotifications` (Swal modal; Client.vue ref üzerinden çağırır), `buildRequestTable` (PickleTable, is-rodevans false/true).
- **Props/Emits:** Yok; parent `ref="infoSection"` ile `openNotifications()` çağırır.
- **Endpoint:** `GET /api/v1/table/documents` (request tabloları); bildirimler `navigationStore.getNotifications()`.
- **Bulgular:** mergeNotifications 3. kopya (duplikasyon). Swal HTML'inde kullanıcı verisi escape'siz.

#### `Dashboard/Client/ClientOfferTable.vue` (~257 satır)
- **Amaç:** "Verdiğim Teklifler" PickleTable.
- **Props/Emits:** Yok.
- **Render:** Cari/Santral/Teklif tipi/Belge Tarihi/Talep (göz butonu ile RequestForm)/Güncel Durum kolonları; OList "tümü" linki.
- **Endpoint:** `GET /api/v1/table/documents` (op-doc-offer filtresi).
- **Bulgular:** rowClick yok. Büyük ölü CSS bloğu (.offers-table vs. — PickleTable kullanılıyor).

### Dashboard/*.md — Refaktör kayıtları (3 dosya)
- **`HEADER_EXTRACTION_SUMMARY.md` (76), `STATS_EXTRACTION_SUMMARY.md` (196), `DISTRIBUTION_EXTRACTION_SUMMARY.md` (239):** Admin.vue'dan DashboardHeader/DashboardStats/DashboardDistribution çıkarım süreçlerinin İngilizce dokümanları. Adım listeleri, API formatları, test checklist'leri içerir. Kod değil; `components/` altında durması yanlış yerde (teknik borç: docs'a taşınmalı veya silinmeli). SUMMARY'lerde belirtilen "Admin.vue ~1950 satır" iddiası bugünkü 249 satırla refaktörün tamamlandığını doğruluyor.

## Offer/ — Teklif detay/liste bileşenleri

### `Offer/OfferLogTimeline.vue` (~351 satır)
- **Amaç:** Teklif işlem geçmişi timeline'ı (durum değişimi / dosya / alan diff'leri).
- **Props:** `documentQnid: String (required)`. **Emits:** Yok.
- **Semboller:** `fetchLogs` (userlogs tablosu, description JSON parse → status/file/edit tiplendirme), `diffEntities(before, after)` (`op-doc-offer-form` entities karşılaştırma), `toggle`, `dotClass`, `iconFor`. FIELD_LABELS (30 alan Türkçe etiket), STATUS_LABEL (7 op_key).
- **Render:** Kart; her log: tip noktası + başlık + kullanıcı + tarih; değişiklik varsa açılır diff tablosu (before → after, kırmızı/yeşil).
- **Endpoint:** `POST /api/v1/table/userlog` (FormData: tableReq{filter doc_qnid=qnid, limit 100}, model=userlogs).
- **Kullanım:** `pages/coalsystem/Offer/OForm.vue`.
- **Bulgular:** Sayfalama yok (limit 100 sabit).

### `Offer/OfferRequestTable.vue` (~324 satır)
- **Amaç:** Bir talebe ait teklifler tablosu + "Teklif Ekle" butonu.
- **Props:** `requestId: String (required)`, `addOfferCallback: Function`. **Emits:** Yok.
- **Semboller:** `buildTable` — durum kolonu `per-05-02` yetkisiyle Swal üzerinden durum değiştirme akışı içerir (İnceleniyor/Revizyon/Reddedildi/Kabul); düzenle butonu sadece revizyon/taslak durumunda veya per-05-02 ile OForm'a gider.
- **Endpoint:** `GET /api/v1/table/documents` (request_id like filtreli); `POST /api/v1/trans/set-status` (durum güncelleme: id, op_key, note).
- **Kullanım:** `pages/coalsystem/Request/RForm.vue`.
- **Bulgular:** Swal butonunda `doc_trans_offer_accepted` key'i kullanılıyor ama OfferSummary'deki onay key'i `doc_trans_offer_approved` — tutarsızlık [INFERENCE: backend ikisini de kabul ediyor olabilir]. Unique `tableId` ile çoklu instance desteklenmiş.

### `Offer/OfferSummary.vue` (~537 satır)
- **Amaç:** Teklif özet/detay görünümü: aksiyon barı + bölümlenmiş alan kartları + kalori fiyat tablosu + ek belgeler + durum geçmişi timeline'ı + versiyon önizleme (diff highlight).
- **Props (14):** `editable`, `entities`, `previewEntities`, `versionOptions`, `selectedVersionId`, `onVersionChange`, `document`, `statusList`, `onEditable`, `onRequestRevision`, `onApprove`, `onReject`, `onDownloadPdf`, `hideActions`. **Emits:** Yok — tamamen callback-prop mimarisi.
- **Semboller:** SECTIONS sabiti (5 bölüm; isCalory özel bölüm), computed `entitiesToRender`/`isPreviewMode`/`isFileOffer` (offer_type `op-doc-offer-file` ise sadece Genel Bilgiler), `caloryRows` (`**calory_settings**tag` gruplama), `offerDocs` (`**offerotherdocs**` gruplama), methods `val/fmt`, `valueChanged`/`offerDocChanged` (versiyon diff sarı highlight), `statusBadgeClass`.
- **Render:** PDF İndir / versiyon select / Düzenle / Revize Talep Et / Onayla / Reddet butonları; mevcut durum badge; dosya-tipi teklifte indirme kartı; sağ kolonda sticky durum geçmişi (dayjs formatlı).
- **Endpoint:** Yok — veri ve aksiyonlar parent (OForm) tarafından sağlanır.
- **Kullanım:** `pages/coalsystem/Offer/OForm.vue`.
- **Bulgular:** Belge linki `/order-file/{qnid|description}` doğrudan href — auth kontrolü backend'de olmalı.

### `Offer/OfferTable.vue` (~235 satır)
- **Amaç:** PickleTable KULLANMAYAN, elle yazılmış kart-liste teklif tablosu (kendi pagination'ı ile).
- **Props:** `initialFilter: Array`, `height: String (default '100vh')`. **Emits:** Yok.
- **Semboller:** `loadData` (POST tableReq; başarısızsa GET fallback; çok şekilli response normalize: data/rows/meta), `getRequestIdFromUrl` (URL son segmenti UUID-benzeri ise request_id filtresi), `statusInfo`, `formatOfferType`, `formatPrice` (VMasker), computed `pages`.
- **Render:** Kart listesi (clititle, offer_type, date, coal_type, unit_price) + durum pill + "Detay Göster" (OForm) + sayısal pagination.
- **Endpoint:** `POST /api/v1/table/documents` (data: tableReq JSON string), fallback `GET /api/v1/table/documents?filter=...`.
- **Kullanım:** Mevcut import bulunamadı — proje içinde kullanılmıyor olabilir (ölü component adayı) [INFERENCE: grep'te hiçbir sayfa import etmiyor].
- **Bulgular:** `laravel-vue-i18n` (wTrans) import edilmiş ama kullanılmıyor. PickleTable import yok ama style.css import var. Liste keyfi `item.id`; `loading`/`error` basit.

## coalparts/ — Çekirdek layout & form motoru

### `coalparts/Form.vue` (~2892 satır) — GENERİK FORM MOTORU
- **Amaç:** Tüm belge formlarını tek component'ten üreten şema-güdümlü (schema-driven) form motoru. **Evet, sayfalar bunu kullanıyor:** `pages/coalsystem/Client/CForm.vue`, `Users/UForm.vue`, `Request/RForm.vue`, `Offer/OForm.vue`, `Example/FlatForm.vue` — hepsi `<Form formtypes="op-doc-...-form">` şeklinde.
- **Props:** `formtypes` (virgüllü form key listesi), `fabtype`, `savecallback`, `savebtntitle`, `rejectcallback`, `acceptcallback`. **Emits:** Yok — callback props + AppFab.
- **Şema tanımları (`data().forms`):** 5 form tipi:
  - `op-doc-flat-form`: Daire ismi + kat malikleri (multiple) — örnek/demo formu.
  - `op-doc-user-form`: Şifre üret butonu, İsim/Kullanıcı Tipi/Durum/Rol (rol seçince permissionTree.setChecked), email/parola çifti, İletişim (multiple), Bağlı Cariler (usersClientClick → Swal+PickleTable cari seçici, duplicate kontrolü), İzinler (tree type → TreeModal).
  - `op-doc-request-form`: Talep formu — qnid/date/rev_date/title, target_type (Yatağan/ÇATES; ÇATES seçince request_type switch görünür), order_radius (Sadece Nakliye'de coal_specs+calory_settings gizlenir, Nakliye Hariç'te fiyat oran satırı gizlenir), ihale tarihleri, unload_area, her_ikisi switch, coal_specs (8 kömür alanı), calory_settings (multiple: kalori aralığı → birim fiyat), amount (Ton), payment_periods/desc, fiyat etki oranları (tiufe/fuel/fuel_2), payment_due, sevkiyat tarihleri, request_type (Rodevans) switch, desc.
  - `op-doc-client-form`: "Kullanıcı Talebinde Bulun" butonu (reseller için; otomatik parola üret + email/telefon doğrulama → `POST /api/v1/auth/register`), Firma Bilgileri (clicode/title/vat_id — vergiKimlikDogrula ile 10-11 hane+VKN/TCKN algoritma doğrulaması/vat_title/fax/website), cli_desc, Yetkililer (multiple), İmza Sirküleri/Vergi Levhaları/Oda Sicil/IBAN (file multiple'lar, requiredIfFirst), Diğer Belgeler.
  - `op-doc-offer-form`: request_id/offer_type/cliid gizli alanlar, clititle, date, target_type (file-teklifte select+editable), order_radius (aynı koşullu görünürlük; `navigationStore.routeParams.offer_type` `op-doc-offer-file` ise çoğu bölüm hidden), coal_specs, calory_settings, amount, payment, sub_8'de ek `transfer_price_impact` (request formunda yok), sevkiyat, desc, Teklif Belgeleri (offerotherdocs multiple).
- **Alan tipleri (render motoru `buildDynamicFForm`):** text/email/password/number, select (async setOptions destekli), textarea, button, file (40MB limit notu, mevcut dosya durumu is-valid/is-invalid + `/order-file/` görüntüleme, DataTransfer ile sahte File enjeksiyonu), switch (checkbox), yesno (radio çifti), tree (TreeModal izin ağacı), section (hr), sub (tek satır grup), multiple (grup_key ile `name**group_key**tag` isimlendirmeli tekrarlanabilir satırlar + satır silme + removedData takibi + el.multiple ile iç-içe çoğaltma).
- **Veri toplama (`submitDynamicChanges`):** Her input event'i `formData.dynamicF[tag**rowId].entities[name] = value`'ya yazar; money maskesi `.`→kaldır/`,`→`.` normalize eder; checkbox 1/0; date `d/m/Y` → `Y-m-d` çevirir; dosyalar `formData.files[...]`'a ayrı key'le. Silinen alanlar `formData.removedData`'ya. Kayıt: parent'ın `savecallback(this.formData)`'i.
- **UI altyapısı:** flatpickr (TR locale, isDate/isMonth + monthSelectPlugin, hasTime), VMasker (money/phone/custom), PickleTable (buildClientTable → `GET /api/v1/table/documents` op-doc-client), TreeModal (izinler), Swal, dayjs. Password alanlarına göster/gizle toggle'ı eklenir. `formDataStore.addional` ile dışarıdan alan ön-doldurma.
- **Endpoint'ler:** `GET /api/v1/table/documents` (cari seçici), `POST /api/v1/auth/register` (kullanıcı talebi); permissiondata store `fetchRoleTemplates`.
- **mounted:** roller yüklenir → her ftype için `buildDynamicFForm` (formDataStore'da mevcut veri varsa onunla, yoksa 'new-{ts}' rowId ile) → store temizlenir. beforeUnmount: flatpickr instance'ları + treeModal destroy.
- **Template:** her form tipi için `<div class="area-target" :data-tag="item">` + AppFab (admin veya request formunda görünür).
- **Bulgular:** (1) Dev monolit — ~2892 satır, DOM'u Vue dışı imperative `document.createElement` ile kurar; Vue reaktivitesi/template kullanılmıyor, bakım maliyeti yüksek teknik borç. (2) DOM traversal ile görünürlük kontrolü (`parentNode.parentNode...` 5 seviye) kırılgan. (3) `vergiKimlikDogrula`'da 10 haneli VKN algoritması yorum satırına alınmış → 10 hane için sadece uzunluk kontrolü yapılıyor. (4) Şifre üretimi `Math.random()` — kriptografik değil. (5) `op-doc-per-kanaat` tag'i yesno bloğunda hardcoded referans — başka projeden kalma ölü mantık. (6) `keyLock` data'da değil ama `this.keyLock = []` atanarak kullanılıyor (reaktif değil, şans eseri çalışır). (7) `hideAdd`, `showRemoveButton`, `isFoldable` gibi bayraklar kısmen kullanılmıyor.

### `coalparts/Sidebar.vue` (~376 satır)
- **Amaç:** Ana navigasyon yan menüsü (CoalPanel layout'u).
- **Semboller:** computed `userName`/`userRoleLabel` (ROLE_LABELS: admin/buyer/reseller)/`userInitials`; methods `toggleMini`, `markActiveRoute` (URL eşleşmesiyle aktif menü + accordion açma — DOM class manipülasyonu), `bindAccordion`, `applySearchFilter` (menü içi arama, bölüm etiketlerini de gizler).
- **Props/Emits:** Yok.
- **Render:** Logo (`/coaltheme/{sysCode}.svg`), kullanıcı kartı (avatar initials + rol + profil linki), arama, menü: Anasayfa(CIndex), Talep (per-05-01; alt: Talep Oluştur per-05-02/RequestForm, Talep Listesi/RequestList), Teklifler (per-08-01/OList), Dökümanlar (per-07-01/DList), Firma (per-06-01; alt: CForm/CList), Kontrol Paneli (per-04-01; alt: LList/NList/Roles/UList), Bildirimler (per-00-01/NSettings); reseller canResponse=false ise uyarı alerti; çıkış butonu (/logout).
- **Endpoint:** `authStore.getPermissions()` (mounted).
- **Kullanım:** `layouts/CoalPanel.vue`.
- **Bulgular:** Menü tamamen DOM-manipülasyonu ile açılır/kapanır (Vue dışı); `usePermissionDataStore` import edilmiş ama kullanılmıyor; reseller menü koşulu `canProceed || typeKey != reseller` garip negatif mantık.

### `coalparts/Header.vue` (~379 satır)
- **Amaç:** Üst bar: mobil header + sticky header (breadcrumb + bildirim + profil + logout).
- **Semboller:** computed `headerBgStyle` (sysCode'a göre cates.jpg/yatagan.jpg arka plan), `totalNotificationCount`, `breadcrumbItems`/`breadcrumbTrail`; methods `loadNotifications`, `mergeNotifications` (sadece forceUpdate), `breadcrumbLink`/`breadcrumbClick`, `showNotifications` (Swal modal — tipler: awaitingUsers/clientChanges/offerRevisionRequests/offerChanges/newOffer + rejectedFiles).
- **Props/Emits:** Yok.
- **Render:** Mobil logo + aside toggle; breadcrumb (Anasayfa + navigationStore.breadcrumps, router-link veya onclick); zil butonu (badge), UForm profil, /logout.
- **Endpoint:** `navigationStore.getNotifications()` üzerinden.
- **Kullanım:** `layouts/CoalPanel.vue`.
- **Bulgular:** sysCode `document.querySelector('input[name="SYS_CODE"]').value` ile DOM'dan okunuyor (blade hidden input'a bağımlı — sayfada yoksa crash). Bildirim modal HTML'inde kullanıcı verisi escape'siz (XSS riski). `notifications()` adında no-op method ile data `notifications` çakışması (method kazanır; data'ya erişim `this.notifications` yine array çünkü data öncelikli — kafa karıştırıcı). Mobil medya sorgusunda breadcrumb tamamen gizleniyor.

### `coalparts/RSummary.vue` (~561 satır)
- **Amaç:** Talep (request) özet görünümü — hero + ihale süreci timeline + kömür özellikleri + kalori fiyat tablosu + fiyat etki + sipariş/ödeme + "Teklif Ekle" FAB.
- **Props:** `entities: Object`, `document: Object`, `addOfferCallback: Function`. **Emits:** Yok.
- **Semboller:** STATUS_MAP (4 request durumu), `parseDate`/`fmtDate` (TR aylar), computed `statusInfo` (status array|string JSON|'**' formatlarını çözer), `logoSrc`/`bothLogos` (ÇATES/YATAGAN svg), `timeline` (başlangıç/bitiş/kalan gün/yüzde/expired), `coalSpecs`/`priceImpacts`/`orderFields` (boş olmayanlar), `caloryRows` (`**calory_settings**` gruplama). Method `val`.
- **Render:** Hero (başlık + durum badge + meta chip'ler + rodevans chip), progress bar'lı timeline, grid spec kartları, kalori tablosu, AppFab (Teklif Ekle + geri).
- **Endpoint:** Yok — veri parent'tan (RForm).
- **Kullanım:** `pages/coalsystem/Request/RForm.vue`.
- **Bulgular:** Dosya başındaki yorum "Field schema mirrors Form.vue:439+" — çift kaynak senkronizasyon borcu. Tarih parse sadece dd.mm.yyyy / ISO kabul ediyor.

### `coalparts/RequestLogTimeline.vue` (~294 satır)
- **Amaç:** Talep işlem geçmişi timeline'ı (OfferLogTimeline'ın request versiyonu; status/file tiplendirmesi yok, sadece create/edit diff).
- **Props:** `requestQnid: String (required)`. **Emits:** Yok.
- **Semboller:** FIELD_LABELS (32 alan), `diffEntities` (`op-doc-request-form` entities), `formatValue` (request_type → Rodevans/Değil), `fetchLogs`, `iconFor` (log-tender-update/create/lock).
- **Endpoint:** `POST /api/v1/table/userlog` (limit 50, doc_qnid filtre).
- **Kullanım:** `pages/coalsystem/Request/RForm.vue`.
- **Bulgular:** OfferLogTimeline ile ~%80 aynı kod — duplikasyon.

### `coalparts/AppFab.vue` (~401 satır)
- **Amaç:** Yüzen aksiyon butonu/barı: 'bar' modu (İptal + Kaydet + opsiyonel Onayla/Reddet) veya 'leftIcon' modu (checkbox ile açılan dairesel FAB wheel).
- **Props:** `fabType` ('bar'|'leftIcon', default 'bar'), `btntype` ('saveBtn'|'options'), `callback`, `rejectcallback`, `acceptcallback`, `savebtntitle`, `cancelcallback`. **Emits:** Yok.
- **Semboller:** `execute`/`executeAccept`/`executeReject` (loading durumu, options modunda fabCheckbox toggle), `executeCancel`.
- **Endpoint:** Yok.
- **Kullanım:** Form.vue, RSummary.vue, NSettings.vue.
- **Bulgular:** `document.getElementById('fabCheckbox')` ile global DOM erişimi (çoklu instance'da çakışır). CSS'te kullanılmayan fab-wheel/fab-dots blokları büyük.

## Alan Özeti
- **Rol-bazlı dashboard üçlüsü:** `pages/coalsystem/Dashboard.vue` role göre Default/Admin/Client render eder; Admin ve Client, kendi alt klasörlerindeki presentational+data-fetching parçalara ayrıştırılmış (SUMMARY.md'ler bu refaktörün kaydı).
- **Veri akışı:** Dashboard widget'ları `/api/v1/dashboard/*` (topstats, monthlydistribution, monthlyoffers[/client], importantinfo), tablolar `/api/v1/table/documents` (form-type/type/is-rodevans filtreli PickleTable), loglar `/api/v1/table/userlog`, durum değişimleri `/api/v1/trans/set-status` üzerinden akar. Bildirimler navigationStore.getNotifications + authStore.currentStatus.rejectedFiles birleşimidir.
- **coalparts/Form.vue sistemin kalbi:** 5 form şeması (user/request/client/offer/flat) ile CForm/UForm/RForm/OForm/FlatForm sayfalarının tamamını imperative DOM inşasıyla üretir; alan değerleri `formData.dynamicF` yapısında toplanıp parent savecallback'e devredilir.
- **Özet/log bileşen çifti:** RForm = RSummary + RequestLogTimeline + OfferRequestTable; OForm = OfferSummary + OfferLogTimeline — offer/request simetrik mimari, ancak Offer/Request timeline'ları ve bildirim merge mantığı 2-3 kez kopyalanmış durumda.
- **Kritik bulgular:** Swal modal HTML'lerinde escape'siz kullanıcı verisi (XSS riski); OfferTable.vue kullanılmıyor görünüyor; Form.vue monoliti + Math.random şifre + devre dışı VKN algoritması; `doc_trans_offer_accepted` vs `approved` key tutarsızlığı; DashboardNotifications'da tıklanamayan bildirimler ve boş `href=""` footer linkleri.
