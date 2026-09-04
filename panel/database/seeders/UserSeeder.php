<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use App\Models\User;
use App\Models\Persons;
use App\Models\SysRoleTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Providers\PersonsServiceProvider;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //get permissions first
        /*$perms = Sys_options::where([
            ['ttitle', '=', 'Perm_con_ops'],
            ['ctitle', '=', 'type_id'],
        ])->get();

        $op = Sys_options::where([
            ['ctitle', '=', 'op_id'],
            ['op_key', '=', 'op-perm'],
        ])->first();*/

        

       
        //set user permissions
        // 4000 gdz elektrik — kbbozat41 is tedarikçi (reseller) with client 0000300186 YILDIZ TEKSTİL
        $users = [
            ['op-pert-admin','Admin Kontent'   , 'kadir@kontent.com.tr'               ,'Kadir412.'  ,'5438826976','immutable-admin'],
            ['op-pert-reseller','KB Bozat'     , 'kbbozat41@hotmail.com'              ,'Kadir412.'  ,'5438826976','immutable-reseller', '0000300186'],
            ['op-pert-admin','Hilal Kontent'   , 'hilal@kontent.com.tr'               ,'Kontent412.','5306091996','immutable-admin'],
            ['op-pert-admin','Tolga TOPALOĞLU' , 'tolga.topaloglu@aydemenerji.com.tr' ,'Kontent412.','5309140574','immutable-admin'],
            ['op-pert-admin','Arın OKŞAŞ'      , 'arin.oksas@aydemenerji.com.tr'      ,'Kontent412.','5309140574','immutable-admin'],
            ['op-pert-admin','Selin SAVAŞ'     , 'selin.savas@aydemenerji.com.tr'     ,'Kontent412.','5326373062','immutable-admin'],
            ['op-pert-admin','Oğuzhan YUKACI'  , 'oguzhan.yukaci@aydemenerji.com.tr'  ,'Kontent412.','5317257402','immutable-admin'],
            ['op-pert-admin','Volkan GÜNDÜZ'   , 'volkan.gunduz@aydemenerji.com.tr'   ,'Kontent412.','5383533514','immutable-admin'],
            ['op-pert-admin','Sıla TEMEL'      , 'sila.temel@aydemenerji.com.tr'      ,'Kontent412.','5357182446','immutable-admin'],

        ];
        $personService = new PersonsServiceProvider();
        foreach ($users as $key => $value) {
            $isReseller = ($value[5] === 'immutable-reseller');
            $lifnr = $isReseller ? ($value[6] ?? null) : null;
            $data = [
                'main_name' => $value[1],
                'type_key' => $value[0],
                'user_status' => 1,
                'user_role' => $value[5],
                'user_username' => $value[2],
                'user_password' => $value[3],
                'user_password_check' => $value[3],
                'contphone**userfacilitygroup**'.$value[2].'-0' => $value[4],
                'conttitle**userfacilitygroup**'.$value[2].'-0' => 'İletisim 1',
                'contmail**userfacilitygroup**'.$value[2].'-0' => $value[2],
                'permissions' => SysRoleTemplate::where('op_key', $value[5])->first()->permissions ?? [],
            ];

            // tedarikçi için client (lifnr) bağla — EAV userclientgroup, Documents::tableList uses cliid→lifnr
            if ($lifnr) {
                $clientDoc = DB::table('documents')
                    ->join('sys_con_ops as sco', function($j){ $j->on('sco.main_id','=','documents.id')->where('sco.conn_id',0); })
                    ->join('sys_options as so', 'so.id','=','sco.type_id')
                    ->join('sys_con_entities as sce', 'sce.conn_id','=','sco.id')
                    ->where('so.op_key','op-doc-client-form')
                    ->where('sce.entity_tag','lifnr')
                    ->where('sce.entity_value',$lifnr)
                    ->select('documents.qnid','documents.id')
                    ->first();
                if ($clientDoc) {
                    $ckey = date('YmdHis').'-'.$key;
                    // resolve title for display
                    $clientTitle = DB::table('sys_con_entities as sce')
                        ->join('sys_con_ops as sco','sco.id','=','sce.conn_id')
                        ->join('sys_options as so','so.id','=','sco.type_id')
                        ->where('sco.main_id',$clientDoc->id)
                        ->where('so.op_key','op-doc-client-form')
                        ->where('sce.entity_tag','title')
                        ->value('sce.entity_value') ?? $lifnr;
                    $data['cliid**userclientgroup**'.$ckey] = $clientDoc->qnid;
                    $data['clicode**userclientgroup**'.$ckey] = $lifnr;
                    $data['clititle**userclientgroup**'.$ckey] = $clientTitle;
                }
            }

            $personService->setPerson(0, $data);

        }
    }
}
