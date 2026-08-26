# Config & Veritabanı — Dosya Haritası
> Kapsam: config/*.php (12) + bootstrap (2) + database/migrations (24) + factories (2) + seeders (5) + .env.example + .env.testing + phpunit.xml = **47 dosya** · Tamamı okundu. (Atamada 27 migration denmişti; diskte 24 adet mevcut.)

---

## config/ (12 dosya)

### `config/app.php` (~126 satır)
- **Amaç:** Uygulama temel ayarları (isim, env, debug, url, timezone, locale, şifreleme, bakım modu).
- **Semboller:** `name`, `env`, `debug`, `url`, `timezone`, `locale`, `fallback_locale`, `faker_locale`, `cipher` (AES-256-CBC), `key`, `previous_keys`, `maintenance`.
- **İlişkiler:** Tüm uygulama `config('app.*')` üzerinden okur.
- **Bulgular:** Standart Laravel 12; `APP_LOCALE=tr` (env'de). Standart dışı değer yok.

### `config/auth.php` (~115 satır)
- **Amaç:** Kimlik doğrulama guard/provider/password-reset ayarları.
- **Semboller:** `defaults.guard=web`, `guards.web` (session), `providers.users` (eloquent, `App\Models\User`), `passwords.users` (expire 60, throttle 60), `password_timeout=10800`.
- **İlişkiler:** `App\Models\User`; Sanctum `guard => ['web']` ile birlikte çalışır.
- **Bulgular:** — (standart).

### `config/cache.php` (~107 satır)
- **Amaç:** Cache store tanımları.
- **Semboller:** `default` = `CACHE_STORE` env (varsayılan `database`); stores: array, database, file, memcached, redis, dynamodb, octane; `prefix`.
- **İlişkiler:** `database` store → `cache` tablosu (migration 0001_01_01_000001).
- **Bulgular:** — (standart Laravel 12 iskeleti).

### `config/cors.php` (~34 satır)
- **Amaç:** CORS politikası.
- **Semboller:** `paths=['api/*','sanctum/csrf-cookie']`, `allowed_origins` env'den (`CORS_ALLOWED_ORIGINS`, varsayılan `komurtedarik.cates.com.tr,komurtedarik.yatagantermik.com.tr`), `supports_credentials=true`, methods/headers `*`.
- **İlişkiler:** SPA frontend (Vue) ile API arası; `.env`'de çift domain tanımı.
- **Bulgular:** `allowed_origins` değerleri scheme'siz (`https://` yok) — Laravel CORS origin eşleşmesi scheme bekler; pratikte wildcard/regex olmadığından eşleşmeme riski.

### `config/database.php` (~170 satır)
- **Amaç:** DB bağlantıları + Redis.
- **Semboller:** `default` = `DB_CONNECTION` (vars. `sqlite`); connections: sqlite, mysql, mariadb, pgsql, sqlsrv; `migrations.table='migrations'`; `redis` (phpredis, default + cache DB'leri).
- **İlişkiler:** Prod `.env.example` → pgsql (`coalpass` DB, host 172.20.11.32:5431).
- **Bulgular:** — (standart).

### `config/filesystems.php` (~76 satır)
- **Amaç:** Disk tanımları (local, public, s3) + `storage:link` hedefi.
- **Bulgular:** — (standart; dosya yüklemeleri local diske).

### `config/logging.php` (~139 satır)
- **Amaç:** Log kanalları.
- **Semboller:** channels: stack, single, daily, **`cron`** (özel — `storage/logs/cron.log`, daily, 2 gün), slack, papertrail, stderr, syslog, errorlog, null, emergency.
- **Bulgular:** Standart dışı: `cron` kanalı eklenmiş (scheduler/job logları için).

### `config/mail.php` (~133 satır)
- **Amaç:** Mailer tanımları + relay bayrağı.
- **Semboller:** mailers: smtp, ses, postmark, sendmail, log, array, failover, roundrobin; `from`; **`use_relay`** (env `MAIL_USE_RELAY`).
- **Bulgular:**
  - ⚠️ **smtp mailer'da TLS doğrulaması tamamen kapalı**: `verify_peer=false`, `verify_peer_name=false`, `allow_self_signed=true` (hem üst seviye hem `stream.ssl`). MITM riski; kodda "use only for development/testing" uyarısı var ama prod `.env.example` da bu mailer'ı kullanıyor.
  - Standart dışı: `use_relay` anahtarı — `MailService` relay (intmail.aydemenerji.com.tr) kullanımını config-cache güvenli okumak için eklenmiş.

### `config/queue.php` (~112 satır)
- **Amaç:** Kuyruk bağlantıları + batching + failed_jobs.
- **Semboller:** `default` = `QUEUE_CONNECTION` (vars. `database`); connections: sync, database, beanstalkd, sqs, redis; `batching` → `job_batches`; `failed` → `failed_jobs` (database-uuids).
- **Bulgular:** — (standart; `.env` yorumu `queue:work --queue=emails` kuyruğunu işaret ediyor).

### `config/sanctum.php` (~83 satır)
- **Amaç:** Sanctum SPA token/cookie ayarları.
- **Semboller:** `stateful` (env `SANCTUM_STATEFUL_DOMAINS`), `guard=['web']`, `expiration=null`, `token_prefix`, `middleware` (authenticate_session, encrypt_cookies, validate_csrf_token).
- **Bulgular:**
  - ⚠️ **`stateful` varsayılanı `'*'` (wildcard)** — eski localhost listesi yorum satırına alınmış. Tüm origin'ler stateful sayılır; CSRF koruması zaten bootstrap'te global devre dışı (aşağıya bak).
  - `expiration=null` → token'lar süresiz.

### `config/services.php` (~52 satır)
- **Amaç:** 3. parti servis kimlikleri.
- **Semboller:** `postmark`, `ses`, `slack`, **`recaptcha`** (site_key, secret, verify_url, test_token, min_score=0.5), **`iletisimmakinesi`** (SMS gateway: base_url, username, password, api_key, vendor_id, customer_code, service_id=7, originator_id, client_id).
- **Bulgular:** Standart dışı: `recaptcha` ve `iletisimmakinesi` blokları projeye özel.

### `config/session.php` (~217 satır)
- **Amaç:** Oturum ayarları.
- **Semboller:** `driver` = `SESSION_DRIVER` (vars. `database` → `sessions` tablosu), `lifetime=120`, `encrypt=false`, `lottery=[2,100]`, `same_site=lax`, `http_only=true`.
- **Bulgular:** — (standart).

---

## bootstrap/ (2 dosya)

### `bootstrap/app.php` (~32 satır)
- **Amaç:** Laravel 12 uygulama tanımı — routing, middleware, exceptions.
- **Semboller:** `Application::configure()` → `withRouting(web, api, commands, health:'/up')`, `withMiddleware`, `withExceptions`.
- **İlişkiler:** `routes/web.php`, `routes/api.php`, `routes/console.php`; `App\Http\Middleware\ParsePutMultipart`, `App\Http\Middleware\CspMiddleware`.
- **Bulgular:**
  - 🔴 **`validateCsrfTokens(except: ['*'])` — CSRF doğrulaması TÜM route'lar için kapalı.** Sanctum stateful `'*'` ile birleşince cookie-tabanlı istekler CSRF'a açık.
  - `trustProxies('*', tüm X-Forwarded header'ları)` — proxy arkasında IP spoofing riski (bilinçli tercih olabilir, reverse proxy kesin kontrol altında olmalı).
  - `ParsePutMultipart` global append; web grubuna `AddLinkHeadersForPreloadedAssets` + `statefulApi()`.
  - Yorum satırı: `//$middleware->append(StartSession::class);` (ölü).

### `bootstrap/providers.php` (~6 satır)
- **Amaç:** Service provider kayıt listesi.
- **Semboller:** `App\Providers\AppServiceProvider`, `App\Providers\DocumentServiceProvider`.
- **Bulgular:** `PersonsServiceProvider` burada kayıtlı DEĞİL ama `UserSeeder` doğrudan `new` ile kullanıyor (service-provider-as-service anti-pattern'i).

---

## database/migrations/ (24 dosya)

### `0001_01_01_000000_create_users_table.php` (~64 satır) — 3 tablo
- **Amaç:** Kullanıcı + parola sıfırlama + session tabloları.
- **Tablolar:**
  - **`users`**: `id` PK; `status` smallint null def 1; `needs_refresh` smallint null def false; `person_id` int null def 0; `grp_code` varchar(100) null def '-'; `name` varchar; `role` varchar; `email` varchar **unique**; `email_verified_at` timestamp null; `password` varchar; `remember_token`; `current_team_id` bigint null (FK'siz); `profile_photo_path` varchar(2048) null; `avatar` varchar null def '-'; `bg_image` varchar null def '-'; `qnid` text null; timestamps.
  - **`password_reset_tokens`**: `email` varchar PK; `token` varchar; `created_at` timestamp null.
  - **`sessions`**: `id` varchar PK; `user_id` bigint null **index**; `ip_address` varchar(45) null; `user_agent` text null; `payload` longtext; `last_activity` int **index**.
- **İlişkiler:** `App\Models\User`; Jetstream kalıntısı kolonlar (current_team_id, profile_photo_path).
- **Bulgular:** `person_id` FK'siz (mantıksal olarak `persons.id`'ye bağlı); `needs_refresh` smallint ama default `false` (boolean) — tip tutarsızlığı.

### `0001_01_01_000001_create_cache_table.php` (~36 satır) — 2 tablo
- **`cache`**: `key` varchar PK; `value` mediumtext; `expiration` int.
- **`cache_locks`**: `key` varchar PK; `owner` varchar; `expiration` int.
- **Bulgular:** — (standart; `CACHE_STORE=database` bunu kullanır).

### `0001_01_01_000002_create_jobs_table.php` (~57 satır) — 3 tablo
- **`jobs`**: `id` PK; `queue` varchar **index**; `payload` longtext; `attempts` tinyint unsigned; `reserved_at` uint null; `available_at` uint; `created_at` uint.
- **`job_batches`**: `id` varchar PK; `name`; `total_jobs` int; `pending_jobs` int; `failed_jobs` int; `failed_job_ids` longtext; `options` mediumtext null; `cancelled_at` int null; `created_at` int; `finished_at` int null.
- **`failed_jobs`**: `id` PK; `uuid` varchar **unique**; `connection` text; `queue` text; `payload` longtext; `exception` longtext; `failed_at` timestamp useCurrent.
- **Bulgular:** — (standart).

### `2022_12_05_073600_create_sys_options_table.php` (~48 satır) — 1 tablo
- **Amaç:** Sistem genelinde tip/seçenek/işlem sözlüğü (EAV-benzeri "op" kataloğu).
- **`sys_options`**: `id` PK; `status` smallint def 1; `parent_id` int def 0 (self-ref, FK'siz); `title` varchar(150); `code` varchar(150) def '-'; `ttitle` varchar(150) (hedef tablo adı); `ctitle` varchar(150) (hedef kolon adı); `op_key` varchar(150) def '-'; `group_key` varchar(150) def '-'; `description` varchar(250) def '-'; `icon` varchar(50) def '-'; timestamps. **Index:** `sys_options_1` (group_key).
- **İlişkiler:** `App\Models\Sys_options`; SysSeeder bu tabloyu doldurur; `ttitle`/`ctitle` ile başka tabloların type_id kolonlarına işaret eder (dinamik sözlük).
- **Bulgular:** ⚠️ Migration `use App\Models\Sys_options;` import ediyor ama kullanmıyor (ölü import). `op_key`'de unique index yok — SysSeeder `first()` ile kontrol ederek idempotent kalıyor ama DB seviyesinde tekillik garantisi yok.

### `2022_12_05_075026_create_persons_table.php` (~43 satır)
- **`persons`**: `id` PK; `status` smallint def true; `type_id` smallint def 0; `email_approved` smallint def 0; `approved` smallint def 0; `parent_id` int def 0; `spec_code` varchar(150) null def '-'; `title` varchar(50) null def '-'; `name` varchar(50); `surname` varchar(50) null def '-'; `phone` varchar(50) def '-'; `address` varchar(250) null def '-'; `grp_code` varchar(100) null def '-'; `qnid` text null; **`balance` float(15,3) def 0**; timestamps.
- **İlişkiler:** `App\Models\Persons`, `users.person_id`, PersonsFactory.
- **Bulgular:** ⚠️ Para için `float` kullanımı (yuvarlama hatası riski; `transactions` decimal iken tutarsız). `status` smallint ama default boolean `true`.

### `2022_12_05_080953_create_user_logs_table.php` (~41 satır)
- **`user_logs`**: `id` PK; `sys_code` text def '-'; `user_id` int (FK'siz); `type_id` int; `relation_id` int def 0; `ip` varchar; `relation` text def '-'; `description` text; timestamps.
- **İlişkiler:** `App\Models\User_logs` (varsayım); log tipleri `sys_options` (`ttitle='User_logs'`) üzerinden.
- **Bulgular:** Yorum satırında Postgres'e özel generated column örnekleri (`description::json`) var — aktif değil; `description`'ın JSON tuttuğu ima ediliyor ama kolon text.

### `2022_12_05_083533_create_transactions_table.php` (~50 satır)
- **`transactions`**: `id` PK; `op_id` smallint def 0; `status` smallint def 1; `trans_id` int def 0; `type_id` int; `target_id` int; `rel_id` int def 0; `cur_id` int def 0; `sign` int def 0; `log_id` int def 0; **`amount` decimal def 0** (ondalık basamak belirtilmemiş!); `period` varchar(7) def '-'; `description` varchar(300) def '-'; `note` varchar(300) def '-'; `grp_code` varchar(100) null def '-'; `qnid` text null; timestamps. **Index:** `transindex_1` (grp_code, period, target_id); `transindex_2` (target_id).
- **İlişkiler:** `App\Models\Transactions`; tipler `sys_options` (`op-trans*` group_key).
- **Bulgular:** ⚠️ `decimal('amount')` precision/scale belirtilmemiş → DB'ye göre (MySQL: decimal(10,0)) kuruş/ondalık kaybolabilir. Prod pgsql'de numeric default farklı davranır.

### `2023_02_15_134911_create_document_files_table.php` (~38 satır)
- **`document_files`**: `id` PK; `status` smallint def 1; `type_id` int; `replaced_id` int def 0; `conn_id` int def 0 ("for nothing for now" yorumu); `relation_id` int def 0 (raporlama için); `relation` varchar(20) def '-'; `title` varchar(250) def '-'; `description` text def '-'; `grp_code` varchar(100) null def '-'; `qnid` text null; `selected_at` timestamp useCurrent; timestamps.
- **İlişkiler:** `App\Models\Document_files`; dosya tipleri `sys_options` (`op-file-types`).
- **Bulgular:** `varchar('20')` / `varchar('250')` — uzunluklar string literal olarak verilmiş (çalışır ama alışılmadık). Dosya yolu/içerik kolonu YOK — muhtemelen `sys_con_entities` üzerinde tutuluyor.

### `2023_11_20_184651_create_currencies_table.php` (~31 satır)
- **`currencies`**: `id` PK; `main_cur_id` int def 0; `target_cur_id` int def 0; `main_cur` varchar(5); `target_cur` varchar(5); `amount` decimal(15,3) def 0 (kur oranı); timestamps.
- **Bulgular:** —

### `2024_04_04_132901_create_documents_table.php` (~52 satır)
- **`documents`**: `id` PK; `status` int def 1; `parent_type_id` int def 0 (eski versiyon işareti); `parent_id` int def 0; `type_id` int def 0; `person_id` **text** def '-' (persons.qnid tutuyor, yorumda belirtilmiş); `title` varchar(300) def '-'; `grp_code` varchar(100) null def 'CATES'; `qnid` text null; `starting_at` timestamp null; `ending_at` timestamp null; timestamps. **Index:** `docindex_1` (grp_code).
- **İlişkiler:** `App\Models\Documents`; DocumentServiceProvider.
- **Bulgular:** ⚠️ `person_id` text + string default — `persons.qnid` ile gevşek bağ (FK'siz). Başında sqlite için `PRAGMA cache_size = 0` koşulu var.

### `2024_04_04_133010_create_sys_con_ops_table.php` (~36 satır)
- **`sys_con_ops`**: `id` PK; `status` smallint def 1; `main_id` int; `conn_id` int; `type_id` int; `sub_type_id` int def 0; `description` varchar(300) def '-'; timestamps. **Index:** `sys_con_ops_1` (conn_id, main_id).
- **Amaç:** Doküman ↔ doküman/kişi bağlantı kayıtları (connection/operation); entity detayları `sys_con_entities`'te.
- **Bulgular:** —

### `2024_04_28_125548_create_personal_access_tokens_table.php` (~36 satır)
- **`personal_access_tokens`**: `id` PK; `tokenable_type`+`tokenable_id` (morphs, index); `name` varchar; `token` varchar(64) **unique**; `abilities` text null; `last_used_at` timestamp null; `expires_at` timestamp null; timestamps.
- **Bulgular:** — (Sanctum standart).

### `2024_04_28_125548_create_teams_table.php` (~28 satır)
- **`teams`**: `id` PK; `user_id` bigint **index** (FK'siz); `name` varchar; `personal_team` boolean; timestamps.
- **Bulgular:** ⚠️ Jetstream kalıntısı — uygulamada takım özelliği kullanılmıyor gibi (ölü tablo adayı).

### `2024_04_28_125549_create_team_user_table.php` (~31 satır)
- **`team_user`**: `id` PK; `team_id` bigint; `user_id` bigint; `role` varchar null; timestamps. **Unique:** (team_id, user_id).
- **Bulgular:** Jetstream kalıntısı.

### `2024_04_28_125550_create_team_invitations_table.php` (~33 satır)
- **`team_invitations`**: `id` PK; `team_id` bigint **FK → teams, cascadeOnDelete**; `email` varchar; `role` varchar null; timestamps. **Unique:** (team_id, email).
- **Bulgular:** Jetstream kalıntısı; projedeki tek gerçek FK'lerden biri.

### `2024_05_06_110912_create_sys_con_entities_table.php` (~32 satır)
- **`sys_con_entities`**: `id` PK; `conn_id` int (→ `sys_con_ops.id`, FK'siz); `table_tag` varchar(100); `entity_tag` varchar(100); `entity_value` text; `qnid` text null; timestamps.
- **Amaç:** EAV entity deposu — dinamik form alanları (title, currency, dosya yolları vb.) burada key-value tutulur.
- **Bulgular:** `conn_id` üzerinde index yok — entity sorguları full scan riski.

### `2026_04_11_000001_create_sys_role_templates_table.php` (~31 satır)
- **`sys_role_templates`**: `id` PK; `name` varchar **unique**; `permissions` json null; `description` varchar null; `op_key` varchar **unique** + **index**; `immutable` boolean def false; timestamps. **Index:** name, created_at.
- **İlişkiler:** `App\Models\SysRoleTemplate`; SysRoleTemplateSeeder + `storage/entities/coal_roles_templates.json`.
- **Bulgular:** Yorum "immutable" dese de `immutable=false` default; seeder `id` 'immutable-' ile başlıyorsa true yapıyor.

### `2026_04_11_000002_create_sys_permission_catalogs_table.php` (~29 satır)
- **`sys_permission_catalogs`**: `id` PK; `code` varchar **unique** + index (ör. `per-04-02`); `title` varchar; `category` varchar null + **index**; `subcategory` varchar null; `metadata` json null; timestamps.
- **İlişkiler:** `App\Models\SysPermissionCatalog`; `storage/entities/role_details.json`.
- **Bulgular:** —

### `2026_04_11_000003_create_sys_notification_types_table.php` (~30 satır)
- **`sys_notification_types`**: `id` PK; `code` varchar **unique** + index (ör. `notif-00`); `title` varchar; `description` text null; `category` varchar null + index; `metadata` json null; timestamps.
- **Bulgular:** —

### `2026_04_11_000004_create_sys_role_template_audit_table.php` (~35 satır)
- **`sys_role_template_audit`**: `id` PK; `role_template_id` bigint **FK → sys_role_templates, cascade delete** + index; `action` enum(created|updated|deleted); `old_data` json null; `new_data` json null; `user_id` bigint null **FK → users, set null**; timestamps. **Index:** created_at.
- **Bulgular:** — (düzgün FK kullanan nadir tablo).

### `2026_04_15_000000_create_notification_logs_table.php` (~31 satır)
- **`notification_logs`**: `id` PK; `type` varchar; `to` varchar null; `subject` varchar null; `body` longtext null; `status` varchar def 'pending'; `error_message` text null; `detail` json null; `payload` json null; `attempts` smallint unsigned def 0; `last_attempt_at` timestamp null; `sent_at` timestamp null; timestamps.
- **Amaç:** Mail/SMS bildirim kuyruğu log'u (İletişim Makinesi + SMTP).
- **Bulgular:** `type`/`status`'ta index yok (pending taramaları büyüyünce yavaşlar).

### `2026_04_24_000000_create_active_sessions_table.php` (~33 satır)
- **`active_sessions`**: `id` bigIncrements; `user_id` bigint unsigned **index**; `token_id` varchar null **index**; `session_id` varchar null **index**; `ip` varchar null; `user_agent` varchar null; `current_status` text null; `permission_version` varchar null **index**; `last_seen` timestamp useCurrent; timestamps.
- **Amaç:** Aktif oturum takibi + yetki versiyonu (yetki değişince oturum tazeleme).
- **Bulgular:** `users`'taki `needs_refresh` kolonuyla aynı mekanizmanın parçası.

### `2026_04_25_000000_add_force_logout_to_active_sessions_table.php` (~28 satır)
- **Değişiklik:** `active_sessions`'a ekler: `force_logout` boolean def false **index**; `force_logout_reason` text null; `force_logout_at` timestamp null **index**. Down'da dropColumn.
- **Bulgular:** ⚠️ `->after(...)` kullanımı MySQL'e özgü — pgsql'de yok sayılır (zararsız ama anlamsız).

---

## database/factories/ (2 dosya)

### `database/factories/PersonsFactory.php` (~37 satır)
- **Amaç:** `App\Models\Persons` test verisi.
- **Semboller:** `definition()` → qnid (unique uuid), name, surname, type_id=1, status=1.
- **Bulgular:** —

### `database/factories/TeamFactory.php` (~26 satır)
- **Amaç:** `Team` modeli için factory (Jetstream kalıntısı).
- **Semboller:** `definition()` → name (company), user_id (User::factory), personal_team=true.
- **Bulgular:** Kullanılmayan Jetstream artığı olabilir.

---

## database/seeders/ (5 dosya)

### `database/seeders/DatabaseSeeder.php` (~23 satır)
- **Amaç:** Varsayılan seeder — sadece `test@example.com` / "Test User" oluşturur.
- **Bulgular:** ⚠️ `SysSeeder`/`UserSeeder`/`SysRoleTemplateSeeder`'ı **çağırmıyor** — `db:seed` tek başına sistemi çalışır hale getirmez; seeder'lar ayrı ayrı çağrılmalı.

### `database/seeders/SysSeeder.php` (~689 satır)
- **Amaç:** `sys_options` tablosuna sistemin tüm tip/işlem sözlüğünü yükler. `seed($item,$groupKey,$parentId)` recursive — `op_key` varsa atlar (idempotent), `childs` varsa parent_id ile alt kayıt açar.
- **Yüklenen gruplar (array_merge ile birleşip tek döngüde):**
  - `$start` — Persons tipleri: `op-per-1` Yüklenici, `op-per-2` İş Birimi (group `op-per-types`).
  - `$apartment` — `CATES` Cates Sistem (group `op-apt-types`).
  - `$logs` — 24 adet user_logs tipi (group `op-logs`): log-login, log-lock, log-login-failed, log-login-code-failed, log-logout, log-role-update, log-user-status-update, log-tender-update, log-document-status-update, log-sys-op-update, log-tender-period-close, log-file-added, log-file-status-trans, log-leave-added, log-file-edited, log-tender-start, log-person-update, log-notification-group-update, log-personnel-add-multiple, log-client-add, log-client-update, log-shift-updated, log-post, log-put, log-delete.
  - `$trans` — 19 transaction tipi: `doc_trans_created`; teklif akışı `doc_trans_offer_{draft,sended,review,revision,revised,approved,rejected}`; talep akışı `doc_trans_request_{start,end,cancelled}`; proje `doc_trans_project_{start,end,sikinti,payment}`; dosya onay `doc_file_{waiting,rejected,refreshed,accepted}`.
  - `$forms` — 11 form tipi (group `op-doc-forms`): op-doc-main, op-doc-main-test, op-doc-request-form, op-doc-offer-form, op-doc-client-form, op-doc-main-file, op-doc-trans-file, op-doc-user-{contact,permission,notification,client}-form.
  - `$formConnTypes` — form-main, form-file, personnel-main (sys_con_ops sub_type_id).
  - `$personTypes` — `op-pert-admin` Yönetici, `op-pert-reseller` Tedarikçi (group `op-pert`).
  - `$documentTypes` — op-doc-offer Teklif, op-doc-request Talep, op-doc-client-main Cari Ana Kart, op-doc-client Cari, op-doc-flat Flat (group `op-doc`).
  - `$curTypes` — TRY/EUR/USD/GBP ikonlarıyla (group `op-cur-types`).
  - `$fileTypes` — op-offer_otherdocs_file, op-cont_iban_file, op-cont_odasicil_file, op-cont_vergi_file, op-cont_imza_file (group `op-file-types`, ttitle=document_files).
- **İlişkiler:** `App\Models\Sys_options`. `ttitle`+`ctitle` çifti başka tabloların hangi kolonunu tipliendirdiğini belirtir (ör. `Persons/type_id`).
- **Bulgular:** Ölü kod: büyük yorum bloğu (eski seed yaklaşımı). `op-doc-flat`/Flat tipleri eski apartman projesinden kalma (DataSeeder ile birlikte ölü miras).

### `database/seeders/DataSeeder.php` (~120 satır)
- **Amaç:** Örnek içerik üretimi — ANCAK `run()` boş (iki çağrı da yorum satırı).
- **Semboller:** `seedFlats()` (A/B blok × 8 daire, `op-doc-flat-form` ile DocumentServiceProvider::registerContent), `seedSafes()` (4 kasa dokümanı: Nakit/Aidat/Yakıt/Kira, `op-doc-target-form`).
- **İlişkiler:** `App\Providers\DocumentServiceProvider::registerContent(0,$data,[])`.
- **Bulgular:** Ölü/legacy kod — önceki apartman yönetim projesinden miras; `op-doc-target` tipi SysSeeder'da tanımlı değil. `(new DocumentServiceProvider())->registerContent(...)` — provider'ı servis gibi doğrudan new'leme anti-pattern'i.

### `database/seeders/UserSeeder.php` (~73 satır)
- **Amaç:** 9 admin kullanıcı + person kaydı oluşturur.
- **Semboller:** `$users` dizisi (op_key, ad, email, parola, telefon, rol) → her biri için `PersonsServiceProvider::setPerson(0,$data)`; `permissions` = `SysRoleTemplate::where('op_key', rol)->first()->permissions`.
- **Yüklenen veri:** 9 super-admin (`immutable-super-admin`): kadir@kontent.com.tr, kbbozat41@hotmail.com, hilal@kontent.com.tr, tolga.topaloglu / arin.oksas / selin.savas / oguzhan.yukaci / volkan.gunduz / sila.temel @aydemenerji.com.tr.
- **İlişkiler:** `PersonsServiceProvider`, `SysRoleTemplate` — **SysRoleTemplateSeeder'dan SONRA çalışmalı** (yoksa `->permissions` null → `?? []`).
- **Bulgular:** 🔴 **Hardcoded düz metin parolalar** (`Kadir412.`, `Kontent412.`) ve gerçek telefon numaraları repoda. SysRoleTemplate bağımlılığı gizli (sıralama garantisi yok).

### `database/seeders/SysRoleTemplateSeeder.php` (~168 satır)
- **Amaç:** Rol şablonları + yetki kataloğu + bildirim tiplerini JSON dosyalarından yükler (hepsi `updateOrCreate` → idempotent).
- **Semboller:** `seedRoleTemplates()` ← `storage/entities/coal_roles_templates.json`; `seedPermissionCatalog()` + recursive `processPermissionHierarchy()` ← `storage/entities/role_details.json`; `seedNotificationTypes()` ← `storage/entities/notification_details.json`.
- **Yüklenen veri (JSON içerikleri):**
  - **Rol şablonları (5):** `immutable-reseller` Tedarikçi (5 yetki), `immutable-satınalma-personeli` (6), `immutable-satınalma-keyuser` Satınalma KeyUser (6), `immutable-admin` Admin (2), `immutable-super-admin` Super Admin (19). `op_key` 'immutable-' ile başlıyorsa `immutable=true`.
  - **Yetki kataloğu (hiyerarşik, 6 ana + 13 alt):** per-00 Bildirimler (per-00-01), per-04 Kontrol Paneli (04-01 Kullanıcı Listeleme, 04-02 Kullanıcı Oluşturma/Düzenleme, 04-03 Rol ve Yetki Yönetimi, 04-04 Sistem Logları), per-05 Talep Yönetimi (05-01/05-02), per-06 Firma Yönetimi (06-01/06-02), per-07 Dökümanlar (07-01/07-02), per-08 Teklifler (08-01/08-02). Category = ilk iki segment (`per-04`), subcategory = 3. segment; parent_code metadata'da.
  - **Bildirim tipleri (4):** notif-00 Tedarikçi Kayıt Başvurusu, notif-01 Tedarikçi bilgi değişikliği, notif-02 Yeni teklif, notif-03 Teklif revize (hepsi category `op-notif`).
- **Bulgular:** JSON dosyası yoksa sadece warn edip sessiz geçiyor (prod'da eksik veri fark edilmeyebilir).

---

## Ortam / Test dosyaları

### `.env.example` (~118 satır)
- **Amaç:** Örnek ortam. pgsql prod DB (172.20.11.32:5431, coalpass), APP_URL komurtedarik.cates.com.tr, locale tr, session/cache/queue = database, MAIL_USE_RELAY=true (intmail.aydemenerji.com.tr:25).
- **Bulgular:** 🔴 **GERÇEK SECRET'LER COMMIT'LENMİŞ:** APP_KEY (base64), DB parolası (`coaltest`), Gmail app password (`avqldsnvlydficcn`), İletişim Makinesi API key + parola (`l23o5y...`, `jnviwf`). `.env.example` olması gereken şablon değil, çalışan kopya. ReCAPTCHA anahtarları Google'ın public test anahtarları (zararsız). Çift `CSP_ADDITIONAL_HOSTS` tanımı (tekrar).

### `.env.testing` (~142 satır)
- **Amaç:** Test ortamı — pgsql localhost:5431 (b2x/b2x), APP_URL localhost:8000, MAIL_USE_RELAY=false, SYS_CUR/SYS_CUR_INFO para ayarları (TRY,USD,EUR,GBP), OLLAMA_CHAT_MODEL/EMBED_MODEL (llama3.1, qwen3-embedding) — AI entegrasyon denemesi.
- **Bulgular:** 🔴 Aynı secret'ler burada da (APP_KEY, Gmail, SMS). "testing" ama sqlite in-memory yerine gerçek pgsql'e bağlanıyor. `MAIL_FROM_ADDRESSYATAGAN`/`CATES` gibi domain-spesifik from adresleri.

### `phpunit.xml` (~33 satır)
- **Amaç:** PHPUnit config — Unit + Feature suite'leri, `app/` coverage.
- **Semboller:** env: APP_ENV=testing, BCRYPT_ROUNDS=4, CACHE_STORE=array, MAIL_MAILER=array, QUEUE_CONNECTION=sync, SESSION_DRIVER=array; sqlite in-memory satırları yorumda.
- **Bulgular:** DB override yorumda → testler `.env.testing`'deki gerçek pgsql'e gider (tehlikeli: testler prod-benzeri DB'ye yazabilir).

---

## Alan Özeti

- **Rol:** Uygulamanın temeli — 12 config dosyası büyük ölçüde Laravel 12 standardı; projeye özgü ekler: `cors` (çift domain), `logging.cron` kanalı, `mail.use_relay` + TLS-doğrulaması-kapalı smtp, `services.recaptcha` + `services.iletisimmakinesi` (SMS), `sanctum.stateful='*'`.
- **Veri modeli (EAV + sözlük mimarisi):** `sys_options` tüm tip/işlem/log/form sözlüğünü tutar (`ttitle`→tablo, `ctitle`→kolon, `op_key`/`group_key`); `documents` + `sys_con_ops` + `sys_con_entities` dinamik doküman/form sistemini kurar; `persons`/`users`/`transactions`/`document_files` bunun üzerinde iş görür. İlişkiler neredeyse tamamen **FK'siz, mantıksal** (id/qnid ile).
- **İstek akışı:** `bootstrap/app.php` → ParsePutMultipart + CspMiddleware + statefulApi (CSRF tüm route'larda kapalı, tüm proxy'lere güven) → api/web route'ları.
- **Seed zinciri:** `SysRoleTemplateSeeder` (rol/yetki/bildirim katalogları, storage/entities JSON) → `UserSeeder` (9 super-admin, rol şablonuna bağımlı) → `SysSeeder` (sys_options sözlüğü). `DatabaseSeeder` bunları ORCHESTRE ETMEZ — elle sırayla çalıştırma gerekir. `DataSeeder` ölü legacy.
- **Tablo listesi (26 tablo):** users, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, sys_options, persons, user_logs, transactions, document_files, currencies, documents, sys_con_ops, personal_access_tokens, teams, team_user, team_invitations, sys_con_entities, sys_role_templates, sys_permission_catalogs, sys_notification_types, sys_role_template_audit, notification_logs, active_sessions (+force_logout kolonları).
- **Kritik bulgular:** (1) Gerçek secret'lar `.env.example`/`.env.testing`'de commit'li (APP_KEY, DB, Gmail, SMS API). (2) CSRF global kapalı + Sanctum stateful `'*'` + trustProxies `'*'`. (3) UserSeeder'da düz metin parolalar. (4) `persons.balance` float, `transactions.amount` scalesiz decimal. (5) phpunit testleri gerçek pgsql'e bağlanıyor (in-memory yorumda). (6) Jetstream teams tabloları kullanılmayan miras.
