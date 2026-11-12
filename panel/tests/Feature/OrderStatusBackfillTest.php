<?php

namespace Tests\Feature;

use App\Models\Sys_options;
use App\Services\OrderStatusBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStatusBackfillTest extends TestCase
{
    use RefreshDatabase;

    private int $orderType;
    private int $itemType;
    private int $fileType;
    private array $status = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderType = $this->option('op-doc-order', 'op-doc');
        $this->itemType = $this->option('op-doc-order-item', 'op-doc');
        $this->fileType = $this->option('form-file', 'op-con-ops', 'sub_type_id');

        foreach ([
            'doc_trans_order_created',
            'doc_trans_order_transfer_sent',
            'doc_trans_order_ready_for_shipment',
            'doc_trans_order_files_rejected',
        ] as $key) {
            $this->status[$key] = $this->option($key, 'op-trans-op-doc-order');
        }
        foreach (['doc_file_accepted', 'doc_file_rejected', 'doc_file_waiting'] as $key) {
            $this->status[$key] = $this->option($key, 'op-trans-op-doc-file');
        }
    }

    private function option(string $opKey, string $group, string $ctitle = 'type_id'): int
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            [
                'title' => $opKey, 'ttitle' => $opKey, 'ctitle' => $ctitle,
                'group_key' => $group, 'status' => 1,
            ]
        )->id;
    }

    private function order(string $no): int
    {
        return (int) \DB::table('documents')->insertGetId([
            'type_id' => $this->orderType, 'status' => 1, 'title' => '-',
            'person_id' => 'system', 'grp_code' => 'GDZ', 'qnid' => (string) Str::uuid(),
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
    }

    private function item(int $orderId): int
    {
        return (int) \DB::table('documents')->insertGetId([
            'type_id' => $this->itemType, 'status' => 1, 'title' => '-',
            'person_id' => 'system', 'grp_code' => 'GDZ', 'parent_id' => $orderId,
            'qnid' => (string) Str::uuid(),
            'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
    }

    private function file(int $hostId, string $lastStatus): int
    {
        $fileId = (int) \DB::table('document_files')->insertGetId([
            'type_id' => $this->fileType, 'status' => 1, 'relation' => 'documents',
            'relation_id' => $hostId, 'description' => 'test.pdf', 'qnid' => (string) Str::uuid(),
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
        \DB::table('transactions')->insert([
            'op_id' => 1, 'type_id' => $this->status[$lastStatus], 'target_id' => $fileId,
            'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(5),
        ]);
        return $fileId;
    }

    public function test_it_backfills_created_ready_transfer_and_rejected_orders(): void
    {
        $created = $this->order('A');
        $readyOrder = $this->order('B');
        $this->file($this->item($readyOrder), 'doc_file_accepted');
        $transferOrder = $this->order('C');
        $this->file($transferOrder, 'doc_file_waiting');
        $rejectedOrder = $this->order('D');
        $this->file($rejectedOrder, 'doc_file_rejected');

        $service = new OrderStatusBackfillService();
        $analysis = $service->analyze();

        $this->assertSame(4, $analysis['total']);
        $this->assertSame(1, $analysis['counts']['created']);
        $this->assertSame(1, $analysis['counts']['ready']);
        $this->assertSame(1, $analysis['counts']['transfer']);
        $this->assertSame(1, $analysis['counts']['rejected']);

        $this->assertSame(4, $service->apply($analysis['decisions']));

        $this->assertDatabaseHas('transactions', ['target_id' => $created, 'type_id' => $this->status['doc_trans_order_created']]);
        $this->assertDatabaseHas('transactions', ['target_id' => $readyOrder, 'type_id' => $this->status['doc_trans_order_ready_for_shipment']]);
        $this->assertDatabaseHas('transactions', ['target_id' => $transferOrder, 'type_id' => $this->status['doc_trans_order_transfer_sent']]);
        $this->assertDatabaseHas('transactions', ['target_id' => $rejectedOrder, 'type_id' => $this->status['doc_trans_order_files_rejected']]);

        // Idempotent: no order is picked up again.
        $again = $service->analyze();
        $this->assertSame(0, $again['total']);
        $this->assertSame(0, $service->apply($again['decisions']));
    }
}
