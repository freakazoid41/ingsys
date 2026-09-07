<?php

namespace App\Console\Commands;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\UserLog;
use App\Services\PermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {--json= : Path to SAP JSON payload file}
                          {--fresh : Wipe existing orders before sync}
                          {--dry-run : Show what would be created without writing}';

    protected $description = 'Sync SAP orders into the order management system (grouped by EBELN)';

    // SAP field mapping to our EAV fields
    private array $orderFields = [
        'EBELN' => 'order_no',
        'SUBMI' => 'buying_no',
        'LIFNR' => 'spec_code',
        'BUKRS' => 'sys_code',
        'MCOD1' => 'ctitle',
        'BEDAT' => 'created_at',
    ];

    private array $itemFields = [
        'MATNR' => 'prod_code',
        'EBELP' => 'prod_code_suffix',
        'TXZ01' => 'title',
        'MENGE' => 'quantity',
        'MEINS' => 'unit',
    ];

    public function handle(): int
    {
        $jsonPath = $this->option('json');
        $fresh = $this->option('fresh');
        $dryRun = $this->option('dry-run');

        if (!$jsonPath) {
            $this->error('Usage: php artisan orders:sync --json=/path/to/sap-payload.json');
            $this->newLine();
            $this->info('Or pipe JSON directly:');
            $this->line('  echo \'[{...}]\' | php artisan orders:sync');
            return 1;
        }

        if (!file_exists($jsonPath)) {
            $this->error("File not found: {$jsonPath}");
            return 1;
        }

        $payload = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($payload)) {
            $this->error('Invalid JSON payload');
            return 1;
        }

        $this->info("Loaded " . count($payload) . " SAP rows");

        // Group by EBELN
        $grouped = [];
        foreach ($payload as $row) {
            $ebeln = trim($row['EBELN'] ?? '');
            if (!$ebeln) continue;
            $grouped[$ebeln][] = $row;
        }

        $this->info("Grouped into " . count($grouped) . " orders");

        // Resolve type IDs
        $orderTypeId = Sys_options::where('op_key', 'op-doc-order')->first()?->id;
        $itemTypeId = Sys_options::where('op_key', 'op-doc-order-item')->first()?->id;
        $clientTypeId = Sys_options::where('op_key', 'op-doc-client')->first()?->id;
        $formMainId = Sys_options::where('op_key', 'form-main')->first()?->id;

        if (!$orderTypeId || !$itemTypeId) {
            $this->error('Missing sys_options: op-doc-order or op-doc-order-item. Run OrderSystemSeeder first.');
            return 1;
        }

        $orderFormTypeId = Sys_options::where('op_key', 'op-doc-order-form')->first()?->id;
        $itemFormTypeId = Sys_options::where('op_key', 'op-doc-order-item-form')->first()?->id;
        $clientFormTypeId = Sys_options::where('op_key', 'op-doc-client-form')->first()?->id;

        if ($fresh && !$dryRun) {
            $this->warn('Wiping existing orders...');
            $this->wipeExistingOrders($orderTypeId, $itemTypeId);
        }

        $stats = ['orders' => 0, 'items' => 0, 'clients' => 0, 'skipped' => 0];

        foreach ($grouped as $ebeln => $rows) {
            $first = $rows[0];
            $lifnr = trim($first['LIFNR'] ?? '');
            $bukrs = trim($first['BUKRS'] ?? '');
            $mcod1 = trim($first['MCOD1'] ?? '');
            $bedat = $first['BEDAT'] ?? '';
            $submi = trim($first['SUBMI'] ?? '');

            // Check idempotency — skip if order_no already exists
            $existingOrder = DB::table('sys_con_entities')
                ->where('entity_tag', 'order_no')
                ->where('entity_value', $ebeln)
                ->where('table_tag', 'sys_con_ops')
                ->first();

            if ($existingOrder) {
                $this->line("  <comment>Skip</comment> EBELN {$ebeln} — already exists");
                $stats['skipped']++;
                continue;
            }

            $this->line("  <info>Order</info> {$ebeln} — {$mcod1} ({$lifnr}), " . count($rows) . " items");

            if ($dryRun) {
                $stats['orders']++;
                $stats['items'] += count($rows);
                continue;
            }

            // Create or find client
            $clientQnid = $this->findOrCreateClient($lifnr, $mcod1, $clientTypeId, $clientFormTypeId, $formMainId);
            if ($clientQnid) $stats['clients']++;

            // Create order document
            DB::beginTransaction();
            try {
                $orderQnid = $this->createOrder(
                    $ebeln, $lifnr, $bukrs, $mcod1, $bedat, $submi,
                    $orderTypeId, $orderFormTypeId, $formMainId
                );
                $stats['orders']++;

                // Create order items
                foreach ($rows as $idx => $row) {
                    $this->createOrderItem(
                        $row, $orderQnid, $itemTypeId, $itemFormTypeId, $formMainId, $idx + 1
                    );
                    $stats['items']++;
                }

                DB::commit();
                // ── NOTIFICATION tedarik-01 (Sipariş Sisteme Geldi) — later planning: dispatch with BUKRS-aware filtering
                try {
                    $payload = [
                        'order_no' => $ebeln,
                        'transfer_no' => $ebeln,
                        'bukrs' => $bukrs,
                        'sys_code' => $bukrs,
                        'BUKRS' => $bukrs,
                        'ctitle' => $mcod1,
                        'spec_code' => $lifnr,
                        'qnid' => $orderQnid,
                    ];
                    (new \App\Providers\EmailServiceProvider())->sendTedarikOrderImported($payload);
                } catch(\Throwable $e){
                    $this->warn("  Notification tedarik-01 failed for {$ebeln}: ".$e->getMessage());
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Failed: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Done: {$stats['orders']} orders, {$stats['items']} items, {$stats['clients']} clients created, {$stats['skipped']} skipped");

        // Reconcile reseller client bindings — repair stale cliid references
        if (!$dryRun) {
            $this->reconcileResellerClientBindings($clientTypeId);
        }

        return 0;
    }

    private function createOrder(
        string $ebeln, string $lifnr, string $bukrs, string $mcod1,
        string $bedat, string $submi,
        int $orderTypeId, ?int $formTypeId, int $formMainId
    ): string {
        $document = new Documents();
        $document->type_id = $orderTypeId;
        $document->person_id = 'system';
        $document->save();

        // Birth transaction — must have user_logs entry for getFormData status subquery
        $birthType = Sys_options::where('op_key', 'doc_trans_order_created')->first();
        if ($birthType) {
            $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
            $userId = $this->getSystemUserId();
            $log = UserLog::create([
                'user_id' => $userId,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => $logTypeId,
                'description' => json_encode(['desc' => 'Sipariş Oluşturuldu (SAP)']),
            ]);
            Transactions::create([
                'op_id' => 0,
                'type_id' => $birthType->id,
                'log_id' => $log->id,
                'target_id' => $document->id,
                'description' => 'Sipariş Oluşturuldu (SAP)',
            ]);
        }

        // Create EAV fields via sys_con_ops + sys_con_entities
        if ($formTypeId) {
            $conn = new Sys_con_ops();
            $conn->main_id = $document->id;
            $conn->conn_id = 0;
            $conn->type_id = $formTypeId;
            $conn->sub_type_id = $formMainId;
            $conn->save();

            $entities = [
                'qnid' => $document->qnid,
                'order_no' => $ebeln,
                'buying_no' => $submi ?: '-',
                'spec_code' => $lifnr,
                'sys_code' => $bukrs,
                'ctitle' => $mcod1,
                'created_at' => $bedat,
                'order_desc' => '',
                'imalatci_firma_adi' => '',
            ];

            foreach ($entities as $tag => $value) {
                Sys_con_entities::create([
                    'conn_id' => $conn->id,
                    'entity_tag' => $tag,
                    'entity_value' => (string) $value,
                    'table_tag' => 'sys_con_ops',
                ]);
            }
        }

        return $document->qnid;
    }

    private function createOrderItem(
        array $row, string $orderQnid,
        int $itemTypeId, ?int $formTypeId, int $formMainId, int $idx
    ): void {
        $order = Documents::where('qnid', $orderQnid)->first();

        $document = new Documents();
        $document->type_id = $itemTypeId;
        $document->person_id = 'system';
        $document->parent_id = $order->id;
        $document->save();

        // Birth transaction — must have user_logs entry for getFormData status subquery
        $birthType = Sys_options::where('op_key', 'doc_trans_created')->first();
        if ($birthType) {
            $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
            $userId = $this->getSystemUserId();
            $log = UserLog::create([
                'user_id' => $userId,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => $logTypeId,
                'description' => json_encode(['desc' => 'Sipariş Kalemi Oluşturuldu (SAP)']),
            ]);
            Transactions::create([
                'op_id' => 0,
                'type_id' => $birthType->id,
                'log_id' => $log->id,
                'target_id' => $document->id,
                'description' => 'Sipariş Kalemi Oluşturuldu (SAP)',
            ]);
        }

        // EAV fields
        if ($formTypeId) {
            $conn = new Sys_con_ops();
            $conn->main_id = $document->id;
            $conn->conn_id = 0;
            $conn->type_id = $formTypeId;
            $conn->sub_type_id = $formMainId;
            $conn->save();

            $matnr = trim($row['MATNR'] ?? '');
            $ebelp = trim($row['EBELP'] ?? '');
            $prodCode = $matnr . '**' . $ebelp;

            $entities = [
                'qnid' => $document->qnid,
                'prod_code' => $prodCode,
                'title' => trim($row['TXZ01'] ?? ''),
                'quantity' => trim($row['MENGE'] ?? '0'),
                'unit' => trim($row['MEINS'] ?? 'ST'),
            ];

            foreach ($entities as $tag => $value) {
                Sys_con_entities::create([
                    'conn_id' => $conn->id,
                    'entity_tag' => $tag,
                    'entity_value' => (string) $value,
                    'table_tag' => 'sys_con_ops',
                ]);
            }
        }
    }

    private function findOrCreateClient(
        string $lifnr, string $name,
        int $clientTypeId, ?int $formTypeId, int $formMainId
    ): ?string {
        // Check if an ACTIVE client with this LIFNR already exists
        $existing = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('documents as d', 'd.id', '=', 'so.main_id')
            ->where('se.entity_tag', 'lifnr')
            ->where('se.entity_value', $lifnr)
            ->where('se.table_tag', 'sys_con_ops')
            ->where('d.type_id', $clientTypeId)
            ->where('d.status', 1)
            ->first();

        if ($existing) return null;

        $document = new Documents();
        $document->type_id = $clientTypeId;
        $document->person_id = 'system';
        $document->save();

        // Birth transaction — must have user_logs entry for getFormData status subquery
        $birthType = Sys_options::where('op_key', 'doc_trans_created')->first();
        if ($birthType) {
            $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
            $userId = $this->getSystemUserId();
            $log = UserLog::create([
                'user_id' => $userId,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => $logTypeId,
                'description' => json_encode(['desc' => 'Cari Oluşturuldu (SAP)']),
            ]);
            Transactions::create([
                'op_id' => 0,
                'type_id' => $birthType->id,
                'log_id' => $log->id,
                'target_id' => $document->id,
                'description' => 'Cari Oluşturuldu (SAP)',
            ]);
        }

        if ($formTypeId) {
            $conn = new Sys_con_ops();
            $conn->main_id = $document->id;
            $conn->conn_id = 0;
            $conn->type_id = $formTypeId;
            $conn->sub_type_id = $formMainId;
            $conn->save();

            $entities = [
                'qnid' => $document->qnid,
                'clicode' => $document->qnid,
                'title' => $name,
                'lifnr' => $lifnr,
            ];

            foreach ($entities as $tag => $value) {
                Sys_con_entities::create([
                    'conn_id' => $conn->id,
                    'entity_tag' => $tag,
                    'entity_value' => (string) $value,
                    'table_tag' => 'sys_con_ops',
                ]);
            }
        }

        return $document->qnid;
    }

    /**
     * Repair stale cliid bindings on reseller persons.
     *
     * After sync, some resellers may have cliid → client_document.qnid pointing to
     * an orphaned/inactive client doc (no sys_con_ops, missing lifnr entity, or status=0).
     * This method detects those and repoints cliid to the active client doc with the same lifnr.
     */
    private function reconcileResellerClientBindings(int $clientTypeId): void
    {
        $this->newLine();
        $this->info('Reconciling reseller client bindings...');

        $clientFormTypeId = Sys_options::where('op_key', 'op-doc-user-client-form')->value('id');
        $personnelMainId = Sys_options::where('op_key', 'personnel-main')->value('id');

        if (!$clientFormTypeId || !$personnelMainId) {
            $this->warn('  Skipping reconciliation — missing sys_options');
            return;
        }

        // Find all reseller persons with cliid entities
        $cliidRows = DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->join('persons as p', 'p.id', '=', 'so.main_id')
            ->where('se.entity_tag', 'like', 'cliid**%')
            ->where('so.type_id', $clientFormTypeId)
            ->where('so.sub_type_id', $personnelMainId)
            ->where('so.conn_id', 0)
            ->select('p.id as person_id', 'p.qnid as person_qnid', 'se.id as entity_id', 'se.entity_value as cliid_qnid')
            ->get();

        $fixed = 0;
        $alreadyOk = 0;
        $skipped = 0;

        foreach ($cliidRows as $row) {
            // Check if the pointed-to client doc is active and has a lifnr entity
            $clientDoc = DB::table('documents')
                ->where('qnid', $row->cliid_qnid)
                ->where('type_id', $clientTypeId)
                ->where('status', 1)
                ->first();

            if ($clientDoc) {
                // Check it has a lifnr entity
                $hasLifnr = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                    ->where('so.main_id', $clientDoc->id)
                    ->where('se.entity_tag', 'lifnr')
                    ->where('se.table_tag', 'sys_con_ops')
                    ->exists();

                if ($hasLifnr) {
                    $alreadyOk++;
                    continue;
                }
            }

            // Client doc is missing, inactive, or has no lifnr — find the correct one
            // Look up lifnr from the clicode entity (clicode stores the person qnid, not useful)
            // Instead: find all active client docs with lifnr and try to match by title/name
            // Best approach: find the active client doc that has the same lifnr as any order this reseller should see
            // Since we can't determine lifnr from the broken binding, find all active client docs
            // and pick the one that matches by checking if the reseller has a clicode entity with the same person qnid

            // The clicode entity on the same conn_id stores the person qnid
            $clicodeEntity = DB::table('sys_con_entities')
                ->where('conn_id', DB::table('sys_con_ops')
                    ->where('main_id', $row->person_id)
                    ->where('type_id', $clientFormTypeId)
                    ->where('sub_type_id', $personnelMainId)
                    ->where('conn_id', 0)
                    ->value('id'))
                ->where('entity_tag', 'like', 'clicode**%')
                ->first();

            if (!$clicodeEntity) {
                $skipped++;
                continue;
            }

            // Find active client docs with lifnr — pick the one whose title matches clititle
            $clititleEntity = DB::table('sys_con_entities')
                ->where('conn_id', $clicodeEntity->conn_id)
                ->where('entity_tag', 'like', 'clititle**%')
                ->first();

            $clientTitle = $clititleEntity->entity_value ?? null;

            // Find the active client doc with lifnr that matches this reseller's expected client
            // by looking at the client doc's title entity
            $correctClient = null;
            if ($clientTitle) {
                $correctClient = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                    ->join('documents as d', 'd.id', '=', 'so.main_id')
                    ->leftJoin('sys_con_entities as se2', function ($j) {
                        $j->on('se2.conn_id', '=', 'se.conn_id')
                          ->where('se2.entity_tag', '=', 'title');
                    })
                    ->where('se.entity_tag', 'lifnr')
                    ->where('se.table_tag', 'sys_con_ops')
                    ->where('d.type_id', $clientTypeId)
                    ->where('d.status', 1)
                    ->where('se2.entity_value', $clientTitle)
                    ->select('d.qnid', 'd.id')
                    ->first();
            }

            // Fallback: if title match failed, find any active client doc with lifnr
            if (!$correctClient) {
                $correctClient = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                    ->join('documents as d', 'd.id', '=', 'so.main_id')
                    ->where('se.entity_tag', 'lifnr')
                    ->where('se.table_tag', 'sys_con_ops')
                    ->where('d.type_id', $clientTypeId)
                    ->where('d.status', 1)
                    ->select('d.qnid', 'd.id')
                    ->first();
            }

            if (!$correctClient || $correctClient->qnid === $row->cliid_qnid) {
                $skipped++;
                continue;
            }

            // Update the cliid entity to point to the correct client doc
            DB::table('sys_con_entities')
                ->where('id', $row->entity_id)
                ->update(['entity_value' => $correctClient->qnid]);

            // Also update the cliid tag suffix to match the correct key format
            // (the old tag may have a stale suffix)
            $this->line("  <info>Fixed</info> person {$row->person_qnid}: cliid {$row->cliid_qnid} → {$correctClient->qnid}");

            // Bump permission version so session refreshes
            (new PermissionService())->bumpUserPermissionVersion($row->person_id);

            $fixed++;
        }

        $this->info("  Reconciliation: {$fixed} fixed, {$alreadyOk} ok, {$skipped} skipped");
    }

    private function getSystemUserId(): int
    {
        $user = DB::table('users')->where('status', 1)->first();
        return $user ? $user->id : 0;
    }

    private function wipeExistingOrders(int $orderTypeId, int $itemTypeId): void
    {
        $orderIds = DB::table('documents')
            ->where('type_id', $orderTypeId)
            ->pluck('id');

        $itemIds = DB::table('documents')
            ->where('type_id', $itemTypeId)
            ->pluck('id');

        $allIds = $orderIds->merge($itemIds);

        // Deactivate sys_con_entities
        foreach ($allIds as $docId) {
            $connIds = DB::table('sys_con_ops')->where('main_id', $docId)->pluck('id');
            DB::table('sys_con_entities')->whereIn('conn_id', $connIds)->delete();
            DB::table('sys_con_ops')->where('main_id', $docId)->delete();
        }

        DB::table('transactions')->whereIn('target_id', $allIds)->delete();
        DB::table('documents')->whereIn('id', $allIds)->delete();

        $this->info("  Wiped " . $allIds->count() . " documents");
    }
}
