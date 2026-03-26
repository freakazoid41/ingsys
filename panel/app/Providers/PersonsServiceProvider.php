<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Sys_con_ops;
use App\Models\Sys_con_entities;
use App\Models\Persons;
use App\Models\User;
use App\Models\Contacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendRegisterMailJob;
use Illuminate\Support\Facades\Hash;


class PersonsServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function getPersonTypes() {
        return Sys_options::where(['group_key' => 'op-pert'])->get();
    }

    public function setPerson($id = 0,$data,$files = [],$fileGroup = 'persons',$allData = []){
        $formData     = $data ?? [];
        DB::beginTransaction();
        //try{
            $contacts     = [];
            $facilities   = [];
            $user         = [];
            $permissions  = $formData['permissions'] ?? [];
            $removed      = json_decode($data['removed'] ?? "[]",true);
            $permTypeId   = (Sys_options::where(['op_key' => 'op-doc-user-permission-form'])->first())->id;
            $typeId       = (Sys_options::where(['op_key' => 'op-doc-user-contact-form'])->first())->id;
            $stypeIdMain  = (Sys_options::where(['op_key' => 'personnel-main'])->first())->id;       
            /*$permConn     = Sys_options::where([
                    ['ctitle', '=', 'op_id'],
                    ['op_key', '=', 'op-perm'],
            ])->first();*/
            //split form elements
            //first main elements
            $document = new Persons();

            if($id != 0) $document = Persons::where('qnid',$id)->first();

            foreach ($formData as $key => $value) {
                //here split data types and set main person data
                if(strpos($key,'main_') !== false){
                    $key = explode('main_',$key)[1];
                    if($key !== 'id') $document->{$key} = str_replace(',','',strip_tags($value));
                }
                if(strpos($key,'contact') !== false){
                    $contacts = json_decode(strip_tags($value),true);
                }

                if(strpos($key,'userfacilitygroup') !== false){
                    if(!isset($facilities[explode('**',$key)[2]]))$facilities[explode('**',$key)[2]] = [];
                    $facilities[explode('**',$key)[2]][explode('**',$key)[0]] = $value;
                }

                if(strpos($key,'user_') !== false){
                    $user[explode('user_',$key)[1]] = $value;
                }

                if($key == 'type_key'){
                    $type = Sys_options::where('op_key',$value)->first();
                    $document->type_id = $type->id;
                }
                
            }

            $rsp = $document->save();
            
        
            
            if(!empty($allData['alldata'])){
                $allData['alldata'] = json_decode($allData['alldata'],true);
                if(isset($allData['alldata']['removedData']) && !empty($allData['alldata']['removedData'])) $removed = $allData['alldata']['removedData'];
                if(isset($allData['alldata']['permissions']) && !empty($allData['alldata']['permissions'])){
                    if($allData['alldata']['permissions'][0] !== "empty"){
                        foreach ($allData['alldata']['permissions'] as $key => $item) {
                            $permissions[$item['op_key']] = $item['op_key'];
                        }
                    }else{
                        $permissions = 'empty';
                    }
                    
                }
            }



            
            //delete removed perm connections from here
            $conn = Sys_con_ops::where(
                ['main_id' => $document->id,'type_id' => $typeId,'sub_type_id' => $stypeIdMain], //ask from this values
            )->first();
            foreach ($removed as $row) {
                
                $check = Sys_con_entities::where(['conn_id' => $conn->id,'entity_tag' => $row['key']])->first();
                if(!empty($check))$check->delete();
            }


           
          
            //user spesific transactions
            if(!empty($user) && isset($user['status'])){
                $u = User::where('person_id',$document->id)->first();
                if($u){
                    $u->status = $user['status'];
                    $u->save();


                    if($user['status'] == '1'){
                        //send mail to admins for new registration
                        SendApprovedMailJob::dispatch($u->email)->onQueue('emails');
                    }
                }
            }

            if(!empty($user) && isset($user['role'])){
                $u = User::where('person_id',$document->id)->first();
                if($u){
                    $u->role = $user['role'];
                    $u->save();
                }
            }
            
            if(!empty($user) && isset($user['password'])){
                $sr = [
                    'person_id' => $document->id,
                    'password'  => Hash::make($user['password']),
                    'name'      => 'Client User',
                    'status'    => $user['status'] ?? '1',
                    'role'      => $user['role'] ?? 'default',
                ];

                if( isset($user['username'])) $sr['email'] = $user['username'];
                User::updateOrInsert(
                    ['person_id' => $document->id],
                    $sr,
                );
            }

            if(!empty($permissions)){
                $conn = Sys_con_ops::updateOrCreate(
                    ['main_id' => $document->id,'type_id' => $permTypeId,'sub_type_id' => $stypeIdMain], //ask from this values
                    [
                        'main_id' => $document->id,
                        'type_id' => $permTypeId,
                        'sub_type_id' => $stypeIdMain,
                        'conn_id'  => 0
                    ] 
                );
                Sys_con_entities::updateOrCreate(
                    ['conn_id' => $conn->id,'entity_tag' => ($document->id.'**userpermissiongroup**'.$document->id)], //ask from this values
                    [
                        'table_tag' => 'user_con_ops',
                        'conn_id' => $conn->id,
                        'entity_tag' => ($document->id.'**userpermissiongroup**'.$document->id),
                        'entity_value' => $permissions !== 'empty' ? json_encode(array_values($permissions)) : '[]'
                    ]
                );

            }
            //user spesific transactions

            //user contacts
            if(!empty($facilities)){
                foreach($facilities as $k => $f){
                    $conn = Sys_con_ops::updateOrCreate(
                        ['main_id' => $document->id,'type_id' => $typeId,'sub_type_id' => $stypeIdMain], //ask from this values
                        [
                            'main_id' => $document->id,
                            'type_id' => $typeId,
                            'sub_type_id' => $stypeIdMain,
                            'conn_id'  => 0
                        ] 
                    );

                    //now check if any entity sended
                    foreach($f as $ekey => $value){
                        Sys_con_entities::updateOrCreate(
                            ['conn_id' => $conn->id,'entity_tag' => ($ekey.'**userfacilitygroup**'.$k)], //ask from this values
                            [
                                'table_tag' => 'user_con_ops',
                                'conn_id' => $conn->id,
                                'entity_tag' => ($ekey.'**userfacilitygroup**'.$k),
                                'entity_value' => $value
                            ]
                        );
                    };
                }
            }

            /*if($files['bgfile']){
                User::updateOrInsert(
                    ['person_id' => $document->id],
                    [
                        'bg_image'      => 'Client User'
                    ],
                );
            }*/

            DB::commit();

            // dispatch notification to configured recipients (queued)
            /*try{
                $userEmail = $user['username'] ?? null;
                $phone = null;
                if (!empty($contacts) && isset($contacts[0])) {
                    if (is_array($contacts[0])) {
                        $phone = $contacts[0]['phone'] ?? null;
                    } elseif (is_object($contacts[0])) {
                        $phone = $contacts[0]->phone ?? null;
                    }
                }

                SendRegisterMailJob::dispatch($userEmail, $phone, $document->id)->onQueue('emails');
            }catch(\Throwable $je){
                Log::error('Failed to dispatch SendRegisterMailJob', ['exception' => $je]);
            }*/

            return [
                'success' => $rsp,
                'id'      => $document->id,
                'data'    => $document
            ];
        /*}catch(\Throwable $e){
            DB::rollBack();
            Log::error('PersonsServiceProvider::setPerson error', ['exception' => $e]);
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }*/
        
    }

    public function getPerson($id = 0,$search = null,$getContacts = false,$realId = null){
        $id      = $realId ?? str_replace(',','',strip_tags($id));
        $search  = str_replace(',','',strip_tags($search));


        $facilities = "(SELECT    json_agg(
                                        json_build_object(
                                            'Key',se.entity_tag,
                                            'Value' , se.entity_value
                                        )
                                    ) 
                                FROM sys_con_entities as se
                                    inner join sys_con_ops as so on so.id = se.conn_id 
                                    inner join sys_options as sp on sp.id = so.type_id
                                where so.conn_id = 0 and sp.op_key = 'op-doc-user-contact-form'  and so.main_id = i.id)::text  as  contacts";
        $permissions = "(SELECT    json_agg(
                                        json_build_object(
                                            'Key',se.entity_tag,
                                            'Value' , se.entity_value
                                        )
                                    ) 
                                FROM sys_con_entities as se                                    
                                    inner join sys_con_ops as so on so.id = se.conn_id 
                                    inner join sys_options as sp on sp.id = so.type_id
                                where so.conn_id = 0 and sp.op_key = 'op-doc-user-permission-form'  and so.main_id = i.id)::text  as  permissions";

        if(!$getContacts){
            $facilities = "'1'  as  contacts";
            $permissions = "'1'  as  permissions";
        }

        $sql     = "select  i.id,
                            i.name,
                            i.surname,
                            i.type_id,
                            i.created_at,
                            u.email  as  email,
                            u.role   as  user_role,
                            i.status,
                            o.title  as  type_title,
                            o.op_key  as  type_key,
                            u.email  as  user_name,
                            u.status as  user_status,
                            u.grp_code as  user_grp_code,
                            $permissions,
                            $facilities
                        from persons as i
                            left join users as u on u.person_id = i.id
                            left join sys_options as o on o.id = i.type_id ";
        if($search == null){
            $where =  $realId == null ? " where i.qnid = '".$id."'" : " where i.id = '".$id."' ";
        }else{
            //$where = " where (i.spec_code ilike '%".$search."%' or i.name ilike '%".$search."%' ) and i.parent_id = 0 ";
        }
        
        $person  = DB::select($sql.$where);  
        $subData = [];

        return [
            'success'      => !empty($person),
            'person'       => $person,
            
        ];
    }

    public function getPersonsExportData(){
        $response = [['Tip','Ünvan','Cari Kodu','Adres','Bakiye']];

        $data = (new Persons())->tableList([])['data'];
        foreach($data as $d){
            $d->contacts = json_decode(str_replace('\"','"',str_replace('}"}','}]',str_replace('{"{','[{',$d->contacts))));
           
            $response[] = [
                $d->type_title,
                $d->name,
                $d->spec_code,
                $d->contacts[0]->address,
                $d->balance
            ];
        }

        return [
            'success' => true,
            'data'    => $response
        ];
    }

    public function removeContent($id){
        //first find all attributes
        $document    = Persons::where('qnid',$id)->first();
        $connections = User::where('person_id',$document->id)->first();
        
        /*$connections->delete();  
        $document->delete();*/
        if(!empty($connections)){
            $connections->status = '0';
            $connections->save();
        }

        if(!empty($document)){
            $document->status = '0';
            $document->save();
        }

        return ['success' => true];
    }

    public function sendregisterMails($email, $phone){
        try{
            SendRegisterMailJob::dispatch($email, $phone, null)->onQueue('emails');
        }catch(\Throwable $e){
            Log::error('sendregisterMails dispatch failed', ['exception' => $e, 'email' => $email, 'phone' => $phone]);
        }
        
    }

    public function getRoleTemplates(){
        $path = storage_path('app/coal_roles_templates.json');
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    public function saveRoleTemplates(array $roles){
        $path = storage_path('app/coal_roles_templates.json');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, json_encode(array_values($roles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }

    public function deleteRoleTemplate($id){
        $roles = $this->getRoleTemplates();
        $filtered = array_filter($roles, function($item) use ($id) {
            return (string)($item['id'] ?? '') !== (string)$id;
        });

        if (count($filtered) === count($roles)) {
            return null;
        }

        $this->saveRoleTemplates(array_values($filtered));

        return array_values($filtered);
    }

    public function updateUserPermissions($role, array $permissions){
        $users = User::where('role', $role)->get();
        foreach($users as $user){
            Sys_con_entities::updateOrCreate(
                ['entity_tag' => ($user->person_id.'**userpermissiongroup**'.$user->person_id)], //ask from this values
                [
                    'entity_value' => empty($permissions) ? '[]' : json_encode($permissions)
                ]
            );
        }
    }
}

