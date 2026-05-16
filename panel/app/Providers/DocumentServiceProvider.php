<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Documents;
use App\Models\Document_files;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\UserLog;
use App\Models\User;
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
        $registerUser = session('person_id'); // this is records user_id for more easy logging and reporting
       
        //now add sended files to the document with connection table info
        foreach($files as $key => $f){
            if(strpos($key,'dynamicFile') !== false){
                $dynamicFiles[$key] = $f;
            }
        }
        try {
            $logData = [
                'user_id'     => auth('sanctum')->user()->id,
                'sys_code'    => $GLOBALS['SYS_CODE'] ?? 'CATES',
                'relation'    => 'documents',
                'relation_id' => $id,
                'type_id'     => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
                'description' => []
            ];


            DB::beginTransaction(); // <= Starting the transaction

            //here set main document
            $document = new Documents();
            //for update purposes
            if(!is_numeric($id) && $id != 0){
                $document = Documents::where('qnid',$id)->first();
                $isUpdate = true;

                //here get all data for before data logging
                
                $logData['description'] = [
                    'before' => $this->getFormData($document->qnid),
                    'after'  => [],
                ];
            }else{
                $document->type_id = (\App\Models\Sys_options::where('op_key' , $typeKey)->first())->id;
            }

            foreach ($requestData as $key => $value) {
                if(strpos($key,'main_') !== false){
                    $key = explode('main_',$key)[1];
                    $document->{$key} = strip_tags($value);
                }
            }

            //we are checking because only creator is important
            if(!$isUpdate) $document->person_id = $registerUser; // this is for more easy reporting or logging
            
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
            $stypeIdMain  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-main'])->first())->id;
            $stypeIdFile  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-file'])->first())->id;

            
            if(in_array($typeKey,['op-doc-request','op-doc-offer']) && !$isUpdate){
                //here count documents for counable request number
                $documentCount = Documents::where('type_id',$document->type_id)->count();

            }
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

                //now check if any entity sended
                $field['entities']['qnid'] = $document->qnid;

                if(in_array($typeKey,['op-doc-request','op-doc-offer'])){
                    if($isUpdate) {
                        $field['entities']['rev_date'] = date('d/m/Y');
                    }else{
                        $field['entities']['req_no'] = $documentCount;
                    }
                } 


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

                //now check if any file is sended
                $stypeId  = (Sys_options::where(['ctitle' => 'sub_type_id','op_key' => 'form-file'])->first())->id;
                foreach($dynamicFiles as $fkey => $file){
                    if(strpos($fkey,$id) !== false){
                        $fileName = explode('*-*',$fkey)[1];
                        
                        $typeTag = explode('**',$fileName)[0];
                        $fileId  = explode('**',$fkey)[2];
                        //add file

                        //here add short info about file log
                        $fileTypeInfo = (Sys_options::where(['ctitle' => 'type_id','op_key' => 'op-'.$typeTag])->first());
                        $fileTypeInfo = !empty($fileTypeInfo) ? $fileTypeInfo->title : 'Dosya';

                        $fileResponse = addFileToDb($file,'form-file',$fileId,'documents',$document->id,($fileTypeInfo.' Dosyası Sisteme Eklendi'));

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

            //here get updated data
            $updatedResult = $this->getFormData($document->qnid);
            $oldData       = $logData['description']['before'] ?? [];
            $logData['description'] = json_encode([
                'before' => $oldData,
                'after'  => $updatedResult,
            ],JSON_UNESCAPED_UNICODE);

            $logData['relation_id'] = $document->id;
            
            //here save log data
            UserLog::create($logData);


            return [
                'success'          => $rsp,
                'id'               => $document->id,
                'data'             => $document,
                'detail'           => $updatedResult,
                'qnid'             => $document->qnid,
                'before'           => $oldData,
                'after'            => $updatedResult,
            ];
        } catch (\Exception $e) {
            
            
            DB::rollBack(); // <= Rollback in case of an exception

            return [
                'success'          => false,
                'id'               => 0,
                'message'          => $e->getMessage().' => '.$e->getLine().' => '.$e->getFile(),
            ];
        }

    }

    public function getFormData($id){
        $dynamicF = [];
        //////////////////////////////// Dynamic Fields ********************************
        //get dynamic fields info
       
        $sql = "select  dco.id,
                        so.op_key,
                        sce.entity_tag,
                        (case
                            when sce.table_tag = 'document_files'
                            then (  select  json_build_object(
                                                'description',description,
                                                'id',df.id,
                                                'status', df.status,
                                                'last_status',(select   json_build_object(
                                                                            'op_key',sot.op_key,
                                                                            'title' , sot.title,
                                                                            'name'  , p.name,
                                                                            'note' , t.description
                                                                            
                                                                        ) 
                                                                        from transactions t 
                                                                
                                                                    inner join sys_options sot on sot.id = t.type_id
                                                                    inner join user_logs ul on ul.id = t.log_id
                                                                    inner join users u on u.id = ul.user_id
                                                                    inner join persons p on p.id = u.person_id
                                                                where t.target_id = df.id and op_id = 1 order by t.id desc limit 1)
                                            )
                                    from document_files as df
                                        where df.id = sce.entity_value::int)::text

                            else  sce.entity_value
                        end) as entity_value
                        
       
                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
                    inner join documents as d on d.id = dco.main_id
    
                    where   so.group_key = 'op-doc-forms' and 
                            so.op_key not in ('op-doc-user-permission-form','op-doc-user-contact-form','op-doc-user-client-form') and
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

        $document = "select sp.op_key,
                            d.* ,
                            (select     json_agg(
                                            json_build_object(
                                                'op_key',so.op_key,
                                                'op_title' , so.title,
                                                'note',t.note,
                                                'created_at',t.created_at,
                                                'name',p.name
                                            )
                                        )
                                from transactions as t
                                    inner join sys_options so on so.id = t.type_id
                                    inner join user_logs ul on ul.id = t.log_id
                                    inner join users u on u.id = ul.user_id
                                    inner join persons p on p.id = u.person_id
                                where target_id = d.id and so.group_key = 'op-trans-' || sp.op_key)  as  status
                            
                            from documents d 
                        inner join sys_options as sp on sp.id = d.type_id
                        where d.qnid = '".$id."'";
       
        $document  = DB::select($document)[0] ?? [];
        return [
            'document'   => $document,
            'formFormat' => $dynamicF
        ];
    }

    public function removeContent($id){
        //first find all attributes
        $document    = Documents::where('qnid',$id)->first();
        $document->status = 0;
        $document->save();
        
        $detail = $this->getFormData($document->qnid);
        
        if($detail['document']->op_key == 'op-doc-client'){
            //here deactivate connected client users (only op-pert-reseller)
            $sql = "select  u.email,
                            sce2.entity_tag,
                            sce2.entity_value,
                            sce2.id
                            
                    from sys_con_entities sce
                            inner join sys_con_entities as sce2 on  sce2.conn_id = sce.conn_id
                            inner join sys_con_ops sco on sco.id = sce2.conn_id
                            inner join persons as p on p.id = sco.main_id
                            inner join users as u on u.person_id = p.id
                        where   sce.entity_tag like '%cliid%'
                            and sce.entity_value = '$id'";
            $data = DB::select($sql);
            
            foreach ($data as $key => $value) {
                if(strpos($value->entity_tag,'userclientgroup') !== false){
                    //here get user and make it disabled
                    User::where('email',$value->email)->update(['status' => 0]);
                }
            }
        }


        UserLog::create([
            'user_id'     => auth('sanctum')->user()->id,
            'sys_code'    => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation'    => 'documents',
            'relation_id' => $document->id,
            'type_id'     => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
            'description' => json_encode([
                'before' => $this->getFormData($document->qnid),
                'after'  => [],
                'desc'   => 'İçerik pasife alındı artık listelerde görünmeyecek..'
            ],JSON_UNESCAPED_UNICODE)
        ]);


        /*$connections = Sys_con_ops::where('main_id',$document->id)->get();
        $entites     = [];

        UserLog::create([
            'user_id'     => auth('sanctum')->user()->id,
            'sys_code'    => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation'    => 'documents',
            'relation_id' => $document->id,
            'type_id'     => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
            'description' => json_encode([
                'before' => $this->getFormData($document->qnid),
                'after'  => [],
                'desc'   => 'Sistemden Kaldırıldı..'
            ],JSON_UNESCAPED_UNICODE)
        ]);

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

        $document->delete();*/

        

        return ['success' => true];
    }

    public function removeTransaction($id){
        //first find all attributes
        $document    = Transactions::where('qnid',$id)->first();
        //remove connected transaction 
        $connTrans   = Transactions::where('trans_id',$document->id)->first();
        if(!empty($connTrans)) $connTrans->delete();
        //finaly remove main element
        $document->delete();

        return ['success' => true];
    }

    /**
     * this method will prepare export data for documents
     */
    public function getExportData($type){
        $response = [];
        switch($type){
            case 'flats':
                $response[] = ['Daire İsmi','Kat Maliki','Güncel Bakiye'];
                $data = (new Documents())->tableList(['filter' => [
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-flat-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-flat'
                        ]
                    ]
                ])['data'];
                break;
            case 'accounts':
                $response[] = ['Kasa','Güncel Bakiye'];
                $data = (new Documents())->tableList(['filter' => [
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-target-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-target'
                        ]
                    ]
                ])['data'];
                break;
            case 'meetings':
                $response[] = ['Tarih','Güncel Yönetici','Güncel Aidat'];
                $data = (new Documents())->tableList(['filter' => [
                        [
                            'key'   => 'form-type',
                            'type'  => '=',
                            'value' => 'op-doc-meeting-form'
                        ],[
                            'key'   => 'type',
                            'type'  => '=',
                            'value' => 'op-doc-meeting'
                        ]
                    ]
                ])['data'];
                break;
        }

       
        foreach($data as $d){
            $detail = json_decode($d->main_attr,true);
            foreach($detail as $row){
                $detail[$row['Key']] = $row['Value'];

                if(strpos($row['Key'],'per_name') !== false){
                    if(!isset($detail['per_name'])) $detail['per_name'] = [];
                    $detail['per_name'][] = $row['Value'];
                }
            }
           
            switch($type){
                case 'meetings':
                    $response[] = [
                        $detail['meet_date'],
                        $detail['meet_active_supervisor'],
                        $detail['meet_amount'].' '.env('SYS_CUR'),
                    ];
                    break;
                case 'accounts':
                    $response[] = [
                        $detail['title'],
                        ($d->balance_pure ?? 0).' '.($detail['currency'] ?? env('SYS_CUR')),
                    ];
                    break;
                case 'flats':
                    $response[] = [
                        $detail['title'],
                        implode(' , ',$detail['per_name']),
                        ($d->balance_pure ?? 0).' '.($detail['currency'] ?? env('SYS_CUR')),
                    ];
                    break;
            }
        }

        return [
            'success' => true,
            'data'    => $response
        ];
    }

    /**
     * this method will set status for documents
     */
    public function setStatus($id,$statusKey,$note){
        try{
            $document = Documents::where('qnid',$id)->first();
            $documentType = Sys_options::where('id', $document->type_id)->first();
            $log = UserLog::create([
                'user_id'     => auth('sanctum')->user()->id ?? 0,
                'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
                'relation'    => 'documents',
                'relation_id' => $document->id,
                'type_id'     => Sys_options::where('op_key', 'log-document-status-update')->first()->id ?? 0,
                'description' => json_encode(
                    [
                        'after' => ['document' => ['op_key' => $documentType->op_key, 'qnid' => $document->qnid]],
                        'desc' => $documentType->title.' Durumu Değiştirildi',
                        'note' => $note
                    ], JSON_UNESCAPED_UNICODE)
            ]);




            $type = Sys_options::where('op_key',$statusKey)->first();
            $trans = new Transactions();
            $trans->target_id  = $document->id;  // can be add from both listing pages.. 
            $trans->log_id     = $log->id;
            $trans->type_id    = $type->id;
            $trans->note       = $note ?? '-';
            $trans->amount     = 0;
            $trans->cur_id     = 0;
            $trans->rel_id     = 0;
            $trans->sign       = 0;
            $trans->period     = '-';
            //$trans->created_at =  
            $trans->save();

            return [
                'data'    => $type->title,
                'detail'  => $this->getFormData($id),
                'success' => true,
            ];
        }catch(Exception $e){
            return [
                'success' => false,
                'msg'     => $e->getMessage(),
            ];
        }
    }

    /**
     * this method will prepare export data for transactions
     */
    public function getTransExportData($id = null){
        $response = [];
        $filter   = [];
        if($id != null){
            $documentId = Documents::where('qnid',$id)->first();


            $filter = [
                [
                    'key'   => 'target_id',
                    'type'  => '=',
                    'value' => $documentId->id
                ]
            ];

            $data = $this->getFormData($id);
            
            $title = array_values(array_values($data)[0])[0][$documentId->id]['entities']['title'];
            $response[] = ['Kasa : ',$title];
            $response[] = [' '];
        } 
        $data = (new Transactions())->tableList(['filter' => $filter ])['data'];

        $response[] = ['Periyod','Tarih','Kaynak','Hedef','Tip','Yön','Miktar','Birim','Açıklama'];
        foreach($data as $d){
            $conn = [];
            foreach(json_decode($d->conn_info,true) as $cv){
                $conn[$cv['Key']] = $cv['Value'];
            }

            $main = [];
            foreach(json_decode($d->main_info,true) as $cv){
                $main[$cv['Key']] = $cv['Value'];
            }

            

            $response[] = [
                $d->period,
                $d->created_at,
                $conn['title'],
                $main['title'],
                $d->type,
                intval($d->sign) == 1 ? '->' : '<-',
                (intval($d->sign) != 1 ? '-' : '').$d->amount,
                $d->cur,
                $d->note
            ];
        }

        return [
            'success' => true,
            'data'    => $response
        ];
    }


    public function documentFileStatus($id,$statusKey,$note){
    
        if(isset($statusKey)){
            try{
                DB::beginTransaction();

                //get file 
                $file              = Document_files::where('qnid',$id)->first();
                $type              = Sys_options::where('op_key',$statusKey)->first();
                $entity            = Sys_con_entities::where(['entity_value' => $file->id,'table_tag' => 'document_files'])->first();
                $fileTitle         = Sys_options::where('op_key','op-'.explode('**',$entity->entity_tag)[0])->first()->title ?? 'Dosya';
                $EntityConnections = Sys_con_entities::where(['conn_id' => $entity->conn_id])->get();
                //add transaction to file
                $log              = UserLog::create([
                    'user_id'     => auth('sanctum')->user()->id,
                    'sys_code'    => $GLOBALS['SYS_CODE'],
                    'relation'    => 'documents',
                    'relation_id' => $file->id,
                    'type_id'     => $type->id,
                    'description' => json_encode(array(
                        'file_id' => $file->id,
                        'desc'    => $fileTitle.' Dosya Durumu Değiştirildi => '.$type->title,
                        'note'    => $note,
                    ),JSON_UNESCAPED_UNICODE)
                ]);

                \App\Models\Transactions::create([
                    'op_id'     => 1,
                    'type_id'   => $type->id,
                    'log_id'    => $log->id,
                    'target_id' => $file->id,
                    'description' => json_encode(array(
                        'file_id' => $file->id,
                        'desc'    => 'Dosya Durumu Değiştirildi => '.$type->title,
                        'note'    => $note,
                    ),JSON_UNESCAPED_UNICODE)
                ]);

                DB::commit();

                return [
                    'success'          => true,
                    'id'               => $log->id,
                    'data'             => $type->title,
                    'fileTitle'        => $fileTitle,
                    'description'      => $fileTitle.' Dosya Durumu Değiştirildi => '.$type->title,
                    'note'             => $note,
                    'connections'      => $EntityConnections,
                ];
            }catch(\Exception $e){
                DB::rollBack();
                return [
                    'success' => false,
                    'id'      => 0,
                    'message' => $e->getMessage().' => '.$e->getLine().' => '.$e->getFile(),
                ];
            }

        }else{
            return [
                'success'          => false,
                'id'               => 0,
            ];
        }
    }

    /**
     * this method will return rejected file warnings for client users
     */
    public function getDocumentFiles($documentId){
        $sql = "SELECT 
                        df.qnid,
                        df.description,
                        sf.title,
                        p.name as rejected_by,
                        d.qnid as cli_id
                    FROM transactions AS t
                        INNER JOIN sys_options AS so
                            ON so.id = t.type_id
                        INNER JOIN document_files AS df 
                            ON df.id = t.target_id
                        INNER JOIN documents AS d 
                            ON d.id = df.relation_id
                        INNER JOIN sys_con_entities AS sys 
                            ON sys.entity_value = df.id::text AND sys.table_tag = 'document_files'
                        inner join sys_options as sf
                            on sf.op_key = 'op-'|| SPLIT_PART(sys.entity_tag, '**', 1)
                        inner join user_logs as ul
                            on ul.id = t.log_id
                        inner join users as u
                            on u.id = ul.user_id    
                        inner join persons as p
                            on p.id = u.person_id
                    WHERE 
                        df.relation = 'documents'       
                    and df.status = 1  
                    and d.qnid = '$documentId'
                    GROUP BY 
                        df.qnid, 
                        df.description,
                        sf.title,
                        p.name,
                        d.qnid";
        $data = DB::select($sql);

        return [
            'success' => true,
            'data'    => $data
        ];
    }

    /**
     * this method will return rejected file warnings for client users
     */
    public function getRejectedClientFiles($list = []){
        $sql = "WITH last_tx AS (
                        SELECT DISTINCT ON (t.target_id)
                            t.target_id AS df_id,
                            sot.op_key,
                            p.name AS rejected_by
                        FROM transactions t
                            JOIN user_logs ul ON ul.id = t.log_id
                            JOIN users u ON u.id = ul.user_id
                            JOIN persons p ON p.id = u.person_id
                            JOIN sys_options sot ON sot.id = t.type_id
                        WHERE t.op_id = 1
                            ORDER BY t.target_id, t.id DESC
                )
                SELECT 
                    df.qnid,
                    df.description,
                    sf.title,
                    d.qnid AS cli_id,
                    lt.rejected_by
                FROM document_files df
                    JOIN documents d ON d.id = df.relation_id
                    JOIN sys_con_entities sys ON sys.entity_value = df.id::text 
                                            AND sys.table_tag = 'document_files'
                    JOIN sys_options sf ON sf.op_key = 'op-' || SPLIT_PART(sys.entity_tag, '**', 1)
                    LEFT JOIN last_tx lt ON lt.df_id = df.id
                        WHERE   df.relation = 'documents' 
                            AND df.status = 1 
                            AND d.qnid in ('".implode("','",$list)."')
                            AND lt.op_key = 'doc_file_rejected'";
        $data = DB::select($sql);

        return [
            'success' => true,
            'data'    => $data
        ];
    }

    /**
     * this method will return awaiting file warnings for client users
     */
    public function getAwaitingClientFiles($list = []){
        $sql = "SELECT 
                        df.qnid,
                        df.description,
                        sf.title,
                        p.name as inserted_by,
                        d.qnid as cli_id,
                        df.created_at
                    FROM transactions AS t
                        INNER JOIN sys_options AS so
                            ON so.id = t.type_id
                        INNER JOIN document_files AS df 
                            ON df.id = t.target_id
                        INNER JOIN documents AS d 
                            ON d.id = df.relation_id
                        INNER JOIN sys_options as sod on sod.id = d.type_id
                        INNER JOIN sys_con_entities AS sys 
                            ON sys.entity_value = df.id::text AND sys.table_tag = 'document_files'
                        inner join sys_options as sf
                            on sf.op_key = 'op-'|| SPLIT_PART(sys.entity_tag, '**', 1)
                        inner join user_logs as ul
                            on ul.id = t.log_id
                        inner join users as u
                            on u.id = ul.user_id    
                        inner join persons as p
                            on p.id = u.person_id
                    WHERE df.relation = 'documents' and (
                            select sot.op_key from transactions as ts
                                    inner join sys_options as sot on sot.id = ts.type_id
                                              where ts.target_id = df.id
                                    order by ts.id desc limit 1

                        ) = 'doc_file_waiting' and sod.op_key = 'op-doc-client'
                    and df.status = 1 ";

        $data = DB::select($sql);

        return [
            'success' => true,
            'data'    => $data
        ];
    }


    /**
     * this method will update client connections for other documents
     */
    public function updatePersonClients($documentId,$clientData){

       $sql = "select   sce2.entity_tag,
                        sce2.entity_value,
                        sce2.id
                    from sys_con_entities sce
                            inner join sys_con_entities as sce2 on  sce2.conn_id = sce.conn_id
                        where   sce.entity_tag like '%cliid%'
                            and sce.entity_value = '$documentId'";
        $data = DB::select($sql);
        
        foreach ($data as $key => $value) {
            # code...
            if(strpos($value->entity_tag,'clicode') !== false){
                $entity = Sys_con_entities::where('id',$value->id)->first();
                $entity->entity_value = $clientData['clicode'] ?? '';
                $entity->save();
            }

            if(strpos($value->entity_tag,'clititle') !== false){
                $entity = Sys_con_entities::where('id',$value->id)->first();
                $entity->entity_value = $clientData['title'] ?? '';
                $entity->save();
            }
        }

    }
}
