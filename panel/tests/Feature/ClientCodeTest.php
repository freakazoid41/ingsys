<?php

namespace Tests\Feature;

use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\User;
use App\Providers\DocumentServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Firma kodu (clicode) backend tarafindan uretilir ve istemciden gelen deger
 * hicbir zaman onu ezmez. Kod, firma belgesinin qnid'idir.
 */
class ClientCodeTest extends TestCase
{
    use RefreshDatabase;

    private function optionFor(string $opKey, string $groupKey, ?string $ctitle = null): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            [
                'title' => $opKey,
                'ttitle' => $opKey,
                'ctitle' => $ctitle ?? $opKey,
                'group_key' => $groupKey,
                'status' => 1,
            ]
        );
    }

    private function seedFormOptions(): void
    {
        $this->optionFor('op-doc-client', 'op-doc-forms');
        $this->optionFor('op-doc-client-form', 'op-doc-forms', 'type_id');
        $this->optionFor('log-tender-update', 'log');
        $this->optionFor('form-main', 'op-form', 'sub_type_id');
        $this->optionFor('form-file', 'op-form', 'sub_type_id');
        // registerContent her yeni belge icin bu transaction'i yaziyor
        $this->optionFor('doc_trans_created', 'op-trans');
    }

    private function actAsAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'role' => 'admin',
            'email' => 'admin'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'person_id' => 4242,
        ]);

        session(['type_key' => 'op-pert-admin', 'person_id' => 4242]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function clientPayload(array $entities): array
    {
        return [
            'typeKey' => 'op-doc-client',
            'dynamicF' => [
                'op-doc-client-form**new-'.uniqid() => [
                    'tag' => 'op-doc-client-form',
                    'entities' => $entities,
                ],
            ],
        ];
    }

    private function storedCode(Documents $document): ?string
    {
        return Sys_con_entities::query()
            ->join('sys_con_ops', 'sys_con_ops.id', '=', 'sys_con_entities.conn_id')
            ->where('sys_con_ops.main_id', $document->id)
            ->where('sys_con_entities.entity_tag', 'clicode')
            ->value('sys_con_entities.entity_value');
    }

    public function test_manual_client_creation_gets_the_document_qnid_as_its_code(): void
    {
        $this->seedFormOptions();
        $this->actAsAdmin();

        $result = (new DocumentServiceProvider)->registerContent(
            0,
            $this->clientPayload(['title' => 'ABC Kömür A.Ş.']),
            []
        );

        $document = Documents::find($result['id']);
        $this->assertNotNull($document);
        $this->assertSame($document->qnid, $this->storedCode($document));
    }

    public function test_client_supplied_code_cannot_override_the_generated_one(): void
    {
        $this->seedFormOptions();
        $this->actAsAdmin();

        $result = (new DocumentServiceProvider)->registerContent(
            0,
            $this->clientPayload(['title' => 'ABC Kömür A.Ş.', 'clicode' => 'ELLE-YAZILAN-KOD']),
            []
        );

        $document = Documents::find($result['id']);
        $this->assertSame($document->qnid, $this->storedCode($document));
        $this->assertNotSame('ELLE-YAZILAN-KOD', $this->storedCode($document));
    }

    public function test_updating_a_client_never_regenerates_or_overwrites_the_code(): void
    {
        $this->seedFormOptions();
        $this->actAsAdmin();

        $created = (new DocumentServiceProvider)->registerContent(
            0,
            $this->clientPayload(['title' => 'ABC Kömür A.Ş.']),
            []
        );
        $document = Documents::find($created['id']);
        $originalCode = $this->storedCode($document);

        // ayni baglantiyi guncelle, istemci farkli bir kod gondersin
        $connId = Sys_con_ops::where('main_id', $document->id)->value('id');
        (new DocumentServiceProvider)->registerContent(
            $document->qnid,
            [
                'typeKey' => 'op-doc-client',
                'dynamicF' => [
                    'op-doc-client-form**'.$connId => [
                        'tag' => 'op-doc-client-form',
                        'entities' => ['title' => 'ABC Kömür Ltd.', 'clicode' => 'BASKA-KOD'],
                    ],
                ],
            ],
            []
        );

        $this->assertSame($originalCode, $this->storedCode($document));
    }
}
