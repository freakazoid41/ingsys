<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Sys_con_ops;
use App\Models\Persons;
use App\Models\User;
use App\Models\Contacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class PersonsServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function getPersonTypes() {
        return Sys_options::where(['group_key' => 'op-pert'])->get();
    }

    public function setPerson($id = 0,$data,$files = [],$fileGroup = 'persons'){
        $formData     = $data;
        $contacts     = [];
        $user         = [];
        $permissions  = json_decode($data['permissions'] ?? "[]",true);
        
        $removed      = json_decode($data['removed'] ?? "[]",true);
       
        /*$permConn     = Sys_options::where([
                ['ctitle', '=', 'op_id'],
                ['op_key', '=', 'op-perm'],
        ])->first();*/
        //split form elements
        //first main elements
        $document = new Persons();
        if($id != 0) $document = Persons::where('id',$id)->first();
        foreach ($formData as $key => $value) {
            //here split data types and set main person data
            if(strpos($key,'main_') !== false){
                $key = explode('main_',$key)[1];
                if($key !== 'id') $document->{$key} = str_replace(',','',strip_tags($value));
            }
            if(strpos($key,'contact') !== false){
                $contacts = json_decode(strip_tags($value),true);
            }

            if(strpos($key,'user') !== false){
                $user[explode('user_',$key)[1]] = $value;
            }

            if($key == 'type_key'){
                $type = Sys_options::where('op_key',$value)->first();
                $document->type_id = $type->id;
            }
            
        }

        $rsp = $document->save();
        
       
        
        //delete removed items from database
        foreach ($removed as $s) {
            if(intval($s['id'] ?? 0) != 0){
                switch ($s['table']) {
                    case 'discountConnections':
                        $d = Discounts::where(['type_id' => $discConn->id,'main_id' => $document->id,'conn_id' => $s['conn_id']]);
                        $d->delete();
                        break;
                    case 'brandConnections':
                        //remove brand connection
                        $d = Sys_con_ops::where(['type_id' => $brandConn->id,'main_id' => $document->id,'conn_id' => $s['id']])->first();
                        $d->delete();

                        //remove discounts
                        /*$d = Discounts::where(['type_id' => $discConn->id,'main_id' => $document->id,'conn_id' => $s['id']]);
                        $d->delete();*/
                        break;
                    case 'branchConnections':
                        //remove brand connection
                        $d = Sys_con_ops::where(['type_id' => $branchConn->id,'main_id' => $document->id,'conn_id' => $s['id']])->first();
                        $d->delete();

                        //remove discounts
                        /*$d = Discounts::where(['type_id' => $discConn->id,'main_id' => $document->id,'conn_id' => $s['id']]);
                        $d->delete();*/
                        break;
                    case 'permConnections':
                        $perm = Sys_options::where('op_key',$s['op_key'])->first();
                        $d    = Sys_con_ops::where(['type_id' => $permConn->id,'main_id' => $s['id'],'conn_id' => $perm->id])->first();
                        $d->delete();
                        break;
                    
                }
            }
        }


        //now detect contact informations
        /*$op = (Sys_options::where(['op_key' => 'op-cont-person'])->first())->id;
        foreach ($contacts as $add) {
            
            $type = (Sys_options::where(['op_key' => $add['type']])->first())->id;
            
            $contact = new Contacts();
            if(isset($add['id'])) $contact = Contacts::where(['id' => $add['id']])->first();

            //this area is for b2b api records
            if(isset($add['b2bapi'])){
                $contact = Contacts::where(['ref_id' => $document->id , 'op_id' => $op,'type_id' => $type])->first();
                if(empty($contact)) $contact = new Contacts();
            } 
            

            if(isset($add['phone'])) $add['phone'] = strip_tags(str_replace('-', "",str_replace(')', "",str_replace('(', "",str_replace(' ', "", $add['phone'])))));

            $contact->type_id     = $type;
            $contact->ref_id      = $document->id; 
            $contact->op_id       = $op; 

            $contact->title       = strip_tags($add['title'] ?? 'Standart Address');
            $contact->description = json_encode($add, JSON_UNESCAPED_UNICODE);

            $contact->save();
        }*/
        

        //permissions inserts for selected users
        if(count(array_keys($permissions)) > 0){
            foreach ($permissions as $key => $list) {
                $perm = Sys_options::where('op_key',$key)->first();
                foreach($list as $u){
                    Sys_con_ops::updateOrCreate(
                        ['type_id' => $permConn->id,'main_id' => $u,'conn_id' => $perm->id],
                        [
                            'type_id' => $permConn->id,
                            'main_id' => $u,
                            'conn_id' => $perm->id
                        ],
                    );
                }
            }
        }
        
        if(!empty($user) && isset($user['password'])){
            $sr = [
                'person_id' => $document->id,
                'password'  => Hash::make($user['password']),
                'name'      => 'Client User'
            ];

            if( isset($user['username'])) $sr['email'] = $user['username'];
            User::updateOrInsert(
                ['person_id' => $document->id],
                $sr,
            );
        }

        /*if($files['bgfile']){
            User::updateOrInsert(
                ['person_id' => $document->id],
                [
                    'bg_image'      => 'Client User'
                ],
            );
        }*/

        return [
            'success' => $rsp,
            'id'      => $document->id,
            'data'    => $document
        ];
        
    }

    public function getPerson($id = 0,$search = null){
        $id      = str_replace(',','',strip_tags($id));
        $search  = str_replace(',','',strip_tags($search));
        $sql     = "select  i.id,
                            i.name,
                            i.type_id,
                            i.created_at,
                            i.status,
                            o.title  as  type_title,
                            o.op_key  as  type_key,
                            u.email  as  user_name
                        from persons as i
                            left join users as u on u.person_id = i.id
                            left join sys_options as o on o.id = i.type_id ";
        if($search == null){
            $where = " where i.id = '".$id."'";
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
        $document    = Persons::where('id',$id)->first();
        $connections = User::where('person_id',$id)->first();
        
        $connections->delete();  
        $document->delete();

        return ['success' => true];
    }
}
