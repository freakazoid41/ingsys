# EAV Dosya Sürümleme Sistemi (File Replacement / Version Tracking) — Taşınabilir Referans

> **Tarih:** 2026-08-31
> **Kaynak:** INGSYS Order Management (`tedarikPanel` branch) — gerçek projede doğrulandı
> **Amaç:** Bu doküman, EAV tabanlı her projeye (müşteri, teklif, sipariş, form vs.) dosya değiştirme + sürüm geçmişi sistemini doğru şekilde taşımak için hazırlandı.
> **Dil:** Türkçe (kod yorumları dahil)

---

## 1. Mimari Özet — Nasıl Çalışır

EAV yapısında dosyalar iki tabloda yaşar:

```
document_files     — fiziksel dosya kaydı (id, qnid, description=şifreli ad, relation, relation_id, status, replaced_id)
sys_con_entities   — form bağlantısı (conn_id → sys_con_ops, entity_tag = {alan}**{grup}**{satır}, entity_value = document_files.id, table_tag='document_files')
```

### 1.1 Sürüm Geçmişi = Entity Satırlarının Çoğaltılması (Kritik Kural)

**Her dosya yüklemesi `sys_con_entities`'e YENİ bir satır ekler.** Aynı slot için (aynı `entity_tag`) zamanla birden çok satır birikir:

```
entity_tag = 'transfer_cins_file**transfer_cins**new-1'

satır 1: entity_value = 110  → document_files 110 (status=0, eski)
satır 2: entity_value = 111  → document_files 111 (status=0, eski)
satır 3: entity_value = 112  → document_files 112 (status=1, GÜNCEL)
```

- **Aktiflik = `document_files.status`** (1 = güncel sürüm). Entity satırında status yoktur, eklenmez.
- `old_versions` (geçmiş versiyon listesi) `entity_tag` eşleşmesiyle tüm satırları bulur.
- Form okuma (`getFormData`) yalnızca **aktif dosyayı** gösterir.

### 1.2 Değiştirme Zinciri = `replaced_id` (Geriye Dönük)

`replaced_id` **yeni dosyada** durur ve **bir önceki sürümü** gösterir:

```
110 (ilk)  → 111 (replaced_id=110)  →  112 (replaced_id=111)
```

Yani: `yeniDosya.replaced_id = eskiDosya.id`. (Eski kod `eski.replaced_id = yeni` yapıyordu — yanlış yön.)

---

## 2. Veritabanı — Schema

```sql
document_files (
    id           bigserial PK,
    qnid         uuid (external id),
    description  text  (şifrelenmiş dosya adı),
    relation     varchar(20) default '-',   -- 'documents' | 'temp' | '-'
    relation_id  int    default 0,          -- documents.id (temp ise 0)
    type_id      int,                        -- sys_options (form-file vs)
    status       smallint default 1,         -- 1 aktif, 0 pasif (sürüm geçmişi)
    replaced_id  int    default 0,           -- YENİ dosyada: bir önceki sürümün id'si
    created_at / updated_at
)

sys_con_entities (
    id           bigserial PK,
    conn_id      int  (→ sys_con_ops.id),
    table_tag    varchar(100),  -- 'document_files' (dosyalar) | 'sys_con_ops' (skaler)
    entity_tag   varchar(100),  -- '{alan}**{grup}**{satır}'
    entity_value text,          -- dosya ise document_files.id (text)
    qnid / created_at / updated_at
)
```

> **NOT:** `sys_con_entities`'e status sütunu EKLEME. Aktiflik her zaman `document_files.status`'tan türetilir.

---

## 3. Doğru Kod Desenleri

### 3.1 Yükleme — Her Yüklemede Yeni Entity Satırı

`registerContent()` (veya projenin EAV kaydedicisi) içinde, dosya işlendikten sonra:

```php
// Her yükleme YENİ bir entity satırı oluşturur → sürüm geçmişi entity satırlarında yaşar.
// Aktiflik document_files.status'tan türetilir (status=1 = güncel sürüm).
$entity = new Sys_con_entities;
$entity->table_tag   = 'document_files';
$entity->conn_id     = $conn->id;           // form bağlantısı (sys_con_ops.id)
$entity->entity_tag  = $fileName;           // {alan}**{grup}**{satır}
$entity->entity_value = (string) $fileId;   // document_files.id
$entity->save();
```

### 3.2 Değiştirme Tespiti — AKTİF Entity'yi Bul

Değiştirmede eski dosyayı pasife almak için **aktif dosyayı gösteren entity satırı** bulunur:

```php
// AKTİF entity = entity_value'su hâlâ status=1 olan bir document_files'ı gösteren satır.
// (Sıralamasız first() kullanmak İLK dosyayı döndürür → her seferinde ilk dosya pasife alınır — BU HATA!)
$oldFileEntity = Sys_con_entities::where([
        'conn_id'    => $conn->id,
        'entity_tag' => $fileName,
        'table_tag'  => 'document_files',
    ])
    ->whereIn('entity_value', function ($q) {
        $q->selectRaw('id::text')->from('document_files')->where('status', 1);
    })
    ->orderByDesc('id')->first();   // en yeni aktif satır

$existingFileId = 0;
if ($oldFileEntity && is_numeric($oldFileEntity->entity_value)) {
    $existingFileId = (int) $oldFileEntity->entity_value;
}
```

**Kritik:** Bu sorguda `->whereIn(...)` + `orderByDesc('id')` ŞARTTIR. Aksi halde:
- Sıralamasız `first()` → en ESKİ satır → `existingFileId` her zaman İLK dosya → her değiştirmede ilk dosya pasife alınır.

### 3.3 Eski Dosyayı Pasife Alma + Zincirleme (`finalizeTempFile` / `addFileToDb`)

```php
// Geçici yükleme yolu (finalizeTempFile) — REPLACEMENT branch:
if ($existingFileId > 0) {
    $fileOld = Document_files::find($existingFileId);
    if ($fileOld) {
        $fileOld->status = 0;              // eski sürüm pasif
        $fileOld->save();

        // Yeni dosya kalıcıya taşınır; replaced_id bir önceki sürümü gösterir
        $docFile->relation_id   = $documentId;
        $docFile->relation      = 'documents';
        $docFile->replaced_id   = $fileOld->id;   // GERİYE DÖNÜK zincir
        $docFile->save();
        // ... doc_file_refreshed transaction + UserLog ...
    }
}

// Klasik yükleme yolu (addFileToDb) — REPLACEMENT branch:
if ($rowId != 0) {
    $fileOld = Document_files::find($rowId);
    $fileOld->status = 0;
    $fileOld->save();

    $file->replaced_id = $fileOld->id;     // GERİYE DÖNÜK zincir
    $file->save();
    // ... doc_file_refreshed transaction ...
}
```

### 3.4 Form Okuma — Yalnız Aktif Dosyayı Göster (`getFormData`)

```sql
-- entity join'inde dosya entity'leri yalnızca dosyası hâlâ AKTİF ise gelsin
left join sys_con_entities sce
       on sce.conn_id = dco.id
      and (sce.table_tag <> 'document_files'
           or exists (
               select 1 from document_files dfe
               where dfe.id = sce.entity_value::int
                 and dfe.status = 1
           ))
```

Bu filtre olmazsa formda aynı slot için geçmiş sürümler de görünür/çakışır.

### 3.5 Geçmiş Versiyon Listesi (`old_versions`)

`tableList` içinde scalar subquery — **entity-tag eşleşmesi** (çoğaltılmış satırlar sayesinde tüm sürümler gelir):

```sql
'old_versions' => "(select json_agg(json_build_object(
                        'description', df2.description,
                        'qnid',        df2.qnid,
                        'created_at',  df2.created_at
                    ))
                    from sys_con_entities se2
                        inner join document_files as df2 on df2.id = se2.entity_value::int
                    where se2.entity_tag = se.entity_tag) as old_versions"
```

> Alternatif (daha sağlam): `replaced_id` zincirini yürüyen recursive CTE. Entity satırları silinmiş olsa bile çalışır. İkisi aynı sonucu verir.

### 3.6 Dosya Kaldırma (`removedData`)

Entity'si silinecek dosya kaydı yalnızca **aktif** ise işlem yap:

```php
$check = Sys_con_entities::where([
        'conn_id'    => $row['id'],
        'entity_tag' => $row['key'],
    ])->orderByDesc('id')->first();

if (! empty($check) && $check->table_tag == 'document_files') {
    $fileStatus = Document_files::where('id', (int) $check->entity_value)->value('status');
    if ($fileStatus != 1) {
        $check = null;   // pasif sürüm satırı — geçmiş, dokunma
    }
}
// $check boş değilse: dosya status=0 + entity sil
```

---

## 4. Geçmişte Yaşanan Hatalar (Nedenler + Dersler)

### Hata 1: `$check` yanlış `table_tag` arıyordu

```php
// YANLIŞ — dosya entity'leri 'document_files' tag'ında saklanır
$check = Sys_con_entities::where([... 'table_tag' => 'sys_con_ops'])->first();
```

Eşleşme asla olmuyordu → her yükleme yeni satır oluşturuyordu (bu aslında sürüm geçmişi için DOĞRUYDU, yan etkiydi).

### Hata 2: Değiştirme tespiti en eski satırı buluyordu

```php
// YANLIŞ — sıralamasız first() en ESKİ entity satırını döndürür
$oldFileEntity = Sys_con_entities::where([...])->first();
```

Çoğaltılmış satırlar birikince `existingFileId` her zaman **ilk dosya** oldu → her yenileme ilk dosyayı pasife aldı, son yüklenen aktif kaldı.

**Çözüm:** `whereIn(entity_value, aktif dosya id'leri)` + `orderByDesc('id')`.

### Hata 3: `replaced_id` yönü ters

```php
// YANLIŞ (ileri zincir)
$fileOld->replaced_id = $docFile->id;
// DOĞRU (geriye dönük — "bir önceki sürüm")
$docFile->replaced_id = $fileOld->id;
```

### Hata 4: `old_versions`'un entity satırlarına bağımlılığı

Tek-satır güncelleme yapıldığında (Hata 2'yi düzeltmek için "entity'yi yerinde güncelle" denenmişti) `old_versions` bozuldu — çünkü geçmiş sürümlerin entity satırları kalmıyordu.

**Ders:** Sürüm geçmişi için entity satırlarını **asla** güncelleme/tekilleştirme; her sürüme yeni satır, aktifi `document_files.status` ile belirle.

### Hata 5: `relation='-'` hayalet kayıtlar

`tempUploadFile` `relation='temp'` kurar; `finalizeTempFile` `'documents'` yapar. `relation='-'` kaldıysa yükleme akışı hiç tamamlanmamış demektir (yoksa başka bir kod yolu). `relation_id=0` + `relation='-'` = orphan → temizle.

---

## 5. Başka Projeye Taşıma Checklist'i

- [ ] `document_files` şeması: `status`, `replaced_id`, `relation`, `relation_id` mevcut
- [ ] Yükleme kodu her seferinde **yeni** `sys_con_entities` satırı oluşturuyor (`table_tag='document_files'`)
- [ ] Değiştirme tespiti: `whereIn(entity_value, aktif dosyalar)` + `orderByDesc('id')`
- [ ] `finalizeTempFile` / `addFileToDb`: eski `status=0`, yeni `replaced_id = eski id`
- [ ] `getFormData`: join'de `EXISTS(document_files status=1)` filtresi
- [ ] `old_versions`: entity-tag eşleşmesiyle scalar subquery (veya replaced_id CTE)
- [ ] `removedData`: yalnızca aktif dosya entity'sine dokun
- [ ] `php -l` + gerçek değiştirme testi (3 kez yükle → 3 entity satırı, 1 aktif, old_versions=3)
- [ ] `relation='temp'` → `'documents'` dönüşümü doğru; `'-'` orphan bırakmıyor

---

## 6. Doğrulama Sorguları

```sql
-- Slot başına entity satırları + dosya aktifliği
SELECT sce.id, sce.entity_tag, sce.entity_value,
       df.status AS file_status, df.replaced_id
FROM sys_con_entities sce
JOIN document_files df ON df.id = sce.entity_value::int
WHERE sce.table_tag = 'document_files'
ORDER BY sce.id;

-- Zincir kontrolü (yeni → eski)
SELECT id, status, replaced_id, created_at
FROM document_files
WHERE relation = 'documents'
ORDER BY id;

-- Geçmiş versiyon sayısı
SELECT count(*)
FROM sys_con_entities se2
JOIN document_files df2 ON df2.id = se2.entity_value::int
WHERE se2.entity_tag = 'alan**grup**satir';
```

Beklenen: her slotta N yükleme için N entity satırı; yalnızca son dosya `status=1`; `replaced_id` zinciri sürekli.