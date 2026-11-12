<?php

namespace App\Console\Commands;

use App\Services\OrderStatusBackfillService;
use Illuminate\Console\Command;

class BackfillOrderStatuses extends Command
{
    protected $signature = 'orders:backfill-status
                            {--dry-run : Classify only, write nothing}
                            {--limit=0 : Limit the number of orders analyzed}';

    protected $description = 'Fill missing order status transactions for legacy-imported orders';

    public function handle(OrderStatusBackfillService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $analysis = $service->analyze($limit);

        $this->info('Missing order status: '.$analysis['total']);
        $labels = [
            'created'  => 'Sipariş Oluşturuldu (no files)',
            'ready'    => 'Sevke Hazır (all files accepted)',
            'transfer' => 'Dosyalar Kontrol Ediliyor (files pending)',
            'rejected' => 'Reddedilen Dosyalar (file rejected)',
        ];
        foreach ($analysis['counts'] as $key => $count) {
            $this->line(sprintf('  %-18s %d', $labels[$key] ?? $key, $count));
        }

        if (! empty($analysis['samples'])) {
            $this->newLine();
            $this->line('Samples:');
            foreach ($analysis['samples'] as $s) {
                $this->line(sprintf(
                    '  #%d %s -> %s (%s)',
                    $s['order_id'],
                    (string) $s['qnid'],
                    $s['type_key'],
                    $s['note']
                ));
            }
        }

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run — nothing written.');
            return self::SUCCESS;
        }

        $applied = $service->apply($analysis['decisions']);
        $this->newLine();
        $this->info("Inserted {$applied} status transactions.");

        return self::SUCCESS;
    }
}
