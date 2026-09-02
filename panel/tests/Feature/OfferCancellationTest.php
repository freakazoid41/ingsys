<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\User;
use App\Models\UserLog;
use App\Providers\DocumentServiceProvider;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfferCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function optionFor(string $opKey, string $groupKey): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            [
                'title' => $opKey,
                'ttitle' => $opKey,
                'ctitle' => $opKey,
                'group_key' => $groupKey,
                'status' => 1,
            ]
        );
    }

    /**
     * Creates a document that is visible to Documents::tableList, which inner joins
     * sys_con_ops and sys_con_entities, so both rows are required.
     */
    private function seedDocument(string $typeKey, string $formKey, int $documentStatus, string $cliid = 'client-qnid-1'): Documents
    {
        $docType = $this->optionFor($typeKey, 'op-doc-forms');
        $formType = $this->optionFor($formKey, 'op-doc-forms');

        $document = Documents::create([
            'type_id' => $docType->id,
            'status' => $documentStatus,
            'title' => 'Test '.$typeKey,
            'person_id' => '-',
            'grp_code' => 'GDZ',
        ]);

        $conn = Sys_con_ops::create([
            'main_id' => $document->id,
            'conn_id' => 0,
            'type_id' => $formType->id,
            'sub_type_id' => 0,
        ]);

        Sys_con_entities::create([
            'conn_id' => $conn->id,
            'table_tag' => 'sys_con_ops',
            'entity_tag' => 'cliid',
            'entity_value' => $cliid,
        ]);

        return $document;
    }

    private function listOffers(array $extraFilters = []): array
    {
        return Documents::tableList([
            'filter' => array_merge([
                ['key' => 'type', 'type' => '=', 'value' => 'op-doc-offer'],
                ['key' => 'form-type', 'type' => '=', 'value' => 'op-doc-offer-form'],
            ], $extraFilters),
        ])['data'];
    }

    public function test_offer_row_exposes_document_status_separately_from_transaction_status(): void
    {
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);

        $rows = $this->listOffers();

        $this->assertCount(1, $rows);
        $this->assertObjectHasProperty('document_status', $rows[0]);
        $this->assertSame(1, (int) $rows[0]->document_status);
    }

    public function test_with_cancelled_filter_includes_cancelled_offers(): void
    {
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);

        $rows = $this->listOffers([['key' => 'with-cancelled', 'type' => '=', 'value' => '1']]);

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing([0, 1], array_map(fn ($r) => (int) $r->document_status, $rows));
    }

    /**
     * The free-text branch ORs every entry of $columns into an ILIKE. document_status must stay
     * out of it, otherwise searching "1" matches every active row of every document type.
     */
    public function test_document_status_is_not_part_of_the_free_text_search(): void
    {
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $q->sql;
        });

        $this->listOffers([['key' => 'all', 'type' => 'like', 'value' => '1']]);

        $searchQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'ilike'));
        $this->assertNotEmpty($searchQueries);

        foreach ($searchQueries as $sql) {
            $this->assertStringNotContainsString('i.status  ::text ilike', $sql);
        }
    }

    public function test_cancelled_offers_stay_hidden_when_the_flag_is_absent(): void
    {
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);

        $rows = $this->listOffers();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->document_status);
    }

    public function test_with_cancelled_flag_does_not_reveal_passive_documents_of_other_types(): void
    {
        $this->seedDocument('op-doc-request', 'op-doc-request-form', 1);
        $this->seedDocument('op-doc-request', 'op-doc-request-form', 0);

        $rows = Documents::tableList([
            'filter' => [
                ['key' => 'type', 'type' => '=', 'value' => 'op-doc-request'],
                ['key' => 'form-type', 'type' => '=', 'value' => 'op-doc-request-form'],
                ['key' => 'with-cancelled', 'type' => '=', 'value' => '1'],
            ],
        ])['data'];

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->document_status);
    }

    /**
     * The document query aliases the transaction history as "status", which shadows the
     * documents.status column coming from d.*, so activeness needs its own alias.
     */
    public function test_form_data_exposes_document_status_despite_the_status_alias(): void
    {
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);

        $data = (new DocumentServiceProvider)->getFormData($document->qnid);

        $this->assertObjectHasProperty('document_status', $data['document']);
        $this->assertSame(0, (int) $data['document']->document_status);
    }

    private function seedRegisterContentOptions(): void
    {
        $this->optionFor('log-tender-update', 'log');
        Sys_options::firstOrCreate(
            ['op_key' => 'form-main'],
            ['title' => 'form-main', 'ttitle' => 'form-main', 'ctitle' => 'sub_type_id', 'group_key' => 'op-form', 'status' => 1]
        );
        Sys_options::firstOrCreate(
            ['op_key' => 'form-file'],
            ['title' => 'form-file', 'ttitle' => 'form-file', 'ctitle' => 'sub_type_id', 'group_key' => 'op-form', 'status' => 1]
        );
    }

    private function actAsUser(array $permissions = [], string $typeKey = 'op-pert-admin'): User
    {
        $user = User::create([
            'name' => 'Test',
            'role' => 'admin',
            'email' => 'test'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'person_id' => 4242,
        ]);

        (new PermissionService)->cacheUserPermissions(4242, $permissions);
        session(['type_key' => $typeKey, 'person_id' => 4242]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * Authentigdz by token only, with no session attributes — the shape a non-SPA
     * (no Origin/Referer) API call arrives in.
     */
    private function actAsUserWithoutSession(array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Token',
            'role' => 'user',
            'email' => 'token'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'person_id' => 4242,
        ]);

        (new PermissionService)->cacheUserPermissions(4242, $permissions);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_generic_form_payload_cannot_flip_document_status(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser();

        (new DocumentServiceProvider)->registerContent($document->qnid, [
            'typeKey' => 'op-doc-offer',
            'main_status' => 0,
            'dynamicF' => [],
        ], []);

        $this->assertSame(1, (int) $document->fresh()->status);
    }

    public function test_cancel_offer_deactivates_the_document_and_logs_the_cancellation(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser();

        $result = (new DocumentServiceProvider)->cancelOffer($document->qnid, 'müşteri vazgeçti');

        $this->assertTrue($result['success']);
        $this->assertSame(0, (int) $document->fresh()->status);

        $log = UserLog::where('relation_id', $document->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Teklif İptal Edildi', $log->description);
    }

    public function test_cancel_offer_refuses_an_already_cancelled_offer(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);
        $this->actAsUser();

        $result = (new DocumentServiceProvider)->cancelOffer($document->qnid, null);

        $this->assertFalse($result['success']);
        $this->assertDatabaseCount('user_logs', 0);
    }

    public function test_cancel_offer_refuses_documents_that_are_not_offers(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-request', 'op-doc-request-form', 1);
        $this->actAsUser();

        $result = (new DocumentServiceProvider)->cancelOffer($document->qnid, null);

        $this->assertFalse($result['success']);
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    private function cancelViaHttp(Documents $document, array $session): TestResponse
    {
        return $this->withSession($session)->postJson('/api/v1/trans/cancel-offer', [
            'id' => $document->qnid,
            'note' => 'test iptali',
        ]);
    }

    public function test_supplier_can_cancel_an_offer_belonging_to_its_own_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-a');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->cancelViaHttp($document, [
            'type_key' => 'op-pert-reseller',
            'currentStatus' => ['clientQnidList' => ['client-a']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(0, (int) $document->fresh()->status);
    }

    public function test_supplier_cannot_cancel_an_offer_of_another_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-b');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->cancelViaHttp($document, [
            'type_key' => 'op-pert-reseller',
            'currentStatus' => ['clientQnidList' => ['client-a']],
        ]);

        $response->assertForbidden();
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    public function test_admin_can_cancel_an_offer_of_any_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-b');
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->cancelViaHttp($document, [
            'type_key' => 'op-pert-admin',
            'currentStatus' => ['clientQnidList' => []],
        ]);

        $response->assertOk();
        $this->assertSame(0, (int) $document->fresh()->status);
    }

    public function test_cancel_endpoint_requires_the_offer_edit_permission(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser(['per-08-01'], 'op-pert-admin');

        $response = $this->cancelViaHttp($document, ['type_key' => 'op-pert-admin']);

        $response->assertForbidden();
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    public function test_cancel_endpoint_returns_422_for_an_already_cancelled_offer(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->cancelViaHttp($document, ['type_key' => 'op-pert-admin']);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    private function adminSession(): array
    {
        return [
            'type_key' => 'op-pert-admin',
            'person_id' => 4242,
            'currentStatus' => ['canResponse' => true, 'clientQnidList' => []],
        ];
    }

    /**
     * #16: iptal artik terminal degil. Yonetici durum degistirdiginde teklif geri doner.
     * (#7'de bu islem reddediliyordu; karar musteri talebiyle tersine cevrildi.)
     */
    public function test_admin_status_change_revives_a_cancelled_offer(): void
    {
        $this->seedRegisterContentOptions();
        $this->optionFor('log-document-status-update', 'log');
        $this->optionFor('doc_trans_offer_approved', 'op-trans-op-doc-offer');
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);
        $this->actAsUser(['per-05-02'], 'op-pert-admin');

        $response = $this->withSession($this->adminSession())->postJson('/api/v1/trans/set-status', [
            'id' => $document->qnid,
            'op_key' => 'doc_trans_offer_approved',
            'note' => 'onayla',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $document->fresh()->status);
        $this->assertDatabaseCount('transactions', 1);
    }

    private function reopenViaHttp(Documents $document, array $session): TestResponse
    {
        return $this->withSession($session)->postJson('/api/v1/trans/reopen-offer', [
            'id' => $document->qnid,
        ]);
    }

    public function test_supplier_can_reopen_a_cancelled_offer_of_its_own_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0, 'client-a');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->reopenViaHttp($document, $this->supplierSession(['client-a']));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    public function test_reopening_does_not_touch_the_transaction_history(): void
    {
        $this->seedRegisterContentOptions();
        $this->optionFor('doc_trans_offer_approved', 'op-trans-op-doc-offer');
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0, 'client-a');

        // iptalden onceki durumu temsil eden transaction
        Transactions::create([
            'op_id' => 0,
            'type_id' => Sys_options::where('op_key', 'doc_trans_offer_approved')->value('id'),
            'log_id' => 0,
            'target_id' => $document->id,
            'note' => 'onceki durum',
            'description' => '-',
        ]);

        $this->actAsUser(['per-08-02'], 'op-pert-reseller');
        $this->reopenViaHttp($document, $this->supplierSession(['client-a']))->assertOk();

        // teklif eski durumuyla geri donmeli: yeni transaction yazilmamali
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', ['note' => 'onceki durum']);
    }

    public function test_supplier_cannot_reopen_an_offer_of_another_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0, 'client-b');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->reopenViaHttp($document, $this->supplierSession(['client-a']));

        $response->assertForbidden();
        $this->assertSame(0, (int) $document->fresh()->status);
    }

    public function test_reopening_an_active_offer_is_refused(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-a');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->reopenViaHttp($document, $this->supplierSession(['client-a']));

        $response->assertStatus(422);
    }

    public function test_reopening_writes_an_attributed_log_entry(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0, 'client-a');
        $user = $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $this->reopenViaHttp($document, $this->supplierSession(['client-a']))->assertOk();

        $log = UserLog::where('relation_id', $document->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertStringContainsString('Teklif Geri Açıldı', $log->description);
    }

    public function test_update_is_refused_on_a_cancelled_offer(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0);
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->withSession($this->adminSession())->putJson('/api/v1/document/'.$document->qnid, [
            'data' => json_encode(['typeKey' => 'op-doc-offer', 'dynamicF' => []]),
        ]);

        $response->assertStatus(422);
        $this->assertSame('Test op-doc-offer', $document->fresh()->title);
    }

    public function test_delete_is_refused_for_offers_so_cancelling_is_the_only_path(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->withSession($this->adminSession())
            ->deleteJson('/api/v1/document/'.$document->qnid);

        $response->assertForbidden();
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    /**
     * A freshly created offer only carries a doc_trans_created row, which lives in the "op-trans"
     * group rather than "op-trans-op-doc-offer", so its status aggregate is null. The revision
     * follow-up must cope with that instead of indexing end([]) === false.
     */
    public function test_updating_an_active_offer_without_offer_transactions_does_not_crash(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->withSession($this->adminSession())->putJson('/api/v1/document/'.$document->qnid, [
            'data' => json_encode(['typeKey' => 'op-doc-offer', 'dynamicF' => []]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    private function supplierSession(array $clients): array
    {
        return [
            'type_key' => 'op-pert-reseller',
            'person_id' => 4242,
            'currentStatus' => ['canResponse' => true, 'clientQnidList' => $clients],
        ];
    }

    public function test_supplier_can_read_an_offer_of_its_own_company(): void
    {
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-a');
        $this->actAsUser(['per-08-01'], 'op-pert-reseller');

        $response = $this->withSession($this->supplierSession(['client-a']))
            ->getJson('/api/v1/document/'.$document->qnid);

        $response->assertOk();
    }

    public function test_a_cancelled_offer_remains_readable_by_its_own_company(): void
    {
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 0, 'client-a');
        $this->actAsUser(['per-08-01'], 'op-pert-reseller');

        $response = $this->withSession($this->supplierSession(['client-a']))
            ->getJson('/api/v1/document/'.$document->qnid);

        $response->assertOk();
        $response->assertJsonPath('data.document.document_status', 0);
    }

    public function test_supplier_cannot_read_an_offer_of_another_company(): void
    {
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-b');
        $this->actAsUser(['per-08-01'], 'op-pert-reseller');

        $response = $this->withSession($this->supplierSession(['client-a']))
            ->getJson('/api/v1/document/'.$document->qnid);

        $response->assertForbidden();
    }

    /**
     * The api stack only starts a session for stateful (SPA) requests, so a plain
     * token-authenticated call arrives with session('type_key') === null. The ownership
     * check must treat an undeterminable role as "not an admin" and deny.
     */
    public function test_token_request_without_a_session_cannot_cancel_a_foreign_offer(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-victim');
        $this->actAsUserWithoutSession(['per-08-02']);

        $response = $this->postJson('/api/v1/trans/cancel-offer', [
            'id' => $document->qnid,
            'note' => 'x',
        ]);

        $response->assertForbidden();
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    public function test_supplier_without_any_bound_company_is_denied(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-a');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->cancelViaHttp($document, [
            'type_key' => 'op-pert-reseller',
            'currentStatus' => ['clientQnidList' => []],
        ]);

        $response->assertForbidden();
        $this->assertSame(1, (int) $document->fresh()->status);
    }

    /**
     * getFormData has no ORDER BY, so cliid may not live on the first op-doc-offer-form row.
     * Ownership must look at every row rather than whichever one Postgres returned first.
     */
    public function test_owner_keeps_access_when_cliid_is_not_on_the_first_form_row(): void
    {
        $docType = $this->optionFor('op-doc-offer', 'op-doc-forms');
        $formType = $this->optionFor('op-doc-offer-form', 'op-doc-forms');

        $document = Documents::create([
            'type_id' => $docType->id,
            'status' => 1,
            'title' => 'Test op-doc-offer',
            'person_id' => '-',
            'grp_code' => 'GDZ',
        ]);

        // first form row deliberately carries no cliid
        $firstConn = Sys_con_ops::create([
            'main_id' => $document->id, 'conn_id' => 0, 'type_id' => $formType->id, 'sub_type_id' => 0,
        ]);
        Sys_con_entities::create([
            'conn_id' => $firstConn->id, 'table_tag' => 'sys_con_ops',
            'entity_tag' => 'unit_price', 'entity_value' => '100',
        ]);

        $secondConn = Sys_con_ops::create([
            'main_id' => $document->id, 'conn_id' => 0, 'type_id' => $formType->id, 'sub_type_id' => 0,
        ]);
        Sys_con_entities::create([
            'conn_id' => $secondConn->id, 'table_tag' => 'sys_con_ops',
            'entity_tag' => 'cliid', 'entity_value' => 'client-a',
        ]);

        $this->actAsUser(['per-08-01'], 'op-pert-reseller');

        $response = $this->withSession($this->supplierSession(['client-a']))
            ->getJson('/api/v1/document/'.$document->qnid);

        $response->assertOk();
    }

    public function test_cancelling_preserves_the_offer_entities(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $this->actAsUser();

        $before = (new DocumentServiceProvider)->getFormData($document->qnid);
        (new DocumentServiceProvider)->cancelOffer($document->qnid, null);
        $after = (new DocumentServiceProvider)->getFormData($document->qnid);

        $entitiesBefore = array_values($before['formFormat']['op-doc-offer-form'])[0]['entities'];
        $entitiesAfter = array_values($after['formFormat']['op-doc-offer-form'])[0]['entities'];

        $this->assertSame($entitiesBefore, $entitiesAfter);
        $this->assertSame('client-qnid-1', $entitiesAfter['cliid']);
        $this->assertDatabaseCount('sys_con_entities', 1);
    }

    public function test_cancel_log_is_shaped_as_a_status_entry_with_attribution(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1);
        $user = $this->actAsUser();

        (new DocumentServiceProvider)->cancelOffer($document->qnid, 'müşteri vazgeçti');

        $log = UserLog::where('relation_id', $document->id)->first();
        $payload = json_decode($log->description, true);

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Teklif İptal Edildi', $payload['desc']);
        $this->assertSame('müşteri vazgeçti', $payload['note']);
        // the log timeline treats an entry as a status change only when "before" is absent
        $this->assertArrayNotHasKey('before', $payload);
    }

    public function test_cancel_endpoint_rejects_a_malformed_id(): void
    {
        $this->actAsUser(['per-08-02'], 'op-pert-admin');

        $response = $this->withSession($this->adminSession())
            ->postJson('/api/v1/trans/cancel-offer', ['id' => "not-a-uuid'"]);

        $response->assertStatus(422);
    }

    public function test_supplier_cannot_update_an_offer_of_another_company(): void
    {
        $this->seedRegisterContentOptions();
        $document = $this->seedDocument('op-doc-offer', 'op-doc-offer-form', 1, 'client-b');
        $this->actAsUser(['per-08-02'], 'op-pert-reseller');

        $response = $this->withSession($this->supplierSession(['client-a']))
            ->putJson('/api/v1/document/'.$document->qnid, [
                'data' => json_encode(['typeKey' => 'op-doc-offer', 'dynamicF' => []]),
            ]);

        $response->assertForbidden();
    }
}
