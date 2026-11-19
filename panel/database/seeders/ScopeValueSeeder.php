<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kapsam Tipi (Scope Value) dictionary — the TEXT equivalent of kVA.
 *
 * The TEDAŞ tanzim tables scope file requirements by text columns (functional
 * units, TİP-A/B, HARİCİ/DAHİLİ) exactly like the transformer sheet scopes by
 * kVA. These values live as documents (same pattern as KvaSeeder) so users can
 * manage them at runtime and file requirements reference them by `code`.
 *
 * Doc type: op-doc-scope-value / form: op-doc-scope-value-form
 * Fields: title (display), code (connection key — exact sheet text), scope_group.
 *
 * Run: php artisan db:seed --class=ScopeValueSeeder
 */
class ScopeValueSeeder extends Seeder
{
    private const VALUES = [
        // Hücre fonksiyonel birimleri — FRM.843 (Gaz Yalıtımlı Hücre)
        'YA G./Ç.',
        'KOMPAKT 2YA+1SYA',
        'YA. SİG.TRF KOR',
        'GER. TRF HÜC.',
        'KES. ÇIK',
        'AK.GER. ÖLÇÜ',
        'YA BAR. BÖL./BAĞ.',
        // Hücre fonksiyonel birimleri — FRM.846 (Hava Yalıtımlı Hücre)
        'YA G/.Ç',
        'YA. TRF KOR',
        'GER. TRF',
        'KUPLAJ',
        'AY G/Ç.',
        'KAB.BAĞ.',
        'AK.GER. ÖLÇ',
        'YA BAR. BÖL.',
        // SDK tipleri — FRM.869
        'TİP-A',
        'TİP-B',
        // Pano kVA + alt-tip birleşik tanımları — FRM.865.
        // Alt tipler AYRI boyut olarak tutulmaz; tek kapsam değeri olarak yazılır:
        // "50kVA (Harici, Dahili)" → "50 kVA Harici" + "50 kVA Dahili".
        // FRM.865: 100=Harici; 160/250/400 = Harici + Dahili; 630/1000/1250/1600 plain kVA.
        '100 kVA Harici',
        '160 kVA Harici',
        '160 kVA Dahili',
        '250 kVA Harici',
        '250 kVA Dahili',
        '400 kVA Harici',
        '400 kVA Dahili',
    ];

    /** Retired definitions (old split sub-types + incorrect combos) — removed on re-seed. */
    private const RETIRED = [
        'HARİCİ', 'DAHİLİ', '1600 kVA Harici', '1600 kVA Dahili',
        '630 kVA Dahili', '1000 kVA Harici', '1250 kVA Dahili',
    ];

    public function run(): void
    {
        // ---- Dictionary rows ----
        $items = [
            ['op_key' => 'op-doc-scope-value',      'group_key' => 'op-doc',      'title' => 'Kapsam Tipi Tanımı', 'ttitle' => '-',          'ctitle' => 'type_id'],
            ['op_key' => 'op-doc-scope-value-form', 'group_key' => 'op-doc-forms', 'title' => 'Kapsam Tipi Formu',  'ttitle' => 'Documents', 'ctitle' => 'type_id'],
        ];
        foreach ($items as $item) {
            $exists = DB::table('sys_options')->where('op_key', $item['op_key'])->first();
            if ($exists) {
                echo $item['title']." already exists, skip..\n";
                continue;
            }
            DB::table('sys_options')->insert([
                'parent_id' => 0, 'title' => $item['title'], 'ttitle' => $item['ttitle'],
                'ctitle' => $item['ctitle'], 'op_key' => $item['op_key'],
                'group_key' => $item['group_key'], 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            echo $item['title']." Added..\n";
        }

        $typeId = DB::table('sys_options')->where('op_key', 'op-doc-scope-value')->value('id');
        $formId = DB::table('sys_options')->where('op_key', 'op-doc-scope-value-form')->value('id');
        $mainId = DB::table('sys_options')->where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');

        if (! $typeId || ! $formId || ! $mainId) {
            echo "Scope value dictionary missing.\n";
            return;
        }

        $grp = $GLOBALS['SYS_CODE'] ?? 'GDZ';

        foreach (self::VALUES as $code) {
            // Idempotency by code (plain or suffixed tag).
            $exists = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->where('so.type_id', $formId)
                ->where(function ($q) {
                    $q->where('se.entity_tag', 'code')->orWhere('se.entity_tag', 'like', 'code**%');
                })
                ->where('se.entity_value', $code)
                ->exists();
            if ($exists) {
                echo "scope {$code} exists, skip..\n";
                continue;
            }

            $now = now();
            $qnid = (string) Str::uuid();
            $docId = DB::table('documents')->insertGetId([
                'type_id' => $typeId, 'person_id' => 'system', 'title' => $code,
                'grp_code' => $grp, 'qnid' => $qnid,
                'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $conn = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $docId, 'conn_id' => 0, 'type_id' => $formId,
                'sub_type_id' => $mainId, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (['qnid' => $qnid, 'title' => $code, 'code' => $code] as $tag => $val) {
                DB::table('sys_con_entities')->insert([
                    'conn_id' => $conn, 'entity_tag' => $tag, 'entity_value' => (string) $val,
                    'table_tag' => 'sys_con_ops', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            echo "scope {$code} Added..\n";
        }

        // ---- retire split sub-types (now folded into combined names) ----
        foreach (self::RETIRED as $retired) {
            $conn = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->where('so.type_id', $formId)
                ->where('se.entity_tag', 'code')
                ->where('se.entity_value', $retired)
                ->value('se.conn_id');
            if (! $conn) continue;

            $mainId = DB::table('sys_con_ops')->where('id', $conn)->value('main_id');
            DB::table('sys_con_entities')->where('conn_id', $conn)->delete();
            DB::table('sys_con_ops')->where('id', $conn)->delete();
            if ($mainId) {
                DB::table('transactions')->where('target_id', $mainId)->delete();
                DB::table('user_logs')->where('relation', 'documents')->where('relation_id', $mainId)->delete();
                DB::table('documents')->where('id', $mainId)->delete();
            }
            echo "scope {$retired} retired (removed).\n";
        }

        // ---- drop the retired "group" concept from existing rows ----
        $scopeConns = DB::table('sys_con_ops')->where('type_id', $formId)->pluck('id')->all();
        if ($scopeConns) {
            DB::table('sys_con_entities')->whereIn('conn_id', $scopeConns)->where('entity_tag', 'scope_group')->delete();
        }

        echo "ScopeValueSeeder done.\n";
    }
}
