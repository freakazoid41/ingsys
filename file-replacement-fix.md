# Dosya Değiştirme (File Replacement) Düzeltmesi

> **Tarih:** 2026-08-31
> **Dosya:** `panel/app/Providers/DocumentServiceProvider.php` + `panel/app/Helpers/DocumentHelpers.php`
> **Etki:** Sipariş/Transfer dosyalarının (transfer_kabul, transfer_cins, item_test, item_images) yeniden yüklenmesinde her seferinde **ilk dosyanın** değil, **son yüklenen dosyanın** pasifleştirilmesi + `replaced_id` zincirinin doğru kurulması (yeni dosya → bir önceki sürümü gösterir).

## 1. Sorun

Teoride her yeni dosya bir öncekini değiştirir (eskisi `status=0`, `replaced_id` bağlanır). Gerçekte:

- Her yüklemede aynı slot için **kopya `sys_con_entities` satırı** oluşuyordu.
- `$oldFileEntity` sorgusu (`->first()`, sıralamasız) **en eski** satırı döndürüyordu.
- Sonuç: `$existingFileId` her zaman **ilk dosyanın id'si** oluyor → her yenileme ilk dosyayı pasifleştiriyor, son yüklenen dosya aktif kalıyordu.

## 2. Kök Neden

`registerContent()` içinde mevcut dosya entity'sini bulmaya çalışan `$check` sorgusu yanlış `table_tag` kullanıyordu:

```php
// YANLIŞ — dosya entity'leri 'document_files' tag'ında saklanır
$check = Sys_con_entities::where([
    'conn_id'   => $conn->id,
    'entity_tag'=> $fileName,
    'table_tag' => 'sys_con_ops',     // <-- hiç eşleşmez
])->first();
```

Dosya entity'leri `table_tag='document_files'` ile saklandığı için `$check` **asla eşleşmiyor** → `$entity = new Sys_con_entities` ile her seferinde **yeni satır** oluşuyor. Zamanla aynı slot için çoklu satır birikiyor ve `$oldFileEntity->first()` (sıralamasız = en eski) ilk dosyayı işaret ediyor.

## 3. Değişiklikler

`panel/app/Providers/DocumentServiceProvider.php` + `panel/app/Helpers/DocumentHelpers.php` içinde **5 değişiklik**:

### 3.1 `$check` sorgusu — doğru table_tag (Kritik Düzeltme)

```php
// DOĞRU — mevcut dosya entity'sini bulur, aynı satır güncellenir
$check = Sys_con_entities::where([
    'conn_id'   => $conn->id,
    'entity_tag'=> $fileName,
    'table_tag' => 'document_files',
])->orderByDesc('id')->first();
```

Artık mevcut entity bulunur ve `entity_value` yeni dosya id'si ile **yerinde güncellenir** (tek satır, her zaman son dosyayı işaret eder).

### 3.2 `$oldFileEntity` — en yeni satırı çöz (legacy kopyalara karşı savunma)

```php
// ÖNCE: first()  → en eski satır (ilk dosya)
$oldFileEntity = Sys_con_entities::where([
    'conn_id'   => $conn->id,
    'entity_tag'=> $fileName,
    'table_tag' => 'document_files',
])->first();

// SONRA: orderByDesc('id') → en yeni satır (son dosya)
$oldFileEntity = Sys_con_entities::where([
    'conn_id'   => $conn->id,
    'entity_tag'=> $fileName,
    'table_tag' => 'document_files',
])->orderByDesc('id')->first();
```

Böylece geçmişte birikmiş kopya satırlar olsa bile `$existingFileId` **son** dosyayı bulur.


### 3.4 `replaced_id` zincir yönü — yeni dosya "bir önceki sürümü" gösterir

`replaced_id` artık **yeni dosya üzerinde** değiştirdiği **bir önceki sürümü** gösterir (geriye dönük zincir). Eski kod dosya→yeni (ileri) yönde bağlıyordu; bozuk veride hep ilk dosyaya yazılıyordu.

**`DocumentHelpers.php` — `finalizeTempFile()` (geçici yükleme yolu):**

```php
// ÖNCE (ileri zincir — yanlış yön, hep ilk dosyaya yazılıyordu):
$fileOld->status       = 0;
$fileOld->replaced_id  = $docFile->id;   // eski → yeni
$fileOld->save();

// SONRA (geriye dönük zincir — doğru):
$fileOld->status = 0;
$fileOld->save();

$docFile->replaced_id = $fileOld->id;    // yeni → bir önceki sürüm
$docFile->save();
```

**`DocumentHelpers.php` — `addFileToDb()` (klasik yükleme yolu):**

```php
// ÖNCE:
$fileOld->status       = 0;
$fileOld->replaced_id  = $file->id;      // eski → yeni
$fileOld->save();

// SONRA:
$fileOld->status = 0;
$fileOld->save();

$file->replaced_id = $fileOld->id;       // yeni → bir önceki sürüm
$file->save();
```


> **Not:** `replaced_id` şu an uygulamada hiçbir yerde okunmuyor (yalnız yazılıyor) — yön değişikliği güvenlidir.

