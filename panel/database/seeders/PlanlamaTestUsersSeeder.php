<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Persons;
use App\Models\Sys_options;
use App\Models\Sys_con_ops;
use App\Models\Sys_con_entities;
use App\Providers\PersonsServiceProvider;
use App\Services\PermissionService;

// Pipeline test users (idempotent, union — never removes existing perms):
//  kadir@kontent.com.tr   => per-041-05 + 05-04 + 05-02  (+ notif planlama-01, planlama-03)
//  kbbozat41@hotmail.com  => per-041-05 + 05-03          (Talep Yükleme)
//  picklecanme@gmail.com   => per-041-05 + 05-01          (Müdürlük Talep Onay, reseller bound to 90000001 Planlama Müdürlüğü GDZ)
class PlanlamaTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $permTypeId = Sys_options::where(['op_key' => 'op-doc-user-permission-form'])->value('id');
        $notifTypeId = Sys_options::where(['op_key' => 'op-doc-user-notification-form'])->value('id');
        $clientFormTypeId = Sys_options::where(['op_key' => 'op-doc-user-client-form'])->value('id');
        $stypeIdMain = Sys_options::where(['op_key' => 'personnel-main'])->value('id');
        $ps = new PersonsServiceProvider();
        $permSvc = new PermissionService();

        $grantPerms = function (int $personId, array $codes) use ($permTypeId, $stypeIdMain, $permSvc) {
            $conn = Sys_con_ops::updateOrCreate(
                ['main_id' => $personId, 'type_id' => $permTypeId, 'sub_type_id' => $stypeIdMain],
                ['main_id' => $personId, 'type_id' => $permTypeId, 'sub_type_id' => $stypeIdMain, 'conn_id' => 0]
            );
            $tag = $personId . '**userpermissiongroup**' . $personId;
            $cur = Sys_con_entities::where('conn_id', $conn->id)->where('entity_tag', $tag)->value('entity_value');
            $have = json_decode($cur ?? '[]', true) ?: [];
            $merged = array_values(array_unique(array_merge($have, $codes)));
            Sys_con_entities::updateOrCreate(
                ['conn_id' => $conn->id, 'entity_tag' => $tag],
                ['table_tag' => 'user_con_ops', 'entity_value' => json_encode($merged)]
            );
            $permSvc->refreshUserPermissionCache($personId);
            $permSvc->bumpUserPermissionVersion($personId);
            return $merged;
        };

        $grantNotifs = function (string $personQnid, array $codes) use ($notifTypeId, $stypeIdMain) {
            $person = Persons::where('qnid', $personQnid)->first();
            if (!$person) return;
            $conn = Sys_con_ops::updateOrCreate(
                ['main_id' => $person->id, 'type_id' => $notifTypeId, 'sub_type_id' => $stypeIdMain],
                ['main_id' => $person->id, 'type_id' => $notifTypeId, 'sub_type_id' => $stypeIdMain, 'conn_id' => 0]
            );
            $tag = $person->id . '**usernotificationgroup**' . $person->id;
            $cur = Sys_con_entities::where('conn_id', $conn->id)->where('entity_tag', $tag)->value('entity_value');
            $have = json_decode($cur ?? '[]', true) ?: [];
            $merged = array_values(array_unique(array_merge($have, $codes)));
            Sys_con_entities::updateOrCreate(
                ['conn_id' => $conn->id, 'entity_tag' => $tag],
                ['table_tag' => 'user_con_ops', 'entity_value' => json_encode($merged)]
            );
        };

        // 1) kadir — union planlama perms (admin keeps everything else)
        $kadir = User::where('email', 'kadir@kontent.com.tr')->first();
        if ($kadir) {
            $merged = $grantPerms($kadir->person_id, ['per-041-05', 'per-041-05-01', 'per-041-05-02', 'per-041-05-03', 'per-041-05-04']);
            $this->command->line('kadir perms: ' . count($merged));
            $qp = Persons::where('id', $kadir->person_id)->value('qnid');
            if ($qp) $grantNotifs($qp, ['planlama-01', 'planlama-03']);
        }

        // 2) kbbozat41 — union module + Talep Yükleme
        $kb = User::where('email', 'kbbozat41@hotmail.com')->first();
        if ($kb) {
            $merged = $grantPerms($kb->person_id, ['per-041-05', 'per-041-05-03']);
            $this->command->line('kbbozat41 perms: ' . count($merged));
        }

        // 3) pickecanme — create reseller bound to 90000001 Planlama Müdürlüğü (GDZ) if missing
        $pick = User::where('email', 'picklecanme@gmail.com')->first();
        if (!$pick) {
            $clientDoc = DB::table('sys_con_entities as se')
                ->join('sys_con_ops as sco', 'sco.id', '=', 'se.conn_id')
                ->join('documents as d', 'd.id', '=', 'sco.main_id')
                ->join('sys_options as so', 'so.id', '=', 'sco.type_id')
                ->where('so.op_key', 'op-doc-client-form')
                ->where('se.entity_tag', 'lifnr')
                ->where('se.entity_value', '90000001')
                ->where('d.status', 1)
                ->select('d.qnid', 'd.id')
                ->first();
            $ckey = date('YmdHis') . '-plan';
            $data = [
                'main_name' => 'Pick Ecanme',
                'type_key' => 'op-pert-reseller',
                'user_status' => 1,
                'user_role' => 'immutable-reseller',
                'user_username' => 'picklecanme@gmail.com',
                'user_password' => 'Kadir412.',
                'user_password_check' => 'Kadir412.',
                'contphone**userfacilitygroup**picklecanme@gmail.com-0' => '5438826976',
                'conttitle**userfacilitygroup**picklecanme@gmail.com-0' => 'Iletisim 1',
                'contmail**userfacilitygroup**picklecanme@gmail.com-0' => 'picklecanme@gmail.com',
                'permissions' => ['per-041-05', 'per-041-05-01'],
            ];
            if ($clientDoc) {
                $clientTitle = DB::table('sys_con_entities as se')
                    ->join('sys_con_ops as sco', 'sco.id', '=', 'se.conn_id')
                    ->join('sys_options as so', 'so.id', '=', 'sco.type_id')
                    ->where('sco.main_id', $clientDoc->id)
                    ->where('so.op_key', 'op-doc-client-form')
                    ->where('se.entity_tag', 'title')
                    ->value('se.entity_value') ?? '90000001';
                $data['cliid**userclientgroup**' . $ckey] = $clientDoc->qnid;
                $data['clicode**userclientgroup**' . $ckey] = '90000001';
                $data['clititle**userclientgroup**' . $ckey] = $clientTitle;
            }
            $res = $ps->setPerson(0, $data);
            $this->command->line('picklecanme created: ' . json_encode($res['success'] ?? false));
            $pick = User::where('email', 'picklecanme@gmail.com')->first();
        }
        if ($pick) {
            $merged = $grantPerms($pick->person_id, ['per-041-05', 'per-041-05-01']);
            $this->command->line('picklecanme perms: ' . json_encode($merged));
            $qp = Persons::where('id', $pick->person_id)->value('qnid');
            if ($qp) $grantNotifs($qp, ['planlama-01']);
            $permSvc->refreshUserPermissionCache($pick->person_id);
            $permSvc->bumpUserPermissionVersion($pick->person_id);
        }

        echo "PlanlamaTestUsersSeeder done.\n";
    }
}
