<?php

/**
 * Order test-data reset + fresh SAP ingest — tedarikNewApp
 *
 * 1) WIPES all order data (op-doc-order / op-doc-order-item / op-doc-transfer,
 *    including partial-transfer clones) + their files + transactions + logs + EAV.
 *    Clients (op-doc-client) and users are untouched.
 * 2) INGESTS a SAP flat-row array (grouped by EBELN) exactly like the future
 *    SyncOrdersCommand cron will: one op-doc-order header per EBELN + one
 *    op-doc-order-item per row, via registerContent (correct birth statuses,
 *    transactions, logs).
 *
 * Run: php seed_sap_orders.php   (from panel/)
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Providers\DocumentServiceProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// FAKE AUTH — registerContent logs via auth('sanctum')->user() and session('person_id')
// ---------------------------------------------------------------------------
$firstUser = User::query()->where('status', 1)->first();
if ($firstUser) {
    Auth::guard('sanctum')->setUser($firstUser);
    session(['person_id' => (string) $firstUser->person_id]);
}

// ===========================================================================
// 1) WIPE ALL ORDER DATA
// ===========================================================================
echo "=== 1) WIPE ORDER DATA ===\n";
try {
    DB::beginTransaction();

    $typeIds = DB::table('sys_options')->whereIn('op_key', ['op-doc-order', 'op-doc-order-item', 'op-doc-transfer'])->pluck('id');
    $docIds = DB::table('documents')->whereIn('type_id', $typeIds)->pluck('id');
    echo 'Orders/items/clones found: '.$docIds->count()."\n";

    $connIds = DB::table('sys_con_ops')->whereIn('main_id', $docIds)->pluck('id');
    $fileIds = DB::table('sys_con_entities')
        ->whereIn('conn_id', $connIds)
        ->where('table_tag', 'document_files')
        ->pluck('entity_value')
        ->filter(fn ($v) => is_numeric($v))
        ->map(fn ($v) => (int) $v)
        ->unique();
    echo 'Connections: '.$connIds->count().' | Files: '.$fileIds->count()."\n";

    if ($connIds->isNotEmpty()) {
        DB::table('sys_con_entities')->whereIn('conn_id', $connIds)->delete();
        DB::table('sys_con_ops')->whereIn('id', $connIds)->delete();
    }
    if ($fileIds->isNotEmpty()) {
        DB::table('transactions')->where('op_id', 1)->whereIn('target_id', $fileIds)->delete();
        DB::table('user_logs')->whereIn('relation_id', $fileIds)->delete();
        DB::table('document_files')->whereIn('id', $fileIds)->delete();
    }
    if ($docIds->isNotEmpty()) {
        DB::table('transactions')->where('op_id', 0)->whereIn('target_id', $docIds)->delete();
        DB::table('user_logs')->whereIn('relation_id', $docIds)->delete();
        DB::table('documents')->whereIn('id', $docIds)->delete();
    }

    DB::commit();
    echo "Wipe OK.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo 'Wipe FAILED: '.$e->getMessage()."\n";
    exit(1);
}

// ===========================================================================
// 2) SAP INGEST — paste your SAP export here (flat rows, same keys as SAP)
// ===========================================================================
echo "=== 2) SAP INGEST ===\n";
$sapRows = [
    // BUKRS   LIFNR       EBELN        EBELP   MATNR         TXZ01               MENGE     MEINS  BEDAT        SUBMI        NETPR     WEMNG   MCOD1
    ['BUKRS' => '4000', 'LIFNR' => '0000300181', 'EBELN' => '3510001793', 'EBELP' => '00120', 'MATNR' => '20.6.1.005', 'TXZ01' => 'Premium Kömür Tip A', 'MENGE' => '2400.000', 'MEINS' => 'ST', 'BEDAT' => '2020-04-17', 'SUBMI' => 'IH20205008', 'NETPR' => '1234.56', 'WEMNG' => '0', 'MCOD1' => 'PANORAMA TEKSTİL'],
    ['BUKRS' => '4000', 'LIFNR' => '0000300181', 'EBELN' => '3510001793', 'EBELP' => '00240', 'MATNR' => '20.6.1.008', 'TXZ01' => 'Premium Kömür Tip B', 'MENGE' => '1800.500', 'MEINS' => 'ST', 'BEDAT' => '2020-04-17', 'SUBMI' => 'IH20205008', 'NETPR' => '1300.00', 'WEMNG' => '0', 'MCOD1' => 'PANORAMA TEKSTİL'],
];

$groups = [];
foreach ($sapRows as $r) {
    $groups[trim((string) $r['EBELN'])][] = $r;
}

$docSvc = new DocumentServiceProvider();

foreach ($groups as $ebeln => $rows) {
    $first = $rows[0];

    // Header fields from the FIRST row of the group
    $orderNo = trim((string) $ebeln);
    $headerEntities = [
        'order_no'   => $orderNo,
        'spec_code'  => (string) $first['LIFNR'],                                  // keep leading zeros (Cari Kodu)
        'buying_no'  => trim((string) ($first['SUBMI'] ?? '-')),
        'sys_code'   => trim((string) $first['BUKRS']),
        'ctitle'     => trim((string) ($first['MCOD1'] ?? '')),
        'created_at' => Carbon::parse($first['BEDAT'])->format('d/m/Y'),           // BEDAT
    ];

    $headerPayload = [
        'typeKey' => 'op-doc-order',
        'dynamicF' => [
            'op-doc-order-form**new-'.microtime(true).rand() => [
                'tag' => 'op-doc-order-form',
                'entities' => $headerEntities,
            ],
        ],
    ];
    $res = $docSvc->registerContent(0, $headerPayload, []);
    if (empty($res['qnid'])) {
        echo 'ORDER CREATE FAILED: '.json_encode($res, JSON_UNESCAPED_UNICODE)."\n";
        exit(1);
    }
    $orderId = $res['id'];
    echo "Order {$orderNo} created (id {$orderId})\n";

    // One item per row
    foreach ($rows as $r) {
        $itemEntities = [
            'prod_code' => trim((string) $r['MATNR']).'**'.trim((string) $r['EBELP']),
            'title'     => trim((string) $r['TXZ01']),
            'quantity'  => (string) $r['MENGE'],
            'unit'      => trim((string) $r['MEINS']),
        ];
        $itemPayload = [
            'typeKey' => 'op-doc-order-item',
            'parent_id' => $orderId,
            'dynamicF' => [
                'op-doc-order-item-form**new-'.microtime(true).rand() => [
                    'tag' => 'op-doc-order-item-form',
                    'entities' => $itemEntities,
                ],
            ],
        ];
        $ires = $docSvc->registerContent(0, $itemPayload, []);
        if (empty($ires['qnid'])) {
            echo 'ITEM CREATE FAILED: '.json_encode($ires, JSON_UNESCAPED_UNICODE)."\n";
        } else {
            echo "  item: {$itemEntities['prod_code']} — {$itemEntities['title']} ({$itemEntities['quantity']} {$itemEntities['unit']})\n";
        }
    }
}

echo "=== DONE ===\n";