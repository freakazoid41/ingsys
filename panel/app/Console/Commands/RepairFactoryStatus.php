<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrects imported order transactions that came from legacy
 * `status-factory-check-wait`. That state means "files checked, waiting for
 * factory/quality", i.e. the new `doc_trans_order_ready_for_shipment`, but the
 * first import mapped it to `doc_trans_order_transfer_sent`.
 *
 * Match order by qnid, then by order_no (some orders were re-created by a later
 * SAP sync with a fresh qnid), and finally by exact transaction timestamp.
 */
class RepairFactoryStatus extends Command
{
    protected $signature = 'orders:repair-factory-status {--dry-run : Report matches only}';

    protected $description = 'Remap legacy status-factory-check-wait events to Sevke Hazır';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        config(['database.connections.legacy_repair' => [
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
            DB::connection('legacy_repair')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->error('legacy DB unreachable: '.$e->getMessage());
            return self::FAILURE;
        }

        $transferId = DB::table('sys_options')->where('op_key', 'doc_trans_order_transfer_sent')->value('id');
        $readyId = DB::table('sys_options')->where('op_key', 'doc_trans_order_ready_for_shipment')->value('id');
        $orderTypeId = DB::table('sys_options')->where('op_key', 'op-doc-order')->value('id');
        if (! $transferId || ! $readyId || ! $orderTypeId) {
            $this->error('missing status/order options');
            return self::FAILURE;
        }

        $events = DB::connection('legacy_repair')
            ->table('transactions as t')
            ->join('sys_options as so', 'so.id', '=', 't.type_id')
            ->join('documents as d', 'd.id', '=', 't.ref_id')
            ->where('t.target_id', 0)
            ->where('so.op_key', 'status-factory-check-wait')
            ->orderBy('t.id')
            ->get(['d.qnid', 'd.order_no', 't.created_at']);

        $this->info('legacy factory-check-wait events: '.count($events));

        $matched = 0;
        $unresolved = 0;
        $unmatched = 0;
        $samples = [];

        foreach ($events->chunk(300) as $chunk) {
            $qnids = $chunk->pluck('qnid')->filter()->unique()->values()->all();
            $orderNos = $chunk->pluck('order_no')->filter()->unique()->values()->all();

            $newByQnid = empty($qnids) ? [] : DB::table('documents')
                ->whereIn('qnid', $qnids)
                ->pluck('id', 'qnid')
                ->all();

            $newByOrderNo = [];
            if (! empty($orderNos)) {
                $rows = DB::table('documents as d')
                    ->join('sys_con_ops as so', 'so.main_id', '=', 'd.id')
                    ->join('sys_con_entities as e', 'e.conn_id', '=', 'so.id')
                    ->where('d.type_id', $orderTypeId)
                    ->where('e.table_tag', 'sys_con_ops')
                    ->where('e.entity_tag', 'order_no')
                    ->whereIn('e.entity_value', $orderNos)
                    ->get(['e.entity_value as order_no', 'd.id']);
                foreach ($rows as $row) {
                    $newByOrderNo[(string) $row->order_no] = (int) $row->id;
                }
            }

            $targetIds = [];
            $eventRows = [];
            foreach ($chunk as $event) {
                $newId = $newByQnid[$event->qnid] ?? $newByOrderNo[(string) $event->order_no] ?? null;
                if (! $newId) {
                    $unresolved++;
                    continue;
                }
                $targetIds[] = (int) $newId;
                $eventRows[] = ['target_id' => (int) $newId, 'at' => $event->created_at];
            }

            if (empty($targetIds)) {
                continue;
            }

            $rows = DB::table('transactions')
                ->whereIn('target_id', $targetIds)
                ->where('type_id', $transferId)
                ->whereIn('created_at', array_column($eventRows, 'at'))
                ->get(['id', 'target_id', 'created_at']);

            $index = [];
            foreach ($rows as $row) {
                $index[$row->target_id.'|'.$row->created_at] = (int) $row->id;
            }

            $updateIds = [];
            foreach ($eventRows as $event) {
                $key = $event['target_id'].'|'.$event['at'];
                if (isset($index[$key])) {
                    $updateIds[] = $index[$key];
                } else {
                    $unmatched++;
                    if (count($samples) < 5) {
                        $samples[] = $key;
                    }
                }
            }

            $updateIds = array_values(array_unique($updateIds));
            if (! $dry && ! empty($updateIds)) {
                DB::table('transactions')->whereIn('id', $updateIds)->update(['type_id' => $readyId]);
            }
            $matched += count($updateIds);
        }

        $this->info('matched transactions: '.$matched);
        $this->line('unresolved orders: '.$unresolved.' (not present in target DB)');
        if ($unmatched > 0) {
            $this->warn('unmatched transactions: '.$unmatched.' (first: '.$samples[0].')');
        }

        if ($dry) {
            $this->comment('Dry run — nothing written.');
        } else {
            $this->info("Remapped {$matched} transactions to doc_trans_order_ready_for_shipment.");
        }

        return self::SUCCESS;
    }
}
