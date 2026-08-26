# KomurTedarik — Dosya Envanteri ve Okuma Checklist'i

> Toplam **362** git-takipli dosya (vendor/ ve node_modules/ hariç — üçüncü parti bağımlılıklar).

> Okuma durumu: [x] okundu ve anlamlandırıldı · [ ] statik/derlenmiş asset (içerik okunmadı, rolü doğrulandı)


## kök (yapılandırma) (21 dosya)

- [x] `.chipperci.yml` — 23 satır, 422 B
- [x] `.editorconfig` — 18 satır, 265 B
- [x] `.env.example` — 118 satır, 2849 B
- [x] `.env.testing` — 142 satır, 3224 B
- [x] `.enve` — 119 satır, 2838 B
- [x] `.gitattributes` — 11 satır, 186 B
- [x] `.gitignore` — 17 satır, 191 B
- [x] `README.md` — 2 satır, 30 B
- [x] `artisan` — 15 satır, 350 B
- [x] `composer.json` — 78 satır, 2311 B
- [x] `composer.lock` — 9955 satır, 369359 B
- [x] `jsconfig.json` — 13 satır, 220 B
- [x] `package-lock.json` — 3280 satır, 131300 B
- [x] `package.json` — 35 satır, 936 B
- [x] `phpunit.xml` — 33 satır, 1191 B
- [x] `pint.json` — 6 satır, 112 B
- [x] `postcss.config.js` — 6 satır, 93 B
- [x] `prdTest` — 3 satır, 178 B
- [x] `prjBuildLive` — 16 satır, 324 B
- [x] `tailwind.config.js` — 20 satır, 368 B
- [x] `vite.config.js` — 49 satır, 1397 B

## app (Backend) (63 dosya)

- [x] `app/Classes/Currencies/Other.php` — 15 satır, 207 B
- [x] `app/Classes/Currencies/TCMB.php` — 70 satır, 2602 B
- [x] `app/Classes/Utils.php` — 15 satır, 282 B
- [x] `app/Console/Commands/CleanActiveSessions.php` — 33 satır, 1196 B
- [x] `app/Console/Commands/CreatePermissionCommand.php` — 81 satır, 2837 B
- [x] `app/Console/Commands/CurrencyCron.php` — 56 satır, 1290 B
- [x] `app/Console/Commands/ReencryptFileDescriptions.php` — 118 satır, 4011 B
- [x] `app/Console/Commands/RequestAutoclose.php` — 61 satır, 1973 B
- [x] `app/Console/Commands/RetryNotificationSend.php` — 52 satır, 1565 B
- [x] `app/Console/Commands/SendTestMail.php` — 73 satır, 2460 B
- [x] `app/Console/Commands/SendTestSms.php` — 45 satır, 1213 B
- [x] `app/Console/Commands/VerifyRecaptcha.php` — 71 satır, 2080 B
- [x] `app/Console/Kernel.php` — 50 satır, 1260 B
- [x] `app/Helpers/DocumentHelpers.php` — 777 satır, 28425 B
- [x] `app/Helpers/NotificationHelpers.php` — 62 satır, 3126 B
- [x] `app/Helpers/PermissionHelpers.php` — 173 satır, 5493 B
- [x] `app/Helpers/ReportHelpers.php` — 7 satır, 200 B
- [x] `app/Http/Controllers/Api/V1/User/MeController.php` — 15 satır, 308 B
- [x] `app/Http/Controllers/AuthController.php` — 872 satır, 38695 B
- [x] `app/Http/Controllers/Controller.php` — 8 satır, 77 B
- [x] `app/Http/Controllers/DocumentController.php` — 347 satır, 14847 B
- [x] `app/Http/Controllers/ExportController.php` — 419 satır, 16473 B
- [x] `app/Http/Controllers/PersonsController.php` — 429 satır, 18304 B
- [x] `app/Http/Controllers/ReportController.php` — 18 satır, 493 B
- [x] `app/Http/Controllers/SystemController.php` — 100 satır, 3887 B
- [x] `app/Http/Middleware/CheckPermissionVersion.php` — 91 satır, 3317 B
- [x] `app/Http/Middleware/CspMiddleware.php` — 74 satır, 3604 B
- [x] `app/Http/Middleware/ParsePutMultipart.php` — 54 satır, 1873 B
- [x] `app/Http/Middleware/TrustProxies.php` — 26 satır, 598 B
- [x] `app/Http/Resources/UserResource.php` — 27 satır, 786 B
- [x] `app/Jobs/RetryNotificationSendJob.php` — 52 satır, 1731 B
- [x] `app/Jobs/SendInfoMailJob.php` — 65 satır, 2024 B
- [x] `app/Jobs/SendNotificationMailJob.php` — 502 satır, 22171 B
- [x] `app/Jobs/SendResetMailJob.php` — 69 satır, 2329 B
- [x] `app/Models/ActiveSession.php` — 30 satır, 591 B
- [x] `app/Models/Currencies.php` — 11 satır, 181 B
- [x] `app/Models/Document_files.php` — 199 satır, 8635 B
- [x] `app/Models/Documents.php` — 398 satır, 18771 B
- [x] `app/Models/NotificationLog.php` — 132 satır, 4701 B
- [x] `app/Models/Persons.php` — 159 satır, 5247 B
- [x] `app/Models/SysNotificationType.php` — 60 satır, 1305 B
- [x] `app/Models/SysPermissionCatalog.php` — 60 satır, 1284 B
- [x] `app/Models/SysRoleTemplate.php` — 55 satır, 1317 B
- [x] `app/Models/SysRoleTemplateAudit.php` — 66 satır, 1552 B
- [x] `app/Models/Sys_con_entities.php` — 36 satır, 722 B
- [x] `app/Models/Sys_con_ops.php` — 24 satır, 439 B
- [x] `app/Models/Sys_options.php` — 138 satır, 5733 B
- [x] `app/Models/Transactions.php` — 243 satır, 11418 B
- [x] `app/Models/User.php` — 190 satır, 6751 B
- [x] `app/Models/UserLog.php` — 155 satır, 6078 B
- [x] `app/Policies/TeamPolicy.php` — 76 satır, 1605 B
- [x] `app/Providers/AppServiceProvider.php` — 46 satır, 1098 B
- [x] `app/Providers/DocumentServiceProvider.php` — 886 satır, 36487 B
- [x] `app/Providers/EmailServiceProvider.php` — 112 satır, 4666 B
- [x] `app/Providers/EncryptionProvider.php` — 119 satır, 3958 B
- [x] `app/Providers/PersonsServiceProvider.php` — 687 satır, 28460 B
- [x] `app/Providers/ReportServiceProvider.php` — 463 satır, 17299 B
- [x] `app/Rules/Recaptcha.php` — 42 satır, 1049 B
- [x] `app/Services/ExportService.php` — 117 satır, 3711 B
- [x] `app/Services/MailService.php` — 394 satır, 15288 B
- [x] `app/Services/PermissionService.php` — 208 satır, 6726 B
- [x] `app/Services/RoleTemplateService.php` — 291 satır, 9160 B
- [x] `app/Services/SmsService.php` — 380 satır, 16689 B

## bootstrap (3 dosya)

- [x] `bootstrap/app.php` — 32 satır, 1235 B
- [x] `bootstrap/cache/.gitignore` — 2 satır, 14 B
- [x] `bootstrap/providers.php` — 6 satır, 114 B

## config (12 dosya)

- [x] `config/app.php` — 126 satır, 4284 B
- [x] `config/auth.php` — 115 satır, 4029 B
- [x] `config/cache.php` — 107 satır, 3427 B
- [x] `config/cors.php` — 34 satır, 963 B
- [x] `config/database.php` — 170 satır, 6091 B
- [x] `config/filesystems.php` — 76 satır, 2370 B
- [x] `config/logging.php` — 139 satır, 4569 B
- [x] `config/mail.php` — 133 satır, 4262 B
- [x] `config/queue.php` — 112 satır, 3824 B
- [x] `config/sanctum.php` — 83 satır, 3015 B
- [x] `config/services.php` — 52 satır, 1875 B
- [x] `config/session.php` — 217 satır, 7852 B

## routes (3 dosya)

- [x] `routes/api.php` — 64 satır, 4474 B
- [x] `routes/console.php` — 25 satır, 760 B
- [x] `routes/web.php` — 69 satır, 2991 B

## database/migrations (23 dosya)

- [x] `database/migrations/0001_01_01_000000_create_users_table.php` — 60 satır, 2111 B
- [x] `database/migrations/0001_01_01_000001_create_cache_table.php` — 35 satır, 849 B
- [x] `database/migrations/0001_01_01_000002_create_jobs_table.php` — 57 satır, 1812 B
- [x] `database/migrations/2022_12_05_073600_create_sys_options_table.php` — 48 satır, 1198 B
- [x] `database/migrations/2022_12_05_075026_create_persons_table.php` — 51 satır, 1449 B
- [x] `database/migrations/2022_12_05_080953_create_user_logs_table.php` — 44 satır, 1239 B
- [x] `database/migrations/2022_12_05_083533_create_transactions_table.php` — 50 satır, 1521 B
- [x] `database/migrations/2023_02_15_134911_create_document_files_table.php` — 45 satır, 1264 B
- [x] `database/migrations/2023_11_20_184651_create_currencies_table.php` — 37 satır, 844 B
- [x] `database/migrations/2024_04_04_132901_create_documents_table.php` — 50 satır, 1548 B
- [x] `database/migrations/2024_04_04_133010_create_sys_con_ops_table.php` — 35 satır, 901 B
- [x] `database/migrations/2024_04_28_125548_create_personal_access_tokens_table.php` — 33 satır, 856 B
- [x] `database/migrations/2024_04_28_125548_create_teams_table.php` — 30 satır, 660 B
- [x] `database/migrations/2024_04_28_125549_create_team_user_table.php` — 32 satır, 720 B
- [x] `database/migrations/2024_04_28_125550_create_team_invitations_table.php` — 32 satır, 761 B
- [x] `database/migrations/2024_05_06_110912_create_sys_con_entities_table.php` — 33 satır, 781 B
- [x] `database/migrations/2026_04_11_000001_create_sys_role_templates_table.php` — 35 satır, 1021 B
- [x] `database/migrations/2026_04_11_000002_create_sys_permission_catalogs_table.php` — 34 satır, 1031 B
- [x] `database/migrations/2026_04_11_000003_create_sys_notification_types_table.php` — 34 satır, 1032 B
- [x] `database/migrations/2026_04_11_000004_create_sys_role_template_audit_table.php` — 34 satır, 1073 B
- [x] `database/migrations/2026_04_15_000000_create_notification_logs_table.php` — 32 satır, 1024 B
- [x] `database/migrations/2026_04_24_000000_create_active_sessions_table.php` — 28 satır, 941 B
- [x] `database/migrations/2026_04_25_000000_add_force_logout_to_active_sessions_table.php` — 23 satır, 806 B

## database (diğer) (8 dosya)

- [x] `database/.gitignore` — 1 satır, 10 B
- [x] `database/factories/PersonsFactory.php` — 37 satır, 867 B
- [x] `database/factories/TeamFactory.php` — 26 satır, 552 B
- [x] `database/seeders/DataSeeder.php` — 120 satır, 4495 B
- [x] `database/seeders/DatabaseSeeder.php` — 23 satır, 456 B
- [x] `database/seeders/SysRoleTemplateSeeder.php` — 168 satır, 5461 B
- [x] `database/seeders/SysSeeder.php` — 688 satır, 26681 B
- [x] `database/seeders/UserSeeder.php` — 73 satır, 3033 B

## resources/js (çekirdek) (13 dosya)

- [x] `resources/js/app.js` — 71 satır, 2039 B
- [x] `resources/js/coal-swal.js` — 2 satır, 52 B
- [x] `resources/js/layouts/App.vue` — 35 satır, 977 B
- [x] `resources/js/layouts/CoalPanel.vue` — 82 satır, 2875 B
- [x] `resources/js/lib/pickle.js` — 814 satır, 26714 B
- [x] `resources/js/lib/treeModal.js` — 409 satır, 15516 B
- [x] `resources/js/plugins/breadcrumbs.js` — 28 satır, 1006 B
- [x] `resources/js/router/index.js` — 103 satır, 4215 B
- [x] `resources/js/stores/auth.js` — 42 satır, 1137 B
- [x] `resources/js/stores/events.js` — 76 satır, 2382 B
- [x] `resources/js/stores/formdata.js` — 16 satır, 397 B
- [x] `resources/js/stores/navigation.js` — 81 satır, 3282 B
- [x] `resources/js/stores/permissiondata.js` — 67 satır, 1966 B

## resources/js/pages (18 dosya)

- [x] `resources/js/pages/NotFound.vue` — 17 satır, 237 B
- [x] `resources/js/pages/coalsystem/Client/CForm.vue` — 182 satır, 7701 B
- [x] `resources/js/pages/coalsystem/Client/CList.vue` — 609 satır, 22870 B
- [x] `resources/js/pages/coalsystem/Dashboard.vue` — 76 satır, 1896 B
- [x] `resources/js/pages/coalsystem/Documents/DList.vue` — 849 satır, 38433 B
- [x] `resources/js/pages/coalsystem/Example/FlatForm.vue` — 115 satır, 3968 B
- [x] `resources/js/pages/coalsystem/Example/FlatList.vue` — 422 satır, 22780 B
- [x] `resources/js/pages/coalsystem/Logs/LList.vue` — 301 satır, 15329 B
- [x] `resources/js/pages/coalsystem/NotificationLogs/NList.vue` — 345 satır, 19094 B
- [x] `resources/js/pages/coalsystem/Notifications/NSettings.vue` — 507 satır, 18195 B
- [x] `resources/js/pages/coalsystem/Offer/OForm.vue` — 743 satır, 28163 B
- [x] `resources/js/pages/coalsystem/Offer/OList.vue` — 982 satır, 43674 B
- [x] `resources/js/pages/coalsystem/Request/RForm.vue` — 164 satır, 6686 B
- [x] `resources/js/pages/coalsystem/Request/RList.vue` — 1140 satır, 51336 B
- [x] `resources/js/pages/coalsystem/Roles/Roles.vue` — 401 satır, 16395 B
- [x] `resources/js/pages/coalsystem/Users/UForm.vue` — 219 satır, 8886 B
- [x] `resources/js/pages/coalsystem/Users/UList.vue` — 286 satır, 13589 B
- [x] `resources/js/pages/coalsystem/treeTest.vue` — 83 satır, 2302 B

## resources/js/components (29 dosya)

- [x] `resources/js/components/Dashboard/Admin.vue` — 249 satır, 5024 B
- [x] `resources/js/components/Dashboard/Admin/DashboardCalendar.vue` — 228 satır, 5403 B
- [x] `resources/js/components/Dashboard/Admin/DashboardDistribution.vue` — 230 satır, 5812 B
- [x] `resources/js/components/Dashboard/Admin/DashboardHeader.vue` — 491 satır, 13709 B
- [x] `resources/js/components/Dashboard/Admin/DashboardNotifications.vue` — 312 satır, 7805 B
- [x] `resources/js/components/Dashboard/Admin/DashboardProcessChart.vue` — 325 satır, 8337 B
- [x] `resources/js/components/Dashboard/Admin/DashboardQuickActions.vue` — 159 satır, 3621 B
- [x] `resources/js/components/Dashboard/Admin/DashboardRequestTables.vue` — 461 satır, 13739 B
- [x] `resources/js/components/Dashboard/Admin/DashboardStats.vue` — 450 satır, 11641 B
- [x] `resources/js/components/Dashboard/Client.vue` — 606 satır, 11813 B
- [x] `resources/js/components/Dashboard/Client/ClientHeader.vue` — 174 satır, 3597 B
- [x] `resources/js/components/Dashboard/Client/ClientInfoSection.vue` — 504 satır, 14076 B
- [x] `resources/js/components/Dashboard/Client/ClientOfferTable.vue` — 257 satır, 6275 B
- [x] `resources/js/components/Dashboard/Client/ClientQuickOps.vue` — 195 satır, 5282 B
- [x] `resources/js/components/Dashboard/Client/ClientStats.vue` — 390 satır, 8323 B
- [x] `resources/js/components/Dashboard/DISTRIBUTION_EXTRACTION_SUMMARY.md` — 239 satır, 8873 B
- [x] `resources/js/components/Dashboard/Default.vue` — 1227 satır, 35104 B
- [x] `resources/js/components/Dashboard/HEADER_EXTRACTION_SUMMARY.md` — 76 satır, 2700 B
- [x] `resources/js/components/Dashboard/STATS_EXTRACTION_SUMMARY.md` — 196 satır, 6303 B
- [x] `resources/js/components/Offer/OfferLogTimeline.vue` — 351 satır, 12944 B
- [x] `resources/js/components/Offer/OfferRequestTable.vue` — 324 satır, 17584 B
- [x] `resources/js/components/Offer/OfferSummary.vue` — 537 satır, 22700 B
- [x] `resources/js/components/Offer/OfferTable.vue` — 235 satır, 10642 B
- [x] `resources/js/components/coalparts/AppFab.vue` — 401 satır, 9842 B
- [x] `resources/js/components/coalparts/Form.vue` — 2892 satır, 170874 B
- [x] `resources/js/components/coalparts/Header.vue` — 379 satır, 15040 B
- [x] `resources/js/components/coalparts/RSummary.vue` — 561 satır, 19036 B
- [x] `resources/js/components/coalparts/RequestLogTimeline.vue` — 294 satır, 10681 B
- [x] `resources/js/components/coalparts/Sidebar.vue` — 376 satır, 21216 B

## resources/views (10 dosya)

- [x] `resources/views/auth/coallogin.blade.php` — 202 satır, 9991 B
- [x] `resources/views/auth/loginSms.blade.php` — 138 satır, 7607 B
- [x] `resources/views/auth/passwordReset.blade.php` — 133 satır, 5821 B
- [x] `resources/views/auth/register.blade.php` — 191 satır, 11329 B
- [x] `resources/views/coalapp.blade.php` — 33 satır, 1242 B
- [x] `resources/views/emails/layout.blade.php` — 69 satır, 4432 B
- [x] `resources/views/emails/verify-email.blade.php` — 14 satır, 665 B
- [x] `resources/views/exports/icmal.blade.php` — 142 satır, 6533 B
- [x] `resources/views/exports/offer.blade.php` — 206 satır, 9801 B
- [x] `resources/views/login.blade.php` — 136 satır, 6437 B

## resources (diğer) (4 dosya)

- [x] `resources/css/Pickle.md` — 52 satır, 36531 B
- [x] `resources/css/app.css` — 1 satır, 1 B
- [x] `resources/markdown/policy.md` — 3 satır, 84 B
- [x] `resources/markdown/terms.md` — 3 satır, 88 B

## lang (2 dosya)

- [x] `lang/en.json` — 68 satır, 2310 B
- [x] `lang/tr.json` — 69 satır, 2473 B

## public (statik) (61 dosya)

- [x] `public/.htaccess` — 21 satır, 603 B
- [ ] `public/banner-bg-new.webp` — 1045 satır, 269278 B — *derlenmiş/statik asset*
- [ ] `public/banner-bg.jpg` — 1993 satır, 737112 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/CATES.svg` — 0 satır, 9268 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/CATESMAIL.png` — 401 satır, 98739 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/YATAGAN.svg` — 53 satır, 8952 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/YATAGANMAIL.png` — 341 satır, 88167 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/cates.jpg` — 2793 satır, 383721 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/custom.css` — 710 satır, 19893 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-brands-400.ttf` — 2406 satır, 189684 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-brands-400.woff2` — 374 satır, 109808 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-regular-400.ttf` — 379 satır, 63348 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-regular-400.woff2` — 97 satır, 24488 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-solid-900.ttf` — 4767 satır, 394668 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-solid-900.woff2` — 573 satır, 150020 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-v4compatibility.ttf` — 151 satır, 10172 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/@fortawesome/fa-v4compatibility.woff2` — 32 satır, 4568 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/bootstrap-icons/bootstrap-icons.woff` — 702 satır, 176200 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/bootstrap-icons/bootstrap-icons.woff2` — 497 satır, 130608 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-duotone.eot` — 1075 satır, 187664 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-duotone.svg` — 1719 satır, 690267 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-duotone.ttf` — 1075 satır, 187500 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-duotone.woff` — 1075 satır, 187576 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-outline.eot` — 2027 satır, 246928 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-outline.svg` — 582 satır, 1058682 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-outline.ttf` — 2027 satır, 246764 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-outline.woff` — 2027 satır, 246840 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-solid.eot` — 1085 satır, 165296 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-solid.svg` — 582 satır, 695434 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-solid.ttf` — 1085 satır, 165132 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/keenicons/keenicons-solid.woff` — 1085 satır, 165208 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-brands-400.eot` — 1918 satır, 156260 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-brands-400.svg` — 1313 satır, 927335 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-brands-400.ttf` — 1918 satır, 156072 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-brands-400.woff` — 361 satır, 98673 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-brands-400.woff2` — 347 satır, 84772 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-regular-400.eot` — 196 satır, 33916 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-regular-400.svg` — 467 satır, 113535 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-regular-400.ttf` — 196 satır, 33724 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-regular-400.woff` — 65 satır, 15489 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-regular-400.woff2` — 55 satır, 12900 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-solid-900.eot` — 1634 satır, 226312 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-solid-900.svg` — 2894 satır, 923151 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-solid-900.ttf` — 1634 satır, 226128 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-solid-900.woff` — 458 satır, 125421 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/fonts/line-awesome/la-solid-900.woff2` — 386 satır, 96752 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/plugins.css` — 13 satır, 741503 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/theme.css` — 44106 satır, 1455468 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/css/treeModal.css` — 77 satır, 2204 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/js/ktdrawer.js` — 5 satır, 29255 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/js/pickle.js` — 758 satır, 24346 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/js/treeModal.js` — 390 satır, 14983 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/media/click-icon-cursor-pixel-sylepixel-600nw-2656412751-removebg-preview.png` — 391 satır, 106649 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/media/login-background-old.svg` — 35 satır, 1414 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/media/login-background.svg` — 33 satır, 1435 B — *derlenmiş/statik asset*
- [x] `public/coaltheme/tree_modal_demo.html` — 33 satır, 1185 B
- [ ] `public/coaltheme/yatagan.jpg` — 575 satır, 135692 B — *derlenmiş/statik asset*
- [ ] `public/favicon.ico` — 0 satır, 0 B — *derlenmiş/statik asset*
- [x] `public/index.php` — 30 satır, 728 B
- [ ] `public/manifest.json` — 7 satır, 127 B — *derlenmiş/statik asset*
- [ ] `public/robots.txt` — 2 satır, 24 B — *derlenmiş/statik asset*

## public/coaltheme/mail (e-posta şablonları) (15 dosya)

- [x] `public/coaltheme/mail/file-added.falanml` — 22 satır, 1299 B
- [x] `public/coaltheme/mail/file-rejected.falanml` — 24 satır, 1334 B
- [x] `public/coaltheme/mail/forgot-pass.falanml` — 29 satır, 1510 B
- [ ] `public/coaltheme/mail/mail-logo-adm.png` — 40 satır, 12099 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/mail/mail-logo-gdz.png` — 11 satır, 3202 B — *derlenmiş/statik asset*
- [x] `public/coaltheme/mail/message.falanml` — 22 satır, 1185 B
- [x] `public/coaltheme/mail/new-order.falanml` — 22 satır, 1195 B
- [x] `public/coaltheme/mail/order-filled.falanml` — 22 satır, 1290 B
- [x] `public/coaltheme/mail/register_notification.html` — 31 satır, 1443 B
- [x] `public/coaltheme/mail/template.falanml` — 23 satır, 1185 B
- [x] `public/coaltheme/mail/test-document-added.falanml` — 76 satır, 4413 B
- [x] `public/coaltheme/mail/test-document-approved.falanml` — 90 satır, 4564 B
- [x] `public/coaltheme/mail/test-document-rejected.falanml` — 95 satır, 4831 B
- [ ] `public/coaltheme/mail/thumbs-down.png` — 4 satır, 585 B — *derlenmiş/statik asset*
- [ ] `public/coaltheme/mail/thumbs-up.png` — 9 satır, 621 B — *derlenmiş/statik asset*

## public (derlenmiş assetler) (53 dosya)

- [ ] `public/build/assets/app-Chq6b3aW.js` — 479 satır, 857025 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/app-Chq6b3aW.js.map` — 0 satır, 3559454 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/app-Cp-ApAGi.css` — 1 satır, 120894 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/bootstrap-icons-BYTZb8m0.woff` — 702 satır, 176200 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/bootstrap-icons-dSOGREQ0.woff2` — 497 satır, 130608 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/coal-swal-Cvt_A_qW.js` — 2 satır, 116 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/coal-swal-Cvt_A_qW.js.map` — 0 satır, 216 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/custom-ds0SJZu9.css` — 1 satır, 14856 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-brands-400-B0G11Utd.woff2` — 374 satır, 109808 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-brands-400-Dh9Nz-AR.ttf` — 2406 satır, 189684 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-regular-400-B0w-yroU.woff2` — 97 satır, 24488 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-regular-400-BHXLjBeH.ttf` — 379 satır, 63348 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-solid-900-BLLQy4Ml.ttf` — 4767 satır, 394668 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-solid-900-DPO7AZHW.woff2` — 573 satır, 150020 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-v4compatibility-CzA-yRXe.ttf` — 151 satır, 10172 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/fa-v4compatibility-QamTF9-e.woff2` — 32 satır, 4568 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-duotone-BdYtl4gH.eot` — 1075 satır, 187664 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-duotone-C4mN2-ZY.svg` — 1719 satır, 690267 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-duotone-CtM_kZPp.woff` — 1075 satır, 187576 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-duotone-DKdN_-MP.ttf` — 1075 satır, 187500 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-outline-2dtxAfdV.woff` — 2027 satır, 246840 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-outline-CkekYDxi.svg` — 582 satır, 1058682 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-outline-DQbBVMHT.ttf` — 2027 satır, 246764 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-outline-UBChmC0V.eot` — 2027 satır, 246928 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-solid-3fU7eTfe.ttf` — 1085 satır, 165132 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-solid-7nivxdSK.eot` — 1085 satır, 165296 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-solid-B_g1gF03.woff` — 1085 satır, 165208 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/keenicons-solid-Bbtzhe37.svg` — 582 satır, 695434 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-brands-400-Cq-R4OEF.woff2` — 347 satır, 84772 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-brands-400-D0lxOIwB.woff` — 361 satır, 98673 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-brands-400-LN4CMlGg.eot` — 1918 satır, 156260 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-brands-400-gDglUfU7.ttf` — 1918 satır, 156072 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-brands-400-wsUI3UJ9.svg` — 1313 satır, 927335 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-regular-400-BmVb34ql.svg` — 467 satır, 113535 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-regular-400-CmnW_RTo.ttf` — 196 satır, 33724 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-regular-400-Cx6vm3uW.eot` — 196 satır, 33916 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-regular-400-DuFMN_sw.woff2` — 55 satır, 12900 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-regular-400-ehe5HgcS.woff` — 65 satır, 15489 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-solid-900-BUOWlSBQ.ttf` — 1634 satır, 226128 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-solid-900-CR_Kd-su.woff` — 458 satır, 125421 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-solid-900-DkmX4G2x.eot` — 1634 satır, 226312 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-solid-900-TjMEgv3Q.woff2` — 386 satır, 96752 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/la-solid-900-dtlPMWb8.svg` — 2894 satır, 923151 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/plugins-WFneVGQ2.css` — 14 satır, 692708 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/sweetalert2.esm.all-0Z_61IYw.js` — 67 satır, 79064 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/sweetalert2.esm.all-0Z_61IYw.js.map` — 0 satır, 244381 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/theme-B0ILI074.css` — 1 satır, 1214495 B — *derlenmiş/statik asset*
- [ ] `public/build/assets/treeModal-IYKjKDyC.css` — 1 satır, 1913 B — *derlenmiş/statik asset*
- [ ] `public/build/manifest.json` — 194 satır, 8445 B — *derlenmiş/statik asset*
- [ ] `public/front/pages/coallogin/page.js` — 109 satır, 4262 B — *derlenmiş/statik asset*
- [ ] `public/front/pages/loginSms/page.js` — 217 satır, 6793 B — *derlenmiş/statik asset*
- [ ] `public/front/pages/passwordReset/page.js` — 110 satır, 4627 B — *derlenmiş/statik asset*
- [ ] `public/front/pages/register/page.js` — 124 satır, 5181 B — *derlenmiş/statik asset*

## storage/entities (3 dosya)

- [x] `storage/entities/coal_roles_templates.json` — 42 satır, 1527 B
- [x] `storage/entities/notification_details.json` — 26 satır, 659 B
- [x] `storage/entities/role_details.json` — 86 satır, 3263 B

## storage (iskelet) (9 dosya)

- [x] `storage/app/.gitignore` — 3 satır, 23 B
- [x] `storage/app/public/.gitignore` — 2 satır, 14 B
- [x] `storage/framework/.gitignore` — 9 satır, 119 B
- [x] `storage/framework/cache/.gitignore` — 3 satır, 21 B
- [x] `storage/framework/cache/data/.gitignore` — 2 satır, 14 B
- [x] `storage/framework/sessions/.gitignore` — 2 satır, 14 B
- [x] `storage/framework/testing/.gitignore` — 2 satır, 14 B
- [x] `storage/framework/views/.gitignore` — 2 satır, 14 B
- [x] `storage/logs/.gitignore` — 2 satır, 14 B

## documentation (5 dosya)

- [x] `documentation/export-system.md` — 147 satır, 5134 B
- [x] `documentation/notification-receiving-system.md` — 615 satır, 20989 B
- [x] `documentation/notification-sending-system.md` — 189 satır, 6275 B
- [x] `documentation/permission-system-analysis.md` — 797 satır, 26317 B
- [x] `documentation/single-session-enforcement-system.md` — 178 satır, 7753 B

## scripts (2 dosya)

- [x] `scripts/send_test_mail.php` — 46 satır, 1282 B
- [x] `scripts/send_test_sms.php` — 31 satır, 807 B

## tests (4 dosya)

- [x] `tests/TestCase.php` — 10 satır, 142 B
- [x] `tests/Unit/ExampleTest.php` — 16 satır, 243 B
- [x] `tests/Unit/RecaptchaRuleTest.php` — 48 satır, 1251 B
- [x] `tests/Unit/SmsServiceTest.php` — 93 satır, 3728 B

## bin (1 dosya)

- [x] `bin/install.sh` — 14 satır, 312 B

---
**Okunan kaynak dosya:** 247 · **Asset (listelenen):** 115
