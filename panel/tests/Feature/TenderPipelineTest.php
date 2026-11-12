<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Document_files;
use App\Models\Persons;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenderPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['SYS_CODE'] = 'GDZ';
        $this->seedTenderOptions();
        $this->actingAsAdmin();
    }

    private function option(string $opKey, string $groupKey, string $title = null): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            ['title' => $title ?: $opKey, 'ttitle' => $opKey, 'ctitle' => 'type_id', 'group_key' => $groupKey, 'status' => 1]
        );
    }

    private function seedTenderOptions(): void
    {
        $this->option('op-doc-ihale', 'op-doc');
        $this->option('op-doc-ihale-form', 'op-doc-forms');
        $this->option('op-doc-ihale-offer', 'op-doc');
        $this->option('op-doc-ihale-offer-form', 'op-doc-forms');
        $this->option('op-doc-ihale-offer-item-form', 'op-doc-forms');
        $this->option('op-doc-product', 'op-doc');
        $this->option('op-doc-product-form', 'op-doc-forms');
        $this->option('op-doc-client', 'op-doc');
        $this->option('op-doc-client-form', 'op-doc-forms');
        $this->option('op-doc-user-permission-form', 'op-doc-forms');
        $this->option('form-main', 'op-con-ops')->update(['ctitle' => 'sub_type_id']);
        $this->option('form-file', 'op-con-ops')->update(['ctitle' => 'sub_type_id']);
        $this->option('log-tender-update', 'op-logs');
        $this->option('log-document-status-update', 'op-logs');
        $this->option('log-file-added', 'op-logs');
        $this->option('tender-created', 'op-trans-op-doc-ihale');
        $this->option('tender-activated', 'op-trans-op-doc-ihale');
        $this->option('tender-complated', 'op-trans-op-doc-ihale');
        $this->option('offer-sended', 'op-trans-op-doc-ihale-offer');
        $this->option('offer-rejected', 'op-trans-op-doc-ihale-offer');
        $this->option('offer-accepted', 'op-trans-op-doc-ihale-offer');
        $this->option('offer-file-rejected', 'op-trans-op-doc-ihale-offer');
        $this->option('doc_trans_created', 'op-trans');
    }

    private function actingAsAdmin(): void
    {
        $type = $this->option('op-pert-admin', 'op-pert');
        $person = Persons::create([
            'name' => 'Tender', 'surname' => 'Admin', 'type_id' => $type->id,
            'status' => 1, 'grp_code' => 'GDZ', 'qnid' => (string) Str::uuid(),
            'spec_code' => '', 'title' => 'Tender Admin', 'phone' => '', 'address' => '',
        ]);
        $user = User::create([
            'name' => 'Tender Admin', 'email' => 'tender-admin@test.local',
            'password' => bcrypt('password'), 'person_id' => $person->id,
            'role' => 'immutable-admin', 'status' => 1, 'grp_code' => 'GDZ',
        ]);
        $perms = ['per-041-01', 'per-041-03', 'per-041-03-01', 'per-05-01', 'per-05-02', 'per-07-01', 'per-07-02'];
        (new PermissionService())->cacheUserPermissions($person->id, $perms);
        session([
            'person_id' => $person->id,
            'type_key' => 'op-pert-admin',
            '2f_success' => true,
            'perms' => $perms,
            'currentStatus' => ['clientQnidList' => [], 'canProceed' => true, 'canResponse' => true],
        ]);
        foreach ($perms as $p) session(['sper-'.$p => true]);
        Sanctum::actingAs($user, ['*']);
    }

    private function createTender(string $status = 'tender-activated', ?string $deadline = null): Documents
    {
        $type = Sys_options::where('op_key', 'op-doc-ihale')->first();
        $form = Sys_options::where('op_key', 'op-doc-ihale-form')->first();
        $doc = Documents::create([
            'type_id' => $type->id, 'status' => 1, 'title' => 'Tender Test',
            'person_id' => 'system', 'grp_code' => 'GDZ',
        ]);
        $conn = Sys_con_ops::create([
            'main_id' => $doc->id, 'conn_id' => 0, 'type_id' => $form->id, 'sub_type_id' => 0,
        ]);
        $row = '1700000000000-0';
        foreach ([
            'ihale_kodu' => 'IHL-TEST-1',
            'teklif_son_tarihi' => $deadline ?: now()->addDay()->format('Y-m-d'),
            'product_qnid**ihale_product_rows**'.$row => (string) Str::uuid(),
            'product_title**ihale_product_rows**'.$row => 'Test Trafo',
            'quantity**ihale_product_rows**'.$row => '10',
            'unit**ihale_product_rows**'.$row => 'ST',
        ] as $tag => $value) {
            Sys_con_entities::create([
                'conn_id' => $conn->id, 'table_tag' => 'sys_con_ops',
                'entity_tag' => $tag, 'entity_value' => $value,
            ]);
        }

        $chain = ['tender-created'];
        if (in_array($status, ['tender-activated', 'tender-complated'], true)) $chain[] = 'tender-activated';
        if ($status === 'tender-complated') $chain[] = 'tender-complated';
        foreach ($chain as $opKey) {
            $typeId = Sys_options::where('op_key', $opKey)->value('id');
            if ($typeId) Transactions::create([
                'op_id' => 0, 'type_id' => $typeId, 'log_id' => 0,
                'target_id' => $doc->id, 'note' => 'test', 'description' => 'test',
            ]);
        }
        return $doc->fresh();
    }

    private function offerPayload(Documents $tender, array $itemOverrides = []): array
    {
        $tenderConn = Sys_con_ops::where('main_id', $tender->id)
            ->where('type_id', Sys_options::where('op_key', 'op-doc-ihale-form')->value('id'))
            ->where('conn_id', 0)->first();
        $productQnid = Sys_con_entities::where('conn_id', $tenderConn->id)
            ->where('entity_tag', 'like', 'product_qnid**ihale_product_rows**%')
            ->value('entity_value');
        $supplier = (string) Str::uuid();
        $item = array_merge([
            'ihale_qnid' => $tender->qnid,
            'supplier_qnid' => $supplier,
            'product_qnid' => $productQnid,
            'product_title' => 'Test Trafo',
            'quantity' => '10',
            'unit' => 'ST',
            'imalatci_firma_adi' => 'Test İmalatçı',
            'urun_markasi' => 'Test Marka',
        ], $itemOverrides);

        return [
            'typeKey' => 'op-doc-ihale-offer',
            'dynamicF' => [
                'op-doc-ihale-offer-form**new-h' => [
                    'tag' => 'op-doc-ihale-offer-form',
                    'entities' => [
                        'ihale_qnid' => $tender->qnid,
                        'ihale_kodu' => 'IHL-TEST-1',
                        'supplier_qnid' => $supplier,
                        'supplier_title' => 'Test Supplier',
                    ],
                ],
                'op-doc-ihale-offer-item-form**new-i' => [
                    'tag' => 'op-doc-ihale-offer-item-form',
                    'entities' => $item,
                ],
            ],
        ];
    }

    public function test_tender_creation_requires_code_product_and_spec_file(): void
    {
        $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode(['typeKey' => 'op-doc-ihale', 'dynamicF' => []])])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_offer_rejects_tampered_quantity(): void
    {
        $tender = $this->createTender();
        $payload = $this->offerPayload($tender, ['quantity' => '9']);

        $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode($payload)])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_expired_tender_deadline_blocks_offer(): void
    {
        $tender = $this->createTender('tender-activated', now()->subDay()->format('Y-m-d'));
        $payload = $this->offerPayload($tender);

        $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode($payload)])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_offer_is_blocked(): void
    {
        $tender = $this->createTender();
        $payload = $this->offerPayload($tender);

        $first = $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode($payload)]);
        $first->assertOk();
        $first->assertJsonPath('success', true);
        $second = $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode($payload)]);
        $second->assertStatus(422);
        $second->assertJsonPath('success', false);
    }


    public function test_activated_tender_appears_in_notification_feed(): void
    {
        $this->createTender('tender-activated');

        $rsp = $this->withSession(session()->all())->getJson('/api/v1/notifications');
        $rsp->assertOk();
        $this->assertNotEmpty($rsp->json('tenderActivated'));
    }


    public function test_submitted_offer_appears_in_notification_feed(): void
    {
        $tender = $this->createTender();
        $payload = $this->offerPayload($tender);

        $this->withSession(session()->all())
            ->post('/api/v1/document', ['data' => json_encode($payload)])
            ->assertOk();

        $rsp = $this->withSession(session()->all())->getJson('/api/v1/notifications');
        $rsp->assertOk();
        $this->assertNotEmpty($rsp->json('offerSubmitted'));
    }


    public function test_foreign_temp_file_reference_is_rejected(): void
    {
        $tender = $this->createTender();
        $payload = $this->offerPayload($tender);
        $temp = Document_files::create([
            'status' => 1, 'type_id' => 1, 'replaced_id' => 0,
            'relation' => 'temp', 'relation_id' => 0, 'title' => '-',
            'description' => 'foreign', 'uploaded_by' => 999999,
        ]);

        $this->withSession(session()->all())
            ->post('/api/v1/document', [
                'data' => json_encode($payload),
                'dynamicFile**offer_cells**x' => json_encode(['reference_id' => $temp->id]),
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_completed_tender_cannot_be_updated(): void
    {
        $tender = $this->createTender('tender-complated');
        $payload = ['typeKey' => 'op-doc-ihale', 'dynamicF' => []];

        $this->withSession(session()->all())
            ->put('/api/v1/document/'.$tender->qnid, ['data' => json_encode($payload)])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_generic_status_route_cannot_change_tender_or_offer(): void
    {
        $tender = $this->createTender();

        $this->withSession(session()->all())
            ->post('/api/v1/trans/set-status', ['id' => $tender->qnid, 'op_key' => 'tender-activated'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
