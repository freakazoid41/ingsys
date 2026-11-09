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

class SystemClientSeeder extends Seeder
{
    // System enums — not editable, not deletable (full lock). Reserved 90M range, GDZ+ADM counterparts.
    public const CLIENTS = [
        ['lifnr' => '90000001', 'title' => 'Planlama Müdürlüğü'],
        ['lifnr' => '90000002', 'title' => 'Sistem İşletme Müdürlüğü'],
        ['lifnr' => '90000003', 'title' => 'Bakım Müdürlüğü'],
    ];
    public const SYSTEMS = ['GDZ', 'ADM'];

    public function run(): void
    {
        $clientTypeId = Sys_options::where('op_key', 'op-doc-client')->value('id');
        $formTypeId = Sys_options::where('op_key', 'op-doc-client-form')->value('id');
        $formMainId = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');
        if (!$clientTypeId || !$formTypeId || !$formMainId) {
            $this->command->warn('op-doc-client dictionary missing, skipping system clients');
            return;
        }

        foreach (self::CLIENTS as $c) {
            foreach (self::SYSTEMS as $sys) {
                $this->ensureClient($clientTypeId, $formTypeId, $formMainId, $c['lifnr'], $c['title'], $sys);
            }
        }
    }

    private function ensureClient($clientTypeId, $formTypeId, $formMainId, string $lifnr, string $title, string $sys): void
    {
            $exists = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->join('documents as d', 'd.id', '=', 'so.main_id')
                ->where('se.entity_tag', 'lifnr')
                ->where('se.entity_value', $lifnr)
                ->where('se.table_tag', 'sys_con_ops')
                ->where('d.type_id', $clientTypeId)
                ->where('d.status', 1)
                ->whereExists(function($q) use ($sys) {
                    $q->from('sys_con_entities as se2')
                      ->join('sys_con_ops as so2','so2.id','=','se2.conn_id')
                      ->whereColumn('so2.main_id','d.id')
                      ->where('se2.entity_tag','client_system')
                      ->where('se2.entity_value',$sys)
                      ->where('se2.table_tag','sys_con_ops');
                })
                ->select('d.id')
                ->first();

            if ($exists) {
                // ensure is_system flag present
                $connId = DB::table('sys_con_ops')->where('main_id', $exists->id)->where('type_id', $formTypeId)->where('conn_id', 0)->value('id');
                if ($connId && !DB::table('sys_con_entities')->where('conn_id', $connId)->where('entity_tag', 'is_system')->exists()) {
                    DB::table('sys_con_entities')->insert([
                        'conn_id' => $connId, 'entity_tag' => 'is_system', 'entity_value' => '1',
                        'table_tag' => 'sys_con_ops', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->command->line("= system client exists: {$lifnr} {$title} [{$sys}]");
                return;
            }

            $document = new Documents();
            $document->type_id = $clientTypeId;
            $document->person_id = 'system';
            $document->grp_code = $sys;
            $document->save();

            $birthType = Sys_options::where('op_key', 'doc_trans_created')->first();
            if ($birthType) {
                $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
                $userId = DB::table('users')->where('status', 1)->value('id') ?? 0;
                $log = UserLog::create([
                    'user_id' => $userId,
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                    'relation' => 'documents',
                    'relation_id' => $document->id,
                    'type_id' => $logTypeId,
                    'description' => json_encode(['desc' => 'Sistem carisi oluşturuldu']),
                ]);
                Transactions::create([
                    'op_id' => 0, 'type_id' => $birthType->id, 'log_id' => $log->id,
                    'target_id' => $document->id, 'description' => 'Sistem carisi oluşturuldu',
                ]);
            }

            $conn = new Sys_con_ops();
            $conn->main_id = $document->id;
            $conn->conn_id = 0;
            $conn->type_id = $formTypeId;
            $conn->sub_type_id = $formMainId;
            $conn->save();

            foreach ([
                'qnid' => $document->qnid,
                'clicode' => $document->qnid,
                'title' => $title,
                'lifnr' => $lifnr,
                'client_system' => $sys,
                'is_system' => '1',
            ] as $tag => $val) {
                $e = new Sys_con_entities();
                $e->conn_id = $conn->id;
                $e->entity_tag = $tag;
                $e->entity_value = $val;
                $e->table_tag = 'sys_con_ops';
                $e->save();
            }
            $this->command->line("+ system client created: {$lifnr} {$title} [{$sys}]");
    }

    public static function isSystemLifnr(string $lifnr): bool
    {
        foreach (self::CLIENTS as $c) {
            if ($c['lifnr'] === $lifnr) return true;
        }
        return false;
    }
}
