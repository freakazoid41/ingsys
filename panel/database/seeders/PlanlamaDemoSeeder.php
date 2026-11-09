<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Documents;
use App\Models\Sys_con_ops;
use App\Models\Sys_con_entities;
use App\Models\Sys_options;
use App\Models\UserLog;
use App\Models\Transactions;

// Demo İhtiyaç Listesi content (3 lists). Idempotent by title. NOT part of first install.
class PlanlamaDemoSeeder extends Seeder
{
    public const DEMOS = [
        [
            'title' => 'Prefabrik Bina İhtiyacı',
            'desc' => 'Şantiye prefabrik bina kalemleri',
            'donem_bas' => '01/2026',
            'donem_bit' => '03/2026',
            'items' => [
                ['mal_grubu' => 'PREFABRİK BİNA', 'ihale_grubu' => 'PREFABRİK BİNA', 'prod_code' => '122.5.5.053.IA', 'title' => 'PREFABRİK BİNA-EK-1C,A=12M,B=2.6M', 'unit' => 'ST'],
                ['mal_grubu' => 'PREFABRİK BİNA', 'ihale_grubu' => 'PREFABRİK BİNA', 'prod_code' => '122.5.5.054.IB', 'title' => 'PREFABRİK BİNA-EK-2C,A=10M,B=2.6M', 'unit' => 'ST'],
            ],
        ],
        [
            'title' => 'Şantiye Elektrik Malzemeleri',
            'desc' => 'Elektrik tesisat kalemleri',
            'donem_bas' => '02/2026',
            'donem_bit' => '04/2026',
            'items' => [
                ['mal_grubu' => 'ELEKTRİK', 'ihale_grubu' => 'ELEKTRİK', 'prod_code' => '310.1.2.011.KB', 'title' => 'KABLO NYY 3X2.5', 'unit' => 'M'],
                ['mal_grubu' => 'ELEKTRİK', 'ihale_grubu' => 'ELEKTRİK', 'prod_code' => '310.1.4.002.PN', 'title' => 'PANO SAC 60X80', 'unit' => 'ST'],
            ],
        ],
        [
            'title' => 'Ofis Mobilyaları',
            'desc' => 'İdari bina ofis donanımı',
            'donem_bas' => '01/2026',
            'donem_bit' => '01/2026',
            'items' => [
                ['mal_grubu' => 'MOBİLYA', 'ihale_grubu' => 'MOBİLYA', 'prod_code' => '410.2.1.007.MS', 'title' => 'ÇALIŞMA MASASI 160X80', 'unit' => 'ST'],
            ],
        ],
    ];

    public function run(): void
    {
        $docTypeId = Sys_options::where('op_key', 'op-doc-request')->value('id');
        $formTypeId = Sys_options::where('op_key', 'op-doc-request-form')->value('id');
        $itemFormTypeId = Sys_options::where('op_key', 'op-doc-request-item-form')->value('id');
        $formMainId = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');
        if (!$docTypeId || !$formTypeId || !$itemFormTypeId || !$formMainId) {
            $this->command->warn('op-doc-request dictionary missing, run PlanlamaRequestSeeder first');
            return;
        }

        foreach (self::DEMOS as $demo) {
            $exists = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->join('documents as d', 'd.id', '=', 'so.main_id')
                ->where('se.entity_tag', 'title')
                ->where('se.entity_value', $demo['title'])
                ->where('se.table_tag', 'sys_con_ops')
                ->where('d.type_id', $docTypeId)
                ->where('d.status', 1)
                ->exists();
            if ($exists) {
                $this->command->line("= demo exists: {$demo['title']}");
                continue;
            }

            $document = new Documents();
            $document->type_id = $docTypeId;
            $document->person_id = 'system';
            $document->grp_code = 'GDZ';
            $document->save();

            $birthType = Sys_options::where('op_key', 'doc_trans_request_created')->first();
            if ($birthType) {
                $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
                $userId = DB::table('users')->where('status', 1)->value('id') ?? 0;
                $log = UserLog::create([
                    'user_id' => $userId,
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                    'relation' => 'documents',
                    'relation_id' => $document->id,
                    'type_id' => $logTypeId,
                    'description' => json_encode(['desc' => 'Demo ihtiyaç oluşturuldu']),
                ]);
                Transactions::create([
                    'op_id' => 0, 'type_id' => $birthType->id, 'log_id' => $log->id,
                    'target_id' => $document->id, 'description' => 'Demo ihtiyaç oluşturuldu',
                ]);
            }

            $head = new Sys_con_ops();
            $head->main_id = $document->id;
            $head->conn_id = 0;
            $head->type_id = $formTypeId;
            $head->sub_type_id = $formMainId;
            $head->save();
            foreach (['qnid' => $document->qnid, 'title' => $demo['title'], 'desc' => $demo['desc'], 'donem_bas' => $demo['donem_bas'], 'donem_bit' => $demo['donem_bit']] as $tag => $val) {
                $e = new Sys_con_entities();
                $e->conn_id = $head->id;
                $e->entity_tag = $tag;
                $e->entity_value = $val;
                $e->table_tag = 'sys_con_ops';
                $e->save();
            }

            $items = new Sys_con_ops();
            $items->main_id = $document->id;
            $items->conn_id = 0;
            $items->type_id = $itemFormTypeId;
            $items->sub_type_id = $formMainId;
            $items->save();
            $ri = 0;
            foreach ($demo['items'] as $it) {
                $rtag = 'demo-' . $document->id . '-' . ($ri++);
                foreach (['mal_grubu' => $it['mal_grubu'], 'ihale_grubu' => $it['ihale_grubu'], 'prod_code' => $it['prod_code'], 'title' => $it['title'], 'unit' => $it['unit'], 'quantity' => '0'] as $f => $v) {
                    $e = new Sys_con_entities();
                    $e->conn_id = $items->id;
                    $e->entity_tag = $f . '**request_items**' . $rtag;
                    $e->entity_value = $v;
                    $e->table_tag = 'sys_con_ops';
                    $e->save();
                }
            }
            $this->command->line("+ demo created: {$demo['title']} (" . count($demo['items']) . " kalem)");
        }
    }
}
