# Modeller (app/Models) — Dosya Haritası
> Kapsam: 16 dosya · Tamamı okundu.

Sistem genelinde ortak kalıplar:
- Çoğu modelde statik `tableList($obj)` metodu var: ham SQL (PostgreSQL) ile sayfalı/filtreli liste üretir; controller'lardan tablo endpoint'leri için çağrılır. Filtre değerleri `noInject(strip_tags(...))` ile temizlenir ama SQL yine string birleştirme ile kurulur (SQL injection riski teknik borç olarak duruyor).
- `creating` event'i ile `qnid = Str::uuid()` ve `grp_code = $GLOBALS['SYS_CODE'] ?? 'CATES'` atanır (multi-tenant grup kodu: CATES / Yatağan ayrımı).
- `Sys_options` tabanlı "option" mimarisi (op_key/group_key) tüm tipleri (belge tipi, işlem tipi, personel tipi, bildirim tipi) tek tabloda tutar; `Sys_con_ops` + `Sys_con_entities` EAV (entity-attribute-value) yapısıyla dinamik form alanlarını saklar.

## `app/Models/ActiveSession.php` (~30 satır)
- **Amaç:** Kullanıcının aktif oturumlarını (cihaz/IP bazlı) takip eder; force-logout ve yetki versiyonu kontrolü için kullanılır.
- **Tablo:** `active_sessions`
- **Fillable:** user_id, token_id, session_id, ip, user_agent, current_status, permission_version, last_seen, force_logout, force_logout_reason, force_logout_at
- **Casts:** current_status→array, force_logout→boolean, force_logout_at→datetime
- **Semboller:** sadece property tanımları; metot yok.
- **İlişkiler:** Tanımlı Eloquent ilişkisi yok; `User::tableList` içindeki `is_active` alt sorgusu bu tabloyu kullanır (last_seen >= now() - 1 dakika). Sanctum token'ı ile `token_id` üzerinden dolaylı bağlı.
- **Bulgular:** `current_status` JSON olarak session'daki müşteri kısıtlarını (clientQnidList vb.) taşıyor gibi — Documents::tableList'teki `session('currentStatus')` mantığıyla paralel.

## `app/Models/Currencies.php` (~11 satır)
- **Amaç:** Döviz kuru tablosu; işlem tutarlarını sistem para birimine (SYS_CUR) çevirmek için kullanılır.
- **Tablo:** `currencies` (varsayılan çoğul ad)
- **Fillable/Casts:** tanımsız (boş model, sadece HasFactory).
- **İlişkiler:** Eloquent ilişkisi yok; `Transactions::tableList` içinde ham SQL ile `currencies where target_cur = cr.code` alt sorgusu olarak kullanılır (kur çarpanı `amount` kolonundan okunur).
- **Bulgular:** Model tamamen boş; kur verisi yalnızca raw SQL üzerinden okunuyor — tek yönüyle "lookup tablosu".

## `app/Models/Document_files.php` (~199 satır)
- **Amaç:** Belgelere/işlemlere bağlı yüklenmiş dosyaları (evrak) temsil eder; dosya yolu şifreli saklanır.
- **Tablo:** `document_files` (varsayılan)
- **Fillable:** description (şifreli dosya yolu), type_id, selected_at, title, qnid, grp_code
- **Rules:** description, type_id required
- **Semboller:** `boot()` (creating: qnid+grp_code; deleting: dosyayı storage'dan siler), `tableList($obj)`
- **Şifreleme:** `deleting` event'inde `App\Providers\EncryptionProvider` ile `description` decrypt edilip `documents/<path>` public disk'ten silinir → description kolonu şifreli dosya yolu tutar.
- **İlişkiler (raw SQL üzerinden):** type_id→sys_options; relation_id→documents; sys_con_entities (entity_value = dosya id, entity_tag ile dosya rolü); transactions + user_logs + users + persons (last_status alt sorgusu).
- **tableList:** sys_options (dosya tipi), sys_con_entities, documents, transactions join'leri; `op-offer_otherdocs_file` hariç tutulur; `old_versions` (aynı entity_tag'e sahip eski dosyalar) ve `last_status` (son işlem + yapan kişi) JSON alt sorguları üretir; `relation_detail` aynı conn_id'deki tüm entity'leri JSON döner.
- **Bulgular:** deleting içinde yorum satırına alınmış transaction temizliği (ölü kod). SQL string birleştirme (noInject olsa da) → injection riski. `grp_code` multi-tenant filtresi where'de zorunlu.

## `app/Models/Documents.php` (~398 satır)
- **Amaç:** Sistemin çekirdek varlığı — talep (request), teklif (offer), müşteri (client), sözleşme (flat/target/period) gibi tüm "belge" tiplerini tek tabloda tutar; tip `type_id→sys_options.op_key` ile ayrılır.
- **Tablo:** `documents`
- **Fillable:** status, person_id, parent_type_id, parent_id, type_id, grp_code, starting_at, ending_at, title, qnid
- **Rules:** tanımsız (boş dizi benzeri liste, validation yapılmıyor)
- **Semboller:** `boot()` (creating: qnid + grp_code), `tableList($obj)`
- **tableList (en karmaşık sorgu):**
  - Zorunlu `type` filtresi (formType: op-doc-request / op-doc-offer / op-doc-client …) olmadan boş döner.
  - `parent_type_id = 0` → sadece ana belgeler (alt belgeler başka tabloya bağlı olanlar).
  - Reseller kısıtı: `session('currentStatus')['clientQnidList']` varsa müşteri qnid listesine göre filtre; reseller + liste yoksa tamamen boş sonuç.
  - grp_code tenant filtresi: SYS_CODE'a göre; `her_ikisi` flag'i ile iki santralde ortak talepler.
  - `form-type` filtresi: LEFT JOIN LATERAL ile sys_con_entities'ten form attribute'larını JSON (`main_attr`) olarak çeker; op-doc-offer-form için ek olarak bağlı talebin attribute'larını (`request_attr` → addional) çeker.
  - Özel filtreler: showExpired (contract_end_date), today-ended, is-rodevans (request_type=1), monthly, month-period, status-null/status-not, transactions (son transaction op_key), attr (entity_tag/value), free/all.
- **İlişkiler:** type_id→sys_options; sys_con_ops (main_id) + sys_con_entities (conn_id) EAV; transactions (target_id, son durum); parent_id/parent_type_id self-reference.
- **Bulgular:** Filtre değerleri SQL'e doğrudan basılıyor (noInject/strip_tags dışında parametre yok) — kritik injection yüzeyi. `$columns['status']` gibi alanlar formType'a göre string birleştirme ile kuruluyor. session bağımlılığı model katmanında (mimari borç).

## `app/Models/NotificationLog.php` (~132 satır)
- **Amaç:** Gönderilen/bekleyen/hatalı bildirimlerin (mail vb.) log kaydı.
- **Tablo:** `notification_logs` (varsayılan)
- **Fillable:** type, to, subject, body, status, error_message, detail, payload, attempts, last_attempt_at, sent_at
- **Casts:** detail→array, payload→array, last_attempt_at/sent_at→datetime
- **Sabitler:** STATUS_PENDING='pending', STATUS_SENT='sent', STATUS_ERROR='error'
- **Semboller:** `tableList($obj)` — join'siz basit liste; filtrelerde `upper(trim(CAST(...)))` ile case-insensitive arama (Türkçe büyük harf için mb_strtoupper).
- **İlişkiler:** Yok (bağımsız log tablosu).
- **Bulgular:** —

## `app/Models/Persons.php` (~159 satır)
- **Amaç:** Gerçek/tüzel kişi kayıtları; her kullanıcı bir person'a bağlıdır.
- **Tablo:** `persons` (varsayılan)
- **Fillable:** name, surname, title, spec_code, sys_code, parent_id (2 kez yazılmış), type_id, phone, address, email_approved, approved, status, qnid, grp_code
- **Rules:** surname, name, type_id required
- **Semboller:** `boot()` (creating: qnid + grp_code; deleting event'i yorum satırı — ölü kod), `tableList($obj)`
- **tableList:** sys_options (kişi tipi) + users join'i; upper/case-insensitive filtreleme.
- **İlişkiler:** type_id→sys_options; users.person_id→persons.id (1-1); parent_id self-reference (firma hiyerarşisi).
- **Bulgular:** fillable'da `parent_id` mükerrer. Yorum satırlı deleting cascade'i (Users, Person_con_ops, User_con_ops silme) — aktif değil, orphaned kayıt riski.

## `app/Models/SysNotificationType.php` (~60 satır)
- **Amaç:** Bildirim tip tanımları (kod/başlık/kategori); eski `storage/entities/notification_details.json` dosyasının DB karşılığı.
- **Tablo:** `sys_notification_types`
- **Fillable:** code, title, description, category, metadata
- **Casts:** metadata→json, timestamps→datetime
- **Semboller:** `getByCode($code)`, `getByCategory($category)` (statik lookup), `toJsonFormat()` (eski JSON yapısına geri dönüşüm)
- **İlişkiler:** Yok (katalog tablosu).
- **Bulgular:** —

## `app/Models/SysPermissionCatalog.php` (~60 satır)
- **Amaç:** İzin kodu kataloğu; eski `storage/entities/role_details.json`'un DB karşılığı.
- **Tablo:** `sys_permission_catalogs`
- **Fillable:** code, title, category, subcategory, metadata
- **Casts:** metadata→json, timestamps→datetime
- **Semboller:** `getByCode`, `getByCategory`, `toJsonFormat()`
- **İlişkiler:** User::tableList'teki `permissions` alt sorgusu bu tabloyu sys_con_entities JSON dizisiyle eşleştirir (jsonb_array_elements_text).
- **Bulgular:** —

## `app/Models/SysRoleTemplate.php` (~55 satır)
- **Amaç:** Rol şablonları + izin listeleri; eski `coal_roles_templates.json`'un DB karşılığı. users.role kolonu bu tablonun `op_key`'ine referans verir.
- **Tablo:** `sys_role_templates`
- **Fillable:** name, permissions, description, immutable, op_key
- **Casts:** permissions→json, immutable→boolean, timestamps→datetime
- **Semboller:** `audits()` (hasMany), `toJsonFormat()`
- **İlişkiler:** hasMany→SysRoleTemplateAudit (role_template_id); users.role ↔ op_key (FK değil, string eşleşme); UserLog::tableList join'i.
- **Bulgular:** users.role FK değil; tutarsız op_key durumunda join'ler sessizce boş döner.

## `app/Models/SysRoleTemplateAudit.php` (~66 satır)
- **Amaç:** Rol şablonu değişikliklerinin audit kaydı.
- **Tablo:** `sys_role_template_audit`
- **Fillable:** role_template_id, action, old_data, new_data, user_id
- **Casts:** old_data/new_data→json, timestamps→datetime
- **Semboller:** `roleTemplate()` (belongsTo), `user()` (belongsTo User), `logChange()` (statik factory; user yoksa sanctum/auth'dan alır)
- **İlişkiler:** belongsTo→SysRoleTemplate, belongsTo→User
- **Bulgular:** —

## `app/Models/Sys_con_entities.php` (~36 satır)
- **Amaç:** EAV yapısının "değer" tablosu — dinamik form alanlarının (entity_tag/entity_value) saklandığı yer; sistemin en kritik tablolarından biri.
- **Tablo:** `sys_con_entities` (varsayılan)
- **Fillable:** conn_id, table_tag, entity_tag, entity_value, qnid
- **Rules:** conn_id, entity_tag, entity_value required
- **Semboller:** `boot()` (creating: qnid)
- **İlişkiler:** conn_id→sys_con_ops.id (form instance); table_tag hedef tablo ipucu; entity_value hem değer hem başka kayıtlara (document id, qnid) referans olarak kullanılır (polimorfik, FK yok).
- **Bulgular:** FK'siz polimorfik referanslar (entity_value::int cast'leri raw SQL'de) → bütünlük uygulama katmanına kalmış.

## `app/Models/Sys_con_ops.php` (~24 satır)
- **Amaç:** EAV yapısının "bağlantı/başlık" tablosu — bir ana kayda (main_id) bağlı form instance'ını temsil eder; entity satırları buna bağlanır.
- **Tablo:** `sys_con_ops` (varsayılan)
- **Fillable:** conn_id, main_id, type_id, sub_type_id
- **Rules:** conn_id, main_id, type_id required
- **İlişkiler:** main_id→documents.id (veya sys_options.id, users.id — bağlama göre); type_id→sys_options (form tipi, örn. op-doc-offer-form); conn_id üst bağlantı (0 = kök form); sys_con_entities.conn_id→id.
- **Bulgular:** Polimorfik main_id (FK yok).

## `app/Models/Sys_options.php` (~138 satır)
- **Amaç:** Sistemin evrensel tip/sözlük tablosu — belge tipleri, işlem tipleri, kişi tipleri, alan tanımları hepsi op_key/group_key ile tek tabloda.
- **Tablo:** `sys_options` (varsayılan)
- **Fillable:** op_key, group_key, title, ttitle, ctitle, status
- **Semboller:** `tableList($obj)` — parent (self join), sys_con_ops/sys_con_entities üzerinden side-area entity'leri (form alan yerleşimi); özel filtreler: group_key (in listesi), normal_list (distinct, entity kolonlarını düşürür), is_filled, free/all.
- **İlişkiler:** parent_id self-reference; neredeyse tüm tabloların type_id'si buraya bakar (documents, transactions, persons, user_logs, document_files).
- **Bulgular:** group_key filtresi `str_replace("''","'",...)` ile doğrudan SQL'e basılıyor — riskli.

## `app/Models/Transactions.php` (~243 satır)
- **Amaç:** Hem belge durum geçişleri (workflow history) hem finansal işlemler (ödeme) için kullanılan kayıt tablosu.
- **Tablo:** `transactions` (varsayılan)
- **Fillable:** status, trans_id, op_id, type_id, target_id, log_id, description, note, created_at, qnid, grp_code
- **Rules:** ref_id, type_id, op_id, target_id required
- **Semboller:** `boot()` (creating: qnid + grp_code), `tableList($obj)`
- **tableList:** Ödeme listesi (group_key='op-trans-payment'); sys_cur/sys_amount (currencies ile kur çevrimi, sign=0 negatif); trans_files (document_files JSON); conn_info/main_info (ilgili belgenin form attribute'ları JSON); `totals` ile negatif/pozitif toplamlar ayrıca hesaplanır.
- **İlişkiler:** type_id→sys_options (işlem tipi); cur_id→sys_options (para birimi); target_id/rel_id→documents; log_id→user_logs (işlemi yapan); SYS_CUR env değeriyle sistem para birimi.
- **Bulgular:** `ref_id` rule'da var ama fillable'da yok (tutarsızlık). Durum geçmişi sorguları `order by t.id desc limit 1` ile son transaction'ı alıyor — id sırasına bağımlılık.

## `app/Models/User.php` (~190 satır)
- **Amaç:** Kimlik doğrulama modeli (Sanctum); person'a bağlı login hesabı.
- **Tablo:** `users` (varsayılan)
- **Fillable:** name, email, password, parent_id, person_id, qnid, status, grp_code, role, needs_refresh
- **Hidden:** password, remember_token
- **Casts:** email_verified_at→datetime, password→hashed
- **Semboller:** `boot()` (creating: qnid + grp_code), `tableList($obj)`
- **tableList:** persons tablosu üzerinden (i = persons!) users'a join — kullanıcı listesi kişi kartlarından okunur; is_active (active_sessions son 1 dk); role_title (sys_role_templates); permissions (sys_con_entities jsonb dizisinden sys_permission_catalogs'a çözümleme, op-doc-user-permission-form). `kadir@kontent.com.tr` listeden hariç tutulur (hardcoded).
- **İlişkiler:** person_id→persons.id; role→sys_role_templates.op_key (string); active_sessions.user_id; SysRoleTemplateAudit.user_id.
- **Bulgular:** Hardcoded e-posta dışlaması. tableList persons üzerinden koşuyor ama User modelinde — kafa karıştırıcı isimlendirme (i = persons, u = users).

## `app/Models/UserLog.php` (~155 satır)
- **Amaç:** Kullanıcı aksiyon logları (kim, neyi, ne zaman değiştirdi); before/after JSON description içinde.
- **Tablo:** `user_logs`
- **Fillable:** sys_code, user_id, type_id, relation_id, relation, ip, description
- **Casts:** sys_code/user_id/type_id/relation_id→integer
- **Semboller:** `boot()` (creating: sys_code + request IP otomatik), `tableList($obj)`
- **tableList:** sys_options (aksiyon tipi) + users + persons + sys_role_templates join'i; created_at Europe/Istanbul'a çevrilir; form_type description JSON'ından (after.document.op_key) çözümlenir; özel filtreler: relation_id (+relation), doc_qnid (description jsonb içinde arama).
- **İlişkiler:** user_id→users.id; type_id→sys_options; transactions.log_id→user_logs.id.
- **Bulgular:** description jsonb cast edilerek filtreleniyor — büyük log tablosunda indekslenemeyen sorgular (performans borcu).

## Alan Özeti

**Rol:** Model katmanı iki dünyadan oluşuyor: (1) klasik Eloquent kaynakları (User, Persons, ActiveSession, NotificationLog, rol/izin katalogları), (2) `tableList()` statik metotlarıyla ham PostgreSQL üreten liste motorları ve EAV çekirdeği (Documents + Sys_con_ops + Sys_con_entities + Sys_options).

**ER ilişkileri (metin diyagramı):**

```
persons 1───1 users (users.person_id)
users   1───n active_sessions (user_id)
users   1───n user_logs (user_id) 1───n transactions (log_id)
users.role ──(string op_key)── sys_role_templates 1───n sys_role_template_audit
documents n───1 sys_options (type_id → op_key: op-doc-*)
documents 1───n sys_con_ops (main_id) 1───n sys_con_entities (conn_id)   [EAV: form alanları]
documents 1───n document_files (relation_id) ; document_files.type_id → sys_options
documents 1───n transactions (target_id / rel_id) ; transactions.type_id/cur_id → sys_options
transactions (kur çevrimi) ── currencies (target_cur, amount)
sys_options self-ref (parent_id) ; tüm type_id'ler buraya bakar
users izinleri: sys_con_entities(entity_value jsonb[]) ── sys_permission_catalogs.code
sys_notification_types / notification_logs : bağımsız katalog + log
```

**Ana veri akışı:** Liste isteği → Controller → `Model::tableList($obj)` (scale/order/filter) → ham SQL (join + JSON alt sorgular) → `{data, pageCount, totalCount, last_page}` sözleşmesi. Form verisi asla belge tablosunda kolon olarak durmaz; `sys_con_ops` (form instance) + `sys_con_entities` (alan/değer) çiftine yazılır ve `entity_tag`/`op_key` ile anlamlandırılır.

**Kritik bulgular:**
1. Tüm `tableList` metotlarında SQL string birleştirme (noInject/strip_tags tek savunma) — geniş injection yüzeyi; özellikle `Sys_options` group_key ve `Documents` attr/month-period filtreleri.
2. Multi-tenant ayrımı `$GLOBALS['SYS_CODE']` global state'iyle yapılıyor; `creating` hook'ları ve where'ler buna bağımlı.
3. `Document_files.description` şifreli dosya yolu tutar (EncryptionProvider); silme event'i fiziksel dosyayı da kaldırır.
4. EAV referansları (entity_value→document id/qnid, main_id→polimorfik) FK'siz; bütünlük tamamen uygulama koduna emanet.
5. Ölü kod/tutarsızlıklar: Persons deleting cascade'i yorum satırı, fillable'da mükerrer parent_id; Transactions ref_id rule/fillable uyumsuzluğu; User::tableList'te hardcoded e-posta dışlaması.
