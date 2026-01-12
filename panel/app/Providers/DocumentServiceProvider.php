<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Documents;
use App\Models\Document_files;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Transactions;
use Illuminate\Support\Facades\DB;


class DocumentServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function registerContent($id = 0,$requestData,$files = []){
        $typeKey      = $requestData['typeKey'] ?? 'op-doc-period';
        $dynamicF     = $requestData['dynamicF'] ?? [];
        $dynamicFiles = [];
        $removed      = $requestData['removedData'] ?? [];
        $isUpdate     = false;
       
        //now add sended files to the document with connection table info
        foreach($files as $key => $f){
            if(strpos($key,'dynamicFile') !== false){
                $dynamicFiles[$key] = $f;
            }
        }
        try {

            DB::beginTransaction(); // <= Starting the transaction

            //here set main document
            $document = new Documents();
            //for update purposes
            if(!is_numeric($id) && $id != 0){
                $document = Documents::where('qnid',$id)->first();
                $isUpdate = true;
            }else{
                $document->type_id = (\App\Models\Sys_options::where('op_key' , $typeKey)->first())->id;
            }
            
            foreach ($requestData as $key => $value) {
                if(strpos($key,'main_') !== false){
                    $key = explode('main_',$key)[1];
                    $document->{$key} = strip_tags($value);
                }
            }

            //if(isset($requestData['facility_id'])) $document->grp_code = $requestData['facility_id'];

            
            
            $rsp = $document->save();

            if(!$isUpdate){
                \App\Models\Transactions::create([
                    'op_id'     => 0, // '0' for document transactions
                    'type_id'   => (\App\Models\Sys_options::where('op_key' , 'doc_trans_created')->first())->id,
                    'log_id'    => 0,//$log->id,
                    'target_id' => $document->id,
                    'description' => 'New Document Added'
                ]);
            }  
            
            
            //removed data process
            foreach ($removed as $row) {
                $check = Sys_con_entities::where(['conn_id' => $row['id'],'entity_tag' => $row['key']])->first();
                if(!empty($check)){
                    if($check->table_tag == 'document_files'){
                        //just deactivate file on system
                        $file = Document_files::where('id',$check->entity_value)->first();
                        $file->status = 0;
                        $file->save();
                    }

                    $check->delete();
                }
            }

            

            //////////////////////////////// Dynamic Fields ********************************
            //now add dynamic fields to the personnel (this is canon way for addional fields)
            $connEntries  = [];
            $stypeIdMain  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-main'])->first())->id;
            $stypeIdFile  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-file'])->first())->id;
            $allConnections = [];
            foreach($dynamicF as $key => $field){
                $id      = explode('**',$key)[1];
                $tag     = $field['tag'];
                $typeId  = (Sys_options::where(['ctitle' => 'type_id','op_key' => $tag])->first())->id;
                
                //set new field
                $conn  = new Sys_con_ops();
                //for value update
                if(strpos($id,'new') === false) $conn = Sys_con_ops::where('id', $id)->first();

                //last add connection
                $conn->main_id     = $document->id; // main connection
                $conn->conn_id     = 0;   
                $conn->type_id     = $typeId;
                $conn->sub_type_id = $stypeIdMain;
                $conn->save();
                $connEntries[$tag] = $conn->id;
                
                //now check if any entity sended
                foreach($field['entities'] as $ekey => $value){
                    $entity  = new Sys_con_entities();

                    //check if entity is exist before
                    $check = Sys_con_entities::where(['conn_id' => $conn->id,'entity_tag' => $ekey,'table_tag' => 'sys_con_ops'])->first();
                    if(!empty($check)) $entity = $check;
                    
                    $entity->table_tag      = 'sys_con_ops';
                    $entity->conn_id        = $conn->id;
                    $entity->entity_tag     = $ekey;
                    $entity->entity_value   = strip_tags($value);

                    $entity->save();
                };

                $entities = Sys_con_entities::where(['conn_id' => $conn->id,'table_tag' => 'sys_con_ops'])->get();
                foreach($entities as $e){
                    $allConnections[$e->entity_tag] = $e->entity_value;
                }

                //now check if any file is sended
                $stypeId  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-file'])->first())->id;
                foreach($dynamicFiles as $fkey => $file){
                    if(strpos($fkey,$id) !== false){
                        $fileName = explode('*-*',$fkey)[1];
                        
                        $typeTag = explode('**',$fileName)[0];
                        $fileId  = explode('**',$fkey)[2];
                        //add file
                        
                        $fileResponse = addFileToDb($file,'form-file',$fileId,'documents',$document->id);

                        if($fileResponse['success'] == false) throw new \Exception('Dosya Sisteme Eklenemedi...');

                        $fileId = $fileResponse['rowId'];


                        //now add file connection
                        $entity  = new Sys_con_entities();

                        //check if entity is exist before
                        $check = Sys_con_entities::where(['conn_id' => $conn->id,'entity_tag' => $fileName,'table_tag' => 'sys_con_ops'])->first();
                        if(!empty($check)) $entity = $check;
                        
                        $entity->table_tag      = 'document_files';
                        $entity->conn_id        = $conn->id;
                        $entity->entity_tag     = $fileName;
                        $entity->entity_value   = strip_tags($fileId);

                        $entity->save();
                    }
                }
            }
            //////////////////////////////// Dynamic Fields ********************************
            DB::commit(); // <= Commit the changes
            return [
                'success'          => $rsp,
                'id'               => $document->id,
                'data'             => $document,
                'connEntries'      => $connEntries,
                'allEntities'      => $allConnections,
                'grpCode'          => $document->grp_code
            ];
        } catch (\Exception $e) {
            
            
            DB::rollBack(); // <= Rollback in case of an exception

            return [
                'success'          => false,
                'id'               => 0,
                'message'          => $e->getMessage()
            ];
        }

    }

    public function getFormData($id){
        $dynamicF = [];
        //////////////////////////////// Dynamic Fields ********************************
        //get dynamic fields info
        $caseSection = env('DB_CONNECTION') == 'sqlsrv' ? 
                        "(case
                            when sce.table_tag = 'document_files'
                            then (  select  CONCAT(
                                                '{\"description\":\"', description, '\",\"id\":\"', df.id, '\"}'
                                            )
                                    from document_files as df
                                        where df.id = sce.entity_value)

                            else  sce.entity_value
                        end) "
                       :
                        "(case
                            when sce.table_tag = 'document_files'
                            then (  select  ".(env('DB_CONNECTION') == 'pgsql' ? 'json_build_object' : 'json_object')."(
                                                'description',description,
                                                'id',df.id
                                            )
                                    from document_files as df
                                        where df.id = sce.entity_value".(env('DB_CONNECTION') == 'pgsql' ? '::int' : '').")".(env('DB_CONNECTION') == 'pgsql' ? '::text' : '')."

                            else  sce.entity_value
                        end)" ;

        $sql = "select  dco.id,
                        so.op_key,
                        sce.entity_tag,
                        $caseSection as entity_value
                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
                    inner join documents as d on d.id = dco.main_id
    
                    where   so.group_key = 'op-doc-forms' and 
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                            d.qnid = '".$id."'";
        $data  = DB::select($sql);

        foreach ($data as $row){
            
            if(!isset($dynamicF[$row->op_key])) $dynamicF[$row->op_key] = [];
            if(!isset($dynamicF[$row->op_key][$row->id]))$dynamicF[$row->op_key][$row->id] = [
                'entities' => [],
                'files'    => [],
            ];
            $dynamicF[$row->op_key][$row->id]['entities'][$row->entity_tag] = $row->entity_value;
            /*if($row->file_id !== null){
                if(!isset($dynamicF[$row->op_key][$row->id]['files'][$row->tag])) $dynamicF[$row->op_key][$row->id]['files'][$row->tag] = [];

                $status = explode('**',$row->last_status);
                $dynamicF[$row->op_key][$row->id]['files'][$row->tag][$row->file_id] = [
                    'file'        => $row->file,
                    'file_id'     => $row->file_id,
                    'tag'         => $row->tag,
                    'last_status' => $status[0],
                    'last_entry'  => $status[1] ?? '',
                ];
            }*/
        }

        //////////////////////////////// Dynamic Fields ********************************


        return [
            'formFormat' => $dynamicF
        ];
    }

    public function removeContent($id){
        //first find all attributes
        $document     = Documents::where('qnid',$id)->first();
        $stypeIdMain  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-main'])->first())->id;
        $stypeIdFile  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-file'])->first())->id;


        $connections = Sys_con_ops::where(['main_id' => $document->id,'sub_type_id' =>  $stypeIdMain])->get();
        $connections = $connections->merge(Sys_con_ops::where(['main_id' => $document->id,'sub_type_id' =>  $stypeIdFile])->get());
        $entites     = [];

        foreach($connections as $c){
            $entites = Sys_con_entities::where('conn_id',$c->id)->get();
            foreach($entites as $e){
                if($e->table_tag == 'document_files'){
                    $rsp = removefile($e->entity_value);
                    if(!empty($rsp['row']) && $rsp['success'] == true) $rsp['row']->delete();
                }
                $e->delete();  
            }
            $c->delete();
        }

        $document->delete();

        return ['success' => true];
    }

    /**
     * this method will return facility informations from given qr_code
     */
    public function getFacility($data){
        $dynamicF = [];
        //get dfacility fields info
        $sql = "select  sce2.entity_tag,
                        (case
                            when sce2.table_tag = 'document_files'
                            then (  select  df.description
                                    from document_files as df
                                        where df.id = sce2.entity_value ".(env('DB_CONNECTION') == 'pgsql' ? '::bigint' : '').")".(env('DB_CONNECTION') == 'pgsql' ? '::text' : '')."

                            else  sce2.entity_value
                        end) as entity_value,
                        d.qnid

                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
                    left join sys_con_entities sce2 on sce2.conn_id = dco.id
                    inner join documents as d on d.id = dco.id

                    where   so.op_key = 'op-doc-facility-form' and 
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                            sce.entity_tag = 'qr_code' and sce.entity_value like '%".strip_tags($data['code'])."%'";
        $data  = DB::select($sql);
        
        //also get inventory list
        $invData = (new Documents())->tableList(['filter' => [
                [
                    'key'   => 'form-type',
                    'type'  => '=',
                    'value' => 'op-doc-inventory-form'
                ],[
                    'key'   => 'type',
                    'type'  => '=',
                    'value' => 'op-doc-inventory'
                ]
            ]
        ])['data'];
       
        $invList = [];
        foreach($invData as $d){
            $detail = json_decode($d->main_attr ?? '{}',true);

            if($detail === null){
                //check if is fixable (json is valid accualy but mssql is braking a bit (because string methods))
                $detail = repair_json_string($d->main_attr ?? '{}',true);
                if($detail == false) continue;
            }
            $invList[$d->id] = /*[
                'qnid'  => $d->id,
                'title' => $detail['title'],
                'code'  => $detail['code'],
            ]*/$detail;
        }

        if(!empty($data)){
            foreach ($data as $row){
                $dynamicF[$row->entity_tag] = $row->entity_value;

                if(strpos($row->entity_tag,'facilityinvetorygroup') !== false){
                    
                    $key = explode('**',$row->entity_tag);
                    if(!isset($invList[$key[1].$key[2]])) $invList[$key[1].$key[2]] = [];

                    $key[0] = str_replace("inventory", "title", $key[0]);
                    $key[0] = str_replace("description", "code", $key[0]);

                    $invList[$key[1].$key[2]][$key[0]] = $row->entity_value;
                    
                }
            } 
            return [
                'success'     => true,
                'data'        => $dynamicF,
                'inventories' => $invList,
                'qnid'        => $data[0]->qnid
            ];
        }else{
            return [
                'success' => false,
            ];
        }
    }

    /**
     * this method will return facility visitor informations from given phone number
     */
    public function getPerson($data,$getOld = true,$facilityId = 0){
        $dynamicF = [];
        //get dfacility fields info
        $sql = "select  sce2.entity_tag,
                        sce2.entity_value,
                        dco.id  as  conn_id,
                        p.qnid  as  qnid,
                        p.grp_code as grp_code


                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
                    left join sys_con_entities sce2 on sce2.conn_id = dco.id
                    inner join documents as p on p.id = dco.main_id
    
                    where   so.op_key = 'op-doc-visit-form' and 
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                           
                            sce.entity_tag like 'phone' and sce.entity_value like '%".strip_tags($data['phone'])."%' and
                            sce.conn_id = (select ".(env('DB_CONNECTION') == 'sqlsrv' ? 'TOP 1' : '')." conn_id from sys_con_entities 
                                where entity_value like '%".strip_tags($data['phone'])."%' order by conn_id desc ".(env('DB_CONNECTION') == 'sqlsrv' ? '' : 'limit 1').")
                            order by sce2.id asc";
        $data  = DB::select($sql);
        $allConnections = [];
        if(!empty($data)){
            foreach ($data as $row) $dynamicF[$row->entity_tag] = $row->entity_value;
            //here detect for video replay pass
            if(!$getOld){

                $startDate = new \DateTime($dynamicF['entered_at']);
                $endDate = new \DateTime(); // today
                // total full days between the two dates
                $dayCount = (int) $startDate->diff($endDate)->format('%a');
                //here detect daycount and facility info
               
                if($dayCount > 29 || (isset($dynamicF['facility_id']) && $facilityId != 0 && $dynamicF['facility_id'] != $facilityId)){
                    return [
                        'success' => false,
                    ];
                }
            }
            //means is older data
            /*if(isset($dynamicF['exited_at']) && new \DateTime($dynamicF['entered_at']) > new \DateTime($dynamicF['exited_at'])){
                unset($dynamicF['exited_at']);
            }*/
            $dynamicF['qnid'] = $data[0]->qnid;
            return [
                'success'     => true,
                'data'        => $dynamicF,
                'connEntries' => ['op-doc-visit-form' => $data[0]->conn_id],
                'allEntities' => $dynamicF,
                'connId'      => $data[0]->conn_id,
                'qnid'        => $data[0]->qnid,
                'id'          => $data[0]->qnid,
                'grpCode'     => $data[0]->grp_code
            ];
        }else{
            return [
                'success' => false,
            ];
        }
    }

    public function getInventory($id){
        $id      = str_replace(',','',strip_tags($id));
        
        $dynamicF = [];
        //get dfacility fields info
        $sql = "select  sce2.entity_tag,
                        sce2.entity_value,
                        dco.id  as  conn_id,
                        p.qnid  as  qnid


                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
                    left join sys_con_entities sce2 on sce2.conn_id = dco.id
                    inner join documents as p on p.id = dco.main_id
    
                    where   so.op_key = 'op-doc-visit-form' and 
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                           
                            p.qnid ='".$id."' 
                            order by sce2.id asc";
        $data  = DB::select($sql);

        if(!empty($data)){
            foreach ($data as $row) {
                if(strpos($row->entity_tag,'**givengroup') !== false || strpos($row->entity_tag,'**visitorinvgroup') !== false){
                    $dynamicF[$row->entity_tag] = $row->entity_value;
                }
            }
            
            return [
                'success'     => true,
                'data'        => $dynamicF,
                'connId'      => $data[0]->conn_id,
                'qnid'        => $data[0]->qnid,
            ];
        }else{
            return [
                'success' => false,
            ];
        }
    }
    
    /**
     * this method will prepare export data for documents
     */
    public function getExportData($type,$filter){
        $response = [];
        // normalize $filter (list of JSON objects) into an array of associative arrays
        // normalize filter (accept JSON string or array of json strings/objects/arrays)
        if (is_string($filter)) { $tmp = json_decode($filter, true); $filter = json_last_error() === JSON_ERROR_NONE ? ($tmp ?? []) : [$filter]; }
        if (!is_array($filter)) $filter = [];
        $filter = array_values(array_map(function($it){ if (is_string($it)){ $d = json_decode(trim($it, "\"'"), true); return json_last_error()===JSON_ERROR_NONE ? $d : $it; } if (is_object($it)) return json_decode(json_encode($it), true); return $it; }, $filter));
        
        switch($type){
            case 'visit':
                $response[] = ['Ad Soyad','Telefon','E-Posta','Tesis','Giriş','Video Başlama','Video Bitiş','İzleme Süresi','İzleme Durum','Çıkış'];
                $data = (new Documents())->tableList(['filter' => array_merge([
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-visit-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-visit'
                        ]
                        ],$filter)
                ])['data'];
                break;
            case 'facility':
                $response[] = ['Tesis İsmi','Adresi','QR Kimlik'];
                $data = (new Documents())->tableList(['filter' => [
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-facility-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-facility'
                        ]
                    ]
                ])['data'];
                break;
            case 'inventory':
                $response[] = ['Ekipman İsmi','Ekipman Kodu'];
                $data = (new Documents())->tableList(['filter' => [
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-inventory-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-inventory'
                        ]
                    ]
                ])['data'];

                break;
        }

        $fancyTimeFormat = function ($duration){
            $duration = intval($duration);
            // Saat, dakika ve saniye hesaplamaları
            $hrs = floor($duration / 3600);
            $mins = floor(($duration % 3600) / 60);
            $secs = floor($duration % 60);

            // "1:01" veya "4:03:59" veya "123:03:59" formatında çıktı
            $ret = "";

            if ($hrs > 0) {
                $ret .= $hrs . ":" . ($mins < 10 ? "0" : "");
            }

            $ret .= $mins . ":" . ($secs < 10 ? "0" : "");
            $ret .= $secs;

            return $ret;
        };
       
        foreach($data as $d){

            //here clear for mssql,
            /*$d->main_attr = str_replace('"":""','":"',$d->main_attr);
            $d->main_attr = str_replace('"",""','","',$d->main_attr);
            $d->main_attr = str_replace('{""','{"',$d->main_attr);
            $d->main_attr = str_replace('""}','"}',$d->main_attr);
            /*$d->main_attr = str_replace('"{','{',$d->main_attr);
            $d->main_attr = str_replace('}"','}',$d->main_attr);*/

            $detail = json_decode($d->main_attr ?? '{}',true);

            if($detail === null){
                //check if is fixable (json is valid accualy but mssql is braking a bit (because string methods))
                $detail = repair_json_string($d->main_attr ?? '{}',true);
                if($detail == false) continue;
            }

            foreach($detail as $row){
                $detail[$row['Key']] = $row['Value'];

                if(strpos($row['Key'],'per_name') !== false){
                    if(!isset($detail['per_name'])) $detail['per_name'] = [];
                    $detail['per_name'][] = $row['Value'];
                }
            }
           
            switch($type){
                 case 'visit':
                    $response[] = [
                        $detail['name'].' '.$detail['surname'],
                        $detail['phone'],
                        $detail['email'] ?? '-',
                        $detail['facility'] ?? '-',
                        $detail['entered_at'] ?? '-',
                        isset($detail['video_start']) ? (explode(' ',$detail['video_start'])[1] ?? '-') : '-',
                        isset($detail['video_end']) ? (explode(' ',$detail['video_end'])[1] ?? '-') : '-',
                        isset($detail['video_second']) ? $fancyTimeFormat($detail['video_second']) : '0:00',
                        $detail['video_status'] ?? '-',
                        $detail['exited_at'] ?? 'Bekleniyor',
                    ];
                    break;
                case 'inventory':
                    $response[] = [
                        $detail['title'],
                        $detail['code'],
                    ];
                    break;
                case 'facility':
                    $response[] = [
                        $detail['title'],
                        $detail['address'],
                        $detail['qr_code'],
                        
                    ];
                    break;
                
            }
        }

        return [
            'success' => true,
            'data'    => $response
        ];
    }

   
    
}
