# Frontend Sayfaları (resources/js/pages) — Dosya Haritası

> Kapsam: 18 dosya · Tamamı okundu.
> Route kaynağı: `resources/js/router/index.js` — tüm sayfalar `/coalpanel` altında `CoalPanel` layout'unun child route'larıdır. `NotFound.vue` router'da import edilmiyor (catch-all route yorum satırında).
> Ortak kalıp: Liste sayfaları `pickletable` (Ajax tablo), form sayfaları `@/components/coalparts/Form.vue` (dinamik form, `formDataStore` üzerinden doldurulur). API çağrıları `@/lib/pickle` (Plib) ile `/api/v1/...` altına gider. Durum değişiklikleri `/api/v1/trans/set-status` (belge) ve `/api/v1/trans/set-file-status*` (dosya) endpoint'lerine POST edilir; `op_key` + not + id gövdesi kullanılır. Yetki kontrolleri `authStore.permissions` (`per-XX-YY`) ve `authStore.typeKey` (`op-pert-admin` / `op-pert-reseller`) üzerinden yapılır.

## Ekran → Route Eşleşmesi (özet)

| Route | Name | Sayfa | Kim görür |
|---|---|---|---|
| `/coalpanel` | CIndex | Dashboard.vue | Tümü (role göre Admin/Client panel) |
| `/coalpanel/client` | CList | Client/CList.vue | Müşteri yönetimi yetkisi |
| `/coalpanel/client/form/:id?` | CForm | Client/CForm.vue | per-06-02 (oluşturma) |
| `/coalpanel/request` | RequestList | Request/RList.vue | Tümü (aksiyonlar per-05-02) |
| `/coalpanel/request/form/:id?` | RequestForm | Request/RForm.vue | Admin düzenler, tedarikçi özet görür |
| `/coalpanel/offer` | OList | Offer/OList.vue | Admin + tedarikçi |
| `/coalpanel/offer/form/:id?` | OForm | Offer/OForm.vue | Tedarikçi form, admin özet |
| `/coalpanel/documents` | DList | Documents/DList.vue | per-07 (aksiyonlar per-07-02) |
| `/coalpanel/users` | UList | Users/UList.vue | per-04-02 (aksiyonlar) |
| `/coalpanel/users/form/:id?` | UForm | Users/UForm.vue | Kullanıcı yönetimi |
| `/coalpanel/roles` | Roles | Roles/Roles.vue | Rol şablonları |
| `/coalpanel/notifications/settings` | NSettings | Notifications/NSettings.vue | per-00-01 (kaydetme) |
| `/coalpanel/sistem-loglari` | LList | Logs/LList.vue | Sistem logları |
| `/coalpanel/notifikasyon-loglari` | NList | NotificationLogs/NList.vue | Bildirim logları |
| `/coalpanel/treeexample` | TreeExample | treeTest.vue | Demo/test |
| `/coalpanel/example` | ExampleList | Example/FlatList.vue | Örnek (apartman yönetimi mirası) |
| `/coalpanel/example/form/:id?` | ExampleForm | Example/FlatForm.vue | Örnek form |
| — (route yok) | — | NotFound.vue | Ölü dosya |

---

## `resources/js/pages/NotFound.vue` (~17 satır)
- **Amaç:** "Page Not found" metni gösteren statik 404 bileşeni.
- **Semboller:** yalnız template + `breadcrumbs` meta.
- **İlişkiler:** Router'daki catch-all (`/:pathMatch(.*)*`) route'u yorum satırı — dosya hiçbir route'a bağlı değil.
- **Bulgular:** Ölü kod; router tanımı kapalı olduğu için ekrana hiç düşmez. Bilinmeyen URL'lerde kullanıcı boş sayfa görür.

## `resources/js/pages/coalsystem/Dashboard.vue` (~76 satır)
- **Amaç:** Panel giriş sayfası; kullanıcı tipine göre farklı dashboard bileşeni gösterir.
- **Semboller:** `mounted` (layout DOM manipülasyonu: `kt_content`, `kt_header`), `beforeUnmount`.
- **İlişkiler:** Route `CIndex` (`/coalpanel`). Bileşenler: `@/components/Dashboard/Admin.vue` (tüm roller), `@/components/Dashboard/Client.vue` (sadece `op-pert-reseller`), `Default.vue` import edilmiş ama template'te kullanılmıyor. Doğrudan API çağrısı yok; veri alt bileşenlerden gelir.
- **İşlevler:** Rol bazlı özet panel görüntüleme (istatistikler alt bileşenlerde).
- **Bulgular:** `Default` import'u kullanılmıyor (ölü import). Layout DOM'unu doğrudan `getElementById` ile kurcalıyor; `kt_content`/`kt_header` yoksa sessiz hata. `PickleTable`/`Swal` import'ları da kullanılmıyor.

## `resources/js/pages/coalsystem/treeTest.vue` (~83 satır)
- **Amaç:** `TreeModal` kütüphanesinin demo/test sayfası; ağaç checkbox seçimini gösterir.
- **Semboller:** `selectAll`, `clearAll`, `setDefaults`, `openNativeTree` (template'te butonu yok), `embedNativeTree`.
- **İlişkiler:** Route `TreeExample` (`/coalpanel/treeexample`). `@/lib/treeModal.js` kullanır. API yok.
- **Bulgular:** Geliştirici demo'su; production'da menüde yer almamalı. `openNativeTree` metoduna template'ten erişim yok.

## `resources/js/pages/coalsystem/Client/CList.vue` (~609 satır)
- **Amaç:** Müşteri (firma) listesi; kart görünümlü responsive tablo.
- **Semboller:** `buildTestTable`, `searchTable`, `resetSearch`, `exportTable`, `formatClientCard`, `handleResponsiveTable`.
- **API:** `GET /api/v1/table/documents` (filter: `form-type=op-doc-client-form`, `type=op-doc-client`), `DELETE /api/v1/document/:id`, `POST /api/v1/export/clients` (Excel, yeni sekme).
- **İşlevler:** Müşteri arama/sıfırlama, Excel export, "Firma Oluştur" (per-06-02), düzenle → `CForm`, silme (per-06-02, SweetAlert onaylı). Mobilde ilk sütun kart görünümüne döner.
- **İlişkiler:** `CForm`'a router ile gider. `main_attr` JSON'u `rowFormatter`'da satıra açılır.
- **Bulgular:** Silme işlemi belgeyi "tamamen" siliyor — geri alınamaz, sunucu tarafında soft-delete olup olmadığı belirsiz. `VMasker`/`Datepicker` import'ları kullanılmıyor.

## `resources/js/pages/coalsystem/Client/CForm.vue` (~182 satır)
- **Amaç:** Müşteri firması oluşturma/düzenleme formu; tedarikçi (`op-pert-reseller`) için kendi firma bilgilerini doldurma ekranı olarak da çalışır.
- **Semboller:** `submitForm`, `submitStatus(type)` (kabul/red).
- **API:** `GET /api/v1/document/:id` (düzenleme verisi), `POST/PUT /api/v1/document[/:id]` (kaydet, `typeKey=op-doc-client`, dosyalar FormData ile), `POST /api/v1/trans/set-file-status-all` (tüm dosyaları `doc_file_accepted`/`doc_file_rejected` yap, not ile).
- **İşlevler:** Dinamik form (`Form` bileşeni, `formtypes="op-doc-client-form"`), dosya yükleme, firma belgelerini toplu kabul/red (not girilerek). Tedarikçi için `canProceed=false` uyarı bandı; kayıt sonrası `authStore.getPermissions()` tazelenir.
- **İlişkiler:** `@/components/coalparts/Form.vue`, `useFormDataStore` (mevcut veriyi forma taşır). `DList`'ten "İlişki" butonu ile de buraya gelinir.
- **Bulgular:** `submitStatus` sonrası `window.location.reload()` — SPA deneyimini kırıyor. Form validasyonu sadece client-side (`plib.checkForm`).

## `resources/js/pages/coalsystem/Documents/DList.vue` (~849 satır)
- **Amaç:** Sistemdeki tüm belge dosyalarının (müşteri/teklif evrakları) listesi; durum onay akışı.
- **Semboller:** `buildTestTable`, `formatDocumentCard`, `createDetailModalContent`, `showDetailModal`, `parseRowStatus`, `exportTable`.
- **API:** `GET /api/v1/table/document_files` (groupBy `relation_detail`), `POST /api/v1/trans/set-file-status` (tek dosya durumu: `doc_file_accepted` / `doc_file_rejected`, not ile), `POST /api/v1/export/documents` (Excel).
- **İşlevler:** Dosya arama, Excel export, detay modalı (ilişki, eklenme tarihi, durum, kontrol eden, not, geçmiş versiyonlar `/order-file/:qnid` linkleri), dosyayı yeni sekmede açma (`/order-file/:id`), ilişkili forma atlama (`CForm`/`OForm`, per-07), durum değiştirme (per-07-02: kabul/red + not). Gruplama: ilişki başlığına göre (`X firma — N belge`).
- **Bulgular:** Durum badge'i her durumda yeşil görünüyor (CSS sabit) — reddedilmiş dosya da yeşil pill; yanıltıcı. `JSON.parse(columnData)` (last_status) try/catch'siz; bozuk veride patlar. Dosya açma `window.open` popup engelleyiciye takılabilir.

## `resources/js/pages/coalsystem/Example/FlatList.vue` (~422 satır)
- **Amaç:** Örnek/demo "Daire" (flat) listesi — apartman yönetim sisteminden miras kalmış şablon kodu.
- **Semboller:** `buildTestTable`, `transmodal(type,id,text)` (bakiye/ödeme modalı).
- **API:** `GET /api/v1/table/documents` (filter: `op-doc-flat-form`/`op-doc-flat`), `DELETE /api/v1/document/:id`, `GET /api/v1/trans/prepare-payment` (kurlar + kasa hesapları), `POST /api/v1/trans/set-payment` (ödeme/bakiye kaydı, dosya + dönem + tutar).
- **İşlevler:** Daire listeleme, düzenle (`ExampleForm`), sil, borçlandır (`addbalance`)/ödeme al (`income`) modalı: işlem tipi (kira/yakıt/aidat/...), kasa, tutar (VMasker para maskesi), kur, dönem (ay/yıl Datepicker), dekont dosyası, açıklama. "Elden ödeme" switch'i.
- **Bulgular:** Kömür sistemiyle ilgisiz örnek kod; toolbar'da kırık dış link (`yts.gdzelektrik.com.tr`). Arama input'u var ama search butonu/fonksiyonu bağlı değil. `ExampleForm` route adı ile eski `FlatList` referansı karışık.

## `resources/js/pages/coalsystem/Example/FlatForm.vue` (~115 satır)
- **Amaç:** Örnek "Daire" oluşturma/düzenleme formu.
- **Semboller:** `submitForm`.
- **API:** `GET /api/v1/document/:id`, `POST/PUT /api/v1/document[/:id]` (`typeKey=op-doc-flat`).
- **İlişkiler:** `Form` bileşeni (`op-doc-flat-form`); template'te `<Transactions :id="id"/>` kullanılıyor ama bileşen import edilmemiş → çalışmaz.
- **Bulgular:** `Transactions` import'u eksik (çalışma zamanında Vue resolve hatası/uyarısı). Başarı sonrası `{ name: 'FlatList' }` route'una gidiyor ama route adı `ExampleList` → yönlendirme çalışmaz. Örnek kod.

## `resources/js/pages/coalsystem/Logs/LList.vue` (~301 satır)
- **Amaç:** Sistem (kullanıcı işlem) logları izleme ekranı.
- **Semboller:** `buildTestTable` (içinde `jsonToDetails`, `escapeHtml` helper'ları), `searchTable`, `exportTable`.
- **API:** `GET /api/v1/table/userlog`, `POST /api/v1/export/userlogs` (Excel).
- **İşlevler:** Log arama (tip, belge tipi, isim, kullanıcı, rol, IP, tarih sütunları; sütun bazlı arama açık), "Açıklama" hücresine tıklayınca JSON log detayını ağaç (collapsible `<details>`) görünümünde modal ile gösterme; tümünü genişlet/daralt, JSON kopyala.
- **Bulgular:** Sadece okuma ekranı; silme/düzenleme yok. Modal içi inline `<style>` enjeksiyonu tekrar tekrar DOM'a eklenir (birikim).

## `resources/js/pages/coalsystem/NotificationLogs/NList.vue` (~345 satır)
- **Amaç:** Gönderilen bildirimlerin (mail vs.) kayıtları ve tekrar gönderim.
- **Semboller:** `buildTestTable` (LList ile aynı JSON-viewer kalıbı), `exportTable`.
- **API:** `GET /api/v1/table/notificationlog`, `POST /api/v1/export/notificationlogs` (Excel), `POST /api/v1/notificationlog/:id/retrigger` (yeniden gönderim; `plib` yerine doğrudan `fetch` + CSRF meta token ile).
- **İşlevler:** Bildirim arama, durum ikonu (sent ✓ / hata ✗), detay modalında `detail`+`payload` JSON birleşimi görüntüleme, kopyalama, başarısız bildirimi "Yeniden Gönder" butonuyla tetikleme.
- **Bulgular:** `retrigger` için `plib.request` yerine el ile `fetch` — tutarsızlık; hata/yetki yönetimi farklı davranabilir. Durum sütununda `order:true` ama `key:'#'` (sıralama fiilen çalışmaz).

## `resources/js/pages/coalsystem/Notifications/NSettings.vue` (~507 satır)
- **Amaç:** Bildirim gruplarına kullanıcı atama ekranı ("Notifikasyon Şablonları" başlıklı).
- **Semboller:** `loadNotificationGroups`, `loadAssignedNotificationUsers`, `selectGroup`, `addUserToSelectedGroup`, `removeGroupMember`, `formCallback` (kaydet), `buildTestTable`.
- **API:** `GET /api/v1/notification/groups` (grup listesi: id/title/op_key), `GET /api/v1/notification-users` (grup→üye eşleşmesi), `POST /api/v1/set-notification-groups` (tüm atamalar: `[{person_id, op_keys[]}]`), `GET /api/v1/table/user` (kullanıcı tablosu, filter `type_key=op-pert-admin`).
- **İşlevler:** Sol sütundan bildirim grubu seçme, ortadaki kullanıcı tablosunda satıra tıklayarak gruba üye ekleme, sağ sütundan üye kaldırma, `AppFab` (per-00-01) ile tüm atamaları toplu kaydetme. `touchedUserIds` ile silinen üyelerin de boş `op_keys` ile gönderilmesi sağlanır.
- **İlişkiler:** `@/components/coalparts/AppFab.vue`, `usePermissionDataStore` (import edilmiş ama sadece setup'ta expose).
- **Bulgular:** Kullanıcı tablosu sadece `op-pert-admin` tipini listeler — tedarikçi kullanıcıları gruplara eklenemez. `Simplebar` import'u kullanılmıyor. Başlıkta "şablon" deniyor ama ekran üye ataması yapıyor (islev-isim uyumsuzluğu).

## `resources/js/pages/coalsystem/Offer/OList.vue` (~982 satır)
- **Amaç:** Teklif listesi — tedarikçi kendi tekliflerini, admin tüm teklifleri görür; ana teklif iş merkezi.
- **Semboller:** `buildTestTable`, `formatOfferCard`, `openStatusChangeModal`, `giveOffer`, `searchTable`, `exportTable`.
- **API:** `GET /api/v1/table/documents` (filter `op-doc-offer-form`/`op-doc-offer`), `POST /api/v1/trans/set-status` (durum: `doc_trans_offer_review`/`_revision`/`_rejected`/`_approved` + not), `DELETE /api/v1/document/:qnid`, `POST /api/v1/export/offers` (Excel).
- **İşlevler:** Teklif arama, Excel export, "Teklif Ver" (bağımsız: tür seçimi modalı — Teklif Formu / Dosya Yükleme; `SYS_CODE` hidden input'tan hedef santral okunur), "Talep'e Teklif Ver" (RequestList'e yönlendirme), detay görüntüleme (`OForm?view=1`), düzenleme (sadece `doc_trans_created`/`_draft`/`_revision` durumları; admin per-05-02 kısıtı bypass eder), silme (per-08-02), durum değiştirme (per-05-02). Durumlar `key**title**note` string formatında kodlanmış. Mobil kart görünümü.
- **Bulgular:** Durum kodlaması `**` ayracıyla string içinde — kırılgan. `giveOffer`'daki `SYS_CODE` input'u template'te yok → bağımsız teklifte `target_type` `undefined` olabilir [doğrulanmadı — input `Form`/layout'tan gelebilir]. "Teklif gönder" akışı yorum satırında; tedarikçi teklifi kaydedince otomatik `created` durumunda kalıyor. `addional` alan adı yazım hatası (additional).

## `resources/js/pages/coalsystem/Offer/OForm.vue` (~743 satır)
- **Amaç:** Teklif verme/düzenleme formu + admin için salt-okunur teklif özeti ve onay akışı.
- **Semboller:** `submitForm`, `statusAction`, `requestRevision`, `approveOffer`, `rejectOffer`, `downloadPdf`, `loadOfferVersionHistory`, `versionSelect`, `parseStatusItems`, `currentOfferStatusKey`.
- **API:** `GET /api/v1/document/:id` (teklif + ilişkili talep), `POST/PUT /api/v1/document[/:id]` (kaydet, `typeKey=op-doc-offer`), `POST /api/v1/trans/set-status` (onay/red/revizyon + admin açınca otomatik `doc_trans_offer_review`), `POST /api/v1/table/userlog` (versiyon geçmişi: `doc_qnid` filtreli `userlogs` modeli), `POST /export/offer` (PDF, yeni sekme).
- **İşlevler:** Talep üzerinden teklif verme (`navigationStore.routeParams` ile `request_id`/`offer_type` taşınır; bağımsız teklifte talep bilgisi istenir), form ile teklif doldurma + dosya yükleme, PDF indirme, admin görünümünde `OfferSummary` (onay/red/revizyon talep butonları), versiyon seçici ile eski teklif snapshot'ı önizleme, durum geçmişi timeline'ı (`OfferLogTimeline`). `canResponse=false` tedarikçi engellenir (firma onaysız). Düzenleme sadece taslak/revizyon durumlarında ve per-08-02 tedarikçide.
- **İlişkiler:** `@/components/coalparts/Form.vue`, `@/components/Offer/OfferSummary.vue`, `@/components/Offer/OfferLogTimeline.vue`, `useFormDataStore`, `useNavigationStore.routeParams` (RequestList/RForm'dan gelen bağlam).
- **Bulgular:** Admin sayfayı açınca otomatik durum değişikliği (`doc_trans_offer_review`) yapılıyor — görüntüleme eylemi veri değiştiriyor (yan etki). `routeParams` sayfa yenilemede kaybolur → form `request_id` olmadan RequestList'e geri atar (kod bunu yönetiyor). `console.log` kalmış.

## `resources/js/pages/coalsystem/Request/RList.vue` (~1140 satır)
- **Amaç:** Kömür talep/ihale listesi; admin talepleri yönetir, tedarikçi açık ihaleleri görür.
- **Semboller:** `buildTestTable`, `formatRequestCard` (mobil kart), `toggleExpired`, `searchTable`, `exportTable`.
- **API:** `GET /api/v1/table/documents` (filter `op-doc-request-form`/`op-doc-request` + `showExpired`), `POST /api/v1/trans/set-status` (`doc_trans_request_start`/`_end`/`_cancelled` + not), `DELETE /api/v1/document/:id`, `POST /api/v1/export/requests` (Excel).
- **İşlevler:** Talep arama, "Vakti Geçen İhaleleri Göster" switch'i (server-side `showExpired` filtresi), Excel export, "Talep Oluştur" (per-05-02) → `RequestForm`, admin aksiyonları: Başlat (ihale aç), Düzenle, Sil, durum değiştir (Başladı/Tamamlandı/İptal + not). Tedarikçi (`op-pert-reseller`) sadece `doc_trans_request_start` durumundaki taleplerin detayını görebilir. Sütunlar: talep kodu, başlık, santral (`her_ikisi=1` ise "Her İki Sistem"), sipariş kapsamı, ihale/sevkiyat tarihleri, durum.
- **Bulgular:** breadcrumb'ta `/coalpanel/requests` yazıyor ama gerçek route `/coalpanel/request`. Silme onayı var ama ihalenin teklif bağlantıları kontrol edilmiyor (sunucuya bağlı). Mobilde kart, masaüstünde tablo — iki ayrı aksiyon implementasyonu (kod tekrarı, drift riski).

## `resources/js/pages/coalsystem/Request/RForm.vue` (~164 satır)
- **Amaç:** Talep oluşturma/düzenleme formu; tedarikçiye salt-okunur talep özeti.
- **Semboller:** `submitForm`, `addOffer`.
- **API:** `GET /api/v1/document/:id`, `POST/PUT /api/v1/document[/:id]` (`typeKey=op-doc-request`).
- **İşlevler:** Admin: dinamik talep formu (`op-doc-request-form`) kaydetme; mevcut talep altında gelen teklifler tablosu (`OfferRequestTable`) + talebe teklif ekleme (tür seçimi modalı → `OForm`'a `request_id` + talep entities'i ile). Tedarikçi: `RSummary` (talep detayı + "Teklif Ver" butonu). Log timeline (`RequestLogTimeline`).
- **İlişkiler:** `@/components/coalparts/Form.vue`, `RSummary.vue`, `RequestLogTimeline.vue`, `@/components/Offer/OfferRequestTable.vue`. `OForm` ile `navigationStore.routeParams` üzerinden konuşur.
- **Bulgular:** `addOffer`'da `rawData.formFormat['op-doc-request-form']` boşsa patlar (id olmadan çağrılamaz ama korunmasız). Toast mesajı hata durumunda da "İşlem Tamamlandı" fallback'i kullanıyor.

## `resources/js/pages/coalsystem/Roles/Roles.vue` (~401 satır)
- **Amaç:** Rol şablonları yönetimi — izin kümelerini isimli şablonlar halinde kaydetme.
- **Semboller:** `loadGroups`, `persistGroups`, `addGroup`, `editRole`, `removeRole`, `viewRole`, `cancelEdit`, `renderPermissionTree`, `togglePermission`, `selectAllGroupPermissions` (template'te kullanılmıyor), computed `permissionItems`/`permissionMap`/`selectedArray`.
- **API:** `GET /api/v1/roles/templates`, `POST /api/v1/roles/templates` (tüm şablonlar JSON), `DELETE /api/v1/roles/templates/:id`.
- **İşlevler:** Şablon adı + açıklama girme, izin ağacından (`TreeModal.render`, izinler `usePermissionDataStore`'dan) checkbox seçimi, şablon kaydet/güncelle/sil/detay görüntüle. `immutableRoles` (Tedarikçi, Satınalma Personeli, Satınalma KeyUser, Admin, Super Admin) silinemez; listede yoksa client-side eklenir.
- **Bulgular:** Persist modeli "tüm listeyi POST" — eşzamanlı iki admin düzenlerse son yazan kazanır (lost update). `op_key` slug'ı client'ta üretiliyor (Türkçe karakter regex'i şüpheli: `ğ` iki kez, `ü` iki kez). Kayıt sonrası toast mesajı `editingRoleId` null edildikten sonra okunduğu için her zaman "kaydedildi" der (güncellemede bile) — kod sırası hatası.

## `resources/js/pages/coalsystem/Users/UList.vue` (~286 satır)
- **Amaç:** Kullanıcı listesi ve hesap yönetimi.
- **Semboller:** `buildTestTable`, `exportTable`, `searchTable` (iki kez tanımlı — ikincisi eziyor), `resetSearch`.
- **API:** `GET /api/v1/table/user`, `POST /api/v1/auth/resetusercradentals/:id` (şifre sıfırla, geçici şifre maili), `DELETE /api/v1/persons/:id` (pasife çekme), `POST /api/v1/export/users` (Excel).
- **İşlevler:** Kullanıcı arama, Excel export, "Kullanıcı Oluştur" → `UForm`, şifre sıfırlama (onay modalı; satırda `needs_refresh=1` olur), düzenleme, silme (DELETE sonrası satırı `user_status=0` olarak işaretler — soft delete). Durum gösterimi: Aktif(1)/Pasif(0)/Onay Bekliyor(-1) + "Şifre Yenileme Bekliyor" etiketi. Aksiyonlar per-04-02 gerektirir.
- **Bulgular:** `searchTable` metodu duplicate. "Sil" aslında pasife alıyor; UI'da çöp ikonu ama kalıcı silme değil. `useRouter` import'u kullanılmıyor.

## `resources/js/pages/coalsystem/Users/UForm.vue` (~219 satır)
- **Amaç:** Kullanıcı oluşturma/düzenleme formu (personel + login bilgileri + izinler).
- **Semboller:** `submitForm` (parola validasyonları dahil).
- **API:** `GET /api/v1/persons/:id` (kullanıcı + permissions/contacts/clients JSON alanları), `POST/PUT /api/v1/users[/:id]` (kaydet; `data`=fields, `alldata`=tam form, dosyalar FormData).
- **İşlevler:** Dinamik form (`op-doc-user-form`); düzenlemede permissions/contacts/clients JSON alanları parse edilip `formDataStore`'a açılır. Parola kuralları: kullanıcı adı varsa parola zorunlu; min 8 karakter + büyük/küçük harf + rakam + özel karakter (`=!-@._*`); parola tekrar eşleşmesi. İzinler `op_key` listesine çevrilip gönderilir. Kayıt sonrası per-04-01 varsa `UList`'e, yoksa sayfa reload.
- **Bulgular:** Parola regex'i özel karakter setini dar tutuyor (`=!\-@._*` dışındakiler reddedilir). Hata durumunda doğrudan DOM'a `is-invalid` sınıfı basılıyor. `formtypes` hesaplaması `formKey+'-form'` → `op-doc-user-form` (doğru) ama `typeKey='op-pert-admin'` sabit — tedarikçi kullanıcısı oluşturma bu formdan yapılamıyor gibi [backend belirliyor olabilir].

---

## Alan Özeti

- **Rol:** `pages/` katmanı, `/coalpanel` SPA'sının tüm ekranlarını barındırır. Her ekran ya "liste" (PickleTable + Ajax) ya "form" (dinamik `Form.vue` + `formDataStore`) kalıbındadır; sayfalar ince birer orkestratördür — asıl mantık component'ler (`coalparts/Form`, `Offer/*`, `Dashboard/*`) ve `lib/pickle`'dadır.
- **Ana veri akışı:** Liste → `GET /api/v1/table/<model>` (PickleTable `initialFilter` ile tip seçimi) → satır aksiyonu → ya route değişimi (`XForm`) ya `POST /api/v1/trans/set-status` (durum makinesi, `op_key**title**note` formatı) ya `DELETE /api/v1/document/:id`. Form → `GET /api/v1/document/:id` → `formDataStore` → `Form.vue` → `POST/PUT /api/v1/document` (FormData + dosyalar). Export'lar `plib.openTab('POST', '/api/v1/export/*', filter)` ile yeni sekmede Excel indirir.
- **İş akışı omurgası:** Talep (RList/RForm, admin açar) → Teklif (OList/OForm, tedarikçi verir) → Admin onay/red/revizyon (`set-status`) → Müşteri/belge evrak onayı (CForm `set-file-status-all`, DList `set-file-status`). Kullanıcı/rol/bildirim ekranları bu akışın yönetim tarafını besler.
- **Yetki modeli:** UI tarafında `authStore.permissions` (`per-04` kullanıcı, `per-05` talep, `per-06` müşteri, `per-07` belge, `per-08` teklif) ve `typeKey` (`op-pert-admin` vs `op-pert-reseller`) ile buton gizleme; tedarikçi pek çok ekranda salt-okunur özet görür.
- **Teknik borç:** `Example/*` ve `treeTest` demo/ölü kod; `NotFound` route'suz; `Dashboard`'da kullanılmayan import'lar; durum kodlamasının `**` ayracıyla string içinde taşınması; mobil kart/masaüstü tablo aksiyonlarının çift implementasyonu; admin sayfa açılışında otomatik durum değişikliği (OForm) gibi yan etkiler.
