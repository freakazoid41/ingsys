<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Sys_options;
use App\Models\User;
use App\Providers\DocumentServiceProvider;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetStatusRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function seedDocumentAndOptions(): Documents
    {
        $docType = Sys_options::create([
            'title'     => 'Teklif',
            'ttitle'    => 'Teklif',
            'ctitle'    => 'Teklif',
            'op_key'    => 'op-doc-offer',
            'group_key' => 'op-doc-forms',
            'status'    => 1,
        ]);

        Sys_options::create([
            'title'     => 'Durum Değiştirildi',
            'ttitle'    => 'Durum Değiştirildi',
            'ctitle'    => 'Durum Değiştirildi',
            'op_key'    => 'log-document-status-update',
            'group_key' => 'log',
            'status'    => 1,
        ]);

        Sys_options::create([
            'title'     => 'Teklif Onaylandı',
            'ttitle'    => 'Teklif Onaylandı',
            'ctitle'    => 'Teklif Onaylandı',
            'op_key'    => 'doc_trans_offer_approved',
            'group_key' => 'op-trans-op-doc-offer',
            'status'    => 1,
        ]);

        return Documents::create([
            'type_id'   => $docType->id,
            'status'    => 1,
            'qnid'      => 'test-offer-doc',
            'title'     => 'Test Teklif',
            'person_id' => '-',
            'grp_code'  => 'GDZ',
        ]);
    }

    public function test_set_status_with_unknown_op_key_returns_meaningful_error(): void
    {
        $doc = $this->seedDocumentAndOptions();
        Sanctum::actingAs(User::create([
            'name'     => 'Admin',
            'role'     => 'admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
        ]), ['*']);

        $result = (new DocumentServiceProvider())->setStatus($doc->qnid, 'doc_trans_offer_accepted', 'test');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Bilinmeyen durum kodu', $result['msg']);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_set_status_with_approved_op_key_transitions_successfully(): void
    {
        $doc = $this->seedDocumentAndOptions();
        Sanctum::actingAs(User::create([
            'name'     => 'Admin',
            'role'     => 'admin',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
        ]), ['*']);

        $result = (new DocumentServiceProvider())->setStatus($doc->qnid, 'doc_trans_offer_approved', 'onaylandı');

        $this->assertTrue($result['success']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'target_id' => $doc->id,
            'note'      => 'onaylandı',
        ]);
    }

    public function test_set_status_via_http_with_unknown_op_key_returns_422_not_500(): void
    {
        $doc = $this->seedDocumentAndOptions();
        $user = User::create([
            'name'      => 'Admin',
            'role'      => 'admin',
            'email'     => 'admin@test.local',
            'password'  => bcrypt('password'),
            'person_id' => 5555,
        ]);

        (new PermissionService())->cacheUserPermissions(5555, ['per-05-02']);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/trans/set-status', [
            'id'     => $doc->qnid,
            'op_key' => 'doc_trans_offer_accepted',
            'note'   => 'test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('user_logs', 0);
    }
}
