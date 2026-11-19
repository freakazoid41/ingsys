<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TEDAŞ Real Catalog — the 5 official categories from the TEDAŞ tanzim tables
 * (FRM.843 / FRM.846 / FRM.847 / FRM.865 / FRM.869) with their full
 * file-requirement templates, kVA relations and text scope (kapsam tipi) relations.
 *
 * Scope model: a requirement row connects to scope values exactly like kVA:
 *   kva      — JSON array of kVA codes from op-doc-kva docs ([] = all powers)
 *   variants — JSON array of scope-type codes from op-doc-scope-value docs
 *              ([] = no text dimension; aqui explicit lists carry every connection)
 *
 * Category metadata entities:
 *   sartname_kodu, sartname_adi — technical specification reference
 *   scope_kind   — 'kva' | 'variant' | 'kva_variant'
 *   scope_headers — sheet column headers (JSON)
 *   scope_values  — every scope code connected to this category's requirements (JSON)
 *   scope_variants — kVA → HARİCİ/DAHİLİ map (FRM.865 only)
 *   row_count
 *
 * Self-sufficient: calls ProductCategorySeeder, ProductSeeder, KvaSeeder and
 * ScopeValueSeeder first (idempotent), so a single command builds the full catalog.
 *
 * Idempotent: matches categories by `code` entity; refreshes rows in place.
 * One example product per category is ensured (matched by prod_code).
 *
 * Run: php artisan db:seed --class=TedarikRealCatalogSeeder
 */
class TedarikRealCatalogSeeder extends Seeder
{
    private const ALL_TRAFO_KVA = ['50', '100', '160', '250', '400', '630', '1000', '1250', '1600'];
    private const ALL_PANO_KVA  = ['100', '160', '250', '400', '630', '1000', '1250', '1600'];

    private const GY_UNITS = ['YA G./Ç.', 'KOMPAKT 2YA+1SYA', 'YA. SİG.TRF KOR', 'GER. TRF HÜC.', 'KES. ÇIK', 'AK.GER. ÖLÇÜ', 'YA BAR. BÖL./BAĞ.'];
    private const HY_UNITS = ['YA G/.Ç', 'YA. TRF KOR', 'GER. TRF', 'KES. ÇIK', 'KUPLAJ', 'AY G/Ç.', 'KAB.BAĞ.', 'AK.GER. ÖLÇ', 'YA BAR. BÖL.'];
    private const SDK_TYPES = ['TİP-A', 'TİP-B'];
    // Pano alt-tipleri tek kapsam değerine katlanır: "160 kVA Harici" gibi.
    // FRM.865: 100=Harici; 160/250/400 = Harici + Dahili; 630/1000/1250/1600 plain kVA.
    private const PANO_SCOPES = [
        '100 kVA Harici',
        '160 kVA Harici', '160 kVA Dahili',
        '250 kVA Harici', '250 kVA Dahili',
        '400 kVA Harici', '400 kVA Dahili',
    ];
    private const PANO_PLAIN_KVA = ['630', '1000', '1250', '1600'];

    private const TIP = 'Tip Test Raporları';
    private const DIG = 'Diğer Belgeler';

    /** One example product per category (prod_code, short_name, unit). */
    private const EXAMPLE_PRODUCTS = [
        'TRF-33KV' => ['TRF-33-630', '630 kVA Hermetik Dağıtım Trafosu', 'ST'],
        'GY-HUCRE' => ['GYH-630', '630A Metal Mahfazalı Gaz Yalıtımlı YG Hücre', 'ST'],
        'HY-HUCRE' => ['HYH-630', '630A Metal Mahfazalı Hava Yalıtımlı YG Hücre', 'ST'],
        'AG-PANO'  => ['AGP-630', '630 kVA AG Dağıtım Panosu (DAHİLİ)', 'ST'],
        'SDK'      => ['SDK-A', 'Saha Dağıtım Kutusu TİP-A', 'ST'],
    ];

    /**
     * row = [evrak_no, standard, file_title, file_group, file_types, kva, variants]
     */
    private array $categories = [
        'TRF-33KV' => [
            'title'          => '33 kV Hermetik Dağıtım Transformatörü',
            'sartname_kodu'  => 'TEDAŞ-MLZ/99-032.E',
            'sartname_adi'   => 'HERMETİK TİP OG/AG DAĞITIM GÜÇ TRANSFORMATÖRLERİ TEKNİK ŞARTNAMESİ',
            'scope_kind'     => 'kva',
            'scope_headers'  => ['50 kVA', '100 kVA', '160 kVA', '250 kVA', '400 kVA', '630 kVA', '1000 kVA', '1250 kVA', '1600 kVA'],
            'scope_variants' => [],
            'rows' => [
                // FRM.847 — Tip Test Raporları (0–6, all 9 kVA)
                ['0', '', 'Garantili Özellikler Listesi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['1', 'TS EN 60076-11', 'Sıcaklık Artışı (Isınma) Deneyi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['2', 'TS EN 60076-3', 'Yıldırım Darbe Deneyi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['3', 'TS EN 60076-10', 'Ses Gücü Düzeyinin Ölçülmesi Deneyi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['4', 'TS EN 60076-1 Madde 11.5', 'Anma Geriliminin %90 ve %110\'unda Boştaki Kayıp ve Akımın Ölçülmesi Deneyi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['5', 'TS EN 50464-4', 'Kazan Ömür ve Sızdırmazlık Deneyi', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                ['6', 'TS EN 60076-5', 'Kısa Devrelere Karşı Mekanik Dayanım Deneyi (K.D.K.M.D.D.)', self::TIP, 'pdf', self::ALL_TRAFO_KVA, []],
                // FRM.847 — Diğer Belgeler (7–13)
                ['7', '', 'İmalatçı firmaya ait TS EN ISO 9001/ EN ISO 9001 Kalite Yönetim Sistem Belgesi', self::DIG, 'pdf', self::ALL_TRAFO_KVA, []],
                ['8', '', 'İmalatçı firmaya ait TS EN ISO 14001/ EN ISO 14001Çevre Yönetim Sistem Belgesi', self::DIG, 'pdf', [], []],
                ['9', '', 'İmalatçı firmaya ait Türk Standardına Uygunluk Belgesi', self::DIG, 'pdf', [], []],
                ['10', '', 'Ayrıntılı katalog, montaj, işletme ve bakım yönergeleri', self::DIG, 'pdf_img', self::ALL_TRAFO_KVA, []],
                ['11', '', 'Tank, çekirdek ve sargıların tipi, sargı düzenlenmesi ile ilgili açıklayıcı bilgiler ve resimler', self::DIG, 'pdf_img', [], []],
                ['12', '', 'Genel Boyutlar ve Yardımcı Teçhizatın Düzenlenmesini Gösteren Resimler', self::DIG, 'pdf_img', self::ALL_TRAFO_KVA, []],
                ['13', '', 'Taşıma Ölçülerini Gösteren Resimler ve Taşıma Ağırlıkları', self::DIG, 'pdf_img', self::ALL_TRAFO_KVA, []],
            ],
        ],
        'GY-HUCRE' => [
            'title'          => 'Metal Mahfazalı Gaz Yalıtımlı YG Hücre',
            'sartname_kodu'  => 'TEDAŞ-MLZ/2006-002.D',
            'sartname_adi'   => 'YG SF6 GAZI YALITIMLI METAL MAHFAZALI ANAHTARLAMA VE KONTROL DÜZENLERİ TEKNİK ŞARTNAMESİ',
            'scope_kind'     => 'variant',
            'scope_headers'  => self::GY_UNITS,
            'scope_variants' => [],
            'rows' => [
                // FRM.843 — Tip Test Raporları (0–13)
                ['0', '', 'Garantili Özellikler Listesi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['1', 'TS EN 62271-200 Madde 6.2', 'Yalıtım (Dielektrik) Deneyleri', self::TIP, 'pdf', [], self::GY_UNITS],
                ['2', 'TS EN 62271-200 Madde 6.5', 'Sıcaklık Artış Deneyi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['3', 'TS EN 62271-200 Madde 6.4', 'Ana Devrenin Direncinin Ölçülmesi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['4', 'TS EN 62271-200 Madde 6.6', 'Kısa Süreli Dayanım Akımı ve Tepe Dayanım Akımı Deneyleri', self::TIP, 'pdf', [], self::GY_UNITS],
                ['5', 'TS EN 62271-200 Madde 6.101', 'Kapama ve Açma Kapasitelerinin Doğrulanması', self::TIP, 'pdf', [], self::GY_UNITS],
                ['6', 'TS EN 62271-200 Madde 6.102', 'Mekanik Manevra Deneyleri', self::TIP, 'pdf', [], self::GY_UNITS],
                ['7', 'TS EN 62271-200 Madde 6.7', 'Korumanın Doğrulanması', self::TIP, 'pdf', [], self::GY_UNITS],
                ['8', 'TS EN 62271-200 Madde 6.103', 'Basınç Dayanıklılık Deneyi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['9', 'TS EN 62271-200 Madde 6.8', 'Sızdırmazlık Deneyi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['10', 'TS EN 62271-200 Madde 6.106', 'İç Ark Deneyi', self::TIP, 'pdf', [], self::GY_UNITS],
                ['11', 'TS EN 62271-200 Madde 8.13', 'Boya ve Galvaniz Kaplamaları Üzerindeki Deneyler', self::TIP, 'pdf', [], self::GY_UNITS],
                ['12', 'TS EN 62271-200 Madde 6.9', 'Elektromanyetik Uyumluluk Deneyleri', self::TIP, 'pdf', [], self::GY_UNITS],
                ['13', 'TS EN 62271-200 Madde 6.11', 'Vakumlu Kesiciler için X Radyasyonu Deneyi', self::TIP, 'pdf', [], ['KES. ÇIK']],
                // FRM.843 — Diğer Belgeler (14–27)
                ['14', '', 'Üretici firmaya ait TS EN ISO 9001/ ISO 9001 Kalite Yönetim Sistem Belgesi', self::DIG, 'pdf', [], self::GY_UNITS],
                ['15', '', 'Ayrıntılı kataloglar, montaj, işletme ve bakım yönergeleri', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['16', '', 'Fonksiyonel Birimlerin Dış Boyutları', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['17', '', 'En büyük taşıma ağırlıkları', self::DIG, 'pdf', [], self::GY_UNITS],
                ['18', '', 'Ambalajlı olarak en büyük taşıma boyutları', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['19', '', 'Elektrik Bağlantı Resimleri', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['20', '', 'Fonksiyonel birimlerin tertip resimleri', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['21', '', 'Dış bağlantıların düzenlenme şekilleri', self::DIG, 'pdf_img', [], self::GY_UNITS],
                ['22', '', 'Fonksiyonel birimlerin monte edileceği zemin için alınması gereken tedbirler ve montaj koşulları', self::DIG, 'pdf', [], self::GY_UNITS],
                ['23', '', 'Fonksiyonel birimde kullanılan teçhizatın listesi, (yapımcı firmanın adı ve teçhizatın tip işareti ile birlikte)', self::DIG, 'pdf', [], self::GY_UNITS],
                ['24', '', 'Alıcının bulundurması önerilen yedek parça listeleri, özel aletler ve deney cihazları listeleri', self::DIG, 'pdf', [], self::GY_UNITS],
                ['25', '', 'İç Ark Sınıflandırılması', self::DIG, 'pdf', [], self::GY_UNITS],
                ['26', '', 'Sigorta seçim çizelgesi, ("Yük ayırıcısı + Sigorta" Birleşiği Transformatör Koruma Fonksiyonel Birimi için)', self::DIG, 'pdf', [], ['KOMPAKT 2YA+1SYA', 'YA. SİG.TRF KOR', 'GER. TRF HÜC.']],
                ['27', '', 'Servis Sürekliliğinin sınıflandırması', self::DIG, 'pdf', [], self::GY_UNITS],
            ],
        ],
        'HY-HUCRE' => [
            'title'          => 'Metal Mahfazalı Hava Yalıtımlı YG Hücre',
            'sartname_kodu'  => 'TEDAŞ-MLZ/95-007.E',
            'sartname_adi'   => 'YG HAVA YALITIMLI METAL MAHFAZALI MODÜLER ANAHTARLAMA VE KONTROL DÜZENLERİ TEKNİK ŞARTNAMESİ',
            'scope_kind'     => 'variant',
            'scope_headers'  => self::HY_UNITS,
            'scope_variants' => [],
            'rows' => [
                // FRM.846 — Tip Test Raporları (0–10)
                ['0', '', 'Garantili Özellikler Listesi', self::TIP, 'pdf', [], self::HY_UNITS],
                ['1', 'TS EN 62271-200 Madde 6.2', 'Yalıtım (Dielektrik Deneyleri)', self::TIP, 'pdf', [], self::HY_UNITS],
                ['2', 'TS EN 62271-200 Madde 6.5', 'Sıcaklık Artış Deneyleri', self::TIP, 'pdf', [], self::HY_UNITS],
                ['3', 'TS EN 62271-200 Madde 6.4', 'Ana Devre Direncinin Ölçülmesi', self::TIP, 'pdf', [], self::HY_UNITS],
                ['4', 'TS EN 62271-200 Madde 6.6', 'Kısa Süreli Dayanım Akım ve Tepe Dayanım Akımı Deneyleri', self::TIP, 'pdf', [], self::HY_UNITS],
                ['5', 'TS EN 62271-200 Madde 6.101', 'Kapama ve Kesme Kapasitelerinin Denetlenmesi', self::TIP, 'pdf', [], self::HY_UNITS],
                ['6', 'TS EN 62271-200 Madde 6.102', 'Mekanik Manevra Deneyleri', self::TIP, 'pdf', [], self::HY_UNITS],
                ['7', 'TS EN 62271-200 Madde 6.7', 'Korumanın Doğrulanması', self::TIP, 'pdf', [], self::HY_UNITS],
                ['8', 'TS EN 62271-200 Madde 6.104', 'Kaçak Akımların Ölçülmesi', self::TIP, 'pdf', [], self::HY_UNITS],
                ['9', 'TS EN 62271-200 Madde 6.106', 'İç Ark Deneyi', self::TIP, 'pdf', [], self::HY_UNITS],
                ['10', 'TS EN 62271-200 Madde 3.17', 'Boya ve Galvaniz Kaplamaları Üzerindeki Deneyler', self::TIP, 'pdf', [], self::HY_UNITS],
                // FRM.846 — Diğer Belgeler (11–24)
                ['11', '', 'TSE Belgeleri', self::DIG, 'pdf', [], self::HY_UNITS],
                ['12', '', 'ISO 9001 veya 9002 Kalite Yönetim Sistem Belgesi', self::DIG, 'pdf', [], self::HY_UNITS],
                ['13', '', 'Ayrıntılı Kataloglar, Montaj, İşletme ve Bakım Yönergeleri', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['14', '', 'Fonksiyonel Birimlerin Dış Boyutları', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['15', '', 'En Büyük Taşıma Ağırlıkları', self::DIG, 'pdf', [], self::HY_UNITS],
                ['16', '', 'Ambalajlı Olarak En Büyük Taşıma Boyutları', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['17', '', 'Elektrik Bağlantı Resimleri', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['18', '', 'Fonksiyonel Birimlerin Tertip Resimleri', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['19', '', 'Dış Bağlantıların Düzenlenme Şekilleri', self::DIG, 'pdf_img', [], self::HY_UNITS],
                ['20', '', 'Metal Mahfazalı Modüler Anahtarlama ve Kontrol Düzenlerinin Monte Edileceği Zemin için Alınması Gereken Tedbirler ve Montaj Koşulları', self::DIG, 'pdf', [], self::HY_UNITS],
                ['21', '', 'Metal Mahfazalı Modüler Anahtarlama ve Kontrol Düzenlerinde Kullanılan Teçhizatın Listesi (yapımcı firmanın adı ve teçhizatın tip işareti ile birlikte)', self::DIG, 'pdf', [], self::HY_UNITS],
                ['22', '', 'Alıcı\'nın Bulundurması Önerilen Yedek Parça Listeleri, Özel Aletler ve Deney Cihazları Listeleri', self::DIG, 'pdf', [], self::HY_UNITS],
                ['23', '', 'İç Ark\'a Karşı Alınan Önlemler', self::DIG, 'pdf', [], self::HY_UNITS],
                ['24', '', 'YG Sigorta seçim çizelgesi. (teklif edilen sigortalar, hem tip deneylerinde kullanılan sigortaları hem de yerli imalatçıların sigortalarını kapsayacaktır). ("Yük ayırıcısı+sigorta" Birleşiği Transformatör Koruma Fonksiyonel Birimi için)', self::DIG, 'pdf', [], ['YA. TRF KOR', 'GER. TRF']],
            ],
        ],
        'AG-PANO' => [
            'title'          => 'AG Dağıtım Panosu',
            'sartname_kodu'  => 'TEDAS-MLZ-2003-006 B',
            'sartname_adi'   => 'ALÇAK GERİLİM DAĞITIM PANOLARI TEKNİK ŞARTNAMESİ',
            'scope_kind'     => 'kva_variant',
            'scope_headers'  => [...self::PANO_SCOPES, '630 kVA', '1000 kVA', '1250 kVA', '1600 kVA'],
            'scope_variants' => [],
            'rows' => [
                // FRM.865 — Tip Test Raporları (0, 2–20 — evrak 1 yok)
                // Alt tipler kapsam değerine katlandı; kVA ayrı boyut değil.
                ['0', '', 'Garantili Özellikler Listesi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['2', 'TS EN 61439-1 Madde 10.3', 'Mahfazaların Koruma Derecesi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['3', 'TS EN 61439-1 Madde 10.4', 'Yalıtma Aralıkları ve Yüzeysel Kaçak Yolu Mesafeleri Doğrulama Deneyi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['4', 'TS EN 61439-1 Madde 10.5', 'Elektrik Çarpmasına Karşı Koruma ve Koruma Devrelerinin Bütünlüğü', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['5', 'TS EN 61439-1 Madde 10.7', 'Anahtarlama Cihazlarının ve Bileşenlerin Birleşmesi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['6', 'TS EN 61439-1 Madde 10.7', 'Dâhili Elektriksel Devreler ve Bağlantılar', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['7', 'TS EN 61439-1 Madde 10.8', 'Harici İletkenler İçin Bağlantı Uçları', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['8', 'TS EN 61439-1 Madde 10.9', 'Dielektrik Özellikler', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['9', 'TS EN 61439-1 Madde 10.10', 'Sıcaklık Artışının Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['10', 'TS EN 61439-1 Madde 10.11', 'Kısa Devre Dayanım Dayanıklılığı Doğrulama Deneyi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['11', 'TS EN 61439-1 Madde 10.12', 'Elektromanyetik Uyumluluk', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['12', 'TS EN 61439-1 Madde 10.13', 'Mekaniksel Çalışma', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['13', 'TS EN 61439-5 Madde 10.2.101', 'Mekanik Dayanımın Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['14', 'TS EN 61439-5 Madde 10.2.101.2', 'Darbe Kuvvetine Dayanımın Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['15', 'TS EN 61439-5 Madde 10.2.101.3', 'Kapıların Mekanik Dayanımının Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['16', 'TS EN 61439-5 Madde 10.2.101.4', 'Sentetik Malzemedeki Metal Parçaların Eksenel Yüke Dayanıklılığın Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['17', 'TS EN 61439-5 Madde 10.2.101.5', 'Keskin Kenarlı Nesneler Tarafından Üretilen Mekanik Darbe Etkilerine Dayanıklılığın Doğrulanması', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['18', 'TS EN 61439-5 Madde 10.2.101.6', 'Zemine Gömülmesi Amaçlanan Tabanın Mekanik Dayanım Deneyi', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['19', '', 'Galvaniz Kalınlığının Kontrolü', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['20', '', 'Boyanın Kalite Kontrolü', self::TIP, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                // FRM.865 — Diğer Belgeler (21–26)
                ['21', '', 'İmalatçı firmaya ait ISO 9001:2000 Kalite Sistem Belgesi', self::DIG, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['22', '', 'Panolara ait tek hat şemaları', self::DIG, 'pdf_img', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['23', '', 'Pano imalatında kullanılan bütün cihazların yapımcısı, tipi, anma değerleri vb. bilgileri', self::DIG, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['24', '', 'Panoların tertip resimleri,', self::DIG, 'pdf_img', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['25', '', 'Panoların dış görünüş resimleri', self::DIG, 'pdf_img', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
                ['26', '', 'Panoların dış boyutları ve net ağırlığı', self::DIG, 'pdf', self::PANO_PLAIN_KVA, self::PANO_SCOPES],
            ],
        ],
        'SDK' => [
            'title'          => 'Saha Dağıtım Kutusu',
            'sartname_kodu'  => 'TEDAŞ-MLZ/2004-046.B',
            'sartname_adi'   => 'ALÇAK GERİLİM SAHA DAĞITIM KUTULARI TEKNİK ŞARTNAMESİ',
            'scope_kind'     => 'variant',
            'scope_headers'  => self::SDK_TYPES,
            'scope_variants' => [],
            'rows' => [
                // FRM.869 — Tip Test Raporları (0–18)
                ['0', '', 'Garantili Özellikler Listesi', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['1', 'TS EN 61439-5 Madde 10.2', 'Malzemelerin ve Bölümlerin Dayanıklılığı', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['2', 'TS EN 61439-1 Madde 10.3', 'Mahfazaların Koruma Derecesi', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['3', 'TS EN 61439-1 Madde 10.4', 'Yalıtma Aralıkları ve Yüzeysel Kaçak Yolu Mesafeleri Doğrulama Deneyi', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['4', 'TS EN 61439-1 Madde 10.5', 'Elektrik Çarpmasına Karşı Koruma ve Koruma Devrelerinin Bütünlüğü', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['5', 'TS EN 61439-1 Madde 10.6', 'Anahtarlama Cihazlarının ve Bileşenlerin Birleşmesi', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['6', 'TS EN 61439-1 Madde 10.7', 'Dâhili Elektriksel Devreler ve Bağlantılar', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['7', 'TS EN 61439-1 Madde 10.8', 'Harici İletkenler için Bağlantı Uçları', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['8', 'TS EN 61439-1 Madde 10.9', 'Dielektrik Özellikler', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['9', 'TS EN 61439-1 Madde 10.10', 'Sıcaklık Artışının Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['10', 'TS EN 61439-1 Madde 10.11', 'Kısa Devre Dayanım Dayanıklılığı Doğrulama Deneyi', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['11', 'TS EN 61439-1 Madde 10.12', 'Elektromanyetik uyumluluk', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['12', 'TS EN 61439-1 Madde 10.13', 'Mekaniksel Çalışma', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['13', 'TS EN 61439-1 Madde 10.2.101', 'Mekanik Dayanımın Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['14', 'TS EN 61439-5 Madde 10.2.101.5', 'Darbe Kuvvetine Dayanımın Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['15', '(TS EN 61439-5 Madde 10.2.101.6', 'Kapıların Mekanik Dayanımının Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['16', 'TS EN 61439-5 Madde 10.2.101.7', 'Sentetik Malzemedeki Metal Parçaların Eksenel Yüke Dayanıklılığın Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['17', 'TS EN 61439-5 Madde 10.2.101.8', 'Keskin Kenarlı Nesneler Tarafından Üretilen Mekanik Darbe Etkilerine Dayanıklılığın Doğrulanması', self::TIP, 'pdf', [], self::SDK_TYPES],
                ['18', 'TS EN 61439-5 Madde 10.2.101.9', 'Zemine Gömülmesi Amaçlanan Tabanın Mekanik Dayanım Deneyi', self::TIP, 'pdf', [], self::SDK_TYPES],
                // FRM.869 — Diğer Belgeler (19–23)
                ['19', '', 'İmalatçı Firmaya Ait ISO 9001:2000 Kalite Sistem Belgesi', self::DIG, 'pdf', [], self::SDK_TYPES],
                ['20', '', 'SDK\'lara ait Tek Hat Şemaları', self::DIG, 'pdf_img', [], self::SDK_TYPES],
                ['21', '', 'SDK imalatında kullanılan bütün cihazların yapımcısı, tipi, anma değerleri vb. bilgileri', self::DIG, 'pdf', [], self::SDK_TYPES],
                ['22', '', 'SDK\'ların dış görünüş ve tertip resimleri', self::DIG, 'pdf_img', [], self::SDK_TYPES],
                ['23', '', 'SDK\'ların dış boyutları ve net ağırlığı', self::DIG, 'pdf', [], self::SDK_TYPES],
            ],
        ],
    ];

    public function run(): void
    {
        // Full catalog self-sufficiency: dictionary + kVA + scope values first (idempotent).
        $this->call(ProductCategorySeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(KvaSeeder::class);
        $this->call(ScopeValueSeeder::class);

        $catType  = DB::table('sys_options')->where('op_key', 'op-doc-product-category')->value('id');
        $catForm  = DB::table('sys_options')->where('op_key', 'op-doc-product-category-form')->value('id');
        $prodType = DB::table('sys_options')->where('op_key', 'op-doc-product')->value('id');
        $prodForm = DB::table('sys_options')->where('op_key', 'op-doc-product-form')->value('id');
        $formMain = DB::table('sys_options')->where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');

        if (! $catType || ! $catForm || ! $prodType || ! $prodForm || ! $formMain) {
            echo "Dictionary missing — cannot seed catalog.\n";
            return;
        }

        $grp = $GLOBALS['SYS_CODE'] ?? 'GDZ';
        $now = now();

        foreach ($this->categories as $code => $def) {
            $this->seedCategory($code, $def, $catType, $catForm, $prodType, $prodForm, $formMain, $grp, $now);
        }

        echo "TedarikRealCatalogSeeder done.\n";
    }

    private function seedCategory(
        string $code,
        array $def,
        $catType, $catForm, $prodType, $prodForm, $formMain,
        string $grp, $now
    ): void {
        DB::beginTransaction();
        try {
            // ---- find existing category by code entity (plain tag or suffixed tag) ----
            $found = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->where('so.type_id', $catForm)
                ->where(function ($q) {
                    $q->where('se.entity_tag', 'code')->orWhere('se.entity_tag', 'like', 'code**%');
                })
                ->where('se.entity_value', $code)
                ->first(['so.id as conn_id', 'so.main_id']);

            $insert = function (int $connId, string $tag, string $value) use ($now) {
                DB::table('sys_con_entities')->insert([
                    'conn_id' => $connId, 'entity_tag' => $tag, 'entity_value' => $value,
                    'table_tag' => 'sys_con_ops', 'created_at' => $now, 'updated_at' => $now,
                ]);
            };

            if ($found) {
                $catId   = (int) $found->main_id;
                $catConn = (int) $found->conn_id;
                echo "Category {$code} exists — refreshing rows in place.\n";
            } else {
                $catQnid = (string) Str::uuid();
                $catId = DB::table('documents')->insertGetId([
                    'status' => 1, 'parent_type_id' => 0, 'parent_id' => 0,
                    'type_id' => $catType, 'person_id' => 'system', 'title' => '-',
                    'grp_code' => $grp, 'qnid' => $catQnid,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $catConn = DB::table('sys_con_ops')->insertGetId([
                    'main_id' => $catId, 'conn_id' => 0, 'type_id' => $catForm,
                    'sub_type_id' => $formMain, 'status' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $insert($catConn, 'qnid', $catQnid);
                $insert($catConn, 'title', $def['title']);
                $insert($catConn, 'code', $code);
                echo "Category {$code} created ({$def['title']}).\n";
            }

            // ---- wipe old requirement rows across ALL conns of this category doc ----
            $connIds = DB::table('sys_con_ops')
                ->where('main_id', $catId)->where('type_id', $catForm)
                ->pluck('id')->toArray();
            DB::table('sys_con_entities')
                ->whereIn('conn_id', $connIds)
                ->where('entity_tag', 'like', '%**category_files**%')
                ->delete();

            // ---- category metadata (spec + scope connections) ----
            $kvaCodes = [];
            $variantCodes = [];
            foreach ($def['rows'] as $r) {
                foreach ($r[5] as $k) $kvaCodes[$k] = true;
                foreach ($r[6] as $v) $variantCodes[$v] = true;
            }
            $scopeValues = array_values(array_unique(array_merge(
                $def['scope_kind'] === 'variant' ? [] : array_map('strval', array_keys($kvaCodes)),
                array_map('strval', array_keys($variantCodes))
            )));

            $meta = [
                'sartname_kodu'  => $def['sartname_kodu'],
                'sartname_adi'   => $def['sartname_adi'],
                'scope_kind'     => $def['scope_kind'],
                'scope_headers'  => json_encode($def['scope_headers']),
                'scope_values'   => json_encode($scopeValues),
                'scope_variants' => json_encode($def['scope_variants']),
            ];
            foreach ($meta as $tag => $value) {
                DB::table('sys_con_entities')->updateOrInsert(
                    ['conn_id' => $catConn, 'entity_tag' => $tag, 'table_tag' => 'sys_con_ops'],
                    ['entity_value' => $value, 'updated_at' => $now]
                );
            }

            // ---- file-requirement rows ----
            DB::table('sys_con_entities')->updateOrInsert(
                ['conn_id' => $catConn, 'entity_tag' => 'row_count', 'table_tag' => 'sys_con_ops'],
                ['entity_value' => (string) count($def['rows']), 'updated_at' => $now]
            );
            foreach ($def['rows'] as $i => $r) {
                [$evrakNo, $standard, $fileTitle, $fileGroup, $fileTypes, $kva, $variants] = $r;
                // Row key shape matters: Form.vue de-dupes multiple rows by the first
                // token before the dash (`rowKey.split('-')[0]`). Unique prefix per row.
                $rowKey = (1700000000000 + $i).'-'.$i;
                $suffix = '**category_files**'.$rowKey;

                $insert($catConn, 'evrak_no'.$suffix, $evrakNo);
                $insert($catConn, 'standard'.$suffix, $standard);
                $insert($catConn, 'file_title'.$suffix, $fileTitle);
                $insert($catConn, 'file_group'.$suffix, $fileGroup);
                $insert($catConn, 'file_types'.$suffix, $fileTypes);
                $insert($catConn, 'kva'.$suffix, json_encode(array_values($kva)));
                $insert($catConn, 'variants'.$suffix, json_encode(array_values($variants)));
            }

            // ---- example product (one per category, idempotent by prod_code) ----
            [$pCode, $pName, $pUnit] = self::EXAMPLE_PRODUCTS[$code];
            $prodExists = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->where('so.type_id', $prodForm)
                ->where(function ($q) {
                    $q->where('se.entity_tag', 'prod_code')->orWhere('se.entity_tag', 'like', 'prod_code**%');
                })
                ->where('se.entity_value', $pCode)
                ->exists();

            if (! $prodExists) {
                $catQnid = DB::table('documents')->where('id', $catId)->value('qnid');
                $pQnid = (string) Str::uuid();
                $pId = DB::table('documents')->insertGetId([
                    'status' => 1, 'parent_type_id' => 0, 'parent_id' => 0,
                    'type_id' => $prodType, 'person_id' => 'system', 'title' => '-',
                    'grp_code' => $grp, 'qnid' => $pQnid,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $pConn = DB::table('sys_con_ops')->insertGetId([
                    'main_id' => $pId, 'conn_id' => 0, 'type_id' => $prodForm,
                    'sub_type_id' => $formMain, 'status' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $insert($pConn, 'qnid', $pQnid);
                $insert($pConn, 'prod_code', $pCode);
                $insert($pConn, 'short_name', $pName);
                $insert($pConn, 'unit', $pUnit);
                $insert($pConn, 'category_title', $def['title']);
                $insert($pConn, 'category_qnid', $catQnid);
                echo "  → example product {$pCode} created.\n";
            }

            DB::commit();
            echo "  → {$code}: ".count($def['rows'])." file requirements seeded (scope=".$def['scope_kind'].").\n";
        } catch (\Throwable $e) {
            DB::rollBack();
            echo "  ✗ {$code} ROLLED BACK: ".$e->getMessage()."\n";
        }
    }
}
