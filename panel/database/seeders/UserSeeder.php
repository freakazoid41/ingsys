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
        // 4000 gdz elektrik
        $users = [
            ['op-pert-admin','Admin Kontent'   , 'kadir@kontent.com.tr'               ,'Kadir412.'  ,'5438826976','immutable-super-admin'],
            ['op-pert-admin','Admin Kontent1'  , 'kbbozat41@hotmail.com'              ,'Kadir412.'  ,'5438826976','immutable-super-admin'],
            ['op-pert-admin','Hilal Kontent'   , 'hilal@kontent.com.tr'               ,'Kontent412.','5306091996','immutable-super-admin'],
            ['op-pert-admin','Tolga TOPALOĞLU' , 'tolga.topaloglu@aydemenerji.com.tr' ,'Kontent412.','5309140574','immutable-super-admin'],
            ['op-pert-admin','Arın OKŞAŞ'      , 'arin.oksas@aydemenerji.com.tr'      ,'Kontent412.','5309140574','immutable-super-admin'],
            ['op-pert-admin','Selin SAVAŞ'     , 'selin.savas@aydemenerji.com.tr'     ,'Kontent412.','5326373062','immutable-super-admin'],
            ['op-pert-admin','Oğuzhan YUKACI'  , 'oguzhan.yukaci@aydemenerji.com.tr'  ,'Kontent412.','5317257402','immutable-super-admin'],
            ['op-pert-admin','Volkan GÜNDÜZ'   , 'volkan.gunduz@aydemenerji.com.tr'   ,'Kontent412.','5383533514','immutable-super-admin'],
            ['op-pert-admin','Sıla TEMEL'      , 'sila.temel@aydemenerji.com.tr'      ,'Kontent412.','5357182446','immutable-super-admin'],

        ];
        $personService = new PersonsServiceProvider();
        foreach ($users as $key => $value) {
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

            $personService->setPerson(0, $data);

        }
    }
}
