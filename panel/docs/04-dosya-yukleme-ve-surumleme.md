# 04 — Dosya Yükleme ve Sürümleme (Version Tracking) Sistemi

> **Kapsam:** Dosya yükleme, geçici (temp) yükleme, değiştirme (replacement), sürüm geçmişi,
> durum makinesi entegrasyonu, dosya servis etme ve kaldırma akışları.
> **Referans doküman:** Kök dizindeki `file-versioning-system.md` (taşınabilir referans, Türkçe).
> **İlgili kod:** `panel/app/Helpers/DocumentHelpers.php`, `panel/app/Providers/DocumentServiceProvider.php`,
> `panel/app/Http/Controllers/DocumentController.php`, `panel/app/Models/Document_files.php`,
> `panel/resources/js/components/coalparts/Form.vue`.
> **Son doğrulama:** 2026-08-31 (Order Management System, `tedarikNewApp` DB).

---

## 1. Mimari Özet — Nasıl Çalışır

EAV yapısında dosyalar iki tabloda yaşar:

```
document_files     — fiziksel dosya kaydı (id, qnid, description=ŞİFRELİ dosya adı,
                     relation, relation_id, status, replaced_id)
sys_con_entities   — form bağlantısı (conn_id → sys_con_ops.id,
                     entity_tag = {alan}**{grup}**{satır},
                     entity_value = document_files.id, table_tag='document_files')
```

- `document_files.description` diskteki gerçek dosya adının **AES-128-CBC + PBKDF2** ile
  şifrelenmiş halidir (`EncryptionProvider`, `pickle` anahtarı). Disk adı
  `{time}{rand5}{slugify(orijinal ad)}.{uzantı}` biçimindedir; DB'de düz metin saklanmaz.
- Dosya hangi forma ait olduğunu `sys_con_entities` üzerinden öğrenir (form bağlantısı
  değil, dosyanın kendisi). FK yoktur, bağlantı `entity_value` metin kolonundaki dosya id'sidir.

### 1.1 Sürüm Geçmişi = Entity Satırlarının Çoğaltılması (Kritik Kural)

**Her dosya yüklemesi `sys_con_entities`'e YENİ bir satır ekler.** Aynı slot için
(aynı `entity_tag`) zamanla birden çok satır birikir:

```
entity_tag = 'transfer_cins_file**transfer_cins**new-1'

satır 1: entity_value = 110  → document_files 110 (status=0, eski)
satır 2: entity_value = 111  → document_files 111 (status=0, eski)
satır 3: entity_value = 112  → document_files 112 (status=1, GÜNCEL)
```

- **Aktiflik = `document_files.status`** (1 = güncel sürüm). Entity satırında status
  YOKTUR, eklenmez.
- `old_versions` (geçmiş versiyon listesi) `entity_tag` eşleşmesiyle tüm satırları bulur.
- Form okuma (`getFormData`) yalnızca **aktif dosyayı** döndürür (`EXISTS(status=1)` filtresi).
- Bu kural 2026-08-31'de son haline geldi: daha önce "entity'yi yerinde güncelle" denenmişti,
  ancak o zaman `old_versions` bozuluyordu. **Entity satırları ASLA güncellenmez/tekilleştirilmez.**

### 1.2 Değiştirme Zinciri = `replaced_id` (Geriye Dönük)

`replaced_id` **yeni dosyada** durur ve **bir önceki sürümü** gösterir:

```
110 (ilk)  →  111 (replaced_id=110)  →  112 (replaced_id=111)
```

Yani: `yeniDosya.replaced_id = eskiDosya.id`. (Eski kod `eski.replaced_id = yeni`
yapıyordu — yanlış yön, düzeltildi.)

---

## 2. Veritabanı — Schema

```sql
document_files (
    id           bigserial PK,
    qnid         uuid          -- dış kimlik (model boot: creating hook'unda üretilir)
    description  text          -- ŞİFRELENMİŞ dosya adı
    relation     varchar(20) default '-',  -- 'documents' | 'temp' | '-'
    relation_id  int    default 0,         -- documents.id (temp ise 0)
    type_id      int,                      -- sys_options.id (örn. form-file)
    status       smallint default 1,       -- 1 AKTİF / 0 pasif (sürüm geçmişi)
    replaced_id  int    default 0,         -- YENİ dosyada: bir önceki sürümün id'si
    grp_code     varchar,                  -- tenant etiketi (model boot: SYS_CODE)
    created_at / updated_at
)

sys_con_entities (
    id           bigserial PK,
    conn_id      int,          -- → sys_con_ops.id (form bağlantısı)
    table_tag    varchar(100), -- 'document_files' (dosyalar) | 'sys_con_ops' (skaler)
    entity_tag   varchar(100), -- '{alan}**{grup}**{satır}'
    entity_value text,         -- dosya ise document_files.id (text)
    qnid / created_at / updated_at
)
```

> **NOT:** `sys_con_entities`'e status sütunu EKLEME. Aktiflik her zaman
> `document_files.status`'tan türetilir.

### 2.1 Dosya tipi dictionary'si

`sys_options`'ta dosya tipi, alan adının başına `op-` eklenerek çözülür:
`op-transfer_kabul_file`, `op-transfer_cins_file`, `op-item_test_file`, `op-item_images_file`
(`DocumentServiceProvider.php:234`, `Document_files::tableList:117`). Tanımsız alan adı
"Dosya" olarak gösterilir.

---

## 3. Yükleme Akışları — İki Yol

### 3.1 Yol A — Geçici (Temp) Yükleme (frontend'in HER ZAMAN kullandığı yol)

```
Form.vue dosya seçildi
  → submitDynamicChanges (Form.vue:2277-2290) anında uploadTempFile()
  → POST /v1/temp-upload (DocumentController::tempUpload, DocumentController.php:478)
  → tempUploadFile (DocumentHelpers.php:468)
      • disk: storage/app/public/temp/{time}{rand5}{slug}.ext
      • DB: Document_files(relation='temp', relation_id=0, status=1, description=şifreli)
      • yanıt: { success, reference_id, encrypted_name, original_name }
  → formData.files[fileKey] = { reference: ref }   (Form.vue:2284)

Form KAYDET
  → FormData: data=JSON(formData) + dosya anahtarları = JSON.stringify(reference)
  → POST/PUT /v1/document (DocumentController::index)
      • reference string'leri $files'a geri birleştirilir (POST:91-96, PUT:157-161)
        — is_string && is_json kontrolü, multipart string alan olarak gelirler
  → registerContent (DocumentServiceProvider.php:241-289)
      • AKTİF entity tespiti ($oldFileEntity, aşağıda §4)
      • isReference ise finalizeTempFile(referenceId, docId, 'form-file', existingFileId)
      • YENİ entity satırı oluşturulur (her yüklemede)
```

**Neden iki adım?** Kullanıcı formu kaydetmeden dosya seçmiş olabilir. Dosya seçilir
seçilmez `temp`'e taşınır; form kaydedilince `finalizeTempFile` temp→documents taşır ve
belgeye bağlar. Kaydedilmeyen temp dosyalar cron ile temizlenir (§7).

### 3.2 Yol B — Direkt Yükleme (`addFileToDb`)

Kömür formları / eski akışlar için korunmuş yol:

```
addFileToDb($file, $tag, $rowId=değiştirilen dosya id, 'documents', $docId, $msg)
  (DocumentHelpers.php:757)
  → uploadFile (DocumentHelpers.php:706): boyut/tip kontrolü + documents/ + şifreleme
  → Document_files oluştur (relation='documents')
  → Transactions doc_file_waiting + UserLog log-file-added
  → $rowId != 0 ise REPLACEMENT: eski status=0, yeni replaced_id=eski,
    Transactions doc_file_refreshed, entity kopyalama bloğu
```

### 3.3 Frontend fileKey → entity_tag üretimi (sözleşme)

`Form.vue` dosya input adı çoklu satırlarda birleşik yapılır (`Form.vue:2736`):

```
el.name = {alan}**{grup}**{satır}         örn: transfer_kabul_file**transfer_kabul**new-1
```

`submitDynamicChanges` (Form.vue:2279) fileKey'i kurar:

```
fileKey = {tag}**dynamicFile**{fileId}**{rowId}*-*{name}
       = op-doc-order-form**dynamicFile**{0|eskiDosyaId}**{connRowId}*-*{alan}**{grup}**{satır}
```

Backend (`DocumentServiceProvider.php:226-232`):

```php
$fileName = explode('*-*', $fkey)[1];   // {alan}**{grup}**{satır}  → entity_tag
$typeTag  = explode('**', $fileName)[0];// alan adı → 'op-'.alan dosya tipi çözümü
$fileId   = explode('**', $fkey)[2];    // eski dosya id (yükleme öncesi mevcutsa)
```

> Bu sözleşme değişirse hem Form.vue hem registerContent birlikte güncellenmelidir.

---

## 4. Değiştirme Tespiti — AKTİF Entity'yi Bul (Kritik Düzeltme)

`registerContent` her dosya işlemeden önce mevcut AKTİF dosyayı bulur
(`DocumentServiceProvider.php:241-249`):

```php
$oldFileEntity = Sys_con_entities::where([
        'conn_id'    => $conn->id,
        'entity_tag' => $fileName,
        'table_tag'  => 'document_files',
    ])
    ->whereIn('entity_value', function ($q) {
        $q->selectRaw('id::text')->from('document_files')->where('status', 1);
    })
    ->orderByDesc('id')->first();   // en yeni AKTİF satır

$existingFileId = 0;
if ($oldFileEntity && is_numeric($oldFileEntity->entity_value)) {
    $existingFileId = (int) $oldFileEntity->entity_value;
}
```

**Kritik:** `whereIn(entity_value, aktif dosya id'leri) + orderByDesc('id')` ŞARTTIR.
Sıralamasız `first()` en ESKİ satırı döndürür → `existingFileId` her zaman İLK dosya olur
→ her değiştirmede ilk dosya pasife alınır, son yüklenen aktif kalır (2026-08-31'de
çözülen Hata 2, bkz. §9).

---

## 5. Okuma Yolu — Yalnız Aktif Dosya

### 5.1 `getFormData` (belge detayı)

`DocumentServiceProvider.php:343-385` — entity join'inde dosya entity'leri yalnızca dosyası
AKTİF ise gelir:

```sql
left join sys_con_entities sce on sce.conn_id = dco.id
  and (sce.table_tag <> 'document_files'
       or exists (select 1 from document_files dfe
                  where dfe.id = sce.entity_value::int and dfe.status = 1))
```

Dosya alanı için değer, `document_files` + son dosya işleminin (`last_status`) JSON'u olarak
text döner (`op_id=1`, `order by t.id desc limit 1`). Frontend `Form.vue:2476` `JSON.parse`
ile okur; `last_status.op_key` değerine göre `is-valid`/`is-invalid` rozetini ve
`/order-file/{qnid}` büyüteç linkini basar (`Form.vue:2482-2506`).

> **Geçmiş sürümler formda görünmez** — `status=0` dosyalar bu sorguda elenir. Geçmiş
> yalnızca `old_versions` (aşağıda) ile görüntülenebilir.

### 5.2 `Document_files::tableList` — liste + `old_versions`

`Document_files.php:61-200`. Liste yalnızca `i.status = 1` dosyaları döndürür; her satırda:

- `relation_detail`: dosyanın bağlı olduğu conn'daki tüm entity değerleri (JSON agg) — DList
  tarafında `order_no` vb. çekmek için.
- `old_versions`: scalar subquery, **entity-tag eşleşmesi** — aynı slotun tüm sürümleri
  (çoğaltılmış entity satırları sayesinde çalışır):

```sql
'old_versions' => "(select json_agg(json_build_object(
                        'description',df2.description, 'qnid',df2.qnid, 'created_at',df2.created_at))
                    from sys_con_entities se2
                        inner join document_files as df2 on df2.id = se2.entity_value::int
                    where se2.entity_tag = se.entity_tag) as old_versions"
```

> Alternatif (daha sağlam): `replaced_id` zincirini yürüyen recursive CTE. Entity satırları
> silinmiş olsa bile çalışır. İkisi aynı sonucu verir.

### 5.3 `getDocumentFiles` (dosya durum listesi)

`DocumentServiceProvider.php:1873` — belgenin aktif dosyalarını, son işlemi yapan kullanıcı
ve dosya tipiyle birlikte döndürür (`df.status=1` filtresiyle). `setFileStatusAll` toplu
onay/redde bu listeyi kullanır.

---

## 6. Kaldırma — `removedData` (Yalnız Aktif Dosyaya Dokun)

`registerContent` içinde `DocumentServiceProvider.php:122-142`:

```php
$check = Sys_con_entities::where(['conn_id' => $row['id'], 'entity_tag' => $row['key']])
    ->orderByDesc('id')->first();

if (! empty($check) && $check->table_tag == 'document_files') {
    $fileStatus = Document_files::where('id', (int) $check->entity_value)->value('status');
    if ($fileStatus != 1) {
        $check = null;   // pasif sürüm satırı — geçmiş, dokunma
    }
}
if (! empty($check)) {
    $file->status = 0;   // dosyayı pasife al
    $check->delete();    // AKTİF entity satırını sil
}
```

- Kaldırılan dosyanın fiziksel kaydı `status=0` olur (geçmiş korunur), AKTİF entity satırı
  silinir. Slotun eski sürüm entity satırları kaldığı için `old_versions` geçmişi göstermeye
  devam eder.
- `removeContent` (belge silme) ve `cancelOrder` (sipariş iptali) klon/parçalı akışlarda
  dosya/quantity geri yükleme yapar (bkz. `memory/05 §5`, `restoreQuantitiesForClone`).

---

## 7. Geçici Dosya Yaşam Döngüsü + Temizlik

- Seçilen dosya `storage/app/public/temp/`'e yazılır; DB'de `relation='temp'`, `relation_id=0`.
- Form kaydedilirse `finalizeTempFile` rename ile `documents/`'e taşır, `relation='documents'`
  yapar (§3.1).
- Kaydedilmezse `Kernel.php:42-44` her gece 03:00 `cleanupTempFiles()` çalıştırır
  (`DocumentHelpers.php:647`): 24 saatten eski temp disk dosyalarını siler + 24 saatten eski
  `relation='temp'` DB kayıtlarını temizler.

---

## 8. Durum Makinesi Entegrasyonu

### 8.1 Dosya işlemleri (`transactions.op_id=1`)

| op_key | Anlam | Nerede yazılır |
|--------|-------|----------------|
| `doc_file_waiting` | Yeni dosya eklendi | `addFileToDb`, `finalizeTempFile` (ilk yükleme) |
| `doc_file_refreshed` | Dosya değiştirildi | `addFileToDb`/`finalizeTempFile` REPLACEMENT branch |
| `doc_file_accepted` / `doc_file_rejected` | Admin onayı/reddi | `documentFileStatus` (`setFileStatus`/`setFileStatusAll`, `per-07-02`) |

### 8.2 `documentFileStatus` (`DocumentServiceProvider.php:948`)

Admin `POST /v1/trans/set-file-status` ile dosya `qnid`'sine göre onay/red yazar
(`DocumentController.php:425`). Sonrasında:

1. `syncOrderStatusFromFiles($entity)` — sipariş durumunu dosyalarla senkron tutar.
2. `refreshAllUserPermissions()` — reddedilmiş dosya bilgisi tüm oturumlara yayılır
   (frontend `currentStatus.rejectedFiles`).
3. `sendClientFileStatus` maili.

### 8.3 `syncOrderStatusFromFiles` — sipariş ↔ dosya durumu (`DocumentServiceProvider.php:1026`)

```
entity → conn → documents → (parent zincirini yukarı yürü) → en yakın op-doc-order/transfer
→ aktif dosyaları topla: siparişin kendi transfer_kabul/transfer_cins slotları (slot başına
  EN YENİ aktif dosya) + tüm item dosyaları (df.status=1)
→ her dosyanın son işlemi:
    doc_file_rejected varsa        → sipariş doc_trans_order_files_rejected
    hepsi doc_file_accepted ise    → sipariş doc_trans_order_ready_for_shipment
    hiçbiri red değil + hepsi kabul de değilse
       ve mevcut durum files_rejected ise → doc_trans_order_transfer_sent (geri dönüş)
```

Tetiklenme noktaları:

- `documentFileStatus` sonrası (`DocumentServiceProvider.php:992`).
- `registerContent` içinde dosya değiştirildiğinde — **yalnızca `transfer_mode` payload'da
  YOKSA** (`DocumentServiceProvider.php:300-302`). Transfer gönderimi sırasında
  `processOrderTransfer` durumu hemen sonra kendisi set ettiği için sync atlanır; aksi halde
  "created only" guard'ı gönderimi bozar (2026-08-31 düzeltmesi).

> `applyOrderStatus` aynı durumu tekrar tekrar yazmaz (son işlem kontrolü) ve
> `documents.status`'a dokunmaz — yalnızca transaction ekler.

---

## 9. Geçmişte Yaşanan Hatalar (Nedenler + Dersler)

### Hata 1 — `$check` yanlış `table_tag` arıyordu

```php
// YANLIŞ — dosya entity'leri 'document_files' tag'ında saklanır
$check = Sys_con_entities::where([... 'table_tag' => 'sys_con_ops'])->first();
```
Eşleşme asla olmuyordu → her yükleme yeni satır oluşturuyordu (sürüm geçmişi için aslında
DOĞRUYDU — yan etki olarak çalıştı).

### Hata 2 — Değiştirme tespiti en eski satırı buluyordu

Sıralamasız `first()` en ESKİ entity satırını döndürüyordu → `existingFileId` hep ilk dosya.
**Çözüm:** `whereIn(entity_value, aktif dosya id'leri) + orderByDesc('id')` (§4).

### Hata 3 — `replaced_id` yönü ters

`$fileOld->replaced_id = $new` (ileri zincir) yanlıştı; doğrusu `$new->replaced_id = $fileOld`.
Hem `finalizeTempFile` (`DocumentHelpers.php:566`) hem `addFileToDb` (`:804`) düzeltildi.

### Hata 4 — `old_versions`'un entity satırlarına bağımlılığı

Tek-satır güncelleme denendiğinde geçmiş sürümlerin entity satırları kalmıyordu.
**Ders:** sürüm geçmişi için entity satırlarını ASLA güncelleme/tekilleştirme; her sürüme
yeni satır, aktifi `document_files.status` ile belirle.

### Hata 5 — `relation='-'` hayalet kayıtlar

`tempUploadFile` `relation='temp'` kurar, `finalizeTempFile` `'documents'` yapar.
`relation='-'` kaldıysa yükleme akışı hiç tamamlanmamıştır → orphan, temizle.

### Hata 6 — `getFormData` status `json_agg` sırasız

Transaction dizisi belirsiz sıralandığı için frontend `parsedStatus[last]` YANLIŞ güncel
durumu seçiyordu (`files_rejected` yerine `transfer_sent`) → `isFilesLocked` yanlış →
reddedilmiş dosya inputları kilitleniyordu. **Çözüm:** `json_agg(... ORDER BY t.id)`
(oldest→newest, son = güncel).

### Hata 7 — `transfer_sent` erken yazılması (pre-emption)

Kaydetme sırasında dosya değişikliği → `registerContent` içindeki yeni sync çağrısı,
`processOrderTransfer` çalışmadan siparişi `transfer_sent` yapıyordu → "created only"
guard'ı gönderimi sessizce reddediyordu. **Çözüm:** `transfer_mode` payload'da varsa sync
atla (§8.3).

---

## 10. Mevcut Zayıflıklar / Teknik Borç

> Kod okumasından tespit edildi (2026-08-31). Onay almadan değiştirmeyin.

1. **Servis katmanı kimlik doğrulaması yok (IDOR).** `/order-file/{doc}` route'u
   `auth:sanctum` grubunda ama `decryptFile` içindeki yetki kontrolü **yorum satırı**
   (`DocumentHelpers.php:92-130`). `qnid` bilen her giriş yapmış kullanıcı herhangi bir
   dosyayı indirebilir (tedarikçi ↔ admin ayrımı yok).
2. **Dosyalar web kökünde düz isimle duruyor.** `Storage::disk('public')`
   = `public/storage` symlink'i → `http://host/storage/documents/{time}{rand5}{slug}.ext`
   auth olmadan erişilebilir (ad bilinirse / dizin listeleme açıksa). "Şifreleme" yalnızca
   DB kolonunu korur, servis katmanını değil. Kaldırılmış/eskimiş dosyalar diskte kalır.
3. **Eski sürümler diskten silinmiyor.** Değiştirme yalnızca `status=0` yapar; fiziksel
   baytlar sonsuza kadar kalır → sınırsız `documents/` büyümesi. Saklama politikası yok.
4. **`copy entities` blokları ölü kod.** `finalizeTempFile:592` ve `addFileToDb:817`
   `conn_id = dosya.id` olan entity'leri kopyalar ama gerçek entity satırları
   `conn_id = sys_con_ops.id` altındadır → subquery asla eşleşmez. Zararsız ama yanıltıcı.
5. **`documentFileStatus:957` sırasız `first()`.** Bugün her dosya id tek gerçek entity
   satırına denk geldiği için sorun yok; çoğaltma olursa aynı `orderByDesc('id')` disiplini
   uygulanmalı.
6. **`cleanupTempFiles` model-hook dizin uyuşmazlığı.** Disk `temp/`'den silinir, DB satırı
   hard-delete edilince model `deleting` hook'u `documents/` dizinini denemeye çalışır —
   zararsız no-op ama özensiz.
7. **Yarış koşulu (race).** Aynı slot için iki eşzamanlı kayıt aynı `$existingFileId`'i
   okur → ikisi de aynı eski dosyayı pasife alır → çatallanan zincir. Düşük risk; istenirse
   transaction içinde `where status=1` re-check eklenebilir.
8. **`relation_id=0` + `relation='temp'` birikmesi** kaydetmeyen kullanıcıdan — cron
   temizler (§7), ancak disk'te 24 saate kadar birikim normaldir.

---

## 11. Doğrulama Sorguları

```sql
-- Slot başına entity satırları + dosya aktifliği (N yükleme → N satır, son dosya status=1)
SELECT sce.id, sce.entity_tag, sce.entity_value,
       df.status AS file_status, df.replaced_id
FROM sys_con_entities sce
JOIN document_files df ON df.id = sce.entity_value::int
WHERE sce.table_tag = 'document_files'
ORDER BY sce.id;

-- Zincir kontrolü (yeni → eski, replaced_id geriye dönük olmalı)
SELECT id, status, replaced_id, created_at
FROM document_files
WHERE relation = 'documents'
ORDER BY id;

-- Geçmiş versiyon sayısı (belirli slot)
SELECT count(*)
FROM sys_con_entities se2
JOIN document_files df2 ON df2.id = se2.entity_value::int
WHERE se2.entity_tag = 'transfer_cins_file**transfer_cins**new-1';
```

Beklenen: her slotta N yükleme için N entity satırı; yalnızca son dosya `status=1`;
`replaced_id` zinciri sürekli; `relation='temp'` → `'documents'` dönüşümü tamamlanmış.

---

## 12. Test Senaryoları / Checklist

- [ ] Yeni dosya yükle → `doc_file_waiting`, entity satırı +1, `status=1`.
- [ ] Aynı slotu 3 kez değiştir → 3 entity satırı, yalnızca sonuncu `status=1`,
      `old_versions` 3 kayıt, `replaced_id` zinciri `1→2→3` (geriye dönük).
- [ ] Değiştirmeden sonra form yalnızca GÜNCEL dosyayı gösteriyor.
- [ ] `documentFileStatus` onay/red → `last_status` doğru; reddedilirse sipariş
      `files_rejected`, tekrar yükleme sonrası `transfer_sent` (bkz. §8.3).
- [ ] Reddedilmiş dosyayı yeniden yükleyip kaydet → eski sürüm pasif, `doc_file_refreshed`.
- [ ] `removedData` ile dosya kaldır → aktif entity silinir, eski sürümler `old_versions`'ta
      kalır, dosya `status=0`.
- [ ] Temp dosyayı kaydetmeden bırak → 24s sonra cron temizler.
- [ ] `/order-file/{qnid}` dosyayı servis ediyor; `storage/documents/` altında dosya mevcut.
- [ ] Birim: `php -l` ile değiştirilen PHP dosyaları temiz.

---

## 13. İlgili Dosyalar & Noktalar

| Ne | Nerede |
|----|--------|
| Temp yükleme | `DocumentHelpers.php:468` (`tempUploadFile`) |
| Kalıcıya taşıma + replacement | `DocumentHelpers.php:529` (`finalizeTempFile`) |
| Direkt yükleme + replacement | `DocumentHelpers.php:757` (`addFileToDb`), `:706` (`uploadFile`) |
| Temizlik cron | `DocumentHelpers.php:647`, `Kernel.php:42-44` |
| Kayıt + değiştirme tespiti + removedData | `DocumentServiceProvider.php:26-335` (`registerContent`) |
| Okuma (aktif filtre) | `DocumentServiceProvider.php:343-385` (`getFormData`) |
| Dosya durum + sipariş senkronu | `DocumentServiceProvider.php:948` / `:1026` |
| Dosya listesi + old_versions | `Document_files.php:61-200` (`tableList`) |
| Temp upload + dosya status endpoint'leri | `DocumentController.php:425/478/492` |
| Servis | `DocumentHelpers.php:77` (`decryptFile`), `routes/web.php:58` |
| Frontend yükleme | `Form.vue:2277-2314` (fileKey, uploadTempFile), `:2476-2506` (gösterim) |
| Taşınabilir referans | `file-versioning-system.md` (repo kökü) |