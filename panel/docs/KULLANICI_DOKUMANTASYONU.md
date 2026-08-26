# KomurTedarik — Kullanıcı Dokümantasyonu

**Sürüm:** 1.0 · **Tarih:** 2026-08-01
**Hedef kitle:** Sistemi kullanan yöneticiler (satınalma personeli) ve tedarikçi firma kullanıcıları.

---

## 1. Sistem nedir, ne işe yarar?

KomurTedarik, kömür satınalma sürecini uçtan uca yöneten bir web uygulamasıdır:

- **Talep (ihale) yönetimi:** Satınalma ekibi kömür talebi açar, süresini ve şartlarını belirler.
- **Tedarikçi (müşteri) yönetimi:** Firmalar sisteme kaydolur, firma bilgilerini ve
  zorunlu belgelerini (imza sirküleri vb.) yükler; belgeler onaylanmadan teklif veremezler.
- **Teklif yönetimi:** Onaylı tedarikçiler açık taleplere teklif verir; satınalma ekibi
  teklifleri onaylar, reddeder veya revizyon ister.
- **Bildirimler:** Teklif geldiğinde, belge onaylandığında/reddedildiğinde, durum
  değiştiğinde ilgili kişilere e-posta (ve SMS) gider; paneldeki zil simgesinden de izlenir.
- **Kayıt ve denetim:** Sistemdeki her işlem (giriş, değişiklik, onay, red) loglanır.

Sisteme iki adres üzerinden girilir (kurumunuza göre):
`komurtedarik.cates.com.tr` veya `komurtedarik.yatagantermik.com.tr`

### 1.1 Kimler kullanır?

| Rol | Kim | Yapabildikleri (özet) |
|---|---|---|
| **Yönetici (Admin)** | Satınalma / sistem personeli | Talep açar/kapatır, firmaları ve belgeleri onaylar, teklifleri değerlendirir, kullanıcı ve rol yönetir, logları izler |
| **Tedarikçi (Müşteri kullanıcısı)** | Firma temsilcisi | Firma bilgi formunu doldurur, belgelerini yükler, açık taleplere teklif verir, teklifini revize eder |

Ayrıca yöneticiler arasında **rol şablonları** ile farklı yetki seviyeleri tanımlanabilir
(ör. sadece görüntüleme yetkisi olan personel).

---

## 2. Giriş, kayıt ve şifre işlemleri

### 2.1 Sisteme giriş (2 aşamalı güvenlik)
1. Giriş sayfasını açın (ana adres sizi karşılar).
2. **E-posta** ve **şifrenizi** girin, "Ben robot değilim" (reCAPTCHA) kutusunu işaretleyin.
3. **Giriş** düğmesine basın.
4. Sistem size **6 haneli doğrulama kodu** gönderir — kayıtlı telefonunuza **SMS**
   ve/veya e-postanıza. Kod **2 dakika** geçerlidir.
5. Kodu ekrandaki kutulara girin.
   - Kod gelmediyse **"Tekrar Gönder"** kullanılabilir; en fazla 2 kez, aralarında
     60 saniye bekleme şartıyla.
6. Kod doğruysa panele yönlendirilirsiniz.

> **Önemli:** Aynı hesapla başka bir cihazdan giriş yapılırsa önceki oturum otomatik
> kapanır ("Bu hesaba başka bir cihazdan giriş yapıldı" uyarısı).

### 2.2 Başarısız girişler ve kilit
- Şifre 5 kez yanlış girilirse hesap **15 dakika** kilitlenir; kalan deneme hakkı
  hata mesajında gösterilir.

### 2.3 İlk girişte şifre değiştirme
Yönetici tarafından hesabınız yeni açıldıysa (veya şifreniz sıfırlandıysa) ilk girişte
sistem sizi **yeni şifre belirleme** ekranına yönlendirir. Kurallar ekranda gösterilir
(güçlü şifre zorunluluğu vardır). Şifreyi belirleyince tekrar giriş yaparsınız.

### 2.4 Şifremi unuttum
1. Giriş sayfasındaki **şifre sıfırlama** bağlantısına tıklayın, e-postanızı yazın.
2. E-postanıza gelen bağlantıya tıklayın.
3. Telefonunuza/e-postanıza gelen **doğrulama kodunu** girin.
4. Yeni şifrenizi belirleyin ve giriş yapın.

### 2.5 Tedarikçi olarak kayıt (ilk defa)
1. Ana sayfadaki **Kayıt Ol** bağlantısını açın.
2. E-posta, telefon ve şifrenizi girin, reCAPTCHA'yı işaretleyin.
3. Kaydınız **yönetici onayına** düşer; "bilgileriniz incelendikten sonra kayıt
   tamamlanacaktır" mesajını görürsünüz. Yöneticilere bilgilendirme e-postası gider.
4. Hesabınız onaylanıp aktifleştirilince giriş yapabilirsiniz.
5. **İlk işiniz:** Firma bilgi formunuzu doldurmak ve zorunlu belgeleri yüklemek
   (bkz. §4.2). Bunlar tamamlanmadan sistemde ilerleyemezsiniz.

---

## 3. Panel genel görünüm

Giriş sonrası **/coalpanel** paneli açılır:

- **Sol menü:** Rolünüze göre sayfalar (Dashboard, Talepler, Teklifler, Müşteriler,
  Belgeler, Kullanıcılar, Roller, Bildirim Ayarları, Sistem Logları, Bildirim Logları).
- **Üst bar:** Bildirim zili (okunmamış sayısı rozetiyle), kullanıcı adınız, çıkış.
- **Dashboard:** Rolünüze göre özet kartları — yöneticide devam eden talepler, aylık
  dağılım grafiği, süreci grafikleri, hızlı aksiyonlar; tedarikçide firma durumunuz,
  açık talepler ve kendi teklifleriniz.

### 3.1 Bildirimler
- **Zil simgesi:** Size atanmış bildirim gruplarına düşen son olayları listeler
  (yeni teklif, reddedilen belge, onay bekleyen kayıt vb.).
- **E-posta/SMS:** Önemli olaylar (teklif alındı, belge reddedildi, firma bilgisi
  değişti, kayıt onayı) kayıtlı iletişim adreslerinize da gider.

---

## 4. Tedarikçi (müşteri) işlemleri

### 4.1 Firma bilgi formu (zorunlu)
**Menü:** Panel açıldığında bilgileriniz eksikse sistem sizi firma formuna yönlendirir
(menüde kendi firmanız).

1. Firma formunu açın: ünvan, vergi numarası, adres, iletişim bilgileri vb. alanları doldurun.
2. **Belgeler** bölümünden zorunlu dosyaları yükleyin — özellikle **imza sirküleri**
   (imza dosyası). Kabul edilen formatlar: PDF, JPG, PNG, XLS/XLSX (en fazla ~40MB).
3. **Kaydet** düğmesine basın.
4. Değişiklikler yöneticilere otomatik e-posta ile bildirilir.

> Bir alana yeniden dosya yüklerseniz eskisi arşivlenir (versiyonlama); eski
> versiyonlar detay ekranından görülebilir.

### 4.2 Belge onay süreci
- Yüklediğiniz her belge **"Beklemede"** durumuna düşer.
- Yönetici belgeyi **onaylar** veya **reddeder** (red notuyla). Sonuç size e-posta ile
  bildirilir; reddedilen belgeler panelinizde uyarı olarak listelenir.
- Reddedilen belgeyi düzeltip **aynı alana tekrar yükleyin**.
- **Teklif verebilmek için** firma formunuzun dolu ve imza belgenizin **onaylı** olması
  gerekir. Onaylanmamışsa teklif düğmeleri çalışmaz.

### 4.3 Açık taleplere teklif verme
1. **Talepler** listesini açın — sadece açık (başlamış) talepleri görürsünüz.
2. Talebin satırına tıklayıp detayını açın: istenen kömür özellikleri, miktar, teslim
   tarihi ve ekleri inceleyin; talep dosyalarını indirebilirsiniz.
3. **Teklif Ver** ile formu doldurun (fiyat, miktar, teslim şartları, açıklama;
   gerekirse teklif dosyası ekleyin) ve **gönderin**.
4. Teklifiniz "Gönderildi" durumuna düşer ve yöneticilere bildirim gider.

Alternatif: **Teklifler** sayfasından bağımsız teklif de oluşturabilirsiniz
(tür seçim penceresi açılır).

### 4.4 Teklif durumunu izleme ve revizyon
**Teklifler** listesinde teklifinizin durumunu görürsünüz:

| Durum | Anlamı |
|---|---|
| Gönderildi | Teklifiniz yöneticiye ulaştı |
| İncelemede | Yönetici teklifi açtı, değerlendiriyor |
| Onaylandı | Teklifiniz kabul edildi |
| Reddedildi | Teklifiniz reddedildi (not e-postayla gelir) |
| Revizyon İstendi | Yönetici düzeltme istiyor — teklifi açıp güncelleyin |

- **Revizyon istendiğinde:** Teklifi açın, alanları güncelleyin, kaydedin. Durum
  otomatik "Revize Edildi" olur ve yöneticiye bildirim gider.
- Revizyon/inceleme sonrası teklif artık düzenlenemez; sadece "Revizyon İstendi",
  taslak veya yeni oluşturulmuş teklifler düzenlenebilir.
- Teklif formundan **PDF** çıktısı alabilirsiniz (ekleriyle birlikte).

---

## 5. Yönetici işlemleri

### 5.1 Yeni tedarikçi (müşteri) kaydı
**Menü: Müşteriler → Yeni Ekle**
1. Firma formunu açın; ünvan, kod, iletişim ve belge alanlarını doldurun.
2. Kaydedin. Firmaya bağlı kullanıcı hesabı gerekiyorsa firma formundaki kullanıcı
   talebi bölümünü kullanın (kayıt isteği oluşturur).
3. Firma kaydını silmek/pasife almak bağlı kullanıcıları da pasifleştirir.

### 5.2 Belge onay/red
**Menü: Belgeler** (veya firma formu içinden)
1. Bekleyen dosyaları grup bazında listeleyin; dosyayı açıp inceleyin (indirilebilir).
2. **Onayla** veya **Reddet** (redde not yazın) düğmesini kullanın.
3. Toplu işlem için firma formundaki **tümünü onayla/reddet** aksiyonu vardır.
4. Sonuç firmaya otomatik e-postayla bildirilir.

### 5.3 Talep (ihale) açma
**Menü: Talepler → Yeni Talep**
1. Talep formunu doldurun: hedef/tür (ör. kömür tipi — liste kodu otomatik büyük harfe
   çevrilir), miktar, termin tarihi, teknik şartlar, ek dosyalar.
2. Kaydedin — talep numarası otomatik verilir.
3. Talep listesinden **başlat/bitir/iptal** durumlarını yönetirsiniz.
4. Bitiş tarihi geçen talep, gece 01:00'de çalışan zamanlanmış görevle otomatik kapanır.

### 5.4 Teklifleri değerlendirme
**Menü: Teklifler** (veya talep detayındaki teklif tablosu)
1. Gelen teklifleri listeleyin; satırdan teklif detayını ve eklerini açın.
2. Teklifi açtığınızda durumu otomatik "İncelemede" olur.
3. Aksiyonlar:
   - **Onayla** — teklifi kabul eder, tedarikçiye bildirim gider.
   - **Reddet** — not ile reddeder.
   - **Revizyon İste** — tedarikçi düzeltsin diye geri gönderir.
4. Her adım **Teklif Log** zaman çizelgesine işlenir (kim, ne zaman, not).
5. **PDF:** Teklif detayından ekleriyle birlikte PDF/ZIP indirilebilir.
6. Listeden Excel çıktısı alınabilir.

### 5.5 Kullanıcı yönetimi
**Menü: Kullanıcılar**
1. **Yeni kullanıcı:** Kişi bilgileri + iletişim + rol ataması + izin seti.
2. **Düzenle:** Bilgi/rol/izin güncelleme — izin değişikliği kullanıcının açık
   oturumlarına otomatik yansır (yeniden giriş gerekmez).
3. **Şifre sıfırla:** Kullanıcıya yeni şifre e-postası gönderir (kullanıcı ilk girişte
   değiştirmek zorundadır).
4. **Pasifleştir:** Kullanıcıyı listeden kaldırır; açık oturumları düşürülür.

### 5.6 Rol ve yetki yönetimi
**Menü: Roller**
1. Hazır rol şablonlarını görürsünüz (5 temel rol kilitlidir, değiştirilemez).
2. **Yeni şablon** oluşturabilir veya özel şablonların izin ağacını açıp izinleri
   işaretleyebilirsiniz (izinler `per-XX-YY` kodlarıyla gruplu).
3. Kaydedince, bu roldeki **tüm kullanıcıların yetkileri anında güncellenir**.
4. Tüm şablon değişiklikleri denetim kaydına (audit) yazılır.

### 5.7 Bildirim ayarları
**Menü: Bildirim Ayarları**
1. Bildirim gruplarını (ör. yeni teklif, belge onayı, kayıt bildirimi) görürsünüz.
2. Her gruba hangi yöneticilerin dahil olacağını işaretleyin ve kaydedin.
3. Gruptaki kişiler ilgili olaylarda e-posta alır ve panel bildirimlerinde görür.

### 5.8 Loglar
- **Sistem Logları:** Tüm işlemler (giriş/çıkış, kayıt değişiklikleri önce/sonra
  değerleriyle, onaylar, kilitlenmeler). Satıra tıklayıp JSON detayını görebilirsiniz.
- **Bildirim Logları:** Gönderilen/bekleyen/hatalı e-posta ve SMS'ler. Hatalı olanları
  **yeniden tetikle** düğmesiyle tekrar gönderebilirsiniz.

### 5.9 Excel / PDF çıktıları
Liste ekranlarındaki **Excel** düğmesi mevcut listeyi indirir (talepler, teklifler,
müşteriler, loglar). Teklif detayındaki **PDF** düğmesi teklif dosyasını (ekleriyle)
indirir.

---

## 6. Uçtan uca örnek senaryolar

### Senaryo A — Yeni tedarikçinin ilk teklifi
1. Tedarikçi kayıt olur → yönetici onaylar.
2. Tedarikçi giriş yapar, firma formunu doldurur, imza sirkülerini yükler.
3. Yönetici belgeyi onaylar → tedarikçiye "belgeniz onaylandı" e-postası.
4. Yönetici yeni talep açar ve başlatır.
5. Tedarikçi talebi görür, teklif verir → yöneticiye "yeni teklif" bildirimi.
6. Yönetici teklifi inceler → **onaylar** → tedarikçiye "teklifiniz onaylandı" e-postası.

### Senaryo B — Revizyonlu teklif
1–5 aynı. 6'da yönetici **Revizyon İste** der (not: "birim fiyatı güncelleyin").
7. Tedarikçiye bildirim gider; teklifini açıp fiyatı güncelleyip kaydeder → durum
   "Revize Edildi" olur, yöneticiye bildirim düşer.
8. Yönetici tekrar inceler, onaylar.

### Senaryo C — Reddedilen belge
1. Tedarikçi imza sirkülerini yükler.
2. Yönetici belgeyi inceler, eksik imza nedeniyle **reddeder** (not yazar).
3. Tedarikçiye e-posta + panel uyarısı düşer; teklif veremez.
4. Doğru belgeyi aynı alana yükler → yönetici onaylar → teklif hakkı açılır.

---

## 7. Sorun giderme / SSS

| Sorun | Çözüm |
|---|---|
| SMS kodu gelmedi | 60 sn bekleyip "Tekrar Gönder" (en fazla 2 kez). E-postanızı da kontrol edin — kod iki kanaldan gidebilir. Sürekli sorunsa yöneticinize telefon/e-posta kaydınızı kontrol ettirin |
| "Kod süresi doldu" | Kod 2 dakika geçerlidir; yeni kod isteyin |
| "Hesap kilitlendi" | 5 yanlış deneme sonrası 15 dk kilit. Bekleyin veya yönetici şifre sıfırlasın |
| Teklif Ver düğmesi çalışmıyor | Firma formunuz eksik veya imza belgeniz onaylanmamıştır; belgelerin durumunu kontrol edin |
| "Başka bir cihazdan giriş yapıldı" | Hesabınızla yeni bir giriş yapılmış; eski oturum kapatılır. Şüpheliyse şifrenizi değiştirin |
| Menüde bazı sayfalar yok | Yetkiniz yoktur; yöneticiniz rolünüzü güncelleyebilir |
| Dosya yüklenmiyor | Format PDF/JPG/PNG/XLS/XLSX ve ~40MB altı olmalı |
| Sayfa boş geliyor | Oturum süresi dolmuş olabilir; sayfayı yenileyip tekrar giriş yapın |

**İletişim:** Sistem sorunları için satınalma biriminize veya sistem yöneticinize başvurun.

---

## 8. Bilinen sınırlamalar (yönetici bilgilendirme)

> Bu maddeler teknik incelemede doğrulanmış kısıtlardır; kullanım alışkanlığını etkiler.

- "Talepler" ekranındaki takvim/etkinlik kartı veri getirmeyebilir (eski bağlantı — düzeltme listesinde).
- Talep detayındaki teklif tablosunda "Kabul Edildi" düğmesi hata verebilir; onay için
  **Teklifler** sayfasını veya teklif detay formunu kullanın.
- Aynı anda iki yönetici rol şablonlarını düzenlerse son kaydedenin değişikliği geçerli olur.
- Gece 01:00'deki otomatik talep kapatma, aynı gün biten birden fazla talebin yalnızca
  ilkini kapatabilir; kalanları listeden elle kapatın.
