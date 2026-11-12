<?php

namespace App\Console\Commands;

use App\Models\Documents;
use App\Models\Sys_options;
use App\Models\Transactions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills missing birth transactions for Planlama documents created before the
 * birth-map/status pipeline existed (or inserted by demo/E2E scripts directly).
 *
 * Only documents with NO transaction in their own status group are touched.
 * Existing approved/rejected history is never rewritten.
 */
class RepairPlanlamaStatuses extends Command
{
    protected $signature = 'planlama:repair-statuses {--dry-run : Report only, do not write}';

    protected $description = 'Add missing birth status transactions for op-doc-request / op-doc-requestf documents';

    public function handle(): int
    {
        $map = [
            'op-doc-request'  => [
                'group' => 'op-trans-op-doc-request',
                'birth' => 'doc_trans_request_created',
            ],
            'op-doc-requestf' => [
                'group' => 'op-trans-op-doc-requestf',
                'birth' => 'tr-request-man-awaiting',
            ],
        ];

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($map as $docKey => $cfg) {
            $typeId = Sys_options::where('op_key', $docKey)->value('id');
            $birthId = Sys_options::where('op_key', $cfg['birth'])->value('id');

            if (! $typeId || ! $birthId) {
                $this->warn("Skip {$docKey}: missing op_key ({$cfg['birth']})");
                continue;
            }

            $docs = Documents::where('type_id', $typeId)->where('status', 1)->get(['id', 'qnid']);
            $missing = [];

            foreach ($docs as $doc) {
                $has = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->where('t.target_id', $doc->id)
                    ->where('so.group_key', $cfg['group'])
                    ->exists();

                if (! $has) $missing[] = $doc;
            }

            $missingCount = count($missing);
            $this->line("{$docKey}: active={$docs->count()} missing={$missingCount}");

            if ($dryRun || empty($missing)) {
                $total += $missingCount;
                continue;
            }

            DB::beginTransaction();
            try {
                foreach ($missing as $doc) {
                    Transactions::create([
                        'op_id' => 0,
                        'type_id' => $birthId,
                        'log_id' => 0,
                        'target_id' => $doc->id,
                        'note' => '-',
                        'description' => 'Planlama birth status backfill',
                    ]);
                }
                DB::commit();
                $total += count($missing);
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Backfill failed for {$docKey}: ".$e->getMessage());
                return self::FAILURE;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Planlama birth statuses repaired: {$total}");

        return self::SUCCESS;
    }
}
