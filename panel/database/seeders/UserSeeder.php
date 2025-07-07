<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use App\Models\User;
use App\Models\Sys_con_ops;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            ['op-pert-admin','Admin Kadir','kbbozat41@hotmail.com','Kadir412.','5438826276'],
            //['op-pert-buyer','Normal Kullanıcı','falanboyle41@test.com','Kadir412.','5434465454'],
        ];

        foreach($users as $u){
            $this->setPerson($u[0],$u[1],$u[2],$u[3],$u[4])->id;
            /*$this->setPermissions(
                $perms,
                $this->setPerson($u[0],$u[1],$u[2],$u[3],$u[4],$u[5],$u[7],$u[8])->id,
                $op->id,
                $u[6]
            );*/
        }
    }

    function setPerson($type = 'op-pert-admin',$name = '', $email = '', $password = '',$phone = ''){
        
        $personType = (Sys_options::where(['op_key' => $type])->first());
        $clientId   = 0;
       
        

        $docId = DB::table('persons')->insertGetId([
            'parent_id'  => 0,
            'spec_code'  => '0',//$code,
            'title'      => 'USER',
            'name'       => $name,
            'surname'    => '-',
            'phone'      => $phone,
            'type_id'    => $personType->id ?? 0,
            'created_at' => \Carbon\Carbon::now()->toDateTimeString()
        ]);

        

        

        $user = new User();
        $user->name      = $name;
        $user->email     = $email;
        $user->password  = Hash::make($password);
        $user->person_id = $docId;
        $user->save();

        return $user;
    }
    function setPermissions($pers,$userId,$opId,$prm){
        $perms = [
            'supplier' => [
                'per-01',
                'per-00',
                'per-00-01',
                'per-00-03',
                'per-01-02',
                'per-01-03',
                'per-01-06',
                'per-01-07',
                'per-01-08',
                'per-01-09',
                'per-01-10',
                'per-01-11',
                'per-01-12',
                'per-03-02',
                'per-06',
                'per-06-01',
                'per-06-02',
                'per-07-04'
            ],
            'office'   => ['all'],
            'admin'    => ['all'],
            'custom'   => [
                'per-01',
                'per-02',
                'per-03',
                'per-04',
                'per-01-03',
                'per-01-04',
                'per-01-05',
                'per-01-06',
                'per-01-07',
                'per-01-09',
                'per-01-10',
                'per-01-11',
                'per-01-12',
                'per-02-02',
                'per-03-01',
                'per-03-02',
                'per-04-02'
            ],
        ];

        foreach ($pers as $value) {
            if($prm !== 'admin' && !in_array($value->op_key,$perms[$prm])){
                continue;
            }else{
                $perm            = new Sys_con_ops();
                $perm->main_id   = $userId;
                $perm->type_id   = $opId;
                $perm->conn_id   = $value->id;
                $perm->save();
            }
        }
    }
}
