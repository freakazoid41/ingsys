<?php
/**
 * Saha İşgücü catalog cross-check: workbook ↔ DB.
 *
 * Parses every product sheet of
 * "Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx",
 * applies the documented import rules and compares the result with the
 * product/category documents created by SahaIsgucuCatalogSeeder.
 *
 * Run: php tests/e2e/saha-isgucu-crosscheck.php [path-to-xlsx]
 */

$panel = dirname(__DIR__, 2);
require $panel.'/vendor/autoload.php';
$app = require $panel.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\DB;

$src = $argv[1] ?? '/Users/freakazoid41/Downloads/RE__Tedarik_Portalı_Geliştirme_Toplantısı/Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx';
if (! is_file($src)) {
    fwrite(STDERR, "Workbook not found: {$src}\n");
    exit(1);
}

function xval($sh, int $r, int $c): string
{
    $v = $sh->getCell(Coordinate::stringFromColumnIndex($c).$r)->getValue();
    return $v === null ? '' : trim(preg_replace('/\s+/u', ' ', (string) $v));
}

function xup(string $s): string
{
    return mb_convert_case(strtr(trim($s), ['i' => 'İ', 'ı' => 'I']), MB_CASE_UPPER, 'UTF-8');
}

function xunit(string $u): string
{
    return match (mb_strtoupper(trim($u), 'UTF-8')) {
        'ADT', 'ADET', 'ST' => 'ST',
        'M', 'METRE'        => 'M',
        'KG', 'KİLOGRAM'    => 'KG',
        'L', 'LİTRE', 'LITRE' => 'LT',
        default => trim($u),
    };
}

$sheetDef = [
    'Sayfa2'     => [5, 3, 4, null, 1, 1],
    'YATIRIM'    => [3, 3, 4, 5, 1, 2],
    'BAKIM'      => [3, 3, 4, 5, 1, 3],
    'İŞLETME'    => [4, 1, 3, 4, 2, 4],
    '2026 ihale' => [2, 6, 7, 8, 3, 5],
];

// Same alias rules as the importer: cosmetic cross-sheet variants collapse.
$alias = [
    'EK MUF BAŞLIK' => 'EK MUF BAŞLIK', 'EK MUFU VE BAŞLIKLAR' => 'EK MUF BAŞLIK',
    'İLETKEN BAĞLAYICI' => 'İLETKEN BAĞLAYICI', 'İLETKEN BAĞLAYICILARI' => 'İLETKEN BAĞLAYICI',
    'AG KABLO' => 'AG KABLO', 'AG KABLOLAR' => 'AG KABLO',
    'AG ŞALT MALZEMELERİ' => 'AG ŞALT MALZEMELERİ', 'AG ŞALT MALZEMELER' => 'AG ŞALT MALZEMELERİ',
    'AKIM GERİLİM TRAFOSU' => 'AKIM GERİLİM TRAFOSU', 'AKIM TRANSFORMATÖRLERİ' => 'AKIM GERİLİM TRAFOSU',
    'GALVANİZLİ AYDINLATMA DİREĞİ' => 'GALVANİZLİ AYDINLATMA DİREĞİ', 'GALVANİZLİ AYDINLATMA' => 'GALVANİZLİ AYDINLATMA DİREĞİ',
    'BETON DİREK, TRAVERS' => 'BETON DİREK, TRAVERS', 'BETON DİREK-TRAVERS' => 'BETON DİREK, TRAVERS',
    'YG SİGORTALAR' => 'YG SİGORTALAR', 'YG SİGORTA' => 'YG SİGORTALAR',
    'HAVAİ HAT EKİPMANLARI' => 'HAVAİ HAT EKİPMANLARI', 'HAVAİ HAT EKİPMANLAR' => 'HAVAİ HAT EKİPMANLARI',
    'DEMİR DİREK, TRAVERS' => 'DEMİR DİREK, TRAVERS', 'DEMİR DİREK-TRAVERS' => 'DEMİR DİREK, TRAVERS',
    'İZOLATÖRLER' => 'İZOLATÖR', 'İZOLATÖR' => 'İZOLATÖR',
    'İLETKENLER' => 'İLETKEN', 'İLETKEN' => 'İLETKEN',
    'KURU TİP TRAFO' => 'KURU TİP TRANSFORMATÖR', 'KURU TİP TRANSFORMATÖR' => 'KURU TİP TRANSFORMATÖR',
    'PARAFUDRLAR' => 'PARAFUDR', 'PARAFUDR' => 'PARAFUDR',
];
// Existing real catalog categories keep their own display titles.
$existing = [
    'GAZ YALITIMLI HÜCRE'    => 'GY-HUCRE',
    'HAVA YALITIMLI HÜCRE'   => 'HY-HUCRE',
    'HERMETİK TRANSFORMATÖR' => 'TRF-33KV',
    'PANO'                   => 'AG-PANO',
    'SDK'                    => 'SDK',
];
$slug = [
    'EK MUF BAŞLIK' => 'EK-MUF-BASLIK', 'İLETKEN BAĞLAYICI' => 'ILETKEN-BAGLAYICI',
    'AG KABLO' => 'AG-KABLO', 'AG ŞALT MALZEMELERİ' => 'AG-SALT-MALZEMELERI',
    'AYDINLATMA MALZEMELERİ' => 'AYDINLATMA-MALZEMELERI', 'AKIM GERİLİM TRAFOSU' => 'AKIM-GERILIM-TRAFOSU',
    'GALVANİZLİ AYDINLATMA DİREĞİ' => 'GALVANIZLI-AYDINLATMA-DIREGI', 'BETON KÖŞK MERKEZ' => 'BETON-KOSK-MERKEZ',
    'BETON DİREK, TRAVERS' => 'BETON-DIREK-TRAVERS', 'YG SİGORTALAR' => 'YG-SIGORTALAR',
    'HAVAİ HAT EKİPMANLARI' => 'HAVAI-HAT-EKIPMANLARI', 'DEMİR DİREK, TRAVERS' => 'DEMIR-DIREK-TRAVERS',
    'AER KABLO' => 'AER-KABLO', 'İZOLATÖR' => 'IZOLATOR', 'HIRDAVAT VE TEKNİK MALZEME' => 'HIRDAVAT-VE-TEKNIK-MALZEME',
    'KESİCİLER' => 'KESICILER', 'İLETKEN' => 'ILETKEN', 'PBK' => 'PBK', 'YG KABLO' => 'YG-KABLO',
    'KLEMENS VE PABUÇLAR' => 'KLEMENS-VE-PABUCLAR', 'YG AYIRICI' => 'YG-AYIRICI', 'AKÜ REDRESÖR' => 'AKU-REDRESOR',
    'MUHTELİF MALZEME' => 'MUHTELIF-MALZEME', 'PANO-SDK' => 'PANO-SDK', 'AĞAÇ DİREK' => 'AGAC-DIREK',
    'KURU TİP TRANSFORMATÖR' => 'KURU-TIP-TRANSFORMATOR', 'ELEKTRİK BARALARI' => 'ELEKTRIK-BARALARI',
    'TOPRAKLAMA MALZEMESİ' => 'TOPRAKLAMA-MALZEMESI', 'PARAFUDR' => 'PARAFUDR', 'GÜÇ TRAFOSU' => 'GUC-TRAFOSU',
    'AKÜ, ŞARJ CİHAZLARI' => 'AKU-SARJ-CIHAZLARI',
];

$ss = IOFactory::load($src);
$rows = [];
$sheetCounts = [];
foreach ($sheetDef as $sheetName => [$start, $codeCol, $nameCol, $unitCol, $catCol, $prio]) {
    $sh = $ss->getSheetByName($sheetName);
    if (! $sh) {
        fwrite(STDERR, "Sheet missing: {$sheetName}\n");
        exit(1);
    }
    $codes = [];
    for ($r = $start; $r <= $sh->getHighestDataRow(); $r++) {
        $code = xval($sh, $r, $codeCol);
        $name = xval($sh, $r, $nameCol);
        if ($code === '' || $code === 'Kod talep edilecek' || $name === '') continue;
        $cat = $catCol ? xval($sh, $r, $catCol) : '';
        if ($sheetName === '2026 ihale' && $cat === '') $cat = xval($sh, $r, 13);
        $unit = $unitCol ? xval($sh, $r, $unitCol) : '';
        $rows[$code][] = compact('prio', 'name', 'cat', 'unit');
        $codes[$code] = true;
    }
    $sheetCounts[$sheetName] = count($codes);
}

$expected = [];
foreach ($rows as $code => $list) {
    usort($list, fn ($a, $b) => $a['prio'] <=> $b['prio']);
    $name = $list[0]['name'];
    $cat = '';
    $unit = '';
    foreach ($list as $x) {
        if ($cat === '' && $x['cat'] !== '') $cat = $x['cat'];
        if ($unit === '' && $x['unit'] !== '') $unit = $x['unit'];
    }
    $key = $alias[xup($cat)] ?? xup($cat);
    $catCode = $existing[$key] ?? ($slug[$key] ?? null);
    if (! $catCode) {
        fwrite(STDERR, "No category mapping for {$code}: {$key}\n");
        exit(1);
    }
    $unit = xunit($unit);
    if ($unit === '') $unit = 'ST'; // LED fixtures with a blank unit cell
    $expected[$code] = ['name' => $name, 'unit' => $unit, 'cat' => $catCode];
}

// DB catalog snapshot
$catForm = DB::table('sys_options')->where('op_key', 'op-doc-product-category-form')->value('id');
$prodForm = DB::table('sys_options')->where('op_key', 'op-doc-product-form')->value('id');

$cats = [];
$catRows = DB::table('documents as d')
    ->join('sys_con_ops as so', 'so.main_id', '=', 'd.id')
    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
    ->where('d.type_id', DB::table('sys_options')->where('op_key', 'op-doc-product-category')->value('id'))
    ->whereIn('se.entity_tag', ['code', 'title', 'qnid'])
    ->get(['d.qnid as doc_qnid', 'se.entity_tag', 'se.entity_value', 'so.id as conn_id']);
foreach ($catRows as $r) {
    $cats[$r->doc_qnid]['doc_qnid'] = $r->doc_qnid;
    $cats[$r->doc_qnid]['conn'] = $r->conn_id;
    $cats[$r->doc_qnid][$r->entity_tag] = $r->entity_value;
}
$catByCode = [];
foreach ($cats as $cat) {
    if (isset($cat['code'])) $catByCode[$cat['code']] = $cat;
}

$db = [];
$prodRows = DB::table('documents as d')
    ->join('sys_con_ops as so', 'so.main_id', '=', 'd.id')
    ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
    ->where('d.type_id', DB::table('sys_options')->where('op_key', 'op-doc-product')->value('id'))
    ->whereIn('se.entity_tag', ['prod_code', 'short_name', 'unit', 'category_title', 'category_qnid'])
    ->get(['d.qnid as doc_qnid', 'se.entity_tag', 'se.entity_value']);
foreach ($prodRows as $r) {
    $db[$r->doc_qnid][$r->entity_tag] = $r->entity_value;
}
$dbByCode = [];
foreach ($db as $p) {
    if (isset($p['prod_code'])) $dbByCode[$p['prod_code']] = $p;
}

echo "SHEET DISTINCT CODES\n";
foreach ($sheetCounts as $s => $n) echo "  {$s}: {$n}\n";
echo '  union: '.count($expected)."\n\n";

$fail = 0;
foreach ($expected as $code => $exp) {
    if (! isset($dbByCode[$code])) {
        echo "  ✗ MISSING product {$code} — {$exp['name']}\n";
        $fail++;
        continue;
    }
    $got = $dbByCode[$code];
    if (($got['short_name'] ?? '') !== $exp['name']) {
        echo "  ✗ name [{$code}] DB='".($got['short_name'] ?? '')."' EXP='{$exp['name']}'\n";
        $fail++;
    }
    if (($got['unit'] ?? '') !== $exp['unit']) {
        echo "  ✗ unit [{$code}] DB='".($got['unit'] ?? '')."' EXP='{$exp['unit']}'\n";
        $fail++;
    }
    $cat = $catByCode[$exp['cat']] ?? null;
    if (! $cat) {
        echo "  ✗ category {$exp['cat']} missing for product {$code}\n";
        $fail++;
        continue;
    }
    if (($got['category_qnid'] ?? '') !== $cat['doc_qnid']) {
        echo "  ✗ category link [{$code}] DB='".($got['category_qnid'] ?? '')."' EXP='{$cat['doc_qnid']}' ({$exp['cat']})\n";
        $fail++;
    }
    if (($got['category_title'] ?? '') !== ($cat['title'] ?? '')) {
        echo "  ✗ category title [{$code}] DB='".($got['category_title'] ?? '')."' EXP='".($cat['title'] ?? '')."'\n";
        $fail++;
    }
}

// Every imported category except the five real ones must have zero requirement rows.
$real = ['GY-HUCRE', 'HY-HUCRE', 'TRF-33KV', 'AG-PANO', 'SDK'];
foreach ($catByCode as $code => $cat) {
    if (in_array($code, $real, true)) continue;
    $n = DB::table('sys_con_entities')
        ->where('conn_id', $cat['conn'])
        ->where('entity_tag', 'like', '%**category_files**%')
        ->count();
    if ($n !== 0) {
        echo "  ✗ new category {$code} has {$n} requirement entities (must be 0)\n";
        $fail++;
    }
}

echo "\nDB: ".count($dbByCode)." products · ".count($catByCode)." categories\n";
echo $fail === 0
    ? 'ALL GREEN — workbook, importer data and DB match ('.count($expected)." products).\n"
    : "MISMATCHES: {$fail} — fix required.\n";
exit($fail === 0 ? 0 : 1);
