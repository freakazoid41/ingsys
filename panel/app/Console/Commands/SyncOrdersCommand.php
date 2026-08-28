<?php

namespace App\Console\Commands;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\UserLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Failed: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Done: {$stats['orders']} orders, {$stats['items']} items, {$stats['clients']} clients created, {$stats['skipped']} skipped");

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
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
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
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
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
        // Check if client with this LIFNR already exists
        $existing = DB::table('sys_con_entities')
            ->where('entity_tag', 'lifnr')
            ->where('entity_value', $lifnr)
            ->where('table_tag', 'sys_con_ops')
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
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
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
