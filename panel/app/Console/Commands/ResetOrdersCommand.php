<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetOrdersCommand extends Command
{
    protected $signature = 'orders:reset
                            {--json=/tmp/sap_fresh_payload.json : Path to SAP JSON payload file}
                            {--keep-clients : Keep existing clients (default)}';

    protected $description = 'Wipe all orders + items + serials + files + sub-data and re-sync fresh SAP examples (one-shot)';

    public function handle(): int
    {
        $jsonPath = $this->option('json');

        if (!file_exists($jsonPath)) {
            $this->error("Payload not found: {$jsonPath}");
            $this->info("Create it first or pass --json=/path/to/payload.json");
            $this->line("Example: cat > /tmp/sap_fresh_payload.json << 'JSON' ...");
            return 1;
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  orders:reset — full wipe + fresh SAP sync");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Payload: {$jsonPath}");

        // ── 1. Stats before ──
        $beforeOrders = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order')->count();
        $beforeItems = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order-item')->count();
        $beforeSerials = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order-serial')->count();
        $beforeFiles = DB::table('document_files')->count();
        $this->line("Before: {$beforeOrders} orders, {$beforeItems} items, {$beforeSerials} serials, {$beforeFiles} files");

        // ── 2. Wipe serials (op-doc-order-serial) ──
        $serialTypeId = DB::table('sys_options')->where('op_key', 'op-doc-order-serial')->value('id');
        if ($serialTypeId) {
            $ids = DB::table('documents')->where('type_id', $serialTypeId)->pluck('id');
            if ($ids->isNotEmpty()) {
                foreach ($ids as $id) {
                    $conns = DB::table('sys_con_ops')->where('main_id', $id)->pluck('id');
                    if ($conns->isNotEmpty()) {
                        DB::table('sys_con_entities')->whereIn('conn_id', $conns)->delete();
                    }
                    DB::table('sys_con_ops')->where('main_id', $id)->delete();
                }
                DB::table('transactions')->whereIn('target_id', $ids)->delete();
                DB::table('documents')->whereIn('id', $ids)->delete();
                $this->info("  Wiped serials: {$ids->count()}");
            } else {
                $this->line("  Serials: 0");
            }
        }

        // ── 3. Wipe files (document_files + file transactions + file entities + storage) ──
        $fileCount = DB::table('document_files')->count();
        if ($fileCount > 0) {
            DB::table('sys_con_entities')->where('table_tag', 'document_files')->delete();
            DB::table('transactions')->where('op_id', 1)->delete();
            DB::table('document_files')->delete();
            $this->info("  Wiped files: {$fileCount}");
        } else {
            $this->line("  Files: 0");
        }

        // Storage: documents + temp
        try {
            Storage::disk('public')->deleteDirectory('documents');
            Storage::disk('public')->deleteDirectory('temp');
            $this->line("  Storage: documents/ + temp/ cleared");
        } catch (\Throwable $e) {
            $this->warn("  Storage clear skipped: ".$e->getMessage());
        }

        // ── 4. Wipe notification reads (order/file related) ──
        $reads = DB::table('notification_reads')->count();
        if ($reads > 0) {
            DB::table('notification_reads')->delete();
            $this->info("  Wiped notification_reads: {$reads}");
        }

        // ── 5. Flush cache (dashboard tedarikStats etc) ──
        Cache::flush();
        $this->line("  Cache: flushed");

        // ── 6. Fresh sync (reuses SyncOrdersCommand --fresh which wipes orders+items) ──
        $this->newLine();
        $this->info("Running orders:sync --fresh ...");
        $this->call('orders:sync', [
            '--json' => $jsonPath,
            '--fresh' => true,
        ]);

        // ── 7. Stats after ──
        $afterOrders = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order')->count();
        $afterItems = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order-item')->count();
        $afterSerials = DB::table('documents')
            ->join('sys_options', 'sys_options.id', '=', 'documents.type_id')
            ->where('sys_options.op_key', 'op-doc-order-serial')->count();
        $afterFiles = DB::table('document_files')->count();
        $this->newLine();
        $this->info("After: {$afterOrders} orders, {$afterItems} items, {$afterSerials} serials, {$afterFiles} files");
        $this->info("Done — fresh SAP examples ready. Hard refresh dashboards.");
        $this->line("Payload used: {$jsonPath} (21 rows → 8 EBELN)");

        return 0;
    }
}
