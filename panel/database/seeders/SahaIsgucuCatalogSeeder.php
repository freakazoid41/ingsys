<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Product catalog imported from
 * "Teknik Değerlendirme Saha İşgücü Modülü Eşleştirme Tablosu.xlsx".
 *
 * Source sheets: Sayfa2, YATIRIM, BAKIM, İŞLETME, 2026 ihale.
 * The workbook is reduced to one row per unique Malzeme Kodu. When a code
 * appears in several sheets, the first sheet in this priority order wins:
 *   Sayfa2 → YATIRIM → BAKIM → İŞLETME → 2026 ihale
 *
 * Categories:
 *   - The five real TEDAŞ groups already exist with their tanzim tables
 *     (GY-HUCRE, HY-HUCRE, TRF-33KV, AG-PANO, SDK) — products attach to them.
 *   - Every other Malzeme Grubu is created as a new category with NO file
 *     requirements on purpose: the technical data for those groups has not
 *     been supplied yet. They will be filled from the panel later.
 *
 * Idempotent:
 *   - categories matched by `code`
 *   - products matched by `prod_code`
 *   - re-running refreshes names/units/category links, never duplicates
 *
 * Run: php artisan db:seed --class=SahaIsgucuCatalogSeeder
 */
class SahaIsgucuCatalogSeeder extends Seeder
{
    private const EXISTING_CODES = ['GY-HUCRE', 'HY-HUCRE', 'TRF-33KV', 'AG-PANO', 'SDK'];

    public function run(): void
    {
        // Self-sufficient: the five real TEDAŞ categories (with their tanzim
        // tables) must exist before products can attach to them. Idempotent.
        $this->call(TedarikRealCatalogSeeder::class);

        $catType  = DB::table('sys_options')->where('op_key', 'op-doc-product-category')->value('id');
        $catForm  = DB::table('sys_options')->where('op_key', 'op-doc-product-category-form')->value('id');
        $prodType = DB::table('sys_options')->where('op_key', 'op-doc-product')->value('id');
        $prodForm = DB::table('sys_options')->where('op_key', 'op-doc-product-form')->value('id');
        $formMain = DB::table('sys_options')->where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');

        if (! $catType || ! $catForm || ! $prodType || ! $prodForm || ! $formMain) {
            echo "Dictionary missing — cannot seed Saha İşgücü catalog.\n";
            return;
        }

        $data = require __DIR__.'/data/saha_isgucu_products.php';
        $grp  = $GLOBALS['SYS_CODE'] ?? 'GDZ';
        $now  = now();

        $insert = function (int $connId, string $tag, string $value) use ($now) {
            DB::table('sys_con_entities')->insert([
                'conn_id'     => $connId,
                'entity_tag'  => $tag,
                'entity_value'=> $value,
                'table_tag'   => 'sys_con_ops',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        };

        // ------------------------------------------------------------------
        // 1. Categories — ensure every workbook group exists.
        // ------------------------------------------------------------------
        $catByCode = [];   // code => ['id' => int, 'conn' => int, 'qnid' => string]
        $newCats   = 0;

        foreach ($data['categories'] as $code => $title) {
            $found = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->where('so.type_id', $catForm)
                ->where(function ($q) {
                    $q->where('se.entity_tag', 'code')->orWhere('se.entity_tag', 'like', 'code**%');
                })
                ->where('se.entity_value', $code)
                ->first(['so.id as conn_id', 'so.main_id']);

            if ($found) {
                $qnid = DB::table('documents')->where('id', $found->main_id)->value('qnid');
                $catByCode[$code] = ['id' => (int) $found->main_id, 'conn' => (int) $found->conn_id, 'qnid' => $qnid];
                continue;
            }

            $qnid = (string) Str::uuid();
            $id = DB::table('documents')->insertGetId([
                'status' => 1, 'parent_type_id' => 0, 'parent_id' => 0,
                'type_id' => $catType, 'person_id' => 'system', 'title' => '-',
                'grp_code' => $grp, 'qnid' => $qnid,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $conn = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $id, 'conn_id' => 0, 'type_id' => $catForm,
                'sub_type_id' => $formMain, 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $insert($conn, 'qnid', $qnid);
            $insert($conn, 'title', $title);
            $insert($conn, 'code', $code);

            $catByCode[$code] = ['id' => (int) $id, 'conn' => (int) $conn, 'qnid' => $qnid];
            $newCats++;
            echo "  → category {$code} created ({$title}) — no file requirements.\n";
        }

        // Refresh titles of the imported (non-real) categories when the data file changes.
        foreach ($data['categories'] as $code => $title) {
            if (in_array($code, self::EXISTING_CODES, true)) continue;
            DB::table('sys_con_entities')
                ->where('conn_id', $catByCode[$code]['conn'])
                ->where('entity_tag', 'title')
                ->update(['entity_value' => $title, 'updated_at' => $now]);
        }

        // ------------------------------------------------------------------
        // 2. Products — one document + EAV conn per unique prod_code.
        // ------------------------------------------------------------------
        $existing = [];    // prod_code => conn id
        $prodConns = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->where('so.type_id', $prodForm)
            ->where(function ($q) {
                $q->where('se.entity_tag', 'prod_code')->orWhere('se.entity_tag', 'like', 'prod_code**%');
            })
            ->get(['se.entity_value', 'so.id as conn_id', 'so.main_id']);

        foreach ($prodConns as $row) {
            $existing[$row->entity_value] = ['conn' => (int) $row->conn_id, 'main' => (int) $row->main_id];
        }

        $created = 0;
        $updated = 0;

        foreach ($data['products'] as [$code, $name, $unit, $catCode]) {
            $cat = $catByCode[$catCode] ?? null;
            if (! $cat) {
                echo "  ✗ product {$code}: category {$catCode} missing.\n";
                continue;
            }

            if (isset($existing[$code])) {
                $connId = $existing[$code]['conn'];
                foreach (['short_name' => $name, 'unit' => $unit, 'category_title' => $data['categories'][$catCode], 'category_qnid' => $cat['qnid']] as $tag => $value) {
                    DB::table('sys_con_entities')->updateOrInsert(
                        ['conn_id' => $connId, 'entity_tag' => $tag, 'table_tag' => 'sys_con_ops'],
                        ['entity_value' => $value, 'updated_at' => $now]
                    );
                }
                $updated++;
                continue;
            }

            $qnid = (string) Str::uuid();
            $id = DB::table('documents')->insertGetId([
                'status' => 1, 'parent_type_id' => 0, 'parent_id' => 0,
                'type_id' => $prodType, 'person_id' => 'system', 'title' => '-',
                'grp_code' => $grp, 'qnid' => $qnid,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $connId = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $id, 'conn_id' => 0, 'type_id' => $prodForm,
                'sub_type_id' => $formMain, 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $insert($connId, 'qnid', $qnid);
            $insert($connId, 'prod_code', $code);
            $insert($connId, 'short_name', $name);
            $insert($connId, 'unit', $unit);
            $insert($connId, 'category_title', $data['categories'][$catCode]);
            $insert($connId, 'category_qnid', $cat['qnid']);

            $existing[$code] = ['conn' => $connId, 'main' => (int) $id];
            $created++;
        }

        $total = count($data['products']);
        echo "SahaIsgucuCatalogSeeder done — {$total} products ({$created} created, {$updated} refreshed), ".count($data['categories'])." categories ({$newCats} new, no file requirements).\n";
    }
}
