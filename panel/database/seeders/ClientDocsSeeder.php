<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Supplier document library dictionary + tender status chain.
 *
 * Tender statuses (group op-trans-op-doc-ihale):
 *   tender-created   → tender-created (birth)
 *   tender-activated → offers can be prepared
 *   tender-complated → ended, winner locked, offers frozen
 *
 * Run: php artisan db:seed --class=ClientDocsSeeder
 */
class ClientDocsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['op_key' => 'op-doc-client-docs',      'group_key' => 'op-doc',          'title' => 'Tedarikçi Evrak Kütüphanesi', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'op-doc-client-docs-form', 'group_key' => 'op-doc-forms',    'title' => 'Tedarikçi Evrak Kütüphanesi Formu', 'ttitle' => 'Documents', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'tender-created',          'group_key' => 'op-trans-op-doc-ihale', 'title' => 'İhale Oluşturuldu', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'tender-activated',        'group_key' => 'op-trans-op-doc-ihale', 'title' => 'İhale Aktif', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'tender-complated',        'group_key' => 'op-trans-op-doc-ihale', 'title' => 'İhale Tamamlandı', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'offer-sended',            'group_key' => 'op-trans-op-doc-ihale-offer', 'title' => 'Teklif Gönderildi', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'offer-accepted',          'group_key' => 'op-trans-op-doc-ihale-offer', 'title' => 'Teklif Kabul Edildi', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'offer-rejected',          'group_key' => 'op-trans-op-doc-ihale-offer', 'title' => 'Teklif Reddedildi', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
            ['op_key' => 'offer-file-rejected',      'group_key' => 'op-trans-op-doc-ihale-offer', 'title' => 'Dosya Reddedildi', 'ttitle' => '-', 'ctitle' => 'type_id', 'parent_id' => 0],
        ];

        foreach ($items as $item) {
            if (Sys_options::where('op_key', $item['op_key'])->exists()) {
                echo $item['title']." already exists, skip..\n";
                continue;
            }
            $row = new Sys_options();
            $row->parent_id = $item['parent_id'];
            $row->title = $item['title'];
            $row->ttitle = $item['ttitle'];
            $row->ctitle = $item['ctitle'];
            $row->op_key = $item['op_key'];
            $row->group_key = $item['group_key'];
            $row->save();
            echo $item['title']." Added..\n";
        }

        // Retire the legacy completion key (safe only while unused)
        $legacy = Sys_options::where('op_key', 'tr-tender-complated')->first();
        if ($legacy) {
            $used = DB::table('transactions')->where('type_id', $legacy->id)->exists();
            if (! $used) {
                $legacy->delete();
                echo "tr-tender-complated (legacy) removed..\n";
            } else {
                echo "tr-tender-complated still has transactions, kept..\n";
            }
        }

        // Backfill: every existing tender gets its birth status in the tender group
        $tenderTypeId = Sys_options::where('op_key', 'op-doc-ihale')->value('id');
        $createdId = Sys_options::where('op_key', 'tender-created')->value('id');
        if ($tenderTypeId && $createdId) {
            $docs = DB::table('documents')->where('type_id', $tenderTypeId)->pluck('id');
            $backfilled = 0;
            foreach ($docs as $docId) {
                $has = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->where('t.target_id', $docId)
                    ->where('so.group_key', 'op-trans-op-doc-ihale')
                    ->exists();
                if ($has) continue;
                DB::table('transactions')->insert([
                    'op_id' => 0,
                    'type_id' => $createdId,
                    'log_id' => 0,
                    'target_id' => $docId,
                    'description' => 'New Document Added',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $backfilled++;
            }
            echo "tender-created backfilled: {$backfilled}\n";
        }

        // Backfill: every existing offer gets its birth status in the offer group
        $offerTypeId = Sys_options::where('op_key', 'op-doc-ihale-offer')->value('id');
        $sendedId = Sys_options::where('op_key', 'offer-sended')->value('id');
        if ($offerTypeId && $sendedId) {
            $docs = DB::table('documents')->where('type_id', $offerTypeId)->pluck('id');
            $backfilledOffers = 0;
            foreach ($docs as $docId) {
                $has = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->where('t.target_id', $docId)
                    ->where('so.group_key', 'op-trans-op-doc-ihale-offer')
                    ->exists();
                if ($has) continue;
                DB::table('transactions')->insert([
                    'op_id' => 0,
                    'type_id' => $sendedId,
                    'log_id' => 0,
                    'target_id' => $docId,
                    'description' => 'New Document Added',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $backfilledOffers++;
            }
            echo "offer-sended backfilled: {$backfilledOffers}\n";
        }

        echo "ClientDocsSeeder done.\n";
    }
}
