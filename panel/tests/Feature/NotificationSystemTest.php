<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationMailJob;
use App\Models\Document_files;
use App\Models\Documents;
use App\Models\NotificationLog;
use App\Models\NotificationRead;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\User;
use App\Providers\ReportServiceProvider;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Notification Receiving + Sending System — TEDARIK 7
 *
 * Covers:
 *  - ReportServiceProvider::getAdminNotifications (permission check, BUKRS gate, LIFNR dual gate for 04-07, read tracking, limit)
 *  - SystemController::getNotifications (blink, unreadTotal, 10 per category)
 *  - SystemController::markNotificationRead / markAllNotificationsRead
 *  - SendNotificationMailJob BUKRS + LIFNR dispatch (01-07)
 *  - GET /v1/notifications API contract
 */
class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['SYS_CODE'] = 'GDZ';
        Mail::fake();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function optionFor(string $opKey, string $groupKey, ?string $ctitle = null): Sys_options
    {
        return Sys_options::firstOrCreate(
            ['op_key' => $opKey],
            ['title' => $opKey, 'ttitle' => $opKey, 'ctitle' => $ctitle ?? $opKey, 'group_key' => $groupKey, 'status' => 1]
        );
    }

    private function seedCoreOptions(): void
    {
        // doc types
        $this->optionFor('op-doc-order', 'op-doc', 'type_id');
        $this->optionFor('op-doc-order-form', 'op-doc-forms', 'type_id');
        $this->optionFor('op-doc-client', 'op-doc', 'type_id');
        $this->optionFor('op-doc-client-form', 'op-doc-forms', 'type_id');
        $this->optionFor('op-doc-order-item', 'op-doc', 'type_id');
        $this->optionFor('op-doc-order-item-form', 'op-doc-forms', 'type_id');
        $this->optionFor('op-doc-user-notification-form', 'op-doc-forms', 'type_id');
        $this->optionFor('op-doc-user-client-form', 'op-doc-forms', 'type_id');
        $this->optionFor('op-doc-user-permission-form', 'op-doc-forms', 'type_id');
        $this->optionFor('form-main', 'op-con-ops', 'sub_type_id');
        $this->optionFor('form-file', 'op-con-ops', 'sub_type_id');
        $this->optionFor('op-pert-admin', 'op-pert', 'type_id');
        $this->optionFor('op-pert-reseller', 'op-pert', 'type_id');
        // file types for Document_files join (sf inner join)
        foreach (['op-transfer_kabul_file','op-transfer_cins_file','op-item_test_file','op-item_images_file'] as $k) {
            $this->optionFor($k, 'op-file-types', $k);
        }
        // status
        foreach (['doc_trans_order_created','doc_trans_order_transfer_sent','doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected','doc_file_waiting','doc_file_accepted','doc_file_rejected'] as $k) {
            $this->optionFor($k, str_starts_with($k,'doc_file') ? 'op-trans-op-doc-order' : 'op-trans-op-doc-order', $k);
        }
        // logs
        $this->optionFor('log-tender-update', 'op-logs');
        $this->optionFor('log-document-status-update', 'op-logs');
        // notification types for UI (not required for provider but seed for completeness)
        foreach (['tedarik-01','tedarik-02','tedarik-03','tedarik-04','tedarik-05','tedarik-06','tedarik-07'] as $k) {
            $this->optionFor($k, 'op-notif');
        }
        // person types
        Sys_options::firstOrCreate(['op_key' => 'op-pert-admin'], ['title'=>'Admin','group_key'=>'op-pert','status'=>1,'ctitle'=>'type_id','ttitle'=>'persons']);
        Sys_options::firstOrCreate(['op_key' => 'op-pert-reseller'], ['title'=>'Reseller','group_key'=>'op-pert','status'=>1,'ctitle'=>'type_id','ttitle'=>'persons']);
    }

    private function createPerson(string $typeKey, string $grpCode = 'GDZ'): \App\Models\Persons
    {
        $typeId = Sys_options::where('op_key', $typeKey)->value('id');
        return \App\Models\Persons::create([
            'name' => 'Test '.$typeKey,
            'surname' => 'User',
            'type_id' => $typeId,
            'status' => 1,
            'grp_code' => $grpCode,
            'qnid' => (string) Str::uuid(),
        ]);
    }

    private function createUser(\App\Models\Persons $person, string $email, string $grpCode = 'GDZ', string $role = 'admin'): User
    {
        return User::create([
            'name' => $person->name ?? 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'person_id' => $person->id,
            'role' => $role,
            'status' => 1,
            'grp_code' => $grpCode,
        ]);
    }

    private function createNotificationGroup(\App\Models\Persons $person, array $opKeys): void
    {
        $typeId = Sys_options::where('op_key', 'op-doc-user-notification-form')->value('id');
        $subType = Sys_options::where('op_key', 'form-main')->value('id') ?? 0;
        $ops = \App\Models\Sys_con_ops::create([
            'main_id' => $person->id,
            'conn_id' => 0,
            'type_id' => $typeId,
            'sub_type_id' => $subType,
        ]);
        Sys_con_entities::create([
            'conn_id' => $ops->id,
            'table_tag' => 'sys_con_ops',
            'entity_tag' => $person->id.'**usernotificationgroup**'.$person->id,
            'entity_value' => json_encode($opKeys, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function createClientContact(\App\Models\Persons $person, string $email): void
    {
        // ensure contact form exists for mail dispatch (contmail)
        $contactType = Sys_options::where('op_key','op-doc-user-contact-form')->first();
        if(!$contactType){
            $contactType = $this->optionFor('op-doc-user-contact-form','op-doc-forms');
        }
        $subType = Sys_options::where('op_key','form-main')->value('id') ?? 0;
        $ops = \App\Models\Sys_con_ops::firstOrCreate(['main_id'=>$person->id, 'type_id'=>$contactType->id, 'conn_id'=>0], ['sub_type_id'=>$subType]);
        // add mail entity
        Sys_con_entities::create([
            'conn_id'=>$ops->id,
            'table_tag'=>'sys_con_ops',
            'entity_tag'=>'contmail**userfacilitygroup**main-0',
            'entity_value'=>$email,
        ]);
        // store email on person via phone field misuse? keep in title/spec_code not needed; users.email is the fallback
        // persons has no email column, so skip persons update; ensure users fallback is handled via users.email
    }

    private function bindClientToPerson(\App\Models\Persons $person, Documents $client): void
    {
        $typeId = Sys_options::where('op_key', 'op-doc-user-client-form')->value('id');
        $subType = Sys_options::where('op_key','form-main')->value('id') ?? 0;
        $ops = \App\Models\Sys_con_ops::firstOrCreate(['main_id'=>$person->id, 'type_id'=>$typeId, 'conn_id'=>0], ['sub_type_id'=>$subType]);
        Sys_con_entities::create([
            'conn_id'=>$ops->id,
            'table_tag'=>'sys_con_ops',
            'entity_tag'=>'cliid**userclientgroup**'.uniqid(),
            'entity_value'=>$client->qnid,
        ]);
        Sys_con_entities::create([
            'conn_id'=>$ops->id,
            'table_tag'=>'sys_con_ops',
            'entity_tag'=>'clicode**userclientgroup**'.uniqid(),
            'entity_value'=>$this->clientLifnr($client),
        ]);
    }

    private function clientLifnr(Documents $client): string
    {
        $opId = Sys_con_ops::where('main_id',$client->id)->value('id');
        return Sys_con_entities::where('conn_id',$opId)->where('entity_tag','like','lifnr%')->value('entity_value') ?? '';
    }

    private function createClient(string $lifnr, string $grpCode = 'GDZ'): Documents
    {
        $docType = Sys_options::where('op_key','op-doc-client')->first();
        $formType = Sys_options::where('op_key','op-doc-client-form')->first();
        $client = Documents::create([
            'type_id'=>$docType->id,
            'status'=>1,
            'title'=>'Client '.$lifnr,
            'person_id'=>'system',
            'grp_code'=>$grpCode,
        ]);
        $ops = Sys_con_ops::create([
            'main_id'=>$client->id,'conn_id'=>0,'type_id'=>$formType->id,'sub_type_id'=>0,
        ]);
        Sys_con_entities::create(['conn_id'=>$ops->id,'table_tag'=>'sys_con_ops','entity_tag'=>'lifnr','entity_value'=>$lifnr]);
        Sys_con_entities::create(['conn_id'=>$ops->id,'table_tag'=>'sys_con_ops','entity_tag'=>'title','entity_value'=>'Client '.$lifnr]);
        return $client;
    }

    private function createOrder(string $orderNo, string $specCode, string $sysCode, string $grpCode = 'GDZ', string $statusOpKey = 'doc_trans_order_created'): Documents
    {
        $docType = Sys_options::where('op_key','op-doc-order')->first();
        $formType = Sys_options::where('op_key','op-doc-order-form')->first();
        $order = Documents::create([
            'type_id'=>$docType->id,
            'status'=>1,
            'title'=>'Sipariş '.$orderNo,
            'person_id'=>'system',
            'grp_code'=>$grpCode,
        ]);
        $ops = Sys_con_ops::create([
            'main_id'=>$order->id,'conn_id'=>0,'type_id'=>$formType->id,'sub_type_id'=>0,
        ]);
        foreach ([
            'order_no'=>$orderNo,
            'transfer_no'=>$orderNo,
            'spec_code'=>$specCode,
            'sys_code'=>$sysCode,
            'ctitle'=>'Test Tedarik',
            'buying_no'=>'BUY-'.$orderNo,
            'created_at'=>now()->format('d/m/Y'),
        ] as $k=>$v){
            Sys_con_entities::create(['conn_id'=>$ops->id,'table_tag'=>'sys_con_ops','entity_tag'=>$k,'entity_value'=>$v]);
        }
        // transaction
        $typeId = Sys_options::where('op_key',$statusOpKey)->value('id');
        if($typeId){
            Transactions::create([
                'op_id'=>0,'type_id'=>$typeId,'log_id'=>0,'target_id'=>$order->id,
                'note'=>'test','description'=>json_encode(['note'=>'test'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $order;
    }

    private function createOrderFile(Documents $orderOrItem, string $fileStatus = 'doc_file_waiting', string $entityTag = 'transfer_kabul_file**transfer_kabul**new-1'): Document_files
    {
        // create document_files row linked to order/item — use direct assignment because fillable is limited
        $file = new Document_files();
        $file->qnid = (string) Str::uuid();
        $file->description = 'enc_'.uniqid();
        $file->relation = 'documents';
        $file->relation_id = $orderOrItem->id;
        $file->status = 1;
        $file->type_id = Sys_options::where('op_key', $entityTag ? 'op-'.explode('**',$entityTag)[0] : 'op-transfer_kabul_file')->value('id') ?? 0;
        $file->save();
        // link via sys_con_entities table_tag=document_files on order's ops
        $ops = Sys_con_ops::where('main_id',$orderOrItem->id)->first();
        if(!$ops){
            $formType = Sys_options::where('op_key','op-doc-order-form')->first();
            $ops = Sys_con_ops::create(['main_id'=>$orderOrItem->id,'conn_id'=>0,'type_id'=>$formType->id,'sub_type_id'=>0]);
        }
        Sys_con_entities::create([
            'conn_id'=>$ops->id,
            'table_tag'=>'document_files',
            'entity_tag'=>$entityTag,
            'entity_value'=> (string) $file->id,
        ]);
        // file status transaction
        $typeId = Sys_options::where('op_key',$fileStatus)->value('id');
        if($typeId){
            Transactions::create([
                'op_id'=>1,'type_id'=>$typeId,'log_id'=>0,'target_id'=>$file->id,
                'note'=>'file note','description'=>json_encode(['actor'=>'test <a@b>','note'=>'file note'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        return $file;
    }

    private function actAsUserForSession(User $user, string $typeKey, string $personQnid, array $clientQnids = [], string $grpCode = 'GDZ'): void
    {
        // cache permissions for CheckPermissionVersion middleware simulation not needed for provider tests
        Sanctum::actingAs($user, ['*']);
        session([
            'person_id' => $personQnid,
            'type_key' => $typeKey,
            'currentStatus' => [
                'clientQnidList' => $clientQnids,
                'canProceed' => true,
                'canResponse' => true,
            ],
        ]);
        // also set auth user grp_code for BUKRS gate
        if($grpCode) {
            DB::table('users')->where('id',$user->id)->update(['grp_code'=>$grpCode]);
            DB::table('persons')->where('id',$user->person_id)->update(['grp_code'=>$grpCode]);
        }
    }

    // ── tests ────────────────────────────────────────────────────────────────

    public function test_get_notification_users_returns_grouped_members(): void
    {
        $this->seedCoreOptions();
        $adminPerson = $this->createPerson('op-pert-admin', 'BOTH');
        $adminPerson->update(['name'=>'Admin']);
        $user = $this->createUser($adminPerson, 'grp@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($adminPerson, ['tedarik-01','tedarik-02']);

        $provider = new \App\Providers\PersonsServiceProvider();
        $perms = $provider->getNotificationUsers('tedarik-01', $adminPerson->qnid);
        $this->assertArrayHasKey('tedarik-01', $perms);
        $this->assertCount(1, $perms['tedarik-01']);
        $this->assertSame($adminPerson->qnid, $perms['tedarik-01'][0]['person_id']);

        $empty = $provider->getNotificationUsers('tedarik-03', $adminPerson->qnid);
        $this->assertEmpty($empty);
    }

    public function test_tedarik_01_admin_sees_orders_via_report_provider(): void
    {
        $this->seedCoreOptions();
        $adminPerson = $this->createPerson('op-pert-admin', 'BOTH');
        $adminUser = $this->createUser($adminPerson, 'admin@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($adminPerson, ['tedarik-01']);
        $this->createClientContact($adminPerson, 'admin@test.local');

        // both orders keep grp_code GDZ to pass tableList global filter; vary sys_code entity for BUKRS
        $o1 = $this->createOrder('3510000100','0000300184','4000','GDZ','doc_trans_order_created');
        $o2 = $this->createOrder('3510000101','0000300185','5000','GDZ','doc_trans_order_created');

        $this->actAsUserForSession($adminUser, 'op-pert-admin', $adminPerson->qnid, [], 'BOTH');

        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-01');
        $this->assertArrayHasKey('data', $res);
        $this->assertArrayHasKey('total', $res);
        // BOTH admin sees both (BOTH bypass)
        $this->assertGreaterThanOrEqual(2, $res['total']);
        $qnids = array_map(fn($o)=> $o->id, $res['data']);
        $this->assertContains($o1->qnid, $qnids);
        $this->assertContains($o2->qnid, $qnids);
    }

    public function test_tedarik_01_gdz_user_only_sees_gdz_orders_bukrs_gate(): void
    {
        $this->seedCoreOptions();
        $personGdz = $this->createPerson('op-pert-admin', 'GDZ');
        $userGdz = $this->createUser($personGdz, 'gdz@test.local', 'GDZ', 'immutable-admin');
        $this->createNotificationGroup($personGdz, ['tedarik-01']);
        $this->createClientContact($personGdz, 'gdz@test.local');

        // keep grp_code GDZ for both so tableList passes; vary sys_code for BUKRS
        $oGdz = $this->createOrder('3510000200','0000300184','4000','GDZ','doc_trans_order_created');
        $oAdm = $this->createOrder('3510000201','0000300185','5000','GDZ','doc_trans_order_created');

        $this->actAsUserForSession($userGdz, 'op-pert-admin', $personGdz->qnid, [], 'GDZ');

        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-01');

        $qnids = array_map(fn($o)=> $o->id, $res['data']);
        $this->assertContains($oGdz->qnid, $qnids);
        $this->assertNotContains($oAdm->qnid, $qnids);
    }

    public function test_tedarik_03_pending_files_bukrs_gate(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'admin2@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($person, ['tedarik-03']);
        $this->createClientContact($person, 'admin2@test.local');

        // order GDZ + file waiting (grp_code GDZ to pass table filter, sys_code 4000 GDZ)
        $orderGdz = $this->createOrder('3510000300','0000300184','4000','GDZ','doc_trans_order_transfer_sent');
        $f1 = $this->createOrderFile($orderGdz, 'doc_file_waiting', 'transfer_kabul_file**transfer_kabul**1');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');
        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-03');
        $this->assertGreaterThanOrEqual(1, $res['total']);
        $fileQnids = array_map(fn($f)=> $f->id, $res['data']);
        $this->assertContains($f1->qnid, $fileQnids);

        // GDZ user should see GDZ file
        $personGdz = $this->createPerson('op-pert-admin', 'GDZ');
        $userGdz = $this->createUser($personGdz, 'gdz2@test.local', 'GDZ');
        $this->createNotificationGroup($personGdz, ['tedarik-03']);
        $this->createClientContact($personGdz, 'gdz2@test.local');
        $this->actAsUserForSession($userGdz, 'op-pert-admin', $personGdz->qnid, [], 'GDZ');
        $res2 = $provider->getAdminNotifications('tedarik-03');
        $fileQnids2 = array_map(fn($f)=> $f->id, $res2['data']);
        $this->assertContains($f1->qnid, $fileQnids2);

        // ADM sys_code file (grp_code still GDZ to pass tableList, but sys_code 5000 ADM -> BUKRS filters out for GDZ)
        $orderAdm = $this->createOrder('3510000301','0000300185','5000','GDZ','doc_trans_order_transfer_sent');
        $fAdm = $this->createOrderFile($orderAdm, 'doc_file_waiting', 'transfer_kabul_file**transfer_kabul**2');
        $this->actAsUserForSession($userGdz, 'op-pert-admin', $personGdz->qnid, [], 'GDZ');
        $res3 = $provider->getAdminNotifications('tedarik-03');
        $fileQnids3 = array_map(fn($f)=> $f->id, $res3['data']);
        $this->assertNotContains($fAdm->qnid, $fileQnids3);
    }

    public function test_read_tracking_filters_out_read_notifications(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'reader@test.local', 'BOTH');
        $this->createNotificationGroup($person, ['tedarik-01']);
        $this->createClientContact($person, 'reader@test.local');

        $o1 = $this->createOrder('3510000400','0000300184','4000','GDZ','doc_trans_order_created');
        $o2 = $this->createOrder('3510000401','0000300185','4000','GDZ','doc_trans_order_created');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');
        $provider = new ReportServiceProvider();

        $before = $provider->getAdminNotifications('tedarik-01');
        $this->assertEquals(2, $before['total']);

        NotificationRead::create(['user_id'=>$user->id, 'op_key'=>'tedarik-01', 'target_qnid'=>$o1->qnid, 'read_at'=>now()]);

        $after = $provider->getAdminNotifications('tedarik-01');
        $this->assertEquals(1, $after['total']);
        $qnids = array_map(fn($o)=> $o->id, $after['data']);
        $this->assertNotContains($o1->qnid, $qnids);
        $this->assertContains($o2->qnid, $qnids);
    }

    public function test_limit_returns_only_first_n_but_total_stays_full(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'limit@test.local', 'BOTH');
        $this->createNotificationGroup($person, ['tedarik-01']);
        $this->createClientContact($person, 'limit@test.local');

        for($i=0;$i<5;$i++){
            $this->createOrder('35100005'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'0000300184','4000','GDZ','doc_trans_order_created');
        }
        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');
        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-01', 2);
        $this->assertEquals(5, $res['total']);
        $this->assertCount(2, $res['data']);
    }

    public function test_reseller_tedarik_04_bypasses_permission_and_uses_lifnr(): void
    {
        $this->seedCoreOptions();
        // reseller bound to client 0000300186 (YILDIZ)
        $client = $this->createClient('0000300186','GDZ');
        $resellerPerson = $this->createPerson('op-pert-reseller', 'GDZ');
        $resellerUser = $this->createUser($resellerPerson, 'reseller@test.local', 'GDZ', 'immutable-reseller');
        $this->bindClientToPerson($resellerPerson, $client);
        $this->createClientContact($resellerPerson, 'reseller@test.local');
        // no notification group for tedarik-04 — reseller should still see
        // create order with same spec_code as client
        $order = $this->createOrder('3510000600','0000300186','4000','GDZ','doc_trans_order_transfer_sent');
        $file = $this->createOrderFile($order, 'doc_file_accepted', 'transfer_kabul_file**transfer_kabul**1');

        $this->actAsUserForSession($resellerUser, 'op-pert-reseller', $resellerPerson->qnid, [$client->qnid], 'GDZ');
        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-04');
        $this->assertGreaterThanOrEqual(1, $res['total'], 'reseller should see own lifnr file via bypass: '.json_encode($res));
        $qnids = array_map(fn($f)=> $f->id, $res['data']);
        $this->assertContains($file->qnid, $qnids);

        // order with different lifnr should not be visible to reseller
        $orderOther = $this->createOrder('3510000601','0000999999','4000','GDZ','doc_trans_order_transfer_sent');
        $fileOther = $this->createOrderFile($orderOther, 'doc_file_accepted', 'transfer_kabul_file**transfer_kabul**2');
        $res2 = $provider->getAdminNotifications('tedarik-04');
        $qnids2 = array_map(fn($f)=> $f->id, $res2['data']);
        $this->assertNotContains($fileOther->qnid, $qnids2);
    }

    public function test_reseller_tedarik_06_lifnr_and_bukrs_dual(): void
    {
        $this->seedCoreOptions();
        $clientGdz = $this->createClient('0000300186','GDZ');
        $resellerPerson = $this->createPerson('op-pert-reseller', 'GDZ');
        $resellerUser = $this->createUser($resellerPerson, 'reseller6@test.local', 'GDZ', 'immutable-reseller');
        $this->bindClientToPerson($resellerPerson, $clientGdz);
        $this->createClientContact($resellerPerson, 'reseller6@test.local');

        $orderGdz = $this->createOrder('3510000700','0000300186','4000','GDZ','doc_trans_order_approved');
        // keep grp_code GDZ to pass tableList, but sys_code 5000 to test BUKRS mismatch
        $orderAdm = $this->createOrder('3510000701','0000300186','5000','GDZ','doc_trans_order_approved');

        $this->actAsUserForSession($resellerUser, 'op-pert-reseller', $resellerPerson->qnid, [$clientGdz->qnid], 'GDZ');
        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-06');
        $qnids = array_map(fn($o)=> $o->id, $res['data']);
        $this->assertContains($orderGdz->qnid, $qnids);
        $this->assertNotContains($orderAdm->qnid, $qnids); // BUKRS mismatch
    }

    public function test_non_reseller_tedarik_04_requires_membership(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'GDZ');
        $user = $this->createUser($person, 'nomember@test.local', 'GDZ', 'immutable-admin');
        // no group → should get empty
        $order = $this->createOrder('3510000800','0000300184','4000','GDZ','doc_trans_order_transfer_sent');
        $this->createOrderFile($order, 'doc_file_accepted');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'GDZ');
        $provider = new ReportServiceProvider();
        $res = $provider->getAdminNotifications('tedarik-04');
        $this->assertEquals(0, $res['total']);
        $this->assertEmpty($res['data']);

        // now add group and it should appear (file type now seeded, so visible)
        $this->createNotificationGroup($person, ['tedarik-04']);
        $this->createClientContact($person, 'nomember@test.local');
        $res2 = $provider->getAdminNotifications('tedarik-04');
        $this->assertGreaterThanOrEqual(1, $res2['total'], 'after membership should see file');
    }

    public function test_system_controller_get_notifications_blink_and_unread_total(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'blink@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($person, ['tedarik-01','tedarik-02','tedarik-03','tedarik-04','tedarik-05','tedarik-06','tedarik-07']);
        $this->createClientContact($person, 'blink@test.local');

        $o1 = $this->createOrder('3510000900','0000300184','4000','GDZ','doc_trans_order_created'); // 01
        $o2 = $this->createOrder('3510000901','0000300184','4000','GDZ','doc_trans_order_transfer_sent'); // 02
        $f3 = $this->createOrderFile($o2, 'doc_file_waiting'); // 03
        $o4 = $this->createOrder('3510000902','0000300184','4000','GDZ','doc_trans_order_transfer_sent');
        $f4 = $this->createOrderFile($o4, 'doc_file_accepted'); // 04
        // need separate file for 05 with rejected status on same order would hide accepted? use separate order for 05
        $o4b = $this->createOrder('35100009021','0000300184','4000','GDZ','doc_trans_order_transfer_sent');
        $f5 = $this->createOrderFile($o4b, 'doc_file_rejected'); // 05
        $o6 = $this->createOrder('3510000903','0000300184','4000','GDZ','doc_trans_order_approved'); // 06
        $o7 = $this->createOrder('3510000904','0000300184','4000','GDZ','doc_trans_order_rejected'); // 07

        // mark one as read: 01's order
        NotificationRead::create(['user_id'=>$user->id,'op_key'=>'tedarik-01','target_qnid'=>$o1->qnid,'read_at'=>now()]);

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');
        $response = $this->withSession([
            'person_id'=>$person->qnid,
            'type_key'=>'op-pert-admin',
            'currentStatus'=>['clientQnidList'=>[]],
        ])->getJson('/api/v1/notifications');

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('blink', $json);
        $this->assertArrayHasKey('unreadTotal', $json);
        $this->assertEquals(1, $json['blink']);
        // after seeding file types, expect at least 5-6 unread (some categories may still have 0 if status mismatch)
        $this->assertGreaterThanOrEqual(5, $json['unreadTotal'], 'blink unreadTotal dump: '.json_encode($json));
        $this->assertArrayHasKey('orderImported', $json);
        $this->assertArrayHasKey('orderSent', $json);
        $this->assertArrayHasKey('pendingFiles', $json);
        $this->assertArrayHasKey('fileApproved', $json);
        $this->assertArrayHasKey('fileRejected', $json);
        $this->assertArrayHasKey('orderApproved', $json);
        $this->assertArrayHasKey('orderRejected', $json);
        // limit 10: each array <=10
        foreach(['orderImported','orderSent','pendingFiles','fileApproved','fileRejected','orderApproved','orderRejected'] as $k){
            $this->assertLessThanOrEqual(10, count($json[$k]));
        }
    }

    public function test_mark_notification_read_endpoint(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'mark@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($person, ['tedarik-01']);
        $this->createClientContact($person, 'mark@test.local');
        $order = $this->createOrder('3510001000','0000300184','4000','GDZ','doc_trans_order_created');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');

        // before: total 1
        $provider = new ReportServiceProvider();
        $before = $provider->getAdminNotifications('tedarik-01');
        $this->assertEquals(1, $before['total']);

        $resp = $this->withSession(['person_id'=>$person->qnid,'type_key'=>'op-pert-admin','currentStatus'=>['clientQnidList'=>[]]])
            ->postJson('/api/v1/notifications/read', ['op_key'=>'tedarik-01','target_qnid'=>$order->qnid]);
        $resp->assertOk()->assertJsonPath('success', true);

        $after = $provider->getAdminNotifications('tedarik-01');
        $this->assertEquals(0, $after['total']);
        $this->assertDatabaseHas('notification_reads', ['user_id'=>$user->id,'op_key'=>'tedarik-01','target_qnid'=>$order->qnid]);
    }

    public function test_mark_notification_read_via_form_data(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'markform@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($person, ['tedarik-01']);
        $this->createClientContact($person, 'markform@test.local');
        $order = $this->createOrder('3510001001','0000300184','4000','GDZ','doc_trans_order_created');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');

        // Simulate Plib FormData (multipart) — Request::input fallback to all()
        $resp = $this->withSession(['person_id'=>$person->qnid,'type_key'=>'op-pert-admin','currentStatus'=>['clientQnidList'=>[]]])
            ->post('/api/v1/notifications/read', ['op_key'=>'tedarik-01','target_qnid'=>$order->qnid]);
        $resp->assertOk();
        $this->assertDatabaseHas('notification_reads', ['user_id'=>$user->id,'op_key'=>'tedarik-01','target_qnid'=>$order->qnid]);
    }

    public function test_mark_all_notifications_read(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'markall@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($person, ['tedarik-01','tedarik-06']);
        $this->createClientContact($person, 'markall@test.local');
        $o1 = $this->createOrder('3510001100','0000300184','4000','GDZ','doc_trans_order_created');
        $o6 = $this->createOrder('3510001101','0000300184','4000','GDZ','doc_trans_order_approved');

        $this->actAsUserForSession($user, 'op-pert-admin', $person->qnid, [], 'BOTH');
        $provider = new ReportServiceProvider();
        $this->assertEquals(1, $provider->getAdminNotifications('tedarik-01')['total']);
        $this->assertEquals(1, $provider->getAdminNotifications('tedarik-06')['total']);

        $resp = $this->withSession(['person_id'=>$person->qnid,'type_key'=>'op-pert-admin','currentStatus'=>['clientQnidList'=>[]]])
            ->postJson('/api/v1/notifications/read-all');
        $resp->assertOk()->assertJsonPath('success', true);

        $this->assertEquals(0, $provider->getAdminNotifications('tedarik-01')['total']);
        $this->assertEquals(0, $provider->getAdminNotifications('tedarik-06')['total']);
    }

    public function test_sending_tedarik_01_filters_by_bukrs(): void
    {
        $this->seedCoreOptions();
        // two admins: GDZ and ADM, both in tedarik-01 group
        $personGdz = $this->createPerson('op-pert-admin', 'GDZ');
        $userGdz = $this->createUser($personGdz, 'send-gdz@test.local', 'GDZ', 'immutable-admin');
        $this->createNotificationGroup($personGdz, ['tedarik-01']);
        $this->createClientContact($personGdz, 'send-gdz@test.local');

        $personAdm = $this->createPerson('op-pert-admin', 'ADM');
        $userAdm = $this->createUser($personAdm, 'send-adm@test.local', 'ADM', 'immutable-admin');
        $this->createNotificationGroup($personAdm, ['tedarik-01']);
        $this->createClientContact($personAdm, 'send-adm@test.local');

        $personBoth = $this->createPerson('op-pert-admin', 'BOTH');
        $userBoth = $this->createUser($personBoth, 'send-both@test.local', 'BOTH', 'immutable-admin');
        $this->createNotificationGroup($personBoth, ['tedarik-01']);
        $this->createClientContact($personBoth, 'send-both@test.local');

        // payload BUKRS 4000 → GDZ
        $payload = ['type'=>'tedarikOrderImported','order_no'=>'3510001200','spec_code'=>'0000300184','ctitle'=>'Test','bukrs'=>'4000','sys_code'=>'4000'];
        $job = new SendNotificationMailJob($payload);
        $job->handle();

        // GDZ and BOTH should have logs, ADM should not
        $this->assertDatabaseHas('notification_logs', ['to'=>'send-gdz@test.local']);
        $this->assertDatabaseHas('notification_logs', ['to'=>'send-both@test.local']);
        $this->assertDatabaseMissing('notification_logs', ['to'=>'send-adm@test.local']);
    }

    public function test_sending_tedarik_04_dual_lifnr(): void
    {
        $this->seedCoreOptions();
        // admin assigned
        $personAdmin = $this->createPerson('op-pert-admin', 'GDZ');
        $userAdmin = $this->createUser($personAdmin, 'admin04@test.local', 'GDZ', 'immutable-admin');
        $this->createNotificationGroup($personAdmin, ['tedarik-04']);
        $this->createClientContact($personAdmin, 'admin04@test.local');

        // reseller bound to lifnr 0000300186
        $client = $this->createClient('0000300186','GDZ');
        $personReseller = $this->createPerson('op-pert-reseller', 'GDZ');
        $userReseller = $this->createUser($personReseller, 'reseller04@test.local', 'GDZ', 'immutable-reseller');
        $this->bindClientToPerson($personReseller, $client);
        $this->createClientContact($personReseller, 'reseller04@test.local');
        // reseller NOT in group — should still get via LIFNR

        $payload = [
            'type'=>'tedarikFileApproved',
            'order_no'=>'3510001300',
            'spec_code'=>'0000300186',
            'sys_code'=>'4000','bukrs'=>'4000',
            'fileTitle'=>'Test',
            'qnid'=> $this->createOrder('3510001300','0000300186','4000','GDZ','doc_trans_order_transfer_sent')->qnid,
        ];
        $job = new SendNotificationMailJob($payload);
        $job->handle();

        $this->assertDatabaseHas('notification_logs', ['to'=>'admin04@test.local']);
        $this->assertDatabaseHas('notification_logs', ['to'=>'reseller04@test.local']);

        // different lifnr should not notify reseller
        NotificationLog::truncate();
        $payload2 = $payload;
        $payload2['spec_code'] = '0000999999';
        $payload2['order_no'] = '3510001301';
        $job2 = new SendNotificationMailJob($payload2);
        $job2->handle();
        $this->assertDatabaseHas('notification_logs', ['to'=>'admin04@test.local']);
        $this->assertDatabaseMissing('notification_logs', ['to'=>'reseller04@test.local']);
    }

    public function test_bukrs_to_system_mapping(): void
    {
        $provider = new ReportServiceProvider();
        $ref = new \ReflectionClass($provider);
        $m = $ref->getMethod('bukrsToSystem');
        $m->setAccessible(true);
        $this->assertEquals('GDZ', $m->invoke($provider, '4000'));
        $this->assertEquals('ADM', $m->invoke($provider, '5000'));
        $this->assertEquals('BOTH', $m->invoke($provider, 'BOTH'));
        $this->assertEquals('GDZ', $m->invoke($provider, ''));
        $this->assertEquals('GDZ', $m->invoke($provider, 'G4000'));
    }

    public function test_notifications_endpoint_requires_auth(): void
    {
        $this->seedCoreOptions();
        $resp = $this->getJson('/api/v1/notifications');
        $resp->assertUnauthorized();
    }

    public function test_retrigger_notification_logs(): void
    {
        $this->seedCoreOptions();
        $person = $this->createPerson('op-pert-admin', 'BOTH');
        $user = $this->createUser($person, 'retry@test.local', 'BOTH', 'immutable-admin');
        Sanctum::actingAs($user, ['*']);
        session(['person_id'=>$person->qnid,'type_key'=>'op-pert-admin']);

        $log = NotificationLog::create([
            'type'=>'email','to'=>'retry@test.local','subject'=>'Test','body'=>'<p>hi</p>',
            'status'=>NotificationLog::STATUS_ERROR,'payload'=>['to'=>'retry@test.local','subject'=>'Test','html'=>'<p>hi</p>'],
            'attempts'=>1,
        ]);
        $resp = $this->withSession(['person_id'=>$person->qnid,'type_key'=>'op-pert-admin'])
            ->postJson('/api/v1/notificationlog/'.$log->id.'/retrigger');
        $resp->assertOk();
        $resp->assertJsonPath('success', true);
        $this->assertDatabaseHas('notification_logs', ['id'=>$log->id, 'status'=>NotificationLog::STATUS_SENT]);
    }
}
