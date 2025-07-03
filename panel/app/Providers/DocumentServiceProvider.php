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

    public function registerContent($id = 0,$requestData,$files){
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
            if(intval($id) != 0){
                $document = Documents::where('id',$id)->first();
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
                'data'             => $document
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
        //$df = "'op-doc-main','op-doc-main-test','op-doc-flat-form','op-doc-period-form','op-doc-target-form','op-doc-meeting-form','op-doc-project-form'";
        $sql = "select  dco.id,
                        so.op_key,
                        sce.entity_tag,
                        (case
                            when sce.table_tag = 'document_files'
                            then (  select  json_object(
                                                'description',description,
                                                'id',df.id
                                            )
                                    from document_files as df
                                        where df.id = sce.entity_value)

                            else  sce.entity_value
                        end) as entity_value
                        
       
                            from sys_con_ops dco 

                    inner join sys_options so on so.id = dco.type_id
                    left join sys_con_entities sce on sce.conn_id = dco.id 
    
    
                    where   so.group_key = 'op-doc-forms' and 
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                            dco.main_id = ".$id;

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
        $document    = Documents::where('id',$id)->first();
        $connections = Sys_con_ops::where('main_id',$id)->get();
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

    public function removeTransaction($id){
        //first find all attributes
        $document    = Transactions::where('id',$id)->first();
        //remove connected transaction 
        $connTrans   = Transactions::where('trans_id',$document->id)->first();
        if(!empty($connTrans)) $connTrans->delete();
        //finaly remove main element
        $document->delete();

        return ['success' => true];
    }

    public function paymentInfo(){
        return [
            'success' => true,
            'periods'  => DB::select("SELECT d.id,(SELECT      json_group_array(
                                                            json_object(
                                                                'Key',se.entity_tag,
                                                                'Value' , se.entity_value
                                                            )
                                                        ) 
                                                    FROM sys_con_entities as se
                                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                                        inner join sys_options as sp on sp.id = so.type_id
                                                    where so.conn_id = 0 and sp.op_key = 'op-doc-period-form'  and so.main_id = d.id)  as  main_attr from documents as d
                                                        inner join sys_options as sp on sp.id = d.type_id
                                                            where sp.op_key = 'op-doc-period'"),
            'accounts' => DB::select("SELECT d.id,(SELECT      json_group_array(
                                                            json_object(
                                                                'Key',se.entity_tag,
                                                                'Value' , se.entity_value
                                                            )
                                                        ) 
                                                    FROM sys_con_entities as se
                                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                                        inner join sys_options as sp on sp.id = so.type_id
                                                    where so.conn_id = 0 and sp.op_key = 'op-doc-target-form'  and so.main_id = d.id)  as  main_attr from documents as d
                                                        inner join sys_options as sp on sp.id = d.type_id
                                                            where sp.op_key = 'op-doc-target'"),
            'flats'  => DB::select("SELECT d.id,(SELECT      json_group_array(
                                                            json_object(
                                                                'Key',se.entity_tag,
                                                                'Value' , se.entity_value
                                                            )
                                                        ) 
                                                    FROM sys_con_entities as se
                                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                                        inner join sys_options as sp on sp.id = so.type_id
                                                    where so.conn_id = 0 and sp.op_key = 'op-doc-flat-form'  and so.main_id = d.id)  as  main_attr from documents as d
                                                        inner join sys_options as sp on sp.id = d.type_id
                                                            where sp.op_key = 'op-doc-flat'"),
            'currencies' => Sys_options::where('group_key','op-cur-types')->get(),
        ];
    }

    /**
     * this method will set payment transactions for documents
     */
    public function setPayment($data,$files){
        try{
            DB::beginTransaction(); // <= Starting the transaction

            $cur = Sys_options::where(['code' => $data['currency'] , 'group_key' => 'op-cur-types'])->first()->id;
            $fileRelIds = [];
            switch($data['op']){
                case 'paydept':
                    break;
                case 'transfer':
                    $typeId = Sys_options::where('op_key','doc_acc_transfer')->first()->id;
                    //now make flat payment to us
                    $etrans = new Transactions();
                    $etrans->target_id = $data['account_id'];
                    $etrans->type_id   = $typeId;
                    $etrans->note      = $data['note'];
                    $etrans->amount    = $data['amount'];
                    $etrans->cur_id    = $cur;
                    $etrans->rel_id    = $data['target_id']; 
                    $etrans->sign      = 0;
                    $etrans->period    = $data['period'];

                    $etrans->save();

                    $fileRelIds[] = $etrans->id;

                    //now enter money to us
                    $trans = new Transactions();
                    $trans->target_id = $data['target_id'];
                    $trans->type_id   = $typeId;
                    $trans->note      = $data['note'];
                    $trans->amount    = $data['amount'];
                    $trans->cur_id    = $cur;
                    $trans->rel_id    = $data['account_id'];
                    $trans->sign      = 1;
                    $trans->period    = $data['period'];

                    $trans->save();

                    // enter reverse trans connection
                    $trans->trans_id = $etrans->id;
                    $trans->save();
                    
                    $etrans->trans_id = $trans->id;
                    $etrans->save();
                    // enter reverse trans connection

                    $fileRelIds[] = $trans->id;
                    break;
                case 'income':
                    $typeId = Sys_options::where('op_key',$data['type'])->first()->id;
                   

                    if(intval($data['from_safe']) == 1){
                        //first add flat transactions 
                        $trans = new Transactions();
                        $trans->target_id = $data['flat_id'];
                        $trans->type_id   = $typeId;
                        $trans->note      = $data['note'];
                        $trans->amount    = $data['amount'];
                        $trans->cur_id    = $cur;
                        $trans->rel_id    = 0; 
                        $trans->sign      = 1;
                        $trans->period    = $data['period'];
        
                        $trans->save();
                    }

                    //now make flat payment to us
                    $etrans = new Transactions();
                    $etrans->target_id = $data['flat_id'];
                    $etrans->type_id   = $typeId;
                    $etrans->note      = $data['note'];
                    $etrans->amount    = $data['amount'];
                    $etrans->cur_id    = $cur;
                    $etrans->rel_id    = $data['account_id']; 
                    $etrans->sign      = 0;
                    $etrans->period    = $data['period'];

                    $etrans->save();
                    $fileRelIds[] = $etrans->id;

                    //now enter money to us
                    $trans = new Transactions();
                    $trans->target_id = $data['account_id'];
                    $trans->type_id   = $typeId;
                    $trans->note      = $data['note'];
                    $trans->amount    = $data['amount'];
                    $trans->cur_id    = $cur;
                    $trans->rel_id    = $data['flat_id'];
                    $trans->sign      = 1;
                    $trans->period    = $data['period'];

                    $trans->save();

                    // enter reverse trans connection
                    $trans->trans_id = $etrans->id;
                    $trans->save();
                    
                    $etrans->trans_id = $trans->id;
                    $etrans->save();
                    // enter reverse trans connection

                    $fileRelIds[] = $trans->id;
                    break;
                case 'outcome':
                    //make aprartment to pay someone
                    $trans = new Transactions();
                    $trans->target_id = $data['account_id']; 
                    $trans->type_id   = Sys_options::where('op_key','doc_acc_other')->first()->id;
                    $trans->note      = $data['note'];
                    $trans->amount    = $data['amount'];
                    $trans->cur_id    = $cur;
                    $trans->rel_id    = 0;
                    $trans->sign      = 0;
                    $trans->period    = $data['period'];

                    $trans->save();

                    $fileRelIds[] = $trans->id;
                    
                    break;
                case 'addbalance':
                    //make aprartment to pay someone
                    $trans = new Transactions();
                    $trans->target_id = $data['flat_id'] ?? $data['account_id'];  // can be add from both listing pages.. 
                    $trans->type_id   = Sys_options::where('op_key','doc_acc_other')->first()->id;
                    $trans->note      = $data['note'];
                    $trans->amount    = $data['amount'];
                    $trans->cur_id    = $cur;
                    $trans->rel_id    = 0;
                    $trans->sign      = 1;
                    $trans->period    = $data['period'];

                    $trans->save();

                    $fileRelIds[] = $trans->id;
                    break;
            }

            //enter file relations if file sended..
            if(!empty($files) && !empty($fileRelIds)){
                foreach($fileRelIds as $id){
                    foreach($files as $file){
                        $fileResponse = addFileToDb($file,'op-doc-trans-file',0,'transactions',$id);

                        if($fileResponse['success'] == false) throw new \Exception('File Cannot Added To Server');
                    }
                }
            }
            DB::commit(); // <= Commit the changes
            return [
                'success' => true,
            ];
        }catch(\Exception $e){
            DB::rollBack(); // <= Rollback in case of an exception

            return [
                'success' => false,
                'msg'     => $e->getMessage(),
            ];
        }
        
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
            $type = Sys_options::where('op_key',$statusKey)->first();
            $trans = new Transactions();
            $trans->target_id  = $id;  // can be add from both listing pages.. 
            $trans->type_id    = $type->id;
            $trans->note       = $note ?? '-';
            $trans->amount     = 0;
            $trans->cur_id     = 0;
            $trans->rel_id     = 0;
            $trans->sign       = 0;
            $trans->period     = date('Y-m');
            //$trans->created_at =  
            $trans->save();

            return [
                'data'    => $type->title,
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
            $filter = [
                [
                    'key'   => 'target_id',
                    'type'  => '=',
                    'value' => $id
                ]
            ];

            $data = $this->getFormData($id);
            $title = array_values(array_values($data)[0])[0][$id]['entities']['title'];
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
}
