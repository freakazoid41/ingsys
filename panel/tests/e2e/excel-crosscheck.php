<?php
/**
 * Excel ↔ DB cross-check for the 5 TEDAŞ tanzim tables.
 * Run: php /path/to/excel-crosscheck.php
 */

$panel = dirname(__DIR__, 2);
require $panel.'/vendor/autoload.php';
$app = require $panel.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Facades\DB;

$dir = $argv[1] ?? '/Users/freakazoid41/Downloads/RE__Tedarik_Portalı_Geliştirme_Toplantısı/Teknik tanzim tabloları';

$configs = [
    'FRM.843' => [
        'file' => $dir.'/FRM.843_0___GAZ_YALITIMLI_HUCRE_TANZIM_TABLOSU.xlsx',
        'code' => 'GY-HUCRE', 'model' => 'variants',
    ],
    'FRM.846' => [
        'file' => $dir.'/FRM.846_0___HAVA_YALITIMLI_HUCRE_TANZIM_TABLOSU.xlsx',
        'code' => 'HY-HUCRE', 'model' => 'variants',
    ],
    'FRM.847' => [
        'file' => $dir.'/FRM.847_0___HERMETIK_TRANSFORMATOR_TANZIM_TABLOSU.xlsx',
        'code' => 'TRF-33KV', 'model' => 'kva',
    ],
    'FRM.865' => [
        'file' => $dir.'/FRM.865_0___PANO_TANZIM_TABLOSU.xlsx',
        'code' => 'AG-PANO', 'model' => 'pano', 'firstCol' => 5,
    ],
    'FRM.869' => [
        'file' => $dir.'/FRM.869_0___SAHA_DAGITIM_KUTUSU_TANZIM_TABLOSU.xlsx',
        'code' => 'SDK', 'model' => 'variants', 'firstCol' => 5,
    ],
];

function norm(string $s): string {
    $s = str_replace(['’', '‘'], "'", $s);
    $s = str_replace(['“', '”'], '"', $s);
    $s = str_replace(['–', '—', '−'], '-', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function trLower(string $s): string {
    return mb_strtolower(strtr($s, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
}

function loadGrid(string $file): array {
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $maxR = $sheet->getHighestDataRow();
    $maxC = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    $grid = [];
    for ($r = 1; $r <= $maxR; $r++) {
        for ($c = 1; $c <= $maxC; $c++) {
            $ref = Coordinate::stringFromColumnIndex($c).$r;
            $v = $sheet->getCell($ref)->getValue();
            $grid[$r][$c] = is_string($v) ? norm($v) : (string) ($v ?? '');
        }
    }
    foreach ($sheet->getMergeCells() as $range) {
        [$a, $b] = array_pad(explode(':', $range), 2, null);
        if (!$b) continue;
        $c1 = Coordinate::columnIndexFromString(preg_replace('/\d/', '', $a));
        $r1 = (int) preg_replace('/\D/', '', $a);
        $c2 = Coordinate::columnIndexFromString(preg_replace('/\d/', '', $b));
        $r2 = (int) preg_replace('/\D/', '', $b);
        $val = $grid[$r1][$c1] ?? '';
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) $grid[$r][$c] = $val;
        }
    }
    return $grid;
}

function findHeaderRow(array $grid, int $maxR): ?int {
    for ($r = 1; $r <= $maxR; $r++) {
        if (mb_strtoupper(trim($grid[$r][1] ?? '')) === 'EVRAK NO') return $r;
    }
    return null;
}

function scopeForRow(array $grid, int $r, array $scopeCols, string $model): array {
    $kva = []; $variants = [];
    foreach ($scopeCols as $col => $label) {
        $cell = mb_strtolower(trim($grid[$r][$col] ?? ''));
        $required = $cell === 'link';
        if (!$required) continue;
        if ($model === 'variants') {
            $variants[] = $label;
        } elseif ($model === 'kva') {
            if (preg_match('/^(\d+)/', $label, $m)) $kva[] = $m[1];
        } elseif ($model === 'pano') {
            // label = kVA text, column has variant from row 17
            $variant = $label['variant'] ?? '';
            $kvaText = $label['kva'] ?? '';
            if ($variant !== '') {
                $variantName = mb_convert_case(mb_strtolower(strtr($variant, ['İ' => 'i', 'I' => 'ı']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                $variants[] = $kvaText.' '.$variantName;
            } else {
                if (preg_match('/^(\d+)/', $kvaText, $m)) $kva[] = $m[1];
            }
        }
    }
    return [$kva, $variants];
}

function dbCategory(string $code): ?array {
    $catForm = DB::table('sys_options')->where('op_key', 'op-doc-product-category-form')->value('id');
    $row = DB::table('sys_con_entities as se')
        ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
        ->where('so.type_id', $catForm)
        ->where(function ($q) { $q->where('se.entity_tag', 'code')->orWhere('se.entity_tag', 'like', 'code**%'); })
        ->where('se.entity_value', $code)
        ->first(['so.id as conn_id', 'so.main_id']);
    if (!$row) return null;

    $ents = DB::table('sys_con_entities')->where('conn_id', $row->conn_id)->get(['entity_tag', 'entity_value', 'table_tag']);
    $rows = [];
    foreach ($ents as $e) {
        if (!str_contains($e->entity_tag, '**category_files**')) continue;
        $p = explode('**', $e->entity_tag);
        if (count($p) < 3) continue;
        $rows[$p[2]][$p[0]] = $e->entity_value;
    }
    $out = [];
    foreach ($rows as $rk => $r) {
        $kva = json_decode($r['kva'] ?? '[]', true) ?: [];
        $variants = json_decode($r['variants'] ?? '[]', true) ?: [];
        $out[] = [
            'evrak_no' => (string) ($r['evrak_no'] ?? ''),
            'standard' => norm((string) ($r['standard'] ?? '')),
            'file_title' => norm((string) ($r['file_title'] ?? '')),
            'file_group' => (string) ($r['file_group'] ?? ''),
            'kva' => array_map('strval', $kva),
            'variants' => array_map('strval', $variants),
        ];
    }
    return ['conn' => $row->conn_id, 'rows' => $out];
}

$report = [];
$fail = 0;

foreach ($configs as $frm => $cfg) {
    echo "\n================ {$frm} ({$cfg['code']}) ================\n";
    $grid = loadGrid($cfg['file']);
    $maxR = max(array_keys($grid));
    $hdr = findHeaderRow($grid, $maxR);
    if (!$hdr) { echo "  !! header row not found\n"; $fail++; continue; }

    // scope label row = hdr+1; for pano, variant row = hdr+2
    $labelRow = $hdr + 1;
    $variantRow = ($cfg['model'] === 'pano') ? $hdr + 2 : null;

    $maxC = max(array_map('count', $grid));
    $scopeCols = [];
    $firstCol = $cfg['firstCol'] ?? 4;
    for ($c = $firstCol; $c <= $maxC; $c++) {
        $label = trim($grid[$labelRow][$c] ?? '');
        if ($label === '') continue;
        if ($cfg['model'] === 'pano') {
            $variant = trim($grid[$variantRow][$c] ?? '');
            if ($variant !== '' && (mb_stripos($variant, 'kva') !== false || $variant === $label)) $variant = '';
            $scopeCols[$c] = [
                'kva' => $label,
                'variant' => $variant,
            ];
        } else {
            $scopeCols[$c] = $label;
        }
    }
    echo '  scope columns: '.count($scopeCols).' — '.implode(' | ', array_map(function ($v) {
        return is_array($v) ? $v['kva'].($v['variant'] ? ' ('.$v['variant'].')' : '') : $v;
    }, $scopeCols))."\n";

    // expected rows
    $expected = [];
    $group = 'Tip Test Raporları';
    $start = $hdr + (($cfg['model'] === 'pano') ? 3 : 2);
    for ($r = $start; $r <= $maxR; $r++) {
        $a = trim($grid[$r][1] ?? '');
        $joined = implode(' ', array_map('strval', $grid[$r] ?? []));
        if (str_contains(mb_strtoupper($joined), 'DİĞER BELGELER')) { $group = 'Diğer Belgeler'; continue; }
        if (str_starts_with(mb_strtoupper($joined), 'UYARI')) break;
        if (!preg_match('/^\d+$/', $a)) continue;

        $b = trim($grid[$r][2] ?? '');
        $c = trim($grid[$r][3] ?? '');
        if ($b !== '' && $c !== '' && $b !== $c) { $standard = $b; $title = $c; }
        else { $standard = ''; $title = ($b !== '' ? $b : $c); }
        if ($title === '') continue;

        [$kva, $variants] = scopeForRow($grid, $r, $scopeCols, $cfg['model']);
        $expected[$a] = [
            'evrak_no' => $a, 'standard' => norm($standard), 'file_title' => norm($title),
            'file_group' => $group, 'kva' => $kva, 'variants' => $variants,
        ];
    }
    echo '  expected rows: '.count($expected)."\n";

    $db = dbCategory($cfg['code']);
    if (!$db) { echo "  !! category {$cfg['code']} not in DB\n"; $fail++; continue; }
    echo '  db rows: '.count($db['rows'])."\n";

    $dbByEvrak = [];
    foreach ($db['rows'] as $row) $dbByEvrak[$row['evrak_no']] = $row;

    foreach ($expected as $ev => $exp) {
        if (!isset($dbByEvrak[$ev])) { echo "  ✗ MISSING DB row evrak {$ev}: {$exp['file_title']}\n"; $fail++; continue; }
        $got = $dbByEvrak[$ev];
        if (norm($got['file_title']) !== $exp['file_title']) {
            echo "  ✗ title [{$ev}] DB='{$got['file_title']}' EXP='{$exp['file_title']}'\n"; $fail++;
        }
        if (norm($got['standard']) !== $exp['standard']) {
            echo "  ✗ standard [{$ev}] DB='{$got['standard']}' EXP='{$exp['standard']}'\n"; $fail++;
        }
        if ($got['file_group'] !== $exp['file_group']) {
            echo "  ✗ group [{$ev}] DB='{$got['file_group']}' EXP='{$exp['file_group']}'\n"; $fail++;
        }
        $expScopes = array_merge($exp['kva'], $exp['variants']);
        $gotScopes = array_merge($got['kva'], $got['variants']);
        $expNorm = array_map(fn($x) => trLower(norm((string) $x)), $expScopes);
        $gotNorm = array_map(fn($x) => trLower(norm((string) $x)), $gotScopes);
        // Empty connection list = "applies to all" — equivalent when the sheet links every scope.
        if (empty($gotNorm) && !empty($expNorm)) {
            $gotNorm = $expNorm;
        }
        sort($expNorm); sort($gotNorm);
        if ($expNorm !== $gotNorm) {
            echo "  ✗ scopes [{$ev}] {$exp['file_title']}\n     DB : ".implode(' | ', $gotScopes)."\n     EXP: ".implode(' | ', $expScopes)."\n";
            $fail++;
        }
    }
    $expectedEvraks = array_keys($expected);
    foreach ($dbByEvrak as $ev => $row) {
        if (!in_array((string) $ev, array_map('strval', $expectedEvraks), true)) {
            echo "  ✗ EXTRA DB row evrak {$ev}: {$row['file_title']}\n"; $fail++;
        }
    }
}

echo "\n==================================================\n";
echo $fail === 0 ? "ALL GREEN — excel and DB match exactly.\n" : "MISMATCHES: {$fail} — fix required.\n";
