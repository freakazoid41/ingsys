<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeDocumentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function optionFor(string $opKey, string $groupKey): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            ['title' => $opKey, 'ttitle' => $opKey, 'ctitle' => $opKey, 'group_key' => $groupKey, 'status' => 1]
        );
    }

    /** Belge + bagli EAV/transaction/log satirlari. */
    private function seedDocument(string $typeKey): Documents
    {
        $type = $this->optionFor($typeKey, 'op-doc-forms');
        $formType = $this->optionFor($typeKey.'-form', 'op-doc-forms');

        $document = Documents::create([
            'type_id' => $type->id,
            'status' => 1,
            'title' => 'Test '.$typeKey,
            'person_id' => '-',
            'grp_code' => 'CATES',
        ]);

        $conn = Sys_con_ops::create([
            'main_id' => $document->id, 'conn_id' => 0,
            'type_id' => $formType->id, 'sub_type_id' => 0,
        ]);

        Sys_con_entities::create([
            'conn_id' => $conn->id, 'table_tag' => 'sys_con_ops',
            'entity_tag' => 'title', 'entity_value' => 'x',
        ]);

        Transactions::create([
            'op_id' => 0, 'type_id' => $type->id, 'log_id' => 0,
            'target_id' => $document->id, 'note' => '-', 'description' => '-',
        ]);

        UserLog::create([
            'user_id' => 0, 'sys_code' => 'CATES', 'relation' => 'documents',
            'relation_id' => $document->id, 'type_id' => 0, 'description' => '{}',
        ]);

        return $document;
    }

    public function test_dry_run_reports_but_deletes_nothing(): void
    {
        $this->seedDocument('op-doc-request');
        $this->seedDocument('op-doc-offer');

        $this->artisan('documents:purge --dry-run')
            ->expectsOutputToContain('KURU CALISMA')
            ->assertSuccessful();

        $this->assertDatabaseCount('documents', 2);
        $this->assertDatabaseCount('sys_con_ops', 2);
        $this->assertDatabaseCount('sys_con_entities', 2);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_purge_removes_documents_and_every_related_row(): void
    {
        $this->seedDocument('op-doc-request');
        $this->seedDocument('op-doc-offer');

        $this->artisan('documents:purge --force')->assertSuccessful();

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('sys_con_ops', 0);
        $this->assertDatabaseCount('sys_con_entities', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('user_logs', 0);
    }

    public function test_clients_are_never_touched(): void
    {
        $client = $this->seedDocument('op-doc-client');
        $this->seedDocument('op-doc-offer');

        $this->artisan('documents:purge --force')->assertSuccessful();

        $this->assertDatabaseHas('documents', ['id' => $client->id]);
        $this->assertDatabaseCount('documents', 1);
        // firmanin EAV satirlari da yerinde durmali
        $this->assertDatabaseCount('sys_con_ops', 1);
        $this->assertDatabaseCount('sys_con_entities', 1);
    }

    public function test_type_option_narrows_the_scope(): void
    {
        $request = $this->seedDocument('op-doc-request');
        $this->seedDocument('op-doc-offer');

        $this->artisan('documents:purge --type=offer --force')->assertSuccessful();

        $this->assertDatabaseHas('documents', ['id' => $request->id]);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_unknown_type_is_rejected_without_deleting(): void
    {
        $this->seedDocument('op-doc-offer');

        $this->artisan('documents:purge --type=firma --force')->assertFailed();

        $this->assertDatabaseCount('documents', 1);
    }
}
