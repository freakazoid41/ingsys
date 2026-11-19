<?php
/**
 * Regenerates database/seeders/data/saha_isgucu_products.php from
 * "Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx".
 *
 * Run: php tests/e2e/saha-isgucu-generate.php [path-to-xlsx]
 */

$panel = dirname(__DIR__, 2);
require $panel.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$src = $argv[1] ?? '/Users/freakazoid41/Downloads/RE__Tedarik_Portalı_Geliştirme_Toplantısı/Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx';
if (! is_file($src)) {
    fwrite(STDERR, "Workbook not found: {$src}\n");
    exit(1);
}
$ss=IOFactory::load($src);
function val($sh,$r,$c){$v=$sh->getCell(Coordinate::stringFromColumnIndex($c).$r)->getValue();return $v===null?'':trim(preg_replace('/\s+/u',' ',(string)$v));}
function up($s){return mb_convert_case(strtr(trim($s),['i'=>'İ','ı'=>'I']),MB_CASE_UPPER,'UTF-8');}

// sheet => [start, codeCol, nameCol, unitCol, catCol, priority]
$sheets=[
 'Sayfa2'     =>[5,3,4,null,1,1],
 'YATIRIM'    =>[3,3,4,5,1,2],
 'BAKIM'      =>[3,3,4,5,1,3],
 'İŞLETME'    =>[4,1,3,4,2,4],
 '2026 ihale' =>[2,6,7,8,3,5],
];
$rows=[];
foreach($sheets as $sn=>$m){
  $sh=$ss->getSheetByName($sn);
  for($r=$m[0];$r<=$sh->getHighestDataRow();$r++){
    $code=val($sh,$r,$m[1]); $name=val($sh,$r,$m[2]);
    if($code===''||$code==='Kod talep edilecek'||$name==='') continue;
    $cat=$m[4]?val($sh,$r,$m[4]):'';
    if($sn==='2026 ihale' && $cat==='') $cat=val($sh,$r,13);
    $unit=$m[3]?val($sh,$r,$m[3]):'';
    $rows[$code][]=['sheet'=>$sn,'prio'=>$m[5],'cat'=>$cat,'unit'=>$unit,'name'=>$name];
  }
}

// Canonical category names: merge cross-sheet aliases (case / plural / dash variants).
$ALIAS = [
  'EK MUF BAŞLIK' => 'EK MUF BAŞLIK', 'EK MUFU VE BAŞLIKLAR' => 'EK MUF BAŞLIK',
  'İLETKEN BAĞLAYICI' => 'İLETKEN BAĞLAYICI', 'İLETKEN BAĞLAYICILARI' => 'İLETKEN BAĞLAYICI',
  'AG KABLO' => 'AG KABLO', 'AG KABLOLAR' => 'AG KABLO',
  'AG ŞALT MALZEMELERİ' => 'AG ŞALT MALZEMELERİ', 'AG ŞALT MALZEMELER' => 'AG ŞALT MALZEMELERİ',
  'AYDINLATMA MALZEMELERİ' => 'AYDINLATMA MALZEMELERİ',
  'AKIM GERİLİM TRAFOSU' => 'AKIM GERİLİM TRAFOSU', 'AKIM TRANSFORMATÖRLERİ' => 'AKIM GERİLİM TRAFOSU',
  'GALVANİZLİ AYDINLATMA DİREĞİ' => 'GALVANİZLİ AYDINLATMA DİREĞİ', 'GALVANİZLİ AYDINLATMA' => 'GALVANİZLİ AYDINLATMA DİREĞİ',
  'BETON KÖŞK MERKEZ' => 'BETON KÖŞK MERKEZ',
  'BETON DİREK, TRAVERS' => 'BETON DİREK, TRAVERS', 'BETON DİREK-TRAVERS' => 'BETON DİREK, TRAVERS',
  'HERMETİK TRANSFORMATÖR' => 'HERMETİK TRANSFORMATÖR',
  'YG SİGORTALAR' => 'YG SİGORTALAR', 'YG SİGORTA' => 'YG SİGORTALAR',
  'HAVAİ HAT EKİPMANLARI' => 'HAVAİ HAT EKİPMANLARI', 'HAVAİ HAT EKİPMANLAR' => 'HAVAİ HAT EKİPMANLARI',
  'DEMİR DİREK, TRAVERS' => 'DEMİR DİREK, TRAVERS', 'DEMİR DİREK-TRAVERS' => 'DEMİR DİREK, TRAVERS',
  'HAVA YALITIMLI HÜCRE' => 'HAVA YALITIMLI HÜCRE',
  'AER KABLO' => 'AER KABLO',
  'İZOLATÖRLER' => 'İZOLATÖR', 'İZOLATÖR' => 'İZOLATÖR',
  'HIRDAVAT VE TEKNİK MALZEME' => 'HIRDAVAT VE TEKNİK MALZEME',
  'PANO' => 'PANO',
  'KESİCİLER' => 'KESİCİLER',
  'İLETKENLER' => 'İLETKEN', 'İLETKEN' => 'İLETKEN',
  'PBK' => 'PBK',
  'YG KABLO' => 'YG KABLO',
  'GAZ YALITIMLI HÜCRE' => 'GAZ YALITIMLI HÜCRE',
  'KLEMENS VE PABUÇLAR' => 'KLEMENS VE PABUÇLAR',
  'YG AYIRICI' => 'YG AYIRICI',
  'AKÜ REDRESÖR' => 'AKÜ REDRESÖR',
  'MUHTELİF MALZEME' => 'MUHTELİF MALZEME',
  'PANO-SDK' => 'PANO-SDK',
  'AĞAÇ DİREK' => 'AĞAÇ DİREK',
  'KURU TİP TRAFO' => 'KURU TİP TRANSFORMATÖR', 'KURU TİP TRANSFORMATÖR' => 'KURU TİP TRANSFORMATÖR',
  'ELEKTRİK BARALARI' => 'ELEKTRİK BARALARI',
  'SDK' => 'SDK',
  'TOPRAKLAMA MALZEMESİ' => 'TOPRAKLAMA MALZEMESİ',
  'PARAFUDRLAR' => 'PARAFUDR', 'PARAFUDR' => 'PARAFUDR',
  'GÜÇ TRAFOSU' => 'GÜÇ TRAFOSU',
  'AKÜ, ŞARJ CİHAZLARI' => 'AKÜ, ŞARJ CİHAZLARI',
];

// Existing real catalog categories — products with these names attach to them.
$EXISTING = [
  'GAZ YALITIMLI HÜCRE'    => ['GY-HUCRE',  'Metal Mahfazalı Gaz Yalıtımlı YG Hücre'],
  'HAVA YALITIMLI HÜCRE'   => ['HY-HUCRE',  'Metal Mahfazalı Hava Yalıtımlı YG Hücre'],
  'HERMETİK TRANSFORMATÖR' => ['TRF-33KV',  '33 kV Hermetik Dağıtım Transformatörü'],
  'PANO'                   => ['AG-PANO',   'AG Dağıtım Panosu'],
  'SDK'                    => ['SDK',       'Saha Dağıtım Kutusu'],
];

// new category code slugs
$SLUG = [
  'EK MUF BAŞLIK' => 'EK-MUF-BASLIK',
  'İLETKEN BAĞLAYICI' => 'ILETKEN-BAGLAYICI',
  'AG KABLO' => 'AG-KABLO',
  'AG ŞALT MALZEMELERİ' => 'AG-SALT-MALZEMELERI',
  'AYDINLATMA MALZEMELERİ' => 'AYDINLATMA-MALZEMELERI',
  'AKIM GERİLİM TRAFOSU' => 'AKIM-GERILIM-TRAFOSU',
  'GALVANİZLİ AYDINLATMA DİREĞİ' => 'GALVANIZLI-AYDINLATMA-DIREGI',
  'BETON KÖŞK MERKEZ' => 'BETON-KOSK-MERKEZ',
  'BETON DİREK, TRAVERS' => 'BETON-DIREK-TRAVERS',
  'YG SİGORTALAR' => 'YG-SIGORTALAR',
  'HAVAİ HAT EKİPMANLARI' => 'HAVAI-HAT-EKIPMANLARI',
  'DEMİR DİREK, TRAVERS' => 'DEMIR-DIREK-TRAVERS',
  'AER KABLO' => 'AER-KABLO',
  'İZOLATÖR' => 'IZOLATOR',
  'HIRDAVAT VE TEKNİK MALZEME' => 'HIRDAVAT-VE-TEKNIK-MALZEME',
  'KESİCİLER' => 'KESICILER',
  'İLETKEN' => 'ILETKEN',
  'PBK' => 'PBK',
  'YG KABLO' => 'YG-KABLO',
  'KLEMENS VE PABUÇLAR' => 'KLEMENS-VE-PABUCLAR',
  'YG AYIRICI' => 'YG-AYIRICI',
  'AKÜ REDRESÖR' => 'AKU-REDRESOR',
  'MUHTELİF MALZEME' => 'MUHTELIF-MALZEME',
  'PANO-SDK' => 'PANO-SDK',
  'AĞAÇ DİREK' => 'AGAC-DIREK',
  'KURU TİP TRANSFORMATÖR' => 'KURU-TIP-TRANSFORMATOR',
  'ELEKTRİK BARALARI' => 'ELEKTRIK-BARALARI',
  'TOPRAKLAMA MALZEMESİ' => 'TOPRAKLAMA-MALZEMESI',
  'PARAFUDR' => 'PARAFUDR',
  'GÜÇ TRAFOSU' => 'GUC-TRAFOSU',
  'AKÜ, ŞARJ CİHAZLARI' => 'AKU-SARJ-CIHAZLARI',
];

// unit normalization
function normUnit(string $u): string {
  $u = trim($u);
  return match (mb_strtoupper($u,'UTF-8')) {
    'ADT','ADET','ST' => 'ST',
    'M','METRE'       => 'M',
    'KG','KİLOGRAM'   => 'KG',
    'L','LİTRE','LITRE' => 'LT',
    default => $u,
  };
}

$products=[]; $cats=[];
foreach($rows as $code=>$list){
  usort($list, fn($a,$b)=>$a['prio']<=>$b['prio']); // stable in PHP 8
  $name=$list[0]['name'];
  $cat=''; $unit='';
  foreach($list as $x){ if($cat===''&&$x['cat']!=='')$cat=$x['cat']; if($unit===''&&$x['unit']!=='')$unit=$x['unit']; }
  $catKey = $ALIAS[up($cat)] ?? up($cat);
  if($catKey==='') { fwrite(STDERR,"NO CAT: $code\n"); continue; }
  if(isset($EXISTING[$catKey])) $catCode=$EXISTING[$catKey][0];
  else { $catCode=$SLUG[$catKey] ?? null; if(!$catCode){ fwrite(STDERR,"NO SLUG: $catKey\n"); continue; } }
  $u = normUnit($unit); if($u==='') $u='ST'; // 4 LED fixtures have no unit in YATIRIM; piece count
  $products[$code]=[$name,$u,$catCode];
  $cats[$catCode]=isset($EXISTING[$catKey]) ? $EXISTING[$catKey][1] : $catKey;
}

// case-insensitive duplicate codes?
$seen=[]; foreach($products as $c=>$_){ $k=mb_strtoupper($c); if(isset($seen[$k])) echo "CASE DUP: $c vs {$seen[$k]}\n"; $seen[$k]=$c; }

ksort($products, SORT_NATURAL|SORT_FLAG_CASE);
ksort($cats);

echo "products: ".count($products)." | categories: ".count($cats)." (existing ".count(array_filter(array_keys($cats),fn($c)=>in_array($c,['GY-HUCRE','HY-HUCRE','TRF-33KV','AG-PANO','SDK'],true))).")\n";
$u=[]; foreach($products as $p)$u[$p[1]]=($u[$p[1]]??0)+1; echo "units: "; foreach($u as $k=>$v)echo "$k=$v "; echo "\n";
foreach($cats as $c=>$t) echo "  $c => $t\n";

// emit data file
$lines=[];
$lines[]='<?php';
$lines[]='';
$lines[]='/**';
$lines[]=' * Product catalog imported from';
$lines[]=' * "Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx".';
$lines[]=' *';
$lines[]=' * Source sheets: Sayfa2, YATIRIM, BAKIM, İŞLETME, 2026 ihale.';
$lines[]=' * One row per unique Malzeme Kodu; category/unit/name resolved by sheet priority';
$lines[]=' * (Sayfa2 → YATIRIM → BAKIM → İŞLETME → 2026 ihale).';
$lines[]=' *';
$lines[]=' * Categories are included WITHOUT file requirements on purpose: the';
$lines[]=' * technical data for these groups has not been supplied yet.';
$lines[]=' *';
$lines[]=' * Auto-generated — do not edit by hand; regenerate from the workbook.';
$lines[]=' */';
$lines[]='return [';
$lines[]='    \'categories\' => [';
foreach($cats as $code=>$title){ $lines[]="        ".var_export($code,true)." => ".var_export($title,true).","; }
$lines[]='    ],';
$lines[]='    \'products\' => [';
foreach($products as $code=>$p){
  $lines[]="        [".var_export($code,true).", ".var_export($p[0],true).", ".var_export($p[1],true).", ".var_export($p[2],true)."],";
}
$lines[]='    ],';
$lines[]='];';
$out=implode("\n",$lines)."\n";
file_put_contents($panel.'/database/seeders/data/saha_isgucu_products.php',$out);
echo "written database/seeders/data/saha_isgucu_products.php (".strlen($out)." bytes)\n";
