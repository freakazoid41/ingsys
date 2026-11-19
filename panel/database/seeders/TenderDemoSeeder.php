<?php

namespace Database\Seeders;

use App\Models\Documents;
use App\Models\Sys_options;
use App\Models\User;
use App\Providers\DocumentServiceProvider;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo tenders built from the REAL catalog (TedarikRealCatalogSeeder example products),
 * created through the real save pipeline (`registerContent`) so transactions/audit
 * logs exist like a UI save — including a real spec PDF per tender.
 *
 * Seeded set:
 *   IH-2026-001  created    TRF-33-630 ×2 · GYH-630 ×1 · AGP-630 ×3
 *   IH-2026-002  activated  HYH-630 ×4 · SDK-A ×2
 *   IH-2026-003  created    AGP-630 ×5 · TRF-33-630 ×1
 *
 * Row keys must have a unique first dash-token — Form.vue de-dupes `multiple`
 * rows by `rowKey.split('-')[0]`, so `new-p1/new-p2` would collapse into one card.
 *
 * Run: php artisan db:seed --class=TenderDemoSeeder
 */
class TenderDemoSeeder extends Seeder
{
    private const TENDERS = [
        [
            'kodu'        => 'IH-2026-001',
            'spec_title'  => 'Hermetik Tip OG/AG Dağıtım Güç Transformatörleri Teknik Şartnamesi',
            'deadline'    => '+21 days',
            'activate'    => false,
            'rows' => [
                ['TRF-33-630', 2],
                ['GYH-630', 1],
                ['AGP-630', 3],
            ],
        ],
        [
            'kodu'        => 'IH-2026-002',
            'spec_title'  => 'Alçak Gerilim Saha Dağıtım Kutuları Teknik Şartnamesi',
            'deadline'    => '+30 days',
            'activate'    => true,
            'rows' => [
                ['HYH-630', 4],
                ['SDK-A', 2],
            ],
        ],
        [
            'kodu'        => 'IH-2026-003',
            'spec_title'  => 'Alçak Gerilim Dağıtım Panoları Teknik Şartnamesi',
            'deadline'    => '+10 days',
            'activate'    => false,
            'rows' => [
                ['AGP-630', 5],
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
            'type_key'  => 'op-pert-admin',
            'email'     => $user->email,
        ]);
        $GLOBALS['SYS_CODE'] = $GLOBALS['SYS_CODE'] ?? 'GDZ';

        if (! Sys_options::where('op_key', 'op-ihale_spec_file')->value('id')) {
            echo "İhale file type missing — run IhaleSeeder first.\n";
            return;
        }

        $products = $this->productsByCode();
        if (empty($products)) {
            echo "No products found — run TedarikRealCatalogSeeder first.\n";
            return;
        }

        $provider = new DocumentServiceProvider();
        $base = 1700000100000;

        foreach (self::TENDERS as $ti => $tender) {
            if ($this->exists($tender['kodu'])) {
                echo $tender['kodu']." already exists, skip..\n";
                continue;
            }

            $rowToken = 'td'.$base.'-'.$ti;
            $connKey  = 'op-doc-ihale-form**new-'.$rowToken;
            $entities = [
                'ihale_kodu'        => $tender['kodu'],
                'teklif_son_tarihi' => date('Y-m-d', strtotime($tender['deadline'])),
            ];

            // Spec block (multiple group ihale_spec): title + file entity
            $specRowKey = ($base + 500 + $ti).'-'.$ti;
            $entities['spec_title**ihale_spec**'.$specRowKey] = $tender['spec_title'];

            foreach ($tender['rows'] as $ri => [$code, $qty]) {
                if (! isset($products[$code])) {
                    echo "  ! product {$code} missing, skipped\n";
                    continue;
                }
                $p = $products[$code];
                $rowKey = ($base + ($ti * 100) + $ri).'-'.$ri;

                $entities['product_qnid**ihale_product_rows**'.$rowKey]  = $p['qnid'];
                $entities['product_title**ihale_product_rows**'.$rowKey] = $p['title'];
                $entities['quantity**ihale_product_rows**'.$rowKey]      = (string) $qty;
                $entities['unit**ihale_product_rows**'.$rowKey]          = $p['unit'] ?: 'ST';
            }

            $files = [
                'op-doc-ihale-form**dynamicFile**new-'.$rowToken.'**new-'.$rowToken
                    .'*-*ihale_spec_file**ihale_spec**'.$specRowKey
                    => $this->specPdf($tender['kodu']),
            ];

            $rsp = $provider->registerContent(0, [
                'typeKey' => 'op-doc-ihale',
                'dynamicF' => [
                    $connKey => [
                        'tag' => 'op-doc-ihale-form',
                        'entities' => $entities,
                    ],
                ],
            ], $files);

            if (empty($rsp['success'])) {
                echo $tender['kodu']." FAILED: ".($rsp['message'] ?? json_encode($rsp))."\n";
                continue;
            }

            echo $tender['kodu'].' created ('.count($tender['rows']).' products, spec PDF) → '.$rsp['qnid']."\n";

            if (! empty($tender['activate'])) {
                $act = $provider->setStatus($rsp['qnid'], 'tender-activated', 'Demo: ihale aktif edildi — teklif alımı açıldı');
                echo '  → activate: '.(! empty($act['success']) ? 'OK' : 'FAILED '.($act['msg'] ?? ''))."\n";
            }
        }
    }

    /** Minimal valid PDF so the spec file renders like a real upload. */
    private function specPdf(string $kodu): UploadedFile
    {
        $body = "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";
        $path = tempnam(sys_get_temp_dir(), 'spec_').'.pdf';
        file_put_contents($path, $body);

        return new UploadedFile($path, 'sartname-'.$kodu.'.pdf', 'application/pdf', null, true);
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
