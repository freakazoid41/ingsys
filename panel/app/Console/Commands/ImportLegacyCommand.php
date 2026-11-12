<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import legacy Malzeme Tedarik (Laravel 9, `livetdedarik_src`) into INGSYS EAV.
 *
 * Source: 10,591 documents / 218,589 details (44,042 lines + 174,547 serials)
 *         / 37,472 files / 115,098 transactions / 92,901 user_logs / 261 users.
 * Target: documents + sys_con_ops/entities (op-doc-order/item/serial/client),
 *         document_files (+ version chain), transactions, users/persons, user_logs.
 *
 * Status map (source -> target):
 *   status-wait -> doc_trans_order_created | status-file-check -> transfer_sent
 *   status-factory-check-wait -> ready_for_shipment | status-transfer-ended -> approved
 *   status-file-rejected -> files_rejected
 *   file-check-wait -> doc_file_waiting | file-check-okey -> accepted
 *   file-check-failed -> rejected | file-check-refreshed -> refreshed
 *   file-inspect-waiting -> transfer_sent | file-inspect-failed -> rejected
 *   file-inspect-success -> approved | file-inspect-waiting-transfer* -> transfer_sent
 * File slots: op-accform -> transfer_kabul | op-accmetform -> transfer_cins
 *   op-factory-form SKIPPED (factory system comes later)
 *   op-testdoc -> item_test | op-imgdoc -> item_images (multi)
 * Serials with srl_code '-' are skipped (20k placeholders).
 * prod_code has no EBELP in source -> synthesized per line (00010, 00020, ...).
 * File bytes are NOT in the dump (DB pointers only) - disk clone happens at go-live.
 */
class ImportLegacyCommand extends Command
{
    protected $signature = 'orders:import-legacy
                            {--dry-run : Count only, write nothing}
                            {--fresh : Wipe target orders/items/serials/files + non-system clients first}
                            {--chunk=1000 : Batch size}
                            {--only= : Comma list of phases to run (clients,orders,details,files,transactions,users,logs)}';

    protected $description = 'Import legacy livetdedarik DB (livetdedarik_src) into INGSYS EAV';

    private array $orderStatusMap = [
        'status-wait' => 'doc_trans_order_created',
        'status-file-check' => 'doc_trans_order_transfer_sent',
        'status-factory-check-wait' => 'doc_trans_order_ready_for_shipment',
        'status-transfer-ended' => 'doc_trans_order_approved',
        'status-file-rejected' => 'doc_trans_order_files_rejected',
    ];

    private array $fileStatusMap = [
        'file-check-wait' => 'doc_file_waiting',
        'file-check-okey' => 'doc_file_accepted',
        'file-check-failed' => 'doc_file_rejected',
        'file-check-refreshed' => 'doc_file_refreshed',
    ];

    private array $itemStatusMap = [
        'file-inspect-waiting' => 'doc_trans_order_transfer_sent',
        'file-inspect-waiting-transfer' => 'doc_trans_order_transfer_sent',
        'file-inspect-waiting-transfer-accepting-forms' => 'doc_trans_order_transfer_sent',
        'file-inspect-failed' => 'doc_trans_order_rejected',
        'file-inspect-success' => 'doc_trans_order_approved',
    ];

    private array $logMap = [
        'log-login' => 'log-login',
        'log-logout' => 'log-logout',
        'log-post' => 'log-tender-update',
        'log-put' => 'log-tender-update',
        'log-delete' => 'log-tender-update',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');
        $chunk = max(100, (int) $this->option('chunk'));
        $only = array_filter(array_map('trim', explode(',', (string)$this->option('only'))));
        $run = fn($phase) => empty($only) || in_array($phase, $only);

        config(['database.connections.legacy' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5431'),
            'database' => 'livetdedarik_src',
            'username' => env('DB_USERNAME', 'tedarikNewApp'),
            'password' => env('DB_PASSWORD', 'tedarikNewApp'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]]);
        try {
            DB::connection('legacy')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('legacy DB unreachable: '.$e->getMessage());
            return 1;
        }
        $L = fn($q) => DB::connection('legacy');

        // Resolve target dictionary
        $opt = fn($k) => DB::table('sys_options')->where('op_key', $k)->value('id');
        $ids = [];
        foreach (['op-doc-order','op-doc-order-item','op-doc-order-serial','op-doc-client',
                  'op-doc-order-form','op-doc-order-item-form','op-doc-order-serial-form','op-doc-client-form',
                  'op-doc-user-client-form','op-doc-user-permission-form','op-doc-user-notification-form',
                  'form-main','form-file','doc_trans_created','log-order-update',
                  'op-pert-admin','op-pert-reseller','op-transfer_kabul_file','op-transfer_cins_file',
                  'op-item_test_file','op-item_images_file'] as $k) {
            $ct = in_array($k, ['form-main','form-file']) ? 'sub_type_id' : 'type_id';
            $ids[$k] = DB::table('sys_options')->where('op_key', $k)->where('ctitle', $ct)->value('id')
                ?? DB::table('sys_options')->where('op_key', $k)->value('id');
        }
        $missing = array_keys(array_filter($ids, fn($v) => !$v));
        if ($missing) { $this->error('missing sys_options: '.implode(',', $missing)); return 1; }

        $statusIds = [];
        foreach (array_unique(array_merge(array_values($this->orderStatusMap), array_values($this->fileStatusMap), array_values($this->itemStatusMap), ['doc_trans_created'])) as $k) {
            $statusIds[$k] = $opt($k);
        }
        if (in_array(null, $statusIds, true)) { $this->error('missing status key'); return 1; }

        $rolePerms = [];
        foreach (['immutable-admin','immutable-satinalma-keyuser','immutable-reseller'] as $rk) {
            // keyuser op_key may differ; resolve by name pattern
            $rolePerms[$rk] = DB::table('sys_role_templates')->where('op_key', $rk)->value('permissions');
        }
        // satınalma keyuser actual op_key
        $keyuserKey = DB::table('sys_role_templates')->where('op_key', 'like', '%keyuser%')->value('op_key');
        $this->line("keyuser role: {$keyuserKey}");

        if ($fresh && !$dry) $this->wipeTarget($ids);

        $stats = [];

        // ---- 1. CLIENTS from supplier persons ----
        $suppliers = $run('clients') ? $L('x')->table('persons')->where('type_id', 1)->orderBy('id')->get() : [];
        $this->info('supplier persons: '.($run('clients') ? count($suppliers) : '(skipped)'));
        $stats['clients'] = ($dry || !$run('clients')) ? count($suppliers) : $this->importClients($suppliers, $ids);

        // ---- 2. ORDERS (+ clones second pass) ----
        $orderCount = $run('orders') ? $L('x')->table('documents')->count() : 0;
        $this->info("orders: {$orderCount}");
        $docMap = [];   // legacy doc id -> new id
        $connMap = [];  // new doc id -> form conn id
        if (!$dry && $run('orders')) {
            [$docMap, $connMap, $stats] = $this->importOrders($L('x'), $ids, $chunk, $stats);
        }

        // ---- 3. ITEMS + SERIALS ----
        if (!$dry && $run('details')) $stats = $this->importDetails($L('x'), $ids, $statusIds, $docMap, $connMap, $chunk, $stats);
        elseif ($dry) {
            $stats['items'] = $L('x')->table('document_details')->where('parent_id', 0)->count();
            $stats['serials'] = $L('x')->table('document_details')->where('parent_id', '<>', 0)->where('srl_code', '<>', '-')->count();
        }

        // ---- 4. FILES (+ version chains + EAV links) ----
        if (!$dry && $run('files')) $stats = $this->importFiles($L('x'), $ids, $statusIds, $docMap, $connMap, $chunk, $stats);
        elseif ($dry) $stats['files'] = $L('x')->table('document_files')->count();

        // ---- 5. TRANSACTIONS ----
        if (!$dry && $run('transactions')) $stats = $this->importTransactions($L('x'), $statusIds, $docMap, $chunk, $stats);
        elseif ($dry) $stats['transactions'] = $L('x')->table('transactions')->count();

        // Legacy never wrote an explicit "created" event; synthesize missing
        // order-level statuses now that file history is available.
        if (!$dry && $run('transactions')) {
            $backfill = (new \App\Services\OrderStatusBackfillService())->backfill();
            $stats['status_backfill'] = $backfill['applied'];
            $this->line('status backfill: '.$backfill['applied'].' ('.json_encode($backfill['counts']).')');
        }

        // ---- 6. USERS/PERSONS ----
        if (!$dry && $run('users')) {
            $stats = $this->importUsers($L('x'), $ids, $rolePerms, $keyuserKey, $stats);
            $this->repairKeptResellers($ids);
        }
        elseif ($dry) $stats['users'] = $L('x')->table('users')->count();

        // ---- 7. USER LOGS ----
        if (!$dry && $run('logs')) $stats = $this->importLogs($L('x'), $stats);
        else $stats['logs'] = $L('x')->table('user_logs')->count();

        $this->newLine();
        foreach ($stats as $k => $v) { if (!is_array($v)) $this->line("  {$k}: {$v}"); }
        $this->info($dry ? 'DRY-RUN done (nothing written).' : 'IMPORT done.');
        return 0;
    }

    private function bukrsToSystem(string $b): string
    {
        $b = strtoupper(trim($b));
        if (in_array($b, ['4000','1000','G4000','GDZ'])) return 'GDZ';
        if (in_array($b, ['5000','A5000','ADM'])) return 'ADM';
        if (str_contains($b, '4000') && str_contains($b, '5000')) return 'BOTH';
        if ($b === '' || $b === '-') return 'GDZ';
        return $b;
    }

    private function wipeTarget(array $ids): void
    {
        $this->warn('wiping target orders/items/serials + non-system clients...');
        $clientType = $ids['op-doc-client'];
        // non-system clients
        $sysConns = DB::table('sys_con_ops')->where('type_id', $ids['op-doc-client-form'])->where('conn_id', 0)->pluck('id', 'main_id');
        $wipeDocs = DB::table('documents')->whereIn('type_id', [$ids['op-doc-order'],$ids['op-doc-order-item'],$ids['op-doc-order-serial']])->pluck('id')->all();
        foreach ($sysConns as $docId => $connId) {
            $isSys = DB::table('sys_con_entities')->where('conn_id', $connId)->where('entity_tag', 'is_system')->where('entity_value', '1')->exists();
            if (!$isSys) $wipeDocs[] = $docId;
        }
        $wipeDocs = array_values(array_unique($wipeDocs));
        $this->line('docs to wipe: '.count($wipeDocs));
        if (!$wipeDocs) return;
        $chunks = array_chunk($wipeDocs, 2000);
        foreach ($chunks as $ch) {
            $ops = DB::table('sys_con_ops')->whereIn('main_id', $ch)->pluck('id');
            DB::table('sys_con_entities')->whereIn('conn_id', $ops)->delete();
            DB::table('sys_con_ops')->whereIn('main_id', $ch)->delete();
            $fileIds = DB::table('document_files')->whereIn('relation_id', $ch)->pluck('id');
            if (count($fileIds)) {
                DB::table('transactions')->where('op_id', 1)->whereIn('target_id', $fileIds)->delete();
            }
            DB::table('document_files')->whereIn('relation_id', $ch)->delete();
            DB::table('transactions')->where('op_id', 0)->whereIn('target_id', $ch)->delete();
            DB::table('user_logs')->where('relation', 'documents')->whereIn('relation_id', $ch)->delete();
            DB::table('documents')->whereIn('id', $ch)->delete();
        }
        // full-fresh extras: transactions + logs + previously imported users/persons
        // (keeps the 3 live accounts; everything else goes back to source)
        DB::table('transactions')->delete();
        DB::table('user_logs')->delete();
        $kept = ['kadir@kontent.com.tr','kbbozat41@hotmail.com','picklecanme@gmail.com'];
        $dropUsers = DB::table('users')->whereNotIn('email', $kept)->pluck('id');
        $dropPersons = DB::table('users')->whereNotIn('email', $kept)->pluck('person_id')->unique()->filter()->all();
        if (count($dropPersons)) {
            $pops = DB::table('sys_con_ops')->whereIn('main_id', $dropPersons)->pluck('id');
            DB::table('sys_con_entities')->whereIn('conn_id', $pops)->delete();
            DB::table('sys_con_ops')->whereIn('main_id', $dropPersons)->delete();
            DB::table('persons')->whereIn('id', $dropPersons)->delete();
        }
        if (count($dropUsers)) DB::table('users')->whereIn('id', $dropUsers)->delete();
        // reseed sequences so inserts don't collide with wiped max ids
        foreach (['documents','sys_con_ops','sys_con_entities','document_files','transactions','user_logs','persons','users'] as $t) {
            try { DB::statement("SELECT setval(pg_get_serial_sequence('{$t}','id'), (SELECT COALESCE(MAX(id),0)+1 FROM {$t}), false)"); } catch (\Throwable $e) {}
        }
        $this->info('wipe done.');
    }

    private function importClients($suppliers, array $ids): int
    {
        $n = 0;
        $now = now();
        foreach ($suppliers as $p) {
            $lifnr = trim($p->spec_code ?? '');
            if ($lifnr === '' || $lifnr === '-') continue;
            $sys = $this->bukrsToSystem((string)($p->sys_code ?? '4000'));
            // composite lifnr+system exists?
            $exists = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so','so.id','=','se.conn_id')
                ->join('documents as d','d.id','=','so.main_id')
                ->where('se.entity_tag','lifnr')->where('se.entity_value',$lifnr)
                ->where('se.table_tag','sys_con_ops')
                ->where('d.type_id',$ids['op-doc-client'])->where('d.status',1)
                ->whereExists(function($q) use ($sys) {
                    $q->from('sys_con_entities as se2')
                      ->join('sys_con_ops as so2','so2.id','=','se2.conn_id')
                      ->whereColumn('so2.main_id','d.id')
                      ->where('se2.entity_tag','client_system')->where('se2.entity_value',$sys);
                })->exists();
            if ($exists) continue;
            $qnid = (string) Str::uuid();
            $docId = DB::table('documents')->insertGetId([
                'type_id' => $ids['op-doc-client'], 'person_id' => 'system',
                'title' => substr($p->title ?? $lifnr, 0, 150), 'grp_code' => $sys,
                'qnid' => $qnid, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $conn = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $docId, 'conn_id' => 0, 'type_id' => $ids['op-doc-client-form'],
                'sub_type_id' => $ids['form-main'], 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $rows = [];
            foreach (['qnid'=>$qnid,'clicode'=>$qnid,'title'=>substr($p->title ?? $lifnr,0,50),
                      'lifnr'=>$lifnr,'client_system'=>$sys] as $tag=>$val) {
                $rows[] = ['conn_id'=>$conn,'entity_tag'=>$tag,'entity_value'=>(string)$val,
                           'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now];
            }
            DB::table('sys_con_entities')->insert($rows);
            $n++;
        }
        $this->info("clients created: {$n}");
        return $n;
    }

    private function importOrders($L, array $ids, int $chunk, array $stats): array
    {
        $docMap = []; $connMap = [];
        $orderType = DB::table('sys_options')->where('op_key','op-doc-order')->value('id');
        // bases whose EBELN has clones -> partial mode
        $cloneBases = [];
        foreach ($L->table('documents')->select('order_no')->where('transfer_id','<>',0)->cursor() as $r) {
            $base = preg_replace('/-\d+$/', '', $r->order_no);
            $cloneBases[$base] = true;
        }
        $now = now();
        $count = 0;
        // pass 1: mains (transfer_id=0), pass 2: clones
        foreach ([0, 1] as $pass) {
            $q = $L->table('documents')->orderBy('id');
            $pass === 0 ? $q->where('transfer_id', 0) : $q->where('transfer_id', '<>', 0);
            foreach ($q->cursor() as $d) {
                $sys = $this->bukrsToSystem((string)$d->sys_code);
                $data = [
                    'type_id' => $orderType, 'person_id' => 'system',
                    'title' => '-', 'grp_code' => $sys, 'qnid' => $d->qnid ?: (string) Str::uuid(),
                    'status' => 1, 'created_at' => $d->created_at ?? $now, 'updated_at' => $d->updated_at ?? $now,
                ];
                $isClone = $pass === 1;
                if ($isClone) {
                    $base = preg_replace('/-\d+$/', '', $d->order_no);
                    $baseLegacy = $L->table('documents')->where('order_no', $base)->where('transfer_id', 0)->value('id');
                    $data['parent_id'] = $baseLegacy && isset($docMap[$baseLegacy]) ? $docMap[$baseLegacy] : 0;
                }
                $newId = DB::table('documents')->insertGetId($data);
                $docMap[$d->id] = $newId;
                $conn = DB::table('sys_con_ops')->insertGetId([
                    'main_id' => $newId, 'conn_id' => 0, 'type_id' => $ids['op-doc-order-form'],
                    'sub_type_id' => $ids['form-main'], 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $connMap[$newId] = $conn;
                $mode = ($isClone || isset($cloneBases[$d->order_no])) ? 'partial' : 'at_once';
                $bedat = $d->created_at ? date('d/m/Y', strtotime($d->created_at)) : '';
                $ents = [
                    'qnid' => $data['qnid'], 'order_no' => $d->order_no, 'transfer_no' => $d->order_no,
                    'buying_no' => $d->buying_no ?: '-', 'spec_code' => $d->spec_code,
                    'sys_code' => (string)$d->sys_code, 'ctitle' => $d->ctitle,
                    'created_at' => $bedat, 'order_desc' => substr($d->form_desc ?? '', 0, 2000),
                    'imalatci_firma_adi' => '', 'transfer_mode' => $mode,
                ];
                $rows = [];
                foreach ($ents as $tag => $val) {
                    $rows[] = ['conn_id'=>$conn,'entity_tag'=>$tag,'entity_value'=>(string)$val,
                               'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now];
                }
                DB::table('sys_con_entities')->insert($rows);
                // Legacy "created" was implicit (no order-level transaction).
                // After the transactions phase, OrderStatusBackfillService derives
                // the missing status from files for every order that has no event.
                $count++;
            }
        }
        $this->info("orders imported: {$count}");
        $stats['orders'] = $count;
        return [$docMap, $connMap, $stats];
    }

    private function importDetails($L, array $ids, array $statusIds, array $docMap, array $connMap, int $chunk, array $stats): array
    {
        $itemType = DB::table('sys_options')->where('op_key','op-doc-order-item')->value('id');
        $serialType = DB::table('sys_options')->where('op_key','op-doc-order-serial')->value('id');
        $now = now();
        $itemMap = [];   // legacy detail id -> new doc id
        $itemConn = [];  // new doc id -> conn id
        $parentUnit = []; // legacy detail id -> unit (for serial fallback)
        $nItems = 0;
        // items grouped per order for EBELP synthesis
        $orders = array_keys($docMap);
        foreach (array_chunk($orders, 200) as $ch) {
            $lines = $L->table('document_details')->whereIn('document_id', $ch)->where('parent_id', 0)->orderBy('id')->get();
            $byOrder = [];
            foreach ($lines as $ln) $byOrder[$ln->document_id][] = $ln;
            foreach ($byOrder as $legOrderId => $lns) {
                if (!isset($docMap[$legOrderId])) continue;
                $newOrder = $docMap[$legOrderId];
                foreach ($lns as $idx => $ln) {
                    $ebelp = str_pad((string)(($idx+1)*10), 5, '0', STR_PAD_LEFT);
                    // newer SAP rows already carry MATNR**EBELP — only synthesize when bare
                    $prodCode = str_contains($ln->prod_code ?? '', '**') ? $ln->prod_code : $ln->prod_code.'**'.$ebelp;
                    $newId = DB::table('documents')->insertGetId([
                        'type_id' => $itemType, 'person_id' => 'system', 'parent_id' => $newOrder,
                        'title' => '-', 'grp_code' => DB::table('documents')->where('id',$newOrder)->value('grp_code') ?? 'GDZ',
                        'qnid' => $ln->qnid ?: (string) Str::uuid(), 'status' => 1,
                        'created_at' => $ln->created_at ?? $now, 'updated_at' => $ln->updated_at ?? $now,
                    ]);
                    $itemMap[$ln->id] = $newId;
                    $parentUnit[$ln->id] = $ln->unit;
                    $conn = DB::table('sys_con_ops')->insertGetId([
                        'main_id' => $newId, 'conn_id' => 0, 'type_id' => $ids['op-doc-order-item-form'],
                        'sub_type_id' => $ids['form-main'], 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $itemConn[$newId] = $conn;
                    $connMap[$newId] = $conn;
                    DB::table('sys_con_entities')->insert([
                        ['conn_id'=>$conn,'entity_tag'=>'qnid','entity_value'=>$ln->qnid,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                        ['conn_id'=>$conn,'entity_tag'=>'prod_code','entity_value'=>$ln->prod_code.'**'.$ebelp,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                        ['conn_id'=>$conn,'entity_tag'=>'title','entity_value'=>substr($ln->title ?? '',0,50),'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                        ['conn_id'=>$conn,'entity_tag'=>'quantity','entity_value'=>(string)$ln->quantity,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                        ['conn_id'=>$conn,'entity_tag'=>'unit','entity_value'=>$ln->unit,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                    ]);
                    if ((float)$ln->main_quantity !== (float)$ln->quantity) {
                        DB::table('sys_con_entities')->insert([
                            'conn_id'=>$conn,'entity_tag'=>'original_quantity','entity_value'=>(string)$ln->main_quantity,
                            'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now,
                        ]);
                    }
                    $nItems++;
                }
            }
        }
        $this->info("items: {$nItems}");
        $stats['items'] = $nItems;
        // serials (skip '-' placeholders)
        $nSerials = 0;
        $itemIds = array_keys($itemMap);
        foreach (array_chunk($itemIds, 500) as $ch) {
            $serials = $L->table('document_details')->whereIn('parent_id', $ch)->where('srl_code', '<>', '-')->orderBy('id')->get();
            foreach ($serials as $s) {
                if (!isset($itemMap[$s->parent_id])) continue;
                $newItem = $itemMap[$s->parent_id];
                $unit = ($s->unit && $s->unit !== 'PRNT') ? $s->unit : ($parentUnit[$s->parent_id] ?? 'ST');
                $prod = $srlDate = null;
                try { $srlDate = $s->srl_date ? date('Y-m-01', strtotime($s->srl_date)) : null; } catch (\Throwable $e) {}
                $newId = DB::table('documents')->insertGetId([
                    'type_id' => $serialType, 'person_id' => 'system', 'parent_id' => $newItem,
                    'title' => '-', 'grp_code' => DB::table('documents')->where('id',$newItem)->value('grp_code') ?? 'GDZ',
                    'qnid' => $s->qnid ?: (string) Str::uuid(), 'status' => 1,
                    'created_at' => $s->created_at ?? $now, 'updated_at' => $s->updated_at ?? $now,
                ]);
                $conn = DB::table('sys_con_ops')->insertGetId([
                    'main_id' => $newId, 'conn_id' => 0, 'type_id' => $ids['op-doc-order-serial-form'],
                    'sub_type_id' => $ids['form-main'], 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('sys_con_entities')->insert([
                    ['conn_id'=>$conn,'entity_tag'=>'serial_no','entity_value'=>$s->srl_code,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                    ['conn_id'=>$conn,'entity_tag'=>'production_date','entity_value'=>$srlDate ?? '','table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                    ['conn_id'=>$conn,'entity_tag'=>'quantity','entity_value'=>(string)$s->quantity,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                    ['conn_id'=>$conn,'entity_tag'=>'unit','entity_value'=>$unit,'table_tag'=>'sys_con_ops','created_at'=>$now,'updated_at'=>$now],
                ]);
                // has_serials flag on parent item
                DB::table('sys_con_entities')->updateOrInsert(
                    ['conn_id' => $itemConn[$newItem], 'entity_tag' => 'has_serials'],
                    ['entity_value' => '1', 'table_tag' => 'sys_con_ops', 'created_at' => $now, 'updated_at' => $now]
                );
                $nSerials++;
            }
        }
        $this->info("serials: {$nSerials}");
        $stats['serials'] = $nSerials;
        // stash maps for later phases via return
        $stats['_itemMap'] = $itemMap;
        $stats['_connMap'] = $connMap + $itemConn;
        return $stats;
    }

    private function importFiles($L, array $ids, array $statusIds, array $docMap, array $connMap, int $chunk, array $stats): array
    {
        $itemMap = $stats['_itemMap'] ?? [];
        if (isset($stats['_connMap'])) $connMap = $stats['_connMap'];
        unset($stats['_itemMap'], $stats['_connMap']);
        $now = now();
        $opOrd = $L->table('sys_options')->where('op_key', 'op-ord-doc')->value('id');
        $opPrd = $L->table('sys_options')->where('op_key', 'op-prd-doc')->value('id');
        $tAcc = $L->table('sys_options')->where('op_key', 'op-accform')->value('id');
        $tAccMet = $L->table('sys_options')->where('op_key', 'op-accmetform')->value('id');
        $tFactory = $L->table('sys_options')->where('op_key', 'op-factory-form')->value('id');
        $tTest = $L->table('sys_options')->where('op_key', 'op-testdoc')->value('id');
        $tImg = $L->table('sys_options')->where('op_key', 'op-imgdoc')->value('id');
        $fileTypeOpt = DB::table('sys_options')->where('op_key', 'form-file')->value('id');

        $slotOf = function ($f) use ($opOrd, $tAcc, $tAccMet, $tFactory, $tTest, $tImg) {
            if ((int)$f->op_id === (int)$opOrd) {
                if ((int)$f->type_id === (int)$tAcc) return ['transfer_kabul_file', 'transfer_kabul'];
                if ((int)$f->type_id === (int)$tAccMet) return ['transfer_cins_file', 'transfer_cins'];
                if ((int)$f->type_id === (int)$tFactory) return null; // factory system later - skip for now
                return null;
            }
            if ((int)$f->type_id === (int)$tTest) return ['item_test_file', 'item_test_docs'];
            if ((int)$f->type_id === (int)$tImg) return ['item_images_file', 'item_images'];
            return null;
        };

        // group by slot to build version chains
        $groups = [];
        foreach ($L->table('document_files')->orderBy('id')->cursor() as $f) {
            $slot = $slotOf($f);
            if (!$slot) continue;
            if (!isset($docMap[$f->order_id])) continue;
            if ((int)$f->op_id !== (int)$opOrd) {
                if (!isset($itemMap[$f->document_id])) continue;
            }
            $scope = ((int)$f->op_id === (int)$opOrd) ? ('o'.$f->order_id) : ('i'.$f->document_id);
            $groups[$scope.'|'.$slot[0]][] = $f;
        }
        $nFiles = 0; $nEnt = 0; $fileMap = [];
        foreach ($groups as $key => $files) {
            [$scope, $field] = explode('|', $key);
            $isMulti = ($field === 'item_images_file');
            $first = $files[0];
            $isOrder = str_starts_with($scope, 'o');
            $newDocId = $isOrder ? $docMap[$first->order_id] : $itemMap[$first->document_id];
            $connId = $connMap[$newDocId] ?? null;
            if (!$connId) continue;
            $group = $field === 'transfer_kabul_file' ? 'transfer_kabul'
                : ($field === 'transfer_cins_file' ? 'transfer_cins'
                : ($field === 'item_test_file' ? 'item_test_docs' : 'item_images'));
            // order files by id: last = current
            $prevNew = 0;
            $total = count($files);
            foreach ($files as $i => $f) {
                $isCurrent = ($i === $total - 1);
                $newFile = DB::table('document_files')->insertGetId([
                    'type_id' => $fileTypeOpt, 'relation' => 'documents', 'relation_id' => $newDocId,
                    'description' => $f->description, 'status' => $isCurrent ? 1 : 0,
                    'replaced_id' => $prevNew, 'qnid' => $f->qnid ?: (string) Str::uuid(),
                    'created_at' => $f->created_at ?? $now, 'updated_at' => $f->updated_at ?? $now,
                ]);
                $fileMap[$f->id] = $newFile;
                $prevNew = $newFile;
                $tag = $isMulti
                    ? "item_images_file**item_images**img-{$newFile}"
                    : "{$field}**{$group}**{$connId}";
                DB::table('sys_con_entities')->insert([
                    'conn_id' => $connId, 'entity_tag' => $tag,
                    'entity_value' => (string)$newFile, 'table_tag' => 'document_files',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $nFiles++; $nEnt++;
            }
        }
        $this->info("files: {$nFiles} (+{$nEnt} entities)");
        $stats['files'] = $nFiles;
        $stats['_fileMap'] = $fileMap;
        $stats['_itemMap'] = $itemMap;
        return $stats;
    }

    private function importTransactions($L, array $statusIds, array $docMap, int $chunk, array $stats): array
    {
        $itemMap = $stats['_itemMap'] ?? [];
        $fileMap = $stats['_fileMap'] ?? [];
        unset($stats['_itemMap'], $stats['_fileMap'], $stats['_connMap']);
        if (empty($docMap) || empty($itemMap) || empty($fileMap)) {
            // rebuild legacy->new maps via qnid (for --only=transactions reruns)
            $this->line('rebuilding id maps via qnid...');
            $docMap = [];
            foreach ($L->table('documents')->select('id','qnid')->cursor() as $r) {
                $nid = DB::table('documents')->where('qnid', $r->qnid)->value('id');
                if ($nid) $docMap[$r->id] = $nid;
            }
            $itemMap = [];
            foreach ($L->table('document_details')->select('id','qnid')->where('parent_id',0)->cursor() as $r) {
                $nid = DB::table('documents')->where('qnid', $r->qnid)->value('id');
                if ($nid) $itemMap[$r->id] = $nid;
            }
            $fileMap = [];
            foreach ($L->table('document_files')->select('id','qnid')->cursor() as $r) {
                $nid = DB::table('document_files')->where('qnid', $r->qnid)->value('id');
                if ($nid) $fileMap[$r->id] = $nid;
            }
            $this->line('maps: docs='.count($docMap).' items='.count($itemMap).' files='.count($fileMap));
        }
        $typeName = [];
        foreach ($L->table('sys_options')->whereIn('op_key', array_merge(array_keys($this->orderStatusMap), array_keys($this->fileStatusMap), array_keys($this->itemStatusMap)))->get() as $o) {
            $typeName[$o->id] = $o->op_key;
        }
        $mapTxn = function ($opKey, $target) use ($statusIds) {
            if ($target == 0) $tk = $this->orderStatusMap[$opKey] ?? null;
            elseif ($target == 2) $tk = $this->fileStatusMap[$opKey] ?? null;
            else $tk = $this->itemStatusMap[$opKey] ?? 'doc_trans_created';
            return $tk ? $statusIds[$tk] : null;
        };
        $n = 0; $skip = 0; $batch = [];
        $flush = function () use (&$batch, &$n) {
            if ($batch) { DB::table('transactions')->insert($batch); $n += count($batch); $batch = []; }
        };
        foreach ($L->table('transactions')->orderBy('id')->cursor() as $t) {
            $opKey = $typeName[$t->type_id] ?? null;
            if (!$opKey) { $skip++; continue; }
            $typeId = $mapTxn($opKey, (int)$t->target_id);
            if (!$typeId) { $skip++; continue; }
            if ((int)$t->target_id === 0) {
                if (!isset($docMap[$t->ref_id])) { $skip++; continue; }
                $ref = $docMap[$t->ref_id]; $op = 0;
            } elseif ((int)$t->target_id === 1) {
                if (!isset($itemMap[$t->ref_id])) { $skip++; continue; }
                $ref = $itemMap[$t->ref_id]; $op = 0;
            } else {
                if (!isset($fileMap[$t->ref_id])) { $skip++; continue; }
                $ref = $fileMap[$t->ref_id]; $op = 1;
            }
            $batch[] = [
                'op_id' => $op, 'type_id' => $typeId, 'log_id' => 0, 'target_id' => $ref,
                'description' => mb_substr((string)($t->description ?? ''), 0, 300),
                'note' => mb_substr((string)($t->note ?? ''), 0, 300),
                'created_at' => $t->created_at ?? now(), 'updated_at' => $t->created_at ?? now(),
            ];
            if (count($batch) >= $chunk) $flush();
        }
        $flush();
        $this->info("transactions: {$n} (skipped {$skip})");
        $stats['transactions'] = $n;
        $stats['_userMap'] = [];
        return $stats;
    }

    private function importUsers($L, array $ids, array $rolePerms, ?string $keyuserKey, array $stats): array
    {
        $now = now();
        $adminPerms = $rolePerms['immutable-admin'] ?? '[]';
        $keyPerms = ($keyuserKey && isset($rolePerms[$keyuserKey])) ? $rolePerms[$keyuserKey] : '[]';
        $resPerms = $rolePerms['immutable-reseller'] ?? '[]';
        if (is_array($adminPerms)) $adminPerms = json_encode($adminPerms);
        if (is_array($keyPerms)) $keyPerms = json_encode($keyPerms);
        if (is_array($resPerms)) $resPerms = json_encode($resPerms);
        $userMap = $stats['_userMap'] ?? [];
        $nU = 0; $nP = 0;
        $permType = $ids['op-doc-user-permission-form'];
        $cliType = $ids['op-doc-user-client-form'];
        $notType = $ids['op-doc-user-notification-form'];
        $stype = DB::table('sys_options')->where(['ctitle'=>'sub_type_id','op_key'=>'personnel-main'])->value('id')
            ?? $ids['form-main'];
        foreach ($L->table('users')->orderBy('id')->cursor() as $u) {
            if (DB::table('users')->where('email', $u->email)->exists()) {
                $userMap[$u->id] = DB::table('users')->where('email', $u->email)->value('id');
                continue;
            }
            $lp = $L->table('persons')->where('id', $u->person_id)->first();
            $perms = $L->table('user_con_ops')->join('sys_options as s','s.id','=','user_con_ops.type_id')
                ->where('user_con_ops.user_id', $u->id)->pluck('s.op_key')->all();
            $isSupplier = $lp && (int)$lp->type_id === 1;
            if (in_array('per-12', $perms)) { $role = 'immutable-admin'; $permJson = $adminPerms; $ptype = $ids['op-pert-admin']; }
            elseif (in_array('per-07', $perms)) { $role = $keyuserKey; $permJson = $keyPerms; $ptype = $ids['op-pert-admin']; }
            else { $role = 'immutable-reseller'; $permJson = $resPerms; $ptype = $ids['op-pert-reseller']; }
            $grp = $lp ? $this->bukrsToSystem((string)($lp->sys_code ?? '4000')) : 'GDZ';
            if ($grp !== 'GDZ' && $grp !== 'ADM') $grp = 'BOTH';
            $personId = DB::table('persons')->insertGetId([
                'type_id' => $ptype, 'parent_id' => 0, 'spec_code' => $lp->spec_code ?? '',
                'title' => substr($lp->title ?? '', 0, 50), 'name' => substr($lp->name ?? '', 0, 50),
                'surname' => substr($lp->surname ?? '', 0, 50), 'phone' => substr($lp->phone ?? '', 0, 50),
                'address' => substr($lp->address ?? '', 0, 250), 'grp_code' => $grp,
                'qnid' => (string) Str::uuid(), 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $newUid = DB::table('users')->insertGetId([
                'name' => trim(($lp->name ?? '').' '.($lp->surname ?? '')) ?: $u->name,
                'email' => $u->email, 'password' => $u->password, 'person_id' => $personId,
                'role' => $role, 'status' => $u->status ? 1 : 0, 'grp_code' => $grp,
                'email_verified_at' => $u->email_verified_at, 'remember_token' => $u->remember_token,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $userMap[$u->id] = $newUid;
            // permission entity
            $op = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $personId, 'conn_id' => 0, 'type_id' => $permType,
                'sub_type_id' => $stype, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('sys_con_entities')->insert([
                'conn_id' => $op, 'entity_tag' => "{$personId}**userpermissiongroup**{$personId}",
                'entity_value' => $permJson, 'table_tag' => 'user_con_ops',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            // supplier -> client binding by lifnr
            if ($role === 'immutable-reseller' && $lp && trim($lp->spec_code ?? '')) {
                $lifnr = trim($lp->spec_code);
                $clients = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as so','so.id','=','se.conn_id')
                    ->join('documents as d','d.id','=','so.main_id')
                    ->where('se.entity_tag','lifnr')->where('se.entity_value',$lifnr)
                    ->where('d.type_id', DB::table('sys_options')->where('op_key','op-doc-client')->value('id'))
                    ->select('d.id','d.qnid')->get();
                if (count($clients)) {
                    $cop = DB::table('sys_con_ops')->insertGetId([
                        'main_id' => $personId, 'conn_id' => 0, 'type_id' => $cliType,
                        'sub_type_id' => $stype, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    foreach ($clients as $c) {
                        $k = "import-{$c->id}";
                        $csys = DB::table('sys_con_entities')->where('conn_id', DB::table('sys_con_ops')->where('main_id',$c->id)->where('conn_id',0)->value('id'))->where('entity_tag','client_system')->value('entity_value') ?? 'GDZ';
                        $ctitle = DB::table('sys_con_entities')->where('entity_tag','title')->whereIn('conn_id', DB::table('sys_con_ops')->where('main_id',$c->id)->pluck('id'))->value('entity_value') ?? '';
                        foreach (['cliid'=>$c->qnid,'clicode'=>$lifnr,'clititle'=>$ctitle,'client_system'=>$csys] as $f=>$v) {
                            DB::table('sys_con_entities')->insert([
                                'conn_id'=>$cop,'entity_tag'=>"{$f}**userclientgroup**{$k}",'entity_value'=>(string)$v,
                                'table_tag'=>'user_con_ops','created_at'=>$now,'updated_at'=>$now,
                            ]);
                        }
                    }
                }
            }
            // per-30 mail receivers -> all 7 tedarik notif groups
            if (in_array('per-30', $perms)) {
                $nop = DB::table('sys_con_ops')->insertGetId([
                    'main_id' => $personId, 'conn_id' => 0, 'type_id' => $notType,
                    'sub_type_id' => $stype, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach (['tedarik-01','tedarik-02','tedarik-03','tedarik-04','tedarik-05','tedarik-06','tedarik-07'] as $nk) {
                    DB::table('sys_con_entities')->insert([
                        'conn_id'=>$nop,'entity_tag'=>"{$personId}**usernotificationgroup**{$personId}-{$nk}",
                        'entity_value'=>$nk,'table_tag'=>'user_con_ops','created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
            $nU++; $nP++;
        }
        $this->info("users: {$nU} (persons {$nP})");
        $stats['users'] = $nU;
        $stats['_userMap'] = $userMap;
        return $stats;
    }

    /**
     * Kept test/supplier accounts (skipped by importUsers on email match) need
     * live bindings to freshly imported clients. Lifnrs are the known test setup:
     * kbbozat -> YILDIZ 0000300186, pickle -> DEMIR CELIK 0000300184.
     */
    private function repairKeptResellers(array $ids): void
    {
        $map = ['kbbozat41@hotmail.com' => '0000009512', 'picklecanme@gmail.com' => '300458'];
        $cliType = DB::table('sys_options')->where('op_key', 'op-doc-user-client-form')->value('id');
        $stype = DB::table('sys_options')->where(['ctitle' => 'sub_type_id', 'op_key' => 'personnel-main'])->value('id') ?? $ids['form-main'];
        $now = now();
        foreach ($map as $email => $lifnr) {
            $u = DB::table('users')->where('email', $email)->first();
            if (!$u) continue;
            $clients = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                ->join('documents as d', 'd.id', '=', 'so.main_id')
                ->where('se.entity_tag', 'lifnr')->where('se.entity_value', $lifnr)
                ->where('d.type_id', DB::table('sys_options')->where('op_key', 'op-doc-client')->value('id'))
                ->where('d.status', 1)->select('d.id', 'd.qnid')->get();
            if (!count($clients)) { $this->warn("no client for {$lifnr}, {$email} left unbound"); continue; }
            // wipe stale bindings for this person (test qnids died in wipe)
            $oldOps = DB::table('sys_con_ops')->where('main_id', $u->person_id)->where('type_id', $cliType)->pluck('id');
            DB::table('sys_con_entities')->whereIn('conn_id', $oldOps)->delete();
            DB::table('sys_con_ops')->whereIn('id', $oldOps)->delete();
            $cop = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $u->person_id, 'conn_id' => 0, 'type_id' => $cliType,
                'sub_type_id' => $stype, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($clients as $c) {
                $k = "kept-{$c->id}";
                $csys = DB::table('sys_con_entities')->where('conn_id', DB::table('sys_con_ops')->where('main_id', $c->id)->where('conn_id', 0)->value('id'))->where('entity_tag', 'client_system')->value('entity_value') ?? 'GDZ';
                $ctitle = DB::table('sys_con_entities')->where('entity_tag', 'title')->whereIn('conn_id', DB::table('sys_con_ops')->where('main_id', $c->id)->pluck('id'))->value('entity_value') ?? '';
                foreach (['cliid' => $c->qnid, 'clicode' => $lifnr, 'clititle' => $ctitle, 'client_system' => $csys] as $f => $v) {
                    DB::table('sys_con_entities')->insert([
                        'conn_id' => $cop, 'entity_tag' => "{$f}**userclientgroup**{$k}", 'entity_value' => (string) $v,
                        'table_tag' => 'user_con_ops', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            // ensure tedarik perms present on kept accounts
            $permType = $ids['op-doc-user-permission-form'];
            $tag = "{$u->person_id}**userpermissiongroup**{$u->person_id}";
            $cur = DB::table('sys_con_entities')->where('entity_tag', $tag)->value('entity_value');
            $perms = json_decode($cur ?? '[]', true) ?: [];
            foreach (['per-041-02','per-05','per-05-01','per-05-02','per-06','per-06-01','per-06-02','per-07-01'] as $p) {
                if (!in_array($p, $perms, true)) $perms[] = $p;
            }
            $op = DB::table('sys_con_ops')->where('main_id', $u->person_id)->where('type_id', $permType)->value('id');
            if (!$op) {
                $op = DB::table('sys_con_ops')->insertGetId(['main_id' => $u->person_id, 'conn_id' => 0, 'type_id' => $permType, 'sub_type_id' => $stype, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('sys_con_entities')->updateOrInsert(['conn_id' => $op, 'entity_tag' => $tag],
                ['entity_value' => json_encode(array_values(array_unique($perms))), 'table_tag' => 'user_con_ops', 'created_at' => $now, 'updated_at' => $now]);
            try { app()->make(\App\Services\PermissionService::class)->bumpUserPermissionVersion($u->person_id); } catch (\Throwable $e) {}
            $this->info("rebound {$email} -> {$lifnr} (" . count($clients) . " systems)");
        }
    }

    private function importLogs($L, array $stats): array
    {
        $userMap = $stats['_userMap'] ?? [];
        unset($stats['_userMap']);
        if (empty($userMap)) {
            // rebuild legacy->new user map by email (for --only=logs reruns)
            foreach ($L->table('users')->select('id', 'email')->cursor() as $lu) {
                $nid = DB::table('users')->where('email', $lu->email)->value('id');
                if ($nid) $userMap[$lu->id] = $nid;
            }
            $this->line('rebuilt userMap: '.count($userMap));
        }
        $logType = [];
        foreach (DB::table('sys_options')->where('group_key', 'op-logs')->get() as $o) $logType[$o->op_key] = $o->id;
        $srcName = [];
        foreach ($L->table('sys_options')->where('op_key', 'like', 'log-%')->get() as $o) $srcName[$o->id] = $o->op_key;
        $n = 0; $skip = 0; $batch = [];
        $now = now();
        foreach ($L->table('user_logs')->orderBy('id')->cursor() as $g) {
            $sk = $srcName[$g->log_id] ?? null;
            $tk = $sk ? ($this->logMap[$sk] ?? null) : null;
            if (!$tk || !isset($logType[$tk])) { $skip++; continue; }
            $uid = $userMap[$g->user_id] ?? null;
            if (!$uid) { $skip++; continue; }
            $desc = $g->description ?? '{}';
            $rel = 'documents'; $relId = 0;
            $jd = json_decode($desc, true);
            if (is_array($jd) && isset($jd['model'])) {
                $m = strtolower($jd['model']);
                $rel = in_array($m, ['persons','users','user_con_ops']) ? 'persons' : 'documents';
            }
            $batch[] = [
                'user_id' => $uid, 'sys_code' => 'GDZ', 'relation' => $rel, 'relation_id' => $relId,
                'type_id' => $logType[$tk], 'description' => $desc,
                'ip' => '127.0.0.1',
                'created_at' => $g->created_at ?? $now, 'updated_at' => $g->updated_at ?? $now,
            ];
            if (count($batch) >= 1000) { DB::table('user_logs')->insert($batch); $n += count($batch); $batch = []; }
        }
        if ($batch) { DB::table('user_logs')->insert($batch); $n += count($batch); }
        $this->info("user_logs: {$n} (skipped {$skip})");
        $stats['logs'] = $n;
        return $stats;
    }
}
