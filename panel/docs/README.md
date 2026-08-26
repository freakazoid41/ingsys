# KomurTedarik — Dokümantasyon İndeksi

> Bu klasör, projenin devir süreci için hazırlanmıştır. Tüm kaynak dosyalar okunarak
> çıkarılmıştır; hiçbir iddia koda bakılmadan yazılmamıştır.
> Oluşturma: 2026-08-01 · Kapsam: 362 git-takipli dosya (247 kaynak dosya tam okundu,
> 115 derlenmiş/statik asset listelendi).

## Dosyalar

| Dosya | İçerik |
|---|---|
| `00-dosya-checklist.md` | Tüm dosyaların envanteri + okuma checklist'i (satır/boyut bilgili) |
| `01-mimari-genel-bakis.md` | Sistemin amacı, mimarisi, modülleri, istek akışları |
| `02-bulgular-ve-dogrulama.md` | Tüm bulgular (güvenlik/hata/teknik borç) + doğrulama kanıtları |
| `03-iliski-haritasi.md` | Route → Controller → Servis → Model → View ilişki haritası |
| `mapping/10-models.md` | app/Models — 16 model detayı |
| `mapping/11-http.md` | Controller/Middleware/Resource + route eşleşmesi |
| `mapping/12-services.md` | Services/Classes/Helpers/Rules/Policies |
| `mapping/13-infra.md` | Console komutları, Job'lar, Provider'lar, zamanlanmış görevler |
| `mapping/14-config-database.md` | config, migration şemaları (26 tablo), seeder zinciri |
| `mapping/15-frontend-core.md` | app.js, router, store'lar, pickle.js, layout'lar |
| `mapping/16-frontend-pages.md` | 18 sayfa: route, endpoint, kullanıcı işlevleri |
| `mapping/17-frontend-components.md` | Dashboard/Offer/coalparts component'leri |
| `mapping/18-views-i18n-mail.md` | Blade view'lar, .falanml mail şablonları, dil dosyaları |
| `mapping/19-mevcut-dokuman-inceleme.md` | documentation/ altındaki 5 eski dokümanın doğrulaması |
| `mapping/20-misc.md` | Testler, scriptler, CI, storage/entities, kök yapılandırma |

## Nihai çıktılar (köktte)

| Dosya | Açıklama |
|---|---|
| `TEKNIK_DOKUMANTASYON.pdf` | Kod rehberi: sistemin nasıl çalıştığı (geliştiriciye) |
| `KULLANICI_DOKUMANTASYONU.pdf` | Kullanıcı kılavuzu: ekranlar ve işlem adımları |

Kaynak markdown'ları: `docs/TEKNIK_DOKUMANTASYON.md`, `docs/KULLANICI_DOKUMANTASYONU.md`.

## Eski dokümanlar (doğrulandı)

`documentation/` altındaki 5 doküman incelendi ve kodla çapraz kontrol edildi
(`mapping/19-mevcut-dokuman-inceleme.md`). Özet: export-system doğru; diğer 4'ü kısmen
güncel değil (kırık route referansları, çözülmüş issue'lar, 24 saat/24 dakika hatası).

## Hızlı başlangıç (yeni geliştirici)

1. Bu dosyayı ve `01-mimari-genel-bakis.md`'yi oku.
2. `03-iliski-haritasi.md` ile hangi isteğin nereye gittiğini gör.
3. Değiştireceğin alanın `mapping/` dosyasını oku.
4. `02-bulgular-ve-dogrulama.md`'deki kırmızı bulguları bil — dokunmadan önce riskleri anla.
