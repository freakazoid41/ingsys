<?php

namespace Tests\Feature;

use App\Models\Sys_options;
use App\Services\OrderStatusBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillOrderStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $orderType;
    private int $createdType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderType = $this->option('op-doc-order', 'op-doc');
        $this->option('op-doc-order-item', 'op-doc');
        $this->createdType = $this->option('doc_trans_order_created', 'op-trans-op-doc-order');
    }

    private function option(string $opKey, string $group): int
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            ['title' => $opKey, 'ttitle' => $opKey, 'ctitle' => 'type_id', 'group_key' => $group, 'status' => 1]
        )->id;
    }

    private function order(): int
    {
        return (int) \DB::table('documents')->insertGetId([
            'type_id' => $this->orderType, 'status' => 1, 'title' => '-',
            'person_id' => 'system', 'grp_code' => 'GDZ', 'qnid' => (string) Str::uuid(),
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $orderId = $this->order();

        $this->artisan('orders:backfill-status', ['--dry-run' => true])
            ->expectsOutputToContain('Missing order status: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('transactions', ['target_id' => $orderId]);
    }

    public function test_command_backfills_and_is_idempotent(): void
    {
        $orderId = $this->order();

        $this->artisan('orders:backfill-status')->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'target_id' => $orderId,
            'type_id' => $this->createdType,
        ]);

        $before = \DB::table('transactions')->where('target_id', $orderId)->count();

        $this->artisan('orders:backfill-status')->assertSuccessful();

        $after = \DB::table('transactions')->where('target_id', $orderId)->count();
        $this->assertSame($before, $after, 're-running must not duplicate status rows');
    }
}
