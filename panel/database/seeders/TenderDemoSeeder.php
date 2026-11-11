<?php

namespace Database\Seeders;

use App\Models\Documents;
use App\Models\Sys_options;
use App\Models\User;
use App\Providers\DocumentServiceProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo tenders with MULTIPLE products, created through the real save pipeline
 * (`registerContent`) so transactions / audit logs exist like a UI save.
 *
 * Products come from TransformerCatalogSeeder (`TRF-33-*`).
 * Row keys must have a unique first dash-token — Form.vue de-dupes `multiple`
 * rows by `rowKey.split('-')[0]`, so `new-p1/new-p2` would collapse into one card.
 *
 * Run: php artisan db:seed --class=TenderDemoSeeder
 */
class TenderDemoSeeder extends Seeder
{
    private const TENDERS = [
        [
            'kodu' => 'IH-2026-001',
            'rows' => [
                ['TRF-33-630', 2],
                ['TRF-33-1000', 1],
                ['TRF-33-1250', 3],
            ],
        ],
        [
            'kodu' => 'IH-2026-002',
            'rows' => [
                ['TRF-33-050', 6],
                ['TRF-33-100', 4],
                ['TRF-33-160', 2],
                ['TRF-33-250', 3],
            ],
        ],
        [
            'kodu' => 'IH-2026-003',
            'rows' => [
                ['TRF-33-400', 5],
                ['TRF-33-1600', 1],
            ],
        ],
        [
            // deliberately repeats TRF-33-100 (also in IH-2026-002) to demo shared client files
            'kodu' => 'IH-2026-004',
            'rows' => [
                ['TRF-33-100', 2],
                ['TRF-33-630', 1],
            ],
        ],
    ];

    public function run(): void
    {
        $user = User::where('email', env('DEV_ADMIN', 'kadir@kontent.com.tr'))->first() ?? User::first();
        if (! $user) {
            echo "No user to attribute the tenders to — abort.\n";
            return;
        }

        Auth::login($user);
        auth('sanctum')->setUser($user);

        $personQnid = DB::table('persons')->where('id', $user->person_id)->value('qnid');
        session([
            'person_id' => $personQnid ?: 'system',
            'type_key' => 'op-pert-admin',
            'email' => $user->email,
        ]);
        $GLOBALS['SYS_CODE'] = $GLOBALS['SYS_CODE'] ?? 'GDZ';

        $products = $this->productsByCode();
        if (empty($products)) {
            echo "No products found — run TransformerCatalogSeeder first.\n";
            return;
        }

        $provider = new DocumentServiceProvider();
        $base = 1700000100000;

        foreach (self::TENDERS as $ti => $tender) {
            if ($this->exists($tender['kodu'])) {
                echo $tender['kodu']." already exists, skip..\n";
                continue;
            }

            $connKey = 'op-doc-ihale-form**new-td'.$base.'-'.$ti;
            $entities = ['ihale_kodu' => $tender['kodu']];

            foreach ($tender['rows'] as $ri => [$code, $qty]) {
                if (! isset($products[$code])) {
                    echo "  ! product {$code} missing, skipped\n";
                    continue;
                }
                $p = $products[$code];
                $rowKey = ($base + ($ti * 100) + $ri).'-'.$ri;

                $entities['product_qnid**ihale_product_rows**'.$rowKey] = $p['qnid'];
                $entities['product_title**ihale_product_rows**'.$rowKey] = $p['title'];
                $entities['quantity**ihale_product_rows**'.$rowKey] = (string) $qty;
                $entities['unit**ihale_product_rows**'.$rowKey] = $p['unit'] ?: 'ST';
            }

            $rsp = $provider->registerContent(0, [
                'typeKey' => 'op-doc-ihale',
                'dynamicF' => [
                    $connKey => [
                        'tag' => 'op-doc-ihale-form',
                        'entities' => $entities,
                    ],
                ],
            ], []);

            if (! empty($rsp['success'])) {
                echo $tender['kodu'].' created ('.count($tender['rows']).' products) → '.$rsp['qnid']."\n";
            } else {
                echo $tender['kodu']." FAILED: ".($rsp['message'] ?? json_encode($rsp))."\n";
            }
        }
    }

    private function productsByCode(): array
    {
        $typeId = Sys_options::where('op_key', 'op-doc-product')->value('id');
        $formId = Sys_options::where('op_key', 'op-doc-product-form')->value('id');
        if (! $typeId || ! $formId) {
            return [];
        }

        $docs = DB::table('documents')->where('type_id', $typeId)->where('status', 1)->get(['id', 'qnid']);
        $out = [];

        foreach ($docs as $doc) {
            $conn = DB::table('sys_con_ops')->where('main_id', $doc->id)->where('type_id', $formId)->where('conn_id', 0)->first();
            if (! $conn) {
                continue;
            }
            $rows = DB::table('sys_con_entities')->where('conn_id', $conn->id)
                ->whereIn('entity_tag', ['prod_code', 'short_name', 'unit'])
                ->pluck('entity_value', 'entity_tag');

            if (empty($rows['prod_code'])) {
                continue;
            }
            $out[$rows['prod_code']] = [
                'qnid' => $doc->qnid,
                'title' => $rows['short_name'] ?? $rows['prod_code'],
                'unit' => $rows['unit'] ?? 'ST',
            ];
        }

        return $out;
    }

    private function exists(string $kodu): bool
    {
        return DB::table('sys_con_entities')
            ->where('entity_tag', 'like', 'ihale_kodu%')
            ->where('entity_value', $kodu)
            ->exists();
    }
}
