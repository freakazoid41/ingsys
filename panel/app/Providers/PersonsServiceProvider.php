<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Sys_con_ops;
use App\Models\Sys_con_entities;
use App\Models\Persons;
use App\Models\User;
use App\Models\Contacts;
use App\Services\RoleTemplateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Providers\DocumentServiceProvider;
use App\Providers\EmailServiceProvider;
use App\Models\UserLog;
use App\Models\ActiveSession;
use App\Services\PermissionService;
use App\Models\SysRoleTemplate;

class PersonsServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    protected function getPermissionCacheStore(){
        return config('permissions.cache_store', env('PERMISSIONS_CACHE_STORE', 'file'));
    }

    public function getPersonTypes() {
        return Sys_options::where(['group_key' => 'op-pert'])->get();
    }

    public function getUserPermissionsByPersonId($personId){
        $permission = DB::selectOne(
            "SELECT se.entity_value FROM sys_con_entities se
             INNER JOIN sys_con_ops so ON so.id = se.conn_id
             INNER JOIN sys_options sp ON sp.id = so.type_id
             WHERE so.conn_id = 0
               AND sp.op_key = 'op-doc-user-permission-form'
               AND so.main_id = ?",
            [$personId]
        );

        $permissions = json_decode($permission->entity_value ?? '[]', true);
        return is_array($permissions) ? array_values($permissions) : [];
    }

    private function upsertConnectionEntity(int $mainId, int $typeId, int $subTypeId, string $entityTag, $entityValue)
    {
        $conn = Sys_con_ops::updateOrCreate(
            ['main_id' => $mainId, 'type_id' => $typeId, 'sub_type_id' => $subTypeId],
            ['main_id' => $mainId, 'type_id' => $typeId, 'sub_type_id' => $subTypeId, 'conn_id' => 0]
        );

        Sys_con_entities::updateOrCreate(
            ['conn_id' => $conn->id, 'entity_tag' => $entityTag],
            [
                'table_tag' => 'user_con_ops',
                'conn_id' => $conn->id,
                'entity_tag' => $entityTag,
                'entity_value' => $entityValue,
            ]
        );

        return $conn;
    }

    public function setPerson($id = 0, $data = [], $files = [], $fileGroup = 'persons', $allData = []){
        $formData     = $data ?? [];
        $logData = [
            'user_id'     => auth('sanctum')->user()->id ?? 0,
            'sys_code'    => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation'    => 'persons',
            'relation_id' => $id,
            'type_id'     => Sys_options::where('op_key', 'log-person-update')->first()->id ?? 0,
            'description' => []
        ];
        DB::beginTransaction();
        try{
            $contacts     = [];
            $facilities   = [];
            $user         = [];
            $clients      = [];
            $permissions  = $formData['permissions'] ?? [];
            $removed      = json_decode($data['removed'] ?? "[]",true);
            $clientTypeId = (Sys_options::where(['op_key' => 'op-doc-user-client-form'])->first())->id;
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

            if($id != 0){
                $document = Persons::where('qnid',$id)->first();
                $logData['description'] = [
                    'before' => $document,
                    'after'  => [],
                ];
            } 

           

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

                if(strpos($key,'userclientgroup') !== false){
                    if(!isset($clients[explode('**',$key)[2]]))$clients[explode('**',$key)[2]] = [];
                    $clients[explode('**',$key)[2]][explode('**',$key)[0]] = $value;
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
            
            foreach ($removed as $row) {
                $stypeId = 0;
                if(strpos($row['key'], 'userpermissiongroup') !== false) $stypeId = $permTypeId;
                if(strpos($row['key'], 'userfacilitygroup') !== false) $stypeId = $typeId;
                if(strpos($row['key'], 'userclientgroup') !== false) $stypeId = $clientTypeId;
                
                $conn = Sys_con_ops::where(
                    ['main_id' => $document->id,'type_id' => $stypeId,'sub_type_id' => $stypeIdMain], //ask from this values
                )->first();
                
                $check = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => $row['key']])->first();
               
                if(!empty($check)) $check->delete();
            }


           
          
            //user spesific transactions
            if(!empty($user) && isset($user['status'])){
                $u = User::where('person_id',$document->id)->first();
                if($u){
                    $u->status = $user['status'];
                    $u->save();
                    (new PermissionService())->forceLogoutPerson($document->id, 'Kullanıcını Durumunuz Değiştirildi. Lütfen tekrar giriş yapın.'); // force logout for all sessions of the user
                }
            }

            if(!empty($user) && isset($user['role']) && !empty($user['role'])){
                $u = User::where('person_id',$document->id)->first();
                if($u){
                    $u->role = $user['role'];
                    $u->save();
                }
            }

            //if no explicit permissions were sent, populate them from the assigned role template
            if(empty($permissions) && !empty($user['role'])){
                $roleTemplate = SysRoleTemplate::where('op_key', $user['role'])->first();
                if($roleTemplate && is_array($roleTemplate->permissions)){
                    $permissions = $roleTemplate->permissions;
                }
            }
            
            if(!empty($user) && isset($user['password'])){
                $sr = [
                    'person_id' => $document->id,
                    'password'  => Hash::make($user['password']),
                    'name'      => 'System User',
                ];

                if(isset($user['status']) && !empty($user['status'])) $sr['status'] = $user['status'];
                if(isset($user['role']) && !empty($user['role'])) $sr['role'] = $user['role'];
                if(isset($user['needs_refresh'])) $sr['needs_refresh'] = $user['needs_refresh'];

                if( isset($user['username'])) $sr['email'] = $user['username'];
                User::updateOrCreate(
                    ['person_id' => $document->id],
                    $sr,
                );
            }

            if(!empty($permissions)){
                $this->upsertConnectionEntity(
                    $document->id,
                    $permTypeId,
                    $stypeIdMain,
                    $document->id.'**userpermissiongroup**'.$document->id,
                    $permissions !== 'empty' ? json_encode(array_values($permissions)) : '[]'
                );
            }
            //user spesific transactions

            //user contacts
            if(!empty($facilities)){
                foreach($facilities as $k => $f){
                    foreach($f as $ekey => $value){
                        $this->upsertConnectionEntity(
                            $document->id,
                            $typeId,
                            $stypeIdMain,
                            $ekey.'**userfacilitygroup**'.$k,
                            $value
                        );
                    }
                }
            }

            //user client connections
            if(!empty($clients)){
                foreach($clients as $k => $f){
                    foreach($f as $ekey => $value){
                        $this->upsertConnectionEntity(
                            $document->id,
                            $clientTypeId,
                            $stypeIdMain,
                            $ekey.'**userclientgroup**'.$k,
                            $value
                        );
                    }
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

            // refresh cached permissions and bump version so active sessions detect the change
            (new PermissionService())->refreshUserPermissionCache($document->id);
            try{
                $newStatus = $this->clientPermInfo($document->qnid, $formData['type_key'] ?? session('type_key'));
                (new PermissionService())->bumpUserPermissionVersion($document->id, $newStatus);
            }catch(\Throwable $e){
                // ignore
            }

            //here get updated data
            $updatedResult = $this->getPerson($document->qnid);
            $logData['description'] = json_encode([
                'before' => $logData['description']['before'] ?? [],
                'after'  => $updatedResult,
            ],JSON_UNESCAPED_UNICODE);

            $logData['relation_id'] = $document->id;
            
            //here save log data
            UserLog::create($logData);


            return [
                'success' => $rsp,
                'id'      => $document->id,
                'data'    => $document,
                'type'    => $formData['type_key'] ?? null,
                'user'    => User::where('person_id', $document->id)->first() ?? [],
            ];
        }catch(\Throwable $e){
            DB::rollBack();
            Log::error('PersonsServiceProvider::setPerson error', ['exception' => $e]);
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
        
    }

    public function getPerson($id = 0,$search = null,$getContacts = false,$realId = null){
        $id      = $realId ?? str_replace(',','',strip_tags($id));
        $search  = str_replace(',','',strip_tags($search));


        $contacts = "(SELECT    json_agg(
                                        json_build_object(
                                            'Key',se.entity_tag,
                                            'Value' , se.entity_value
                                        )
                                    ) 
                                FROM sys_con_entities as se
                                    inner join sys_con_ops as so on so.id = se.conn_id 
                                    inner join sys_options as sp on sp.id = so.type_id
                                where so.conn_id = 0 and sp.op_key = 'op-doc-user-contact-form'  and so.main_id = i.id)::text  as  contacts";
        $clients  = "(SELECT    json_agg(
                                        json_build_object(
                                            'Key',se.entity_tag,
                                            'Value' , se.entity_value
                                        )
                                    ) 
                                FROM sys_con_entities as se
                                    inner join sys_con_ops as so on so.id = se.conn_id 
                                    inner join sys_options as sp on sp.id = so.type_id
                                where so.conn_id = 0 and sp.op_key = 'op-doc-user-client-form'  and so.main_id = i.id)::text  as  clients";
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
            $contacts = "'1'  as  contacts";
            $permissions = "'1'  as  permissions";
        }

        $sql     = "select  i.id,
                            i.qnid,
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
                            $contacts,
                            $clients
                        from persons as i
                            left join users as u on u.person_id = i.id
                            left join sys_options as o on o.id = i.type_id ";
        if($search == null){
            $where =  $realId == null ? " where i.qnid = '".$id."'" : " where i.id = '".$id."' ";
        }else{
            $where = " where (".$search.")";
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

    public function roleTemplateTrans($type = 'get',$id = null,$roles = []){
        $service = new RoleTemplateService();
        if($type == 'get'){
            return $service->getRoleTemplates();
        }elseif($type == 'save'){
            return $service->saveRoleTemplates($roles);
        }elseif($type == 'delete' && $id != null){
            return $service->deleteRoleTemplate($id);
        }

        return null;    
    }

    /**
     * this function updates permissions for all users with the given role, it is called when role templates are updated to reflect changes in permissions to users with that role
     */
    public function updateUserPermissions($role, array $permissions){
        $users = User::where('role', $role)->get();
        $permTypeId   = (Sys_options::where(['op_key' => 'op-doc-user-permission-form'])->first())->id;
        $stypeIdMain  = (Sys_options::where(['op_key' => 'personnel-main'])->first())->id;       
        foreach($users as $user){
            $this->upsertConnectionEntity(
                $user->person_id,
                $permTypeId,
                $stypeIdMain,
                $user->person_id.'**userpermissiongroup**'.$user->person_id,
                empty($permissions) ? '[]' : json_encode($permissions)
            );
            (new PermissionService())->refreshUserPermissionCache($user->person_id);
        }
    }

    public function updateUserNotificationGroups($persons){
        $notifTypeId   = (Sys_options::where(['op_key' => 'op-doc-user-notification-form'])->first())->id;
        $stypeIdMain  = (Sys_options::where(['op_key' => 'personnel-main'])->first())->id;       
        foreach ($persons as $personE) {
            $person = Persons::where('qnid', $personE['person_id'])->first();
            $this->upsertConnectionEntity(
                $person->id,
                $notifTypeId,
                $stypeIdMain,
                $person->id.'**usernotificationgroup**'.$person->id,
                empty($personE['op_keys']) ? '[]' : json_encode($personE['op_keys'])
            );
        }
    }

    // this method will set glue between client and main user of it
    public function setClientToPerson($personData,$userData){
        try {
            $connId = Sys_options::where('op_key','personnel-main')->first()->id;
            $typeId = Sys_options::where('op_key','op-doc-client-main')->first()->id;
            $clientTypeId = (Sys_options::where(['op_key' => 'op-doc-user-client-form'])->first())->id;
            $stypeIdMain  = (Sys_options::where(['op_key' => 'personnel-main'])->first())->id;    
            
            //first chech if connection already exists
            $conn   = Sys_con_ops::where(
                ['main_id' => $personData['id'],'type_id' => $typeId,'sub_type_id' => $connId], //ask from this values
            )->first();

            if(empty($conn)){
                $res = (new DocumentServiceProvider())->registerContent(0,[
                    'dynamicF' => [
                        'op-doc-client-form**new-'.date('YmdHis') => [
                            'entities' => [
                                'clicode' => $personData->qnid,
                                'title'   => $userData->email,
                            ],
                            "tag" => "op-doc-client-form"
                        ],
                    ],
                    "typeKey" => "op-doc-client"
                ],[]);

                if($res['success'] == true){
                    Sys_con_ops::updateOrCreate(
                        ['main_id' => $personData['id'],'type_id' => $typeId,'sub_type_id' => $connId], //ask from this values
                        ['main_id' => $personData['id'],'type_id' => $typeId,'sub_type_id' => $connId, 'conn_id' => $res['id']]
                    );

                    $k = date('YmdHis').'-0';
                    //add client info to person connections
                    $this->upsertConnectionEntity(
                        $personData['id'],
                        $clientTypeId,
                        $stypeIdMain,
                        'cliid**userclientgroup**'.$k,
                        $res['qnid']
                    );

                    $this->upsertConnectionEntity(
                        $personData['id'],
                        $clientTypeId,
                        $stypeIdMain,
                        'clicode**userclientgroup**'.$k,
                        $personData->qnid
                    );

                    $this->upsertConnectionEntity(
                        $personData['id'],
                        $clientTypeId,
                        $stypeIdMain,
                        'clititle**userclientgroup**'.$k,
                        $userData->email
                    );

                    //send mail
                }
            }
            
            return true;
        } catch (\Throwable $e) {
            print_r($e->getMessage()."\n".$e->getFile()."\n".$e->getLine());
            Log::error('PersonsServiceProvider::setClientToPerson error', [
                'exception' => $e,
                'personData' => $personData,
                'clientData' => $userData,
            ]);

            return false;
        }
    }

    /**
     * What is the purpose of this method? 
     * Clients have 2 addional statuses
     * 1 - If they our not entered their some informations they cannot travel across system so we will check that in here when needed
     * 2 - If they have non approved required file they cannot give response to tenders also we sill check that here
     */
    public function clientPermInfo($personQnId, $typeKey){
        $response = [
            'canProceed'  => true,
            'canResponse' => true,
            'clientQnid'  => false,
            'clientTitle' => false,
            'clientQnidList' => [],
            'rejectedFiles' => []
        ];

        try {
            //first find client qnid's from user connections which are unique connection ids
            $clients  = "SELECT se.entity_value as client_qnid,
                                se2.entity_value as title
                        FROM sys_con_entities as se
                            inner join sys_con_ops as so on so.id = se.conn_id 
                            inner join sys_options as sp on sp.id = so.type_id
                            inner join persons as p on p.id = so.main_id
                            inner join sys_con_entities se2 on se2.conn_id = se.conn_id and se2.entity_tag like '%title%'

                        where so.conn_id = 0 and sp.op_key = 'op-doc-user-client-form' and se.entity_tag like '%cliid**%'  and p.qnid = '$personQnId'";
            
            $clients  = DB::select($clients);  

            $documentProd = new DocumentServiceProvider();
            foreach ($clients as $row) {
                try {
                    // Get client document information
                   
                    $response['clientQnidList'][] = $row->client_qnid;
                    $clientInfo = $documentProd->getFormData($row->client_qnid);
                    $clientInfo = array_values($clientInfo['formFormat']['op-doc-client-form'])[0]['entities'];
                    
                    $result = array_filter($clientInfo, fn($key) => str_starts_with($key, 'cont_imza_file**'), ARRAY_FILTER_USE_KEY);
                    if (empty($result)) {
                        if($typeKey == 'op-pert-reseller') $response['canProceed'] = false;
                    } else {
                        foreach ($result as $key => $value) {
                            $data = json_decode($value, true);
                            if ($data['status'] == 1) {
                                if ($data['last_status']['op_key'] !== 'doc_file_accepted') {
                                    if($typeKey == 'op-pert-reseller') $response['canResponse'] = false;
                                    break;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Error processing client document information', [
                        'exception' => $e,
                        'client_qnid' => $row->client_qnid
                    ]);
                    if($typeKey == 'op-pert-reseller') {
                        $response['canProceed'] = false;
                        $response['canResponse'] = false;
                    }
                }
            }
            if(isset($clients[0])){
                $response['clientQnid']  = $clients[0]->client_qnid;
                $response['clientTitle'] = $clients[0]->title;
            }
           

            //get rejected files here
            $response['rejectedFiles'] = $documentProd->getRejectedClientFiles($response['clientQnidList'])['data'];
        } catch (\Throwable $e) {
            Log::error('Error fetching client information', [
                'exception' => $e,
                'personQnId' => $personQnId
            ]);
            $response['canProceed'] = false;
            $response['canResponse'] = false;
        }

        return $response;
    }

    //this method will return users who have some notification group permission ornotification groups with permitted users
    public function getNotificationUsers($opKey = null,$personId = null){
        try {
            $sql = "select  p.qnid,
                        sce.entity_value,
                        ct.op_key,
                        p.name as person_name,
                        u.email as username
                        

                    from persons as p
                        inner join sys_con_ops as sco on sco.main_id = p.id
                        inner join sys_options as ct on ct.id = sco.type_id
                        inner join sys_con_entities as sce on sce.conn_id = sco.id
                        inner join users as u on u.person_id = p.id
                    where ct.op_key = 'op-doc-user-notification-form' ";

            if($opKey != null){
                $sql.= " and sce.entity_value like '%$opKey%'";
            }

            if($personId != null){
                $sql.= " and p.qnid = '$personId' ";
            }
            $data = DB::select($sql);
            $permittedUsers = [];
            foreach($data as $d){
                $permissions = json_decode($d->entity_value, true);
                foreach ($permissions as $key) {
                    if($opKey != null && $key != $opKey) continue;
                    $member = [
                        'person_id' => $d->qnid,
                        'name' => $d->person_name ?? '',
                        'username' => $d->username ?? '',
                    ];
                    if(isset($permittedUsers[$key])){
                        $permittedUsers[$key][] = $member;
                    }else{
                        $permittedUsers[$key] = [$member];
                    }
                }
            }
            return $permittedUsers;
        } catch (\Throwable $e) {
            Log::error('PersonsServiceProvider::getNotificationUsers error', [
                'exception' => $e,
                'opKey' => $opKey,
            ]);
            return [];
        }
    }
}

