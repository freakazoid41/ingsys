<?php

namespace Database\Seeders;

use App\Models\User;
use App\Providers\DocumentServiceProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo offers against the activated demo tender (IH-2026-002) so the admin
 * Teklifler queue renders real rows (header + item conns via registerContent).
 *
 *   KB Bozat   → offer-sended
 *   Pick Ecanme→ offer-sended then offer-rejected ("Tip test raporu eksik")
 *
 * Idempotent by (ihale_qnid + supplier_qnid) pair.
 *
 * Run: php artisan db:seed --class=TenderOfferDemoSeeder
 */
class TenderOfferDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', env('DEV_ADMIN', 'kadir@kontent.com.tr'))->first() ?? User::first();
        if (! $user) { echo "No user — abort.\n"; return; }

        Auth::login($user);
        auth('sanctum')->setUser($user);
        $personQnid = DB::table('persons')->where('id', $user->person_id)->value('qnid');
        session([
            'person_id' => $personQnid ?: 'system',
            'type_key'  => 'op-pert-admin',
            'email'     => $user->email,
        ]);
        $GLOBALS['SYS_CODE'] = $GLOBALS['SYS_CODE'] ?? 'GDZ';

        $tender = $this->tender('IH-2026-002');
        if (! $tender) { echo "IH-2026-002 not found — run TenderDemoSeeder first.\n"; return; }

        $suppliers = [
            'kbbozat41@hotmail.com'  => ['imalatci' => 'KB Elektrik Sanayi A.Ş.', 'brands' => ['PANOBEL', 'KB Marka'], 'reject' => false],
            'picklecanme@gmail.com'  => ['imalatci' => 'Pickle Enerji Ltd.',      'brands' => ['MATRIX',  'Pickle Marka'], 'reject' => true],
        ];

        $provider = new DocumentServiceProvider();
        $base = 1700000200000;
        $ti = 0;

        foreach ($suppliers as $email => $cfg) {
            $supplier = $this->supplier($email);
            if (! $supplier) { echo "supplier {$email} not found, skip..\n"; $ti++; continue; }
            if ($this->offerExists($tender['qnid'], $supplier['qnid'])) {
                echo "offer {$email} / {$tender['kodu']} already exists, skip..\n";
                $ti++;
                continue;
            }

            $rowToken = 'of'.$base.'-'.$ti;
            $dynamicF = [];
            $dynamicF['op-doc-ihale-offer-form**new-'.$rowToken] = [
                'tag' => 'op-doc-ihale-offer-form',
                'entities' => [
                    'ihale_qnid'     => $tender['qnid'],
                    'ihale_kodu'     => $tender['kodu'],
                    'supplier_qnid'  => $supplier['qnid'],
                    'supplier_title' => $supplier['title'],
                ],
            ];

            foreach ($tender['products'] as $pi => $p) {
                $dynamicF['op-doc-ihale-offer-item-form**new-'.$rowToken.'-'.$pi] = [
                    'tag' => 'op-doc-ihale-offer-item-form',
                    'entities' => [
                        'ihale_qnid'        => $tender['qnid'],
                        'supplier_qnid'     => $supplier['qnid'],
                        'product_qnid'      => $p['qnid'],
                        'product_title'     => $p['title'],
                        'quantity'          => $p['quantity'],
                        'unit'              => $p['unit'],
                        'imalatci_firma_adi' => $cfg['imalatci'],
                        'urun_markasi'      => $cfg['brands'][$pi % count($cfg['brands'])],
                    ],
                ];
            }

            $rsp = $provider->registerContent(0, [
                'typeKey'  => 'op-doc-ihale-offer',
                'dynamicF' => $dynamicF,
            ], []);

            if (empty($rsp['success'])) {
                echo "offer {$email} FAILED: ".($rsp['message'] ?? json_encode($rsp))."\n";
                $ti++;
                continue;
            }

            echo "offer {$email} → {$tender['kodu']} created (".count($tender['products'])." items) → {$rsp['qnid']}\n";

            if (! empty($cfg['reject'])) {
                $st = $provider->setStatus($rsp['qnid'], 'offer-rejected', 'Tip test raporu eksik');
                echo '  → reject: '.(! empty($st['success']) ? 'OK' : 'FAILED '.($st['msg'] ?? ''))."\n";
            }
            $ti++;
        }
    }

    private function tender(string $kodu): ?array
    {
        $doc = DB::table('documents as d')
            ->join('sys_options as o', 'o.id', '=', 'd.type_id')
            ->where('o.op_key', 'op-doc-ihale')
            ->whereIn('d.id', function ($q) use ($kodu) {
                $q->select('so.main_id')->from('sys_con_entities as se')
                    ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
                    ->where('se.entity_tag', 'like', 'ihale_kodu%')
                    ->where('se.entity_value', $kodu);
            })
            ->first(['d.id', 'd.qnid']);

        if (! $doc) return null;

        $formId = DB::table('sys_options')->where('op_key', 'op-doc-ihale-form')->value('id');
        $conn = DB::table('sys_con_ops')->where('main_id', $doc->id)->where('type_id', $formId)->first();
        if (! $conn) return null;

        $rows = [];
        foreach (DB::table('sys_con_entities')->where('conn_id', $conn->id)
                     ->where('entity_tag', 'like', '%**ihale_product_rows**%')->get(['entity_tag', 'entity_value']) as $e) {
            $p = explode('**', $e->entity_tag);
            if (count($p) < 3) continue;
            $rows[$p[2]][$p[0]] = $e->entity_value;
        }

        $products = [];
        foreach ($rows as $r) {
            if (empty($r['product_qnid'])) continue;
            $products[] = [
                'qnid' => $r['product_qnid'],
                'title' => $r['product_title'] ?? $r['product_qnid'],
                'quantity' => $r['quantity'] ?? '',
                'unit' => $r['unit'] ?? 'ST',
            ];
        }
        if (! $products) return null;

        return ['qnid' => $doc->qnid, 'kodu' => $kodu, 'products' => $products];
    }

    private function supplier(string $email): ?array
    {
        $row = DB::table('users as u')
            ->join('persons as p', 'p.id', '=', 'u.person_id')
            ->where('u.email', $email)
            ->where('p.status', 1)
            ->first(['p.qnid', 'p.name', 'p.surname']);

        if (! $row) return null;

        return [
            'qnid' => $row->qnid,
            'title' => trim(($row->name ?? '').' '.($row->surname ?? '')) ?: $email,
        ];
    }

    private function offerExists(string $ihaleQnid, string $supplierQnid): bool
    {
        $conn = DB::table('sys_con_ops as so')
            ->join('sys_con_entities as se', 'se.conn_id', '=', 'so.id')
            ->where('so.type_id', DB::table('sys_options')->where('op_key', 'op-doc-ihale-offer-form')->value('id'))
            ->where('se.entity_tag', 'supplier_qnid')
            ->where('se.entity_value', $supplierQnid)
            ->value('se.conn_id');

        if (! $conn) return false;

        return DB::table('sys_con_entities')
            ->where('conn_id', $conn)
            ->where('entity_tag', 'ihale_qnid')
            ->where('entity_value', $ihaleQnid)
            ->exists();
    }
}
