<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 33 kV Hermetik Dağıtım Transformatörü — category + file-requirement template + per-kVA products.
 *
 * File requirements are pointer definitions only (no document_files rows are created).
 * kva = []  → applies to every power rating (single spanning cell in the matrix).
 * kva = [..] → applies only to the listed kVA codes.
 *
 * Run: php artisan db:seed --class=TransformerCatalogSeeder
 */
class TransformerCatalogSeeder extends Seeder
{
    private const CATEGORY_TITLE = '33 kV Hermetik Dağıtım Transformatörü';
    private const CATEGORY_CODE  = 'TRF-33KV';
    private const ALL_KVA        = ['50', '100', '160', '250', '400', '630', '1000', '1250', '1600'];

    public function run(): void
    {
        $catType   = DB::table('sys_options')->where('op_key', 'op-doc-product-category')->value('id');
        $catForm   = DB::table('sys_options')->where('op_key', 'op-doc-product-category-form')->value('id');
        $prodType  = DB::table('sys_options')->where('op_key', 'op-doc-product')->value('id');
        $prodForm  = DB::table('sys_options')->where('op_key', 'op-doc-product-form')->value('id');
        $formMain  = DB::table('sys_options')->where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');

        if (! $catType || ! $catForm || ! $prodType || ! $prodForm || ! $formMain) {
            echo "Dictionary missing — run ProductCategorySeeder + ProductSeeder first.\n";
            return;
        }

        // Idempotency: same category code already seeded → skip.
        $exists = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->where('so.type_id', $catForm)
            ->where('se.entity_tag', 'like', 'code**%')
            ->where('se.entity_value', self::CATEGORY_CODE)
            ->exists();

        if ($exists) {
            echo self::CATEGORY_TITLE." already seeded, skip..\n";
            return;
        }

        $grp = $GLOBALS['SYS_CODE'] ?? 'GDZ';

        DB::beginTransaction();
        try {
            $now = now();

            // ---------------- Category ----------------
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

            $insert = function (int $connId, string $tag, string $value) use ($now) {
                DB::table('sys_con_entities')->insert([
                    'conn_id' => $connId, 'entity_tag' => $tag, 'entity_value' => $value,
                    'table_tag' => 'sys_con_ops', 'created_at' => $now, 'updated_at' => $now,
                ]);
            };

            $insert($catConn, 'qnid', $catQnid);
            $insert($catConn, 'title', self::CATEGORY_TITLE);
            $insert($catConn, 'code', self::CATEGORY_CODE);

            // ---------------- Category file requirements (image, 1:1) ----------------
            $rows = [
                // evrak_no, standard, file_title, file_group, file_types, kva
                ['0',  '',                       'Garantili Özellikler Listesi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['1',  'TS EN 60076-11',         'Sıcaklık Artışı (Isınma) Deneyi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['2',  'TS EN 60076-3',          'Yıldırım Darbe Deneyi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['3',  'TS EN 60076-10',         'Ses Gücü Düzeyinin Ölçülmesi Deneyi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['4',  'TS EN 60076-1 Madde 11.5', 'Anma Geriliminin %90 ve %110’unda Boştaki Kayıp ve Akımın Ölçülmesi Deneyi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['5',  'TS EN 50464-4',          'Kazan Ömür ve Sızdırmazlık Deneyi', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['6',  'TS EN 60076-5',          'Kısa Devrelere Karşı Mekanik Dayanım Deneyi (K.D.K.M.D.D.)', 'Tip Test Raporları', 'pdf', self::ALL_KVA],
                ['8',  '',                       'İmalatçı firmaya ait TS EN ISO 9001/EN ISO 9001 Kalite Yönetim Sistem Belgesi', 'Diğer Belgeler', 'pdf', self::ALL_KVA],
                ['9',  '',                       'İmalatçı firmaya ait TS EN ISO 14001/EN ISO 14001 Çevre Yönetim Sistem Belgesi', 'Diğer Belgeler', 'pdf', []],
                ['10', '',                       'İmalatçı firmaya ait Türk Standardına Uygunluk Belgesi', 'Diğer Belgeler', 'pdf', []],
                ['11', '',                       'Ayrıntılı katalog, montaj, işletme ve bakım yönergeleri', 'Diğer Belgeler', 'pdf_img', self::ALL_KVA],
                ['12', '',                       'Tank, çekirdek ve sargıların tipi, sargı düzenlenmesi ile ilgili açıklayıcı bilgiler ve resimler', 'Diğer Belgeler', 'pdf_img', []],
                ['13', '',                       'Genel Boyutlar ve Yardımcı Teçhizatın Düzenlenmesini Gösteren Resimler', 'Diğer Belgeler', 'pdf_img', self::ALL_KVA],
                ['14', '',                       'Taşıma Ölçülerini Gösteren Resimler ve Taşıma Ağırlıkları', 'Diğer Belgeler', 'pdf_img', self::ALL_KVA],
            ];

            foreach ($rows as $i => $r) {
                [$evrakNo, $standard, $fileTitle, $fileGroup, $fileTypes, $kva] = $r;
                // Row key shape matters: Form.vue de-dupes multiple rows by the first
                // token before the dash (`rowKey.split('-')[0]`). A shared prefix
                // ("seed-1"..."seed-14") collapses every row into ONE card.
                $rowKey = (1700000000000 + $i).'-'.$i;
                $suffix = '**category_files**'.$rowKey;

                $insert($catConn, 'evrak_no'.$suffix, $evrakNo);
                $insert($catConn, 'standard'.$suffix, $standard);
                $insert($catConn, 'file_title'.$suffix, $fileTitle);
                $insert($catConn, 'file_group'.$suffix, $fileGroup);
                $insert($catConn, 'file_types'.$suffix, $fileTypes);
                $insert($catConn, 'kva'.$suffix, json_encode($kva));
            }

            // ---------------- Products (one per kVA) ----------------
            $products = [
                ['TRF-33-050',  '50 kVA'],
                ['TRF-33-100',  '100 kVA'],
                ['TRF-33-160',  '160 kVA'],
                ['TRF-33-250',  '250 kVA'],
                ['TRF-33-400',  '400 kVA'],
                ['TRF-33-630',  '630 kVA'],
                ['TRF-33-1000', '1000 kVA'],
                ['TRF-33-1250', '1250 kVA'],
                ['TRF-33-1600', '1600 kVA'],
            ];

            foreach ($products as [$code, $power]) {
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
                $insert($pConn, 'prod_code', $code);
                $insert($pConn, 'short_name', '33 kV Hermetik Dağıtım Trafosu '.$power);
                $insert($pConn, 'unit', 'ST');
                $insert($pConn, 'category_title', self::CATEGORY_TITLE);
                $insert($pConn, 'category_qnid', $catQnid);
            }

            DB::commit();

            echo "Seeded category \"".self::CATEGORY_TITLE."\" (".self::CATEGORY_CODE.") with ".count($rows)." file requirements and ".count($products)." products.\n";
            echo "Category qnid: ".$catQnid."\n";
        } catch (\Throwable $e) {
            DB::rollBack();
            echo 'ROLLED BACK: '.$e->getMessage()."\n";
        }
    }
}
