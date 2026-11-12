<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Legacy orders only wrote an order-level status when the workflow progressed;
 * "created" was implicit. The importer copies transactions but cannot invent
 * that missing event, so untouched orders end up with an empty status pill.
 *
 * This service derives the correct first status from the order's files:
 *   no files                         -> doc_trans_order_created
 *   any last file rejected           -> doc_trans_order_files_rejected
 *   all last files accepted          -> doc_trans_order_ready_for_shipment
 *   files exist, not all accepted    -> doc_trans_order_transfer_sent
 *
 * It only touches orders that have no op-trans-op-doc-order transaction at all,
 * so it is safe to re-run and never overwrites live workflow history.
 */
class OrderStatusBackfillService
{
    public const ORDER_STATUS_GROUP = 'op-trans-op-doc-order';

    private const STATUS = [
        'created'  => 'doc_trans_order_created',
        'transfer' => 'doc_trans_order_transfer_sent',
        'ready'    => 'doc_trans_order_ready_for_shipment',
        'rejected' => 'doc_trans_order_files_rejected',
    ];

    public function analyze(int $limit = 0): array
    {
        $orderType = (int) DB::table('sys_options')->where('op_key', 'op-doc-order')->value('id');
        $itemType  = (int) DB::table('sys_options')->where('op_key', 'op-doc-order-item')->value('id');
        if ($orderType <= 0 || $itemType <= 0) {
            return ['counts' => [], 'decisions' => [], 'total' => 0, 'samples' => []];
        }

        $query = DB::table('documents as d')
            ->where('d.type_id', $orderType)
            ->where('d.status', 1)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->whereColumn('t.target_id', 'd.id')
                    ->where('so.group_key', self::ORDER_STATUS_GROUP);
            })
            ->orderBy('d.id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $orders = $query->get(['d.id', 'd.qnid', 'd.created_at', 'd.grp_code']);
        $decisions = [];
        $counts = ['created' => 0, 'ready' => 0, 'transfer' => 0, 'rejected' => 0];

        foreach ($orders->chunk(200) as $orderChunk) {
            $orderIds = $orderChunk->pluck('id')->map(fn ($v) => (int) $v)->all();
            if (empty($orderIds)) {
                continue;
            }

            // Order items belong to the order; item files participate in the
            // same "all files accepted" decision as order-level files.
            $itemOwner = [];
            foreach (DB::table('documents')
                ->whereIn('parent_id', $orderIds)
                ->where('type_id', $itemType)
                ->where('status', 1)
                ->get(['id', 'parent_id']) as $item) {
                $itemOwner[(int) $item->id] = (int) $item->parent_id;
            }

            $hostIds = array_merge($orderIds, array_keys($itemOwner));
            $filesByOrder = [];
            $fileIds = [];
            foreach (DB::table('document_files')
                ->where('relation', 'documents')
                ->where('status', 1)
                ->whereIn('relation_id', $hostIds)
                ->get(['id', 'relation_id']) as $file) {
                $host = (int) $file->relation_id;
                $orderId = $itemOwner[$host] ?? (in_array($host, $orderIds, true) ? $host : null);
                if ($orderId === null) {
                    continue;
                }
                $filesByOrder[$orderId][] = (int) $file->id;
                $fileIds[] = (int) $file->id;
            }

            // Latest file transaction per file (op_id=1).
            $lastFile = [];
            foreach (array_chunk($fileIds, 1000) as $fileChunk) {
                if (empty($fileChunk)) {
                    continue;
                }
                $rows = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->where('t.op_id', 1)
                    ->whereIn('t.target_id', $fileChunk)
                    ->orderByDesc('t.id')
                    ->get(['t.target_id', 'so.op_key', 't.created_at']);
                foreach ($rows as $row) {
                    $fid = (int) $row->target_id;
                    if (! isset($lastFile[$fid])) {
                        $lastFile[$fid] = $row;
                    }
                }
            }

            foreach ($orderChunk as $order) {
                $fids = $filesByOrder[(int) $order->id] ?? [];
                $statusKey = 'created';
                $note = 'Legacy veri: sipariş oluşturuldu (durum kaydı yoktu)';
                $at = $order->created_at;

                if (! empty($fids)) {
                    $hasRejected = false;
                    $allAccepted = true;
                    $latestAt = null;
                    foreach ($fids as $fid) {
                        $last = $lastFile[$fid] ?? null;
                        if (! $last || $last->op_key !== 'doc_file_accepted') {
                            $allAccepted = false;
                        }
                        if ($last && $last->op_key === 'doc_file_rejected') {
                            $hasRejected = true;
                        }
                        if ($last && (! $latestAt || $last->created_at > $latestAt)) {
                            $latestAt = $last->created_at;
                        }
                    }

                    if ($hasRejected) {
                        $statusKey = 'rejected';
                        $note = 'Legacy veri: reddedilen dosyalar mevcut';
                    } elseif ($allAccepted) {
                        $statusKey = 'ready';
                        $note = 'Legacy veri: tüm dosyalar kabul edilmiş';
                    } else {
                        $statusKey = 'transfer';
                        $note = 'Legacy veri: dosyalar kontrol ediliyor';
                    }
                    $at = $latestAt ?: $order->created_at;
                }

                $counts[$statusKey]++;
                $decisions[] = [
                    'order_id'   => (int) $order->id,
                    'qnid'       => $order->qnid,
                    'status'     => $statusKey,
                    'type_key'   => self::STATUS[$statusKey],
                    'note'       => $note,
                    'grp_code'   => $order->grp_code ?: 'GDZ',
                    'at'         => $at,
                ];
            }
        }

        return [
            'counts'    => $counts,
            'decisions' => $decisions,
            'total'     => count($decisions),
            'samples'   => array_slice($decisions, 0, 5),
        ];
    }

    public function apply(array $decisions): int
    {
        if (empty($decisions)) {
            return 0;
        }

        $typeIds = DB::table('sys_options')
            ->whereIn('op_key', array_values(self::STATUS))
            ->pluck('id', 'op_key')
            ->all();

        $applied = 0;
        foreach (array_chunk($decisions, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $typeIds, &$applied) {
                $orderIds = array_map(fn ($d) => (int) $d['order_id'], $chunk);

                // Re-check inside the transaction: never duplicate a status.
                $existing = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->whereIn('t.target_id', $orderIds)
                    ->where('so.group_key', self::ORDER_STATUS_GROUP)
                    ->pluck('t.target_id')
                    ->map(fn ($v) => (int) $v)
                    ->flip()
                    ->all();

                $rows = [];
                $now = now();
                foreach ($chunk as $d) {
                    if (isset($existing[(int) $d['order_id']])) {
                        continue;
                    }
                    $typeId = $typeIds[$d['type_key']] ?? null;
                    if (! $typeId) {
                        continue;
                    }
                    $at = $d['at'] ?: $now;
                    $rows[] = [
                        'op_id'       => 0,
                        'type_id'     => $typeId,
                        'log_id'      => 0,
                        'target_id'   => (int) $d['order_id'],
                        'note'        => mb_substr((string) $d['note'], 0, 300),
                        'description' => mb_substr((string) json_encode([
                            'actor' => 'legacy-import',
                            'note'  => $d['note'],
                        ], JSON_UNESCAPED_UNICODE), 0, 300),
                        'grp_code'    => $d['grp_code'],
                        'created_at'  => $at,
                        'updated_at'  => $at,
                    ];
                }

                if (! empty($rows)) {
                    DB::table('transactions')->insert($rows);
                    $applied += count($rows);
                }
            });
        }

        return $applied;
    }

    public function backfill(): array
    {
        $analysis = $this->analyze();
        $analysis['applied'] = $this->apply($analysis['decisions']);
        return $analysis;
    }
}
