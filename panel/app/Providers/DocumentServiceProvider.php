<?php

namespace App\Providers;

use App\Models\Document_files;
use App\Models\Documents;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Sys_options;
use App\Models\Transactions;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class DocumentServiceProvider extends ServiceProvider
{
    /**
     * Document columns that the generic "main_*" form payload is allowed to write.
     * Everything else — status, id, qnid, type_id, person_id, grp_code, parent_* — is off limits.
     */
    private const GENERIC_WRITABLE_MAIN_FIELDS = ['title', 'starting_at', 'ending_at'];

    public function __construct() {}

    public function registerContent($id, $requestData, $files = [])
    {
        $typeKey = $requestData['typeKey'] ?? 'op-doc-period';
        $dynamicF = $requestData['dynamicF'] ?? [];
        $dynamicFiles = [];
        $removed = $requestData['removedData'] ?? [];
        $isUpdate = false;
        $registerUser = session('person_id'); // this is records user_id for more easy logging and reporting

        // now add sended files to the document with connection table info
        foreach ($files as $key => $f) {
            if (strpos($key, 'dynamicFile') !== false) {
                $dynamicFiles[$key] = $f;
            }
        }
        try {
            $logData = [
                'user_id' => auth('sanctum')->user()->id,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
                'relation' => 'documents',
                'relation_id' => $id,
                'type_id' => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
                'description' => [],
            ];

            DB::beginTransaction(); // <= Starting the transaction

            // here set main document
            $document = new Documents;
            // for update purposes
            if (! is_numeric($id) && $id != 0) {
                $document = Documents::where('qnid', $id)->first();
                $isUpdate = true;

                // here get all data for before data logging

                $logData['description'] = [
                    'before' => $this->getFormData($document->qnid),
                    'after' => [],
                ];
            } else {
                $document->type_id = (Sys_options::where('op_key', $typeKey)->first())->id;
            }

            // only descriptive columns may be set through the generic payload. status, identity and
            // ownership columns (status, id, qnid, type_id, person_id, grp_code, parent_*) are never
            // writable this way — status changes go through the controlled cancel/transaction flows.
            foreach ($requestData as $key => $value) {
                if (! str_starts_with($key, 'main_')) {
                    continue;
                }

                $field = substr($key, strlen('main_'));
                if (! in_array($field, self::GENERIC_WRITABLE_MAIN_FIELDS, true)) {
                    continue;
                }

                $document->{$field} = strip_tags($value);
            }

            // we are checking because only creator is important
            if (! $isUpdate) {
                $document->person_id = $registerUser;
                // Order System: support parent linking for items/transfers (SAP cron and clone flows)
                if (isset($requestData['parent_id']) && is_numeric($requestData['parent_id'])) {
                    $document->parent_id = (int) $requestData['parent_id'];
                }
                if (isset($requestData['parent_qnid'])) {
                    $pq = Documents::where('qnid', $requestData['parent_qnid'])->first();
                    if ($pq) $document->parent_id = $pq->id;
                }
            }

            $rsp = $document->save();

            if (! $isUpdate) {
                // Birth status must be in the document's own transaction group so Documents::tableList status subquery finds it.
                // op-doc-order -> doc_trans_order_created, op-doc-order-item -> doc_trans_created (generic, items have no dedicated order flow), etc.
                $birthMap = [
                    'op-doc-order' => 'doc_trans_order_created',
                    'op-doc-transfer' => 'doc_trans_transfer_created',
                    'op-doc-order-item' => 'doc_trans_created',
                ];
                $birthKey = $birthMap[$typeKey] ?? 'doc_trans_created';
                $birthType = Sys_options::where('op_key', $birthKey)->first();
                if (!$birthType) $birthType = Sys_options::where('op_key','doc_trans_created')->first();
                Transactions::create([
                    'op_id' => 0,
                    'type_id' => $birthType->id,
                    'log_id' => 0,
                    'target_id' => $document->id,
                    'description' => 'New Document Added',
                ]);
            }

            // removed data process
            foreach ($removed as $row) {
                // Resolve the entity pointing at the ACTIVE file — older version rows
                // (their file has document_files.status=0) are history, not the live link.
                $check = Sys_con_entities::where(['conn_id' => $row['id'], 'entity_tag' => $row['key']])->orderByDesc('id')->first();
                if (! empty($check) && $check->table_tag == 'document_files') {
                    $fileStatus = Document_files::where('id', (int) $check->entity_value)->value('status');
                    if ($fileStatus != 1) {
                        $check = null;
                    }
                }
                if (! empty($check)) {
                    if ($check->table_tag == 'document_files') {
                        // just deactivate file on system
                        $file = Document_files::where('id', $check->entity_value)->first();
                        $file->status = 0;
                        $file->save();
                    }

                    $check->delete();
                }
            }

            // ////////////////////////////// Dynamic Fields ********************************
            // now add dynamic fields to the personnel (this is canon way for addional fields)
            $stypeIdMain = (Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->first())->id;
            $stypeIdFile = (Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-file'])->first())->id;

            if (in_array($typeKey, ['op-doc-request', 'op-doc-offer']) && ! $isUpdate) {
                // here count documents for counable request number
                $documentCount = Documents::where('type_id', $document->type_id)->count();
            }
            $lastFileEntity = null;
            foreach ($dynamicF as $key => $field) {
                $id = explode('**', $key)[1];
                $tag = $field['tag'];
                $typeId = (Sys_options::where(['ctitle' => 'type_id', 'op_key' => $tag])->first())->id;

                // set new field
                $conn = new Sys_con_ops;
                // for value update
                if (strpos($id, 'new') === false) {
                    $conn = Sys_con_ops::where('id', $id)->first();
                }

                // last add connection
                $conn->main_id = $document->id; // main connection
                $conn->conn_id = 0;
                $conn->type_id = $typeId;
                $conn->sub_type_id = $stypeIdMain;
                $conn->save();

                // now check if any entity sended
                $field['entities']['qnid'] = $document->qnid;

                // firma kodunun tek dogruluk kaynagi backend: yeni firmada belgenin qnid'i
                // yazilir, guncellemede hic dokunulmaz. Istemciden gelen clicode her iki
                // durumda da yok sayilir, boylece kod disaridan ezilemez.
                if ($typeKey === 'op-doc-client') {
                    if ($isUpdate) {
                        unset($field['entities']['clicode']);
                    } else {
                        $field['entities']['clicode'] = $document->qnid;
                    }
                }

                if (in_array($typeKey, ['op-doc-request', 'op-doc-offer'])) {
                    if ($isUpdate) {
                        $field['entities']['rev_date'] = date('d/m/Y');
                    } else {
                        $field['entities']['req_no'] = $documentCount;
                    }
                }

                foreach ($field['entities'] as $ekey => $value) {
                    $entity = new Sys_con_entities;

                    // check if entity is exist before
                    $check = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => $ekey, 'table_tag' => 'sys_con_ops'])->first();
                    if (! empty($check)) {
                        $entity = $check;
                    }

                    $entity->table_tag = 'sys_con_ops';
                    $entity->conn_id = $conn->id;
                    $entity->entity_tag = $ekey;
                    $entity->entity_value = strip_tags($value);

                    $entity->save();

                    if ($entity->entity_tag == 'target_type' && in_array($typeKey, ['op-doc-request', 'op-doc-offer'])) {
                        $document->grp_code = mb_strtoupper(strtr($entity->entity_value, [
                            'ç' => 'c', 'Ç' => 'C',
                            'ğ' => 'g', 'Ğ' => 'G',
                            'ı' => 'I', 'İ' => 'I',
                            'ö' => 'o', 'Ö' => 'O',
                            'ş' => 's', 'Ş' => 'S',
                            'ü' => 'u', 'Ü' => 'U',
                        ]), 'UTF-8');
                        $document->save();
                    }
                }

                // now check if any file is sended
                $stypeId = (Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-file'])->first())->id;
                foreach ($dynamicFiles as $fkey => $file) {
                    if (strpos($fkey, $id) !== false) {
                        $fileName = explode('*-*', $fkey)[1];

                        $typeTag = explode('**', $fileName)[0];
                        $fileId = explode('**', $fkey)[2];

                        // here add short info about file log
                        $fileTypeInfo = (Sys_options::where(['ctitle' => 'type_id', 'op_key' => 'op-'.$typeTag])->first());
                        $fileTypeInfo = ! empty($fileTypeInfo) ? $fileTypeInfo->title : 'Dosya';

                        // Look up existing file entity BEFORE processing — needed for replacement detection.
                        // On first upload there is no entity yet ($oldFileEntity = null, $existingFileId = 0).
                        // On re-upload, the ACTIVE entity is the one whose file is still active
                        // (document_files.status=1); older version rows are ignored.
                        $oldFileEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => $fileName, 'table_tag' => 'document_files'])
                            ->whereIn('entity_value', function ($q) {
                                $q->selectRaw('id::text')->from('document_files')->where('status', 1);
                            })
                            ->orderByDesc('id')->first();
                        $existingFileId = 0;
                        if($oldFileEntity && is_numeric($oldFileEntity->entity_value)){
                            $existingFileId = (int) $oldFileEntity->entity_value;
                        }

                        // Check if file is a reference (temp upload) or a File object
                        $isReference = is_string($file) && is_json($file);
                        if($isReference){
                            $refData = json_decode($file, true);
                            $referenceId = $refData['reference_id'] ?? 0;

                            if($referenceId > 0){
                                // Finalize temp file: move to permanent storage and link to document.
                                // Pass existingFileId so finalizeTempFile can handle replacement
                                // (deactivate old, create new record, chain via replaced_id, copy entities).
                                $fileResponse = finalizeTempFile($referenceId, $document->id, 'form-file', $existingFileId);
                                if($fileResponse['success']){
                                    $fileId = $fileResponse['file_id'];
                                }else{
                                    throw new \Exception('Geçici dosya kalıcı alana taşınamadı: '.$fileResponse['msg']);
                                }
                            }else{
                                throw new \Exception('Geçersiz dosya referansı');
                            }
                        }else{
                            // Traditional upload: file is a File object
                            $fileResponse = addFileToDb($file, 'form-file', $fileId, 'documents', $document->id, ($fileTypeInfo.' Dosyası Sisteme Eklendi'));
                            if ($fileResponse['success'] == false) {
                                throw new \Exception('Dosya Sisteme Eklenemedi...');
                            }
                            $fileId = $fileResponse['rowId'];
                        }

                        // now add file connection — every upload creates a NEW entity row so the
                        // version history lives in the entity rows (old_versions/DList).
                        // Activeness is derived from the linked document_files.status.
                        $entity = new Sys_con_entities;
                        $entity->table_tag = 'document_files';
                        $entity->conn_id = $conn->id;
                        $entity->entity_tag = $fileName;
                        $entity->entity_value = strip_tags($fileId);
                        $entity->save();
                        $lastFileEntity = $entity;
                    }
                }
            }
            // ////////////////////////////// Dynamic Fields ********************************
            DB::commit(); // <= Commit the changes

            // Order System: re-uploading a rejected file replaces it (old → status 0,
            // new → doc_file_refreshed). Recompute the parent order status so
            // files_rejected returns to "Dosyalar Kontrol Ediliyor" (transfer_sent).
            // Skip on a transfer send — processOrderTransfer sets the status right after,
            // otherwise its "created only" guard would reject the send.
            if (! empty($lastFileEntity) && empty($requestData['transfer_mode'])) {
                $this->syncOrderStatusFromFiles($lastFileEntity);
            }

            // here get updated data
            $updatedResult = $this->getFormData($document->qnid);
            $oldData = $logData['description']['before'] ?? [];
            $logData['description'] = json_encode([
                'before' => $oldData,
                'after' => $updatedResult,
            ], JSON_UNESCAPED_UNICODE);

            $logData['relation_id'] = $document->id;

            // here save log data
            UserLog::create($logData);

            return [
                'success' => $rsp,
                'id' => $document->id,
                'data' => $document,
                'detail' => $updatedResult,
                'qnid' => $document->qnid,
                'before' => $oldData,
                'after' => $updatedResult,
            ];
        } catch (\Exception $e) {
            DB::rollBack(); // <= Rollback in case of an exception

            return [
                'success' => false,
                'id' => 0,
                'message' => $e->getMessage().' => '.$e->getLine().' => '.$e->getFile(),
            ];
        }
    }

    public function getFormData($id)
    {
        $dynamicF = [];
        // ////////////////////////////// Dynamic Fields ********************************
        // get dynamic fields info

        $sql = "select  dco.id,
                        so.op_key,
                        sce.entity_tag,
                        (case
                            when sce.table_tag = 'document_files'
                            then (  select  json_build_object(
                                                'description',description,
                                                'qnid',df.qnid,
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
                    left join sys_con_entities sce on sce.conn_id = dco.id and (sce.table_tag <> 'document_files' or exists (select 1 from document_files dfe where dfe.id = sce.entity_value::int and dfe.status = 1))
                    inner join documents as d on d.id = dco.main_id
    
                    where   so.group_key = 'op-doc-forms' and 
                            so.op_key not in ('op-doc-user-permission-form','op-doc-user-contact-form','op-doc-user-client-form') and
                            dco.conn_id = 0 and 
                            dco.status  = 1 and
                            d.qnid = '".$id."'";
        $data = DB::select($sql);

        foreach ($data as $row) {
            if (! isset($dynamicF[$row->op_key])) {
                $dynamicF[$row->op_key] = [];
            }
            if (! isset($dynamicF[$row->op_key][$row->id])) {
                $dynamicF[$row->op_key][$row->id] = [
                    'entities' => [],
                    'files' => [],
                ];
            }
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

        // ////////////////////////////// Dynamic Fields ********************************

        // d.status is shadowed by the transaction aggregate aliased as "status" below, so the
        // document activeness flag is exposed under its own name.
        $document = "select sp.op_key,
                            d.* ,
                            d.status as document_status,
                            (select     json_agg(
                                            json_build_object(
                                                'op_key',so.op_key,
                                                'op_title' , so.title,
                                                'note',t.note,
                                                'created_at',t.created_at,
                                                'name',p.name
                                            )
                                            ORDER BY t.id
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

        $document = DB::select($document)[0] ?? [];

        return [
            'document' => $document,
            'formFormat' => $dynamicF,
        ];
    }

    /**
     * Cancels an offer. For offers documents.status = 0 means "İptal Edildi" rather than "removed",
     * so the record, its EAV rows and its files are all kept and stay visible in the offer screens.
     * Deliberately separate from removeContent(), which still carries the passivation semantics
     * (and the connected-user deactivation) used by the other document types.
     */
    public function cancelOffer($id, $note = null)
    {
        $document = Documents::where('qnid', $id)->first();
        if (empty($document)) {
            return ['success' => false, 'msg' => 'Teklif bulunamadı: '.$id];
        }

        $documentType = Sys_options::where('id', $document->type_id)->first();
        if (($documentType->op_key ?? null) !== 'op-doc-offer') {
            return ['success' => false, 'msg' => 'Bu belge tipi iptal edilemez.'];
        }

        if ((int) $document->status === 0) {
            return ['success' => false, 'msg' => 'Teklif zaten iptal edilmiş.'];
        }

        try {
            DB::beginTransaction();

            // conditional write: loses the race cleanly if two cancels arrive together
            $flipped = Documents::where('qnid', $id)->where('status', 1)->update(['status' => 0]);
            if ($flipped === 0) {
                DB::rollBack();

                return ['success' => false, 'msg' => 'Teklif zaten iptal edilmiş.'];
            }

            // "before" is deliberately omitted: OfferLogTimeline classifies an entry as a status
            // change only when it is absent, and the entities are unchanged by a cancellation anyway
            UserLog::create([
                'user_id' => auth('sanctum')->user()->id ?? 0,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
                'description' => json_encode([
                    'after' => ['document' => ['op_key' => 'op-doc-offer', 'qnid' => $document->qnid]],
                    'desc' => 'Teklif İptal Edildi',
                    'note' => $note ?? '-',
                ], JSON_UNESCAPED_UNICODE),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true];
    }

    /**
     * Yanlislikla iptal edilmis bir teklifi geri acar (#16).
     *
     * Durum degistirmez: iptal transaction olarak yazilmadigi icin teklif kendiliginden
     * iptalden onceki durumuna doner.
     */
    public function reopenOffer($id, $note = null)
    {
        $document = Documents::where('qnid', $id)->first();
        if (empty($document)) {
            return ['success' => false, 'msg' => 'Teklif bulunamadı: '.$id];
        }

        $documentType = Sys_options::where('id', $document->type_id)->first();
        if (($documentType->op_key ?? null) !== 'op-doc-offer') {
            return ['success' => false, 'msg' => 'Bu belge tipi geri açılamaz.'];
        }

        try {
            DB::beginTransaction();

            // kosullu yazim: iptal edilmemis teklifte hicbir sey yapmadan temiz cikilir
            $revived = Documents::where('qnid', $id)->where('status', 0)->update(['status' => 1]);
            if ($revived === 0) {
                DB::rollBack();

                return ['success' => false, 'msg' => 'Teklif iptal edilmiş durumda değil.'];
            }

            $this->logOfferReopened($document, $note);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true];
    }

    /** Geri acilisi kim ve ne zaman yapti bilgisiyle loglar. */
    private function logOfferReopened($document, $note = null)
    {
        UserLog::create([
            'user_id' => auth('sanctum')->user()->id ?? 0,
            'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation' => 'documents',
            'relation_id' => $document->id,
            'type_id' => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
            'description' => json_encode([
                'after' => ['document' => ['op_key' => 'op-doc-offer', 'qnid' => $document->qnid]],
                'desc' => 'Teklif Geri Açıldı',
                'note' => $note ?? '-',
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function removeContent($id)
    {
        // first find all attributes
        $document = Documents::where('qnid', $id)->first();
        if (empty($document)) return ['success' => false, 'msg' => 'Belge bulunamadı'];
        $docTypeKey = Sys_options::where('id', $document->type_id)->value('op_key');
        // Clone order/item restoration: if this is a partitioned piece, give its qty back to main
        $needsRestoreClone = ($docTypeKey === 'op-doc-order' && (int)$document->parent_id !== 0);
        $needsRestoreItem  = ($docTypeKey === 'op-doc-order-item');
        // Need to capture detail before soft-delete for type check via EAV suffix fallback
        $preDetail = null;
        if ($docTypeKey === 'op-doc-order' && (int)$document->parent_id === 0) {
            try { $preDetail = $this->getFormData($document->qnid); } catch(\Throwable $e) {}
        }
        $document->status = 0;
        $document->save();
        if ($needsRestoreClone) {
            $this->restoreQuantitiesForClone($document);
        } elseif ($needsRestoreItem) {
            // Only restore if this item itself is a clone piece (has split_from_qnid)
            try {
                $d = $this->getFormData($document->qnid);
                $f = $d['formFormat']['op-doc-order-item-form'] ?? [];
                $ents=[]; foreach($f as $cr) foreach(($cr['entities']??[]) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
                if (!empty($ents['split_from_qnid'])) $this->restoreQuantityForSingleCloneItem($document);
            } catch(\Throwable $e) {}
        } elseif ($docTypeKey === 'op-doc-order' && $preDetail) {
            // Fallback suffix check for clones where parent_id wasn't set (legacy)
            $form = $preDetail['formFormat']['op-doc-order-form'] ?? [];
            $ents=[]; foreach($form as $cr) foreach(($cr['entities']??[]) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
            $on = $ents['order_no'] ?? '';
            if (preg_match('/-\d+$/',$on) && (int)$document->parent_id !== 0) {
                $this->restoreQuantitiesForClone($document);
            }
        }

        $detail = $this->getFormData($document->qnid);

        if ($detail['document']->op_key == 'op-doc-client') {
            // here deactivate connected client users (only op-pert-reseller)
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
                if (strpos($value->entity_tag, 'userclientgroup') !== false) {
                    // here get user and make it disabled
                    User::where('email', $value->email)->update(['status' => 0]);
                }
            }
        }

        UserLog::create([
            'user_id' => auth('sanctum')->user()->id ?? DB::table('users')->where('status',1)->value('id') ?? 0,
            'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation' => 'documents',
            'relation_id' => $document->id,
            'type_id' => Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
            'description' => json_encode([
                'before' => $this->getFormData($document->qnid),
                'after' => [],
                'desc' => 'İçerik pasife alındı artık listelerde görünmeyecek..',
            ], JSON_UNESCAPED_UNICODE),
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

    public function removeTransaction($id)
    {
        // first find all attributes
        $document = Transactions::where('qnid', $id)->first();
        // remove connected transaction
        $connTrans = Transactions::where('trans_id', $document->id)->first();
        if (! empty($connTrans)) {
            $connTrans->delete();
        }
        // finaly remove main element
        $document->delete();

        return ['success' => true];
    }

    /**
     * this method will prepare export data for documents
     */
    public function getExportData($type)
    {
        $response = [];
        switch ($type) {
            case 'flats':
                $response[] = ['Daire İsmi', 'Kat Maliki', 'Güncel Bakiye'];
                $data = (new Documents)->tableList(['filter' => [
                    [
                        'key' => 'form-type',
                        'type' => '=',
                        'value' => 'op-doc-flat-form',
                    ], [
                        'key' => 'type',
                        'type' => '=',
                        'value' => 'op-doc-flat',
                    ],
                ],
                ])['data'];
                break;
            case 'accounts':
                $response[] = ['Kasa', 'Güncel Bakiye'];
                $data = (new Documents)->tableList(['filter' => [
                    [
                        'key' => 'form-type',
                        'type' => '=',
                        'value' => 'op-doc-target-form',
                    ], [
                        'key' => 'type',
                        'type' => '=',
                        'value' => 'op-doc-target',
                    ],
                ],
                ])['data'];
                break;
            case 'meetings':
                $response[] = ['Tarih', 'Güncel Yönetici', 'Güncel Aidat'];
                $data = (new Documents)->tableList(['filter' => [
                    [
                        'key' => 'form-type',
                        'type' => '=',
                        'value' => 'op-doc-meeting-form',
                    ], [
                        'key' => 'type',
                        'type' => '=',
                        'value' => 'op-doc-meeting',
                    ],
                ],
                ])['data'];
                break;
        }

        foreach ($data as $d) {
            $detail = json_decode($d->main_attr, true);
            foreach ($detail as $row) {
                $detail[$row['Key']] = $row['Value'];

                if (strpos($row['Key'], 'per_name') !== false) {
                    if (! isset($detail['per_name'])) {
                        $detail['per_name'] = [];
                    }
                    $detail['per_name'][] = $row['Value'];
                }
            }

            switch ($type) {
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
                        implode(' , ', $detail['per_name']),
                        ($d->balance_pure ?? 0).' '.($detail['currency'] ?? env('SYS_CUR')),
                    ];
                    break;
            }
        }

        return [
            'success' => true,
            'data' => $response,
        ];
    }

    /**
     * this method will set status for documents
     */
    public function setStatus($id, $statusKey, $note)
    {
        try {
            $document = Documents::where('qnid', $id)->first();
            if (! $document) {
                return [
                    'success' => false,
                    'msg' => 'Belge bulunamadı: '.$id,
                ];
            }
            $documentType = Sys_options::where('id', $document->type_id)->first();

            // #16: iptal artik terminal degil. Iptal edilmis bir teklife durum yazmak onu geri
            // getirir. Bu yol per-05-02 ile korundugu icin yalnizca yoneticiye acik.
            $revived = ($documentType->op_key ?? null) === 'op-doc-offer' && (int) $document->status === 0;

            $type = Sys_options::where('op_key', $statusKey)->first();
            if (! $type) {
                return [
                    'success' => false,
                    'msg' => 'Bilinmeyen durum kodu: '.$statusKey,
                ];
            }

            // Order status guard — like offer's editableStatuses, enforce valid transitions
            if (in_array($documentType->op_key ?? null, ['op-doc-order','op-doc-transfer'])) {
                $allowed = [
                    'doc_trans_order_created' => ['doc_trans_order_transfer_sent'],
                    'doc_trans_order_transfer_sent' => ['doc_trans_order_ready_for_shipment','doc_trans_order_approved','doc_trans_order_rejected'],
                    'doc_trans_order_ready_for_shipment' => ['doc_trans_order_approved','doc_trans_order_rejected'],
                    'doc_trans_order_files_rejected' => ['doc_trans_order_ready_for_shipment','doc_trans_order_transfer_sent','doc_trans_order_approved','doc_trans_order_rejected'],
                    'doc_trans_transfer_created' => ['doc_trans_transfer_sent'],
                    'doc_trans_transfer_sent' => ['doc_trans_transfer_approved','doc_trans_transfer_rejected'],
                ];
                // fetch last order status (op-trans-op-doc-order group)
                $lastOpKey = DB::table('transactions as t')
                    ->join('sys_options as so','so.id','=','t.type_id')
                    ->where('t.target_id',$document->id)
                    ->where('so.group_key','op-trans-'.$documentType->op_key)
                    ->orderBy('t.id','desc')
                    ->value('so.op_key');
                // if no history yet, treat as created is allowed to go anywhere in its outgoing list
                if ($lastOpKey) {
                    $allowedNext = $allowed[$lastOpKey] ?? null;
                    // terminal states have no entry or empty array → no outgoing allowed
                    if ($allowedNext === null || !in_array($statusKey,$allowedNext)) {
                        // allow idempotent re-set to same status? No, block.
                        return [
                            'success' => false,
                            'msg' => 'Bu durum geçişine izin verilmiyor: '.$lastOpKey.' → '.$statusKey,
                        ];
                    }
                }
            }

            $log = UserLog::create([
                'user_id' => auth('sanctum')->user()->id ?? 0,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 0,
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => Sys_options::where('op_key', 'log-document-status-update')->first()->id ?? 0,
                'description' => json_encode(
                    [
                        'after' => ['document' => ['op_key' => $documentType->op_key, 'qnid' => $document->qnid]],
                        'desc' => $documentType->title.' Durumu Değiştirildi',
                        'note' => $note,
                    ], JSON_UNESCAPED_UNICODE),
            ]);

            $trans = new Transactions;
            $trans->target_id = $document->id;  // can be add from both listing pages..
            $trans->log_id = $log->id;
            $trans->type_id = $type->id;
            $trans->note = $note ?? '-';
            $trans->amount = 0;
            $trans->cur_id = 0;
            $trans->rel_id = 0;
            $trans->sign = 0;
            $trans->period = '-';
            // $trans->created_at =
            $trans->save();

            // durum yazildiktan sonra teklifi aktife al; geri acilis ayrica loglanir
            if ($revived) {
                $document->status = 1;
                $document->save();

                $this->logOfferReopened($document, 'Durum değişikliğiyle geri açıldı: '.$type->title);
            }

            return [
                'data' => $type->title,
                'detail' => $this->getFormData($id),
                'success' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'msg' => $e->getMessage(),
            ];
        }
    }

    /**
     * this method will prepare export data for transactions
     */
    public function getTransExportData($id = null)
    {
        $response = [];
        $filter = [];
        if ($id != null) {
            $documentId = Documents::where('qnid', $id)->first();

            $filter = [
                [
                    'key' => 'target_id',
                    'type' => '=',
                    'value' => $documentId->id,
                ],
            ];

            $data = $this->getFormData($id);

            $title = array_values(array_values($data)[0])[0][$documentId->id]['entities']['title'];
            $response[] = ['Kasa : ', $title];
            $response[] = [' '];
        }
        $data = (new Transactions)->tableList(['filter' => $filter])['data'];

        $response[] = ['Periyod', 'Tarih', 'Kaynak', 'Hedef', 'Tip', 'Yön', 'Miktar', 'Birim', 'Açıklama'];
        foreach ($data as $d) {
            $conn = [];
            foreach (json_decode($d->conn_info, true) as $cv) {
                $conn[$cv['Key']] = $cv['Value'];
            }

            $main = [];
            foreach (json_decode($d->main_info, true) as $cv) {
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
                $d->note,
            ];
        }

        return [
            'success' => true,
            'data' => $response,
        ];
    }

    public function documentFileStatus($id, $statusKey, $note)
    {
        if (isset($statusKey)) {
            try {
                DB::beginTransaction();

                // get file
                $file = Document_files::where('qnid', $id)->first();
                $type = Sys_options::where('op_key', $statusKey)->first();
                $entity = Sys_con_entities::where(['entity_value' => $file->id, 'table_tag' => 'document_files'])->first();
                $fileTitle = Sys_options::where('op_key', 'op-'.explode('**', $entity->entity_tag)[0])->first()->title ?? 'Dosya';
                $EntityConnections = Sys_con_entities::where(['conn_id' => $entity->conn_id])->get();
                // add transaction to file
                $log = UserLog::create([
                    'user_id' => auth('sanctum')->user()->id,
                    'sys_code' => $GLOBALS['SYS_CODE'],
                    'relation' => 'documents',
                    'relation_id' => $file->id,
                    'type_id' => $type->id,
                    'description' => json_encode([
                        'file_id' => $file->id,
                        'desc' => $fileTitle.' Dosya Durumu Değiştirildi => '.$type->title,
                        'note' => $note,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                Transactions::create([
                    'op_id' => 1,
                    'type_id' => $type->id,
                    'log_id' => $log->id,
                    'target_id' => $file->id,
                    'description' => json_encode([
                        'file_id' => $file->id,
                        'desc' => 'Dosya Durumu Değiştirildi => '.$type->title,
                        'note' => $note,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                DB::commit();

                // Order System: keep the parent order's status in sync with its files.
                // Any rejected file on an order (or its items) flips the order to
                // "Reddedilen Dosyalar Mevcut"; when all files are accepted/cleared it
                // returns to "Dosyalar Kontrol Ediliyor".
                $this->syncOrderStatusFromFiles($entity);

                return [
                    'success' => true,
                    'id' => $log->id,
                    'data' => $type->title,
                    'fileTitle' => $fileTitle,
                    'description' => $fileTitle.' Dosya Durumu Değiştirildi => '.$type->title,
                    'note' => $note,
                    'connections' => $EntityConnections,
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                return [
                    'success' => false,
                    'id' => 0,
                    'message' => $e->getMessage().' => '.$e->getLine().' => '.$e->getFile(),
                ];
            }
        } else {
            return [
                'success' => false,
                'id' => 0,
            ];
        }
    }

    /**
     * Order System: recompute the parent order's status from its file states.
     * If any active (status=1) file under the order (or its items) is in a rejected
     * state we flip the order to "Reddedilen Dosyalar Mevcut", otherwise it returns
     * to "Dosyalar Kontrol Ediliyor". Called after every file status change.
     */
    public function syncOrderStatusFromFiles($entity)
    {
        try {
            if (empty($entity) || empty($entity->conn_id)) {
                return;
            }

            $conn = Sys_con_ops::where('id', $entity->conn_id)->first();
            if (empty($conn)) {
                return;
            }

            // Resolve the nearest order. A clone is itself an order, even though it
            // keeps the original order as its parent for traceability.
            $doc = Documents::where('id', $conn->main_id)->first();
            $docTypeKey = $doc
                ? Sys_options::where('id', $doc->type_id)->value('op_key')
                : null;
            while ($doc && ! in_array($docTypeKey, ['op-doc-order', 'op-doc-transfer']) && (int) $doc->parent_id > 0) {
                $parent = Documents::where('id', $doc->parent_id)->first();
                $doc = $parent;
                $docTypeKey = $doc
                    ? Sys_options::where('id', $doc->type_id)->value('op_key')
                    : null;
            }
            if (empty($doc)) {
                return;
            }

            $docType = Sys_options::where('id', $doc->type_id)->first();
            if (! in_array($docType->op_key ?? null, ['op-doc-order', 'op-doc-transfer'])) {
                return;
            }

            // Collect active files on this order and its direct order items only.
            $orderFileRows = DB::select(
                "SELECT df.id, sce.entity_tag, d.id AS doc_id FROM document_files df
                 JOIN sys_con_entities sce ON sce.entity_value = df.id::text AND sce.table_tag = 'document_files'
                 JOIN sys_con_ops sco ON sco.id = sce.conn_id
                 JOIN documents d ON d.id = sco.main_id
                 WHERE df.status = 1 AND df.relation = 'documents'
                   AND (d.id = ? OR d.parent_id = ?)",
                [$doc->id, $doc->id]
            );

            if (empty($orderFileRows)) {
                return;
            }

            // The order-level upload fields are single slots. Some older replacement
            // records can remain active without a replaced_id link, so only the newest
            // active file in each slot participates in the order status calculation.
            $latestOrderSlotFiles = [];
            $filteredFileRows = [];
            foreach ($orderFileRows as $fileRow) {
                $parts = explode('**', (string) ($fileRow->entity_tag ?? ''));
                $slotKey = ($fileRow->doc_id == $doc->id && in_array($parts[1] ?? null, ['transfer_kabul', 'transfer_cins']))
                    ? $fileRow->doc_id.'|'.$parts[0].'|'.$parts[1]
                    : null;

                if ($slotKey !== null) {
                    if (! isset($latestOrderSlotFiles[$slotKey]) || $fileRow->id > $latestOrderSlotFiles[$slotKey]->id) {
                        $latestOrderSlotFiles[$slotKey] = $fileRow;
                    }
                    continue;
                }

                $filteredFileRows[] = $fileRow;
            }
            $orderFileRows = array_merge($filteredFileRows, array_values($latestOrderSlotFiles));

            $hasRejected = false;
            $allAccepted = true;
            foreach ($orderFileRows as $fr) {
                $lastOp = DB::table('transactions as t')
                    ->join('sys_options as so', 'so.id', '=', 't.type_id')
                    ->where('t.target_id', $fr->id)
                    ->where('t.op_id', 1)
                    ->orderBy('t.id', 'desc')
                    ->value('so.op_key');
                if ($lastOp === 'doc_file_rejected') {
                    $hasRejected = true;
                } elseif ($lastOp !== 'doc_file_accepted') {
                    $allAccepted = false;
                }
            }

            if ($hasRejected) {
                $this->applyOrderStatus($doc, 'doc_trans_order_files_rejected', 'Dosya reddedildi');
            } elseif ($allAccepted && ! empty($orderFileRows)) {
                $this->applyOrderStatus($doc, 'doc_trans_order_ready_for_shipment', 'Tüm aktif dosyalar kabul edildi');
            } elseif (! empty($orderFileRows)) {
                // Files exist but are still waiting / refreshed (not all accepted, none rejected).
                // Only recover to "Dosyalar Kontrol Ediliyor" from the files_rejected state —
                // on a fresh send (created) the transfer flow itself sets transfer_sent.
                if ($this->getLatestOrderStatus($doc->id) === 'doc_trans_order_files_rejected') {
                    $this->applyOrderStatus($doc, 'doc_trans_order_transfer_sent', 'Dosyalar kontrol ediliyor');
                }
            }
        } catch (\Exception $e) {
            // never break the file status flow because order status sync failed
        }
    }

    /** Writes an order status transaction (op-trans-op-doc-order) without changing documents.status. */
    private function applyOrderStatus($doc, $statusKey, $note)
    {
        $type = Sys_options::where('op_key', $statusKey)->first();
        if (empty($type)) {
            return;
        }

        // Do not rewrite the same status repeatedly.
        $last = DB::table('transactions as t')
            ->join('sys_options as so', 'so.id', '=', 't.type_id')
            ->where('t.target_id', $doc->id)
            ->where('so.group_key', 'op-trans-op-doc-order')
            ->orderBy('t.id', 'desc')
            ->value('so.op_key');
        if ($last === $statusKey) {
            return;
        }

        $log = UserLog::create([
            'user_id' => auth('sanctum')->user()->id ?? 0,
            'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
            'relation' => 'documents',
            'relation_id' => $doc->id,
            'type_id' => Sys_options::where('op_key', 'log-order-update')->first()->id
                ?? Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
            'description' => json_encode([
                'after' => ['document' => ['op_key' => 'op-doc-order', 'qnid' => $doc->qnid]],
                'desc' => 'Sipariş Durumu Güncellendi',
                'note' => $note,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        Transactions::create([
            'op_id' => 0,
            'type_id' => $type->id,
            'log_id' => $log->id,
            'target_id' => $doc->id,
            'note' => $note ?? '-',
            'description' => json_encode(['note' => $note], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Order System: send an order to transfer. Runs from the SAME order-detail save endpoint.
     *
     * @param  string  $orderQnid  order qnid
     * @param  string  $mode  at_once | partial
     * @param  array   $selectedItemQnids  order-item qnids to ship (partial only)
     *
     * @return array
     */
    public function processOrderTransfer($orderQnid, $mode, $selectedItems = [], $itemSerials = [])
    {
        $order = Documents::where('qnid', $orderQnid)->first();
        if (empty($order)) {
            return ['success' => false, 'msg' => 'Sipariş bulunamadı: '.$orderQnid];
        }
        $docType = Sys_options::where('id', $order->type_id)->first();
        if (($docType->op_key ?? null) !== 'op-doc-order') {
            return ['success' => false, 'msg' => 'Bu belge transfer edilemez.'];
        }

        // Guard: transfer mode can only be set on first send (status must be doc_trans_order_created).
        // After first send the transfer type is locked and cannot be changed.
        $currentStatus = $this->getLatestOrderStatus($order->id);
        if ($currentStatus !== 'doc_trans_order_created') {
            return ['success' => false, 'msg' => 'Transfer türü ilk gönderimden sonra değiştirilemez.'];
        }

        // Collect the order form entities so we can carry them to the clone.
        $detail = $this->getFormData($order->qnid);
        $form = $detail['formFormat']['op-doc-order-form'] ?? [];
        $entities = [];
        foreach ($form as $connRow) {
            foreach (($connRow['entities'] ?? []) as $k => $v) {
                if (! isset($entities[$k])) {
                    $entities[$k] = $v;
                }
            }
        }

        $baseNo = $entities['order_no'] ?? '';
        $baseNo = preg_replace('/-\d+$/', '', (string) $baseNo);

        // NEW RULE: if order was partitioned before (has active EBELN-X clones), at_once is forbidden.
        // Always partial anymore, except when ALL partitions are removed (status=0).
        if ($mode === 'at_once' && $baseNo !== '' && $this->hasActivePartitions($baseNo, $order->id)) {
            return ['success' => false, 'msg' => 'Bu sipariş daha önce parçalı gönderildi. Artık sadece parçalı gönderim yapılabilir. (Tüm parçalar silinirse tek seferde tekrar mümkün)'];
        }

        if ($mode === 'partial') {
            // Compute next EBELN-X suffix for this base order number.
            $x = 1;
            $clones = DB::select(
                "SELECT sce.entity_value FROM sys_con_entities sce
                 JOIN sys_con_ops sco ON sco.id = sce.conn_id
                 JOIN documents d ON d.id = sco.main_id
                 JOIN sys_options so ON so.id = d.type_id
                 WHERE so.op_key = 'op-doc-order' AND sce.entity_tag = 'order_no'
                   AND sce.entity_value LIKE ?",
                [$baseNo.'-%']
            );
            foreach ($clones as $c) {
                if (preg_match('/-(\d+)$/', (string) $c->entity_value, $m)) {
                    $x = max($x, (int) $m[1] + 1);
                }
            }
            $transferNo = $baseNo.'-'.$x;

            // Build the clone payload (header + desc + imalatci carried from the order).
            $cloneEntities = [
                'order_no' => $transferNo,
                'transfer_no' => $transferNo,
                'spec_code' => $entities['spec_code'] ?? '',
                'sys_code' => $entities['sys_code'] ?? '',
                'buying_no' => $entities['buying_no'] ?? '',
                'ctitle' => $entities['ctitle'] ?? '',
                'created_at' => $entities['created_at'] ?? '',
                'order_desc' => $entities['order_desc'] ?? '',
                'imalatci_firma_adi' => $entities['imalatci_firma_adi'] ?? '',
            ];
            $clonePayload = [
                'typeKey' => 'op-doc-order',
                'parent_qnid' => $order->qnid,
                'dynamicF' => [
                    'op-doc-order-form**new-'.microtime(true).rand() => [
                        'tag' => 'op-doc-order-form',
                        'entities' => $cloneEntities,
                    ],
                ],
            ];
            $cloneRes = $this->registerContent(0, $clonePayload, []);
            if (empty($cloneRes['id'])) {
                return ['success' => false, 'msg' => 'Klon sipariş oluşturulamadı: '.($cloneRes['message'] ?? '')];
            }
            $clone = Documents::where('qnid', $cloneRes['qnid'])->first();

            // Move this shipment's files from the original order to the clone so the
            // admin checks exactly the files attached to this transfer.
            $this->moveOrderFilesToDocument($order->id, $clone->id);

            // Duplicate the selected items under the clone, with split amounts and quantity decrement.
            if (! empty($selectedItems)) {
                foreach ($selectedItems as $item) {
                    $itemQnid = is_array($item) ? ($item['qnid'] ?? '') : $item;
                    $splitAmount = is_array($item) ? (float) ($item['amount'] ?? 0) : 0;
                    $serials = is_array($item) ? ($item['serials'] ?? []) : [];
                    if (empty($itemQnid) || $splitAmount <= 0) continue;

                    $cloneItem = $this->duplicateOrderItem($itemQnid, $clone->id, $splitAmount);
                    if ($cloneItem) {
                        // Decrement original item's quantity
                        $this->decrementItemQuantity($itemQnid, $splitAmount);

                        // Create serial entries if provided
                        if (! empty($serials)) {
                            $this->createSerialEntries($cloneItem->id, $serials);
                        }

                        $sourceItem = Documents::where('qnid', $itemQnid)->first();
                        if ($sourceItem) {
                            $this->moveOrderFilesToDocument(
                                $sourceItem->id,
                                $cloneItem->id,
                                'op-doc-order-item-form'
                            );
                        }
                    }
                }
            }

            // Mark the original as partially shipped (old status unchanged, remembers parts sent).
            $this->recordPartiallySent($order, $transferNo);

            // The clone is the thing being sent for review.
            $st = $this->setStatus($clone->qnid, 'doc_trans_order_transfer_sent', 'Transfer gönderildi (parçalı): '.$transferNo);
            if (! ($st['success'] ?? false)) {
            return ['success' => false, 'msg' => 'Klon durumu güncellenemedi: '.($st['msg'] ?? '')];
        }

        // Store transfer_mode as EAV entity so frontend can display it read-only after first send.
        $this->saveTransferModeEntity($order, $mode);
        $this->saveTransferModeEntity($clone, $mode);

        return [
            'success' => true,
            'transfer_no' => $transferNo,
            'clone_qnid' => $clone->qnid,
            'msg' => 'Parçalı transfer oluşturuldu: '.$transferNo,
        ];
        }

        // At-once: the order itself is the transfer being sent.
        // Process serials for all items if provided.
        if (! empty($itemSerials)) {
            foreach ($itemSerials as $itemSerial) {
                $itemQnid = $itemSerial['qnid'] ?? '';
                $serials = $itemSerial['serials'] ?? [];
                if (empty($itemQnid) || empty($serials)) continue;
                $itemDoc = Documents::where('qnid', $itemQnid)->first();
                if ($itemDoc) {
                    $this->createSerialEntries($itemDoc->id, $serials);
                }
            }
        }

        $st = $this->setStatus($order->qnid, 'doc_trans_order_transfer_sent', 'Transfer gönderildi (tek seferde)');
        if (! ($st['success'] ?? false)) {
            return ['success' => false, 'msg' => 'Sipariş durumu güncellenemedi: '.($st['msg'] ?? '')];
        }

        // Store transfer_mode as EAV entity so frontend can display it read-only after first send.
        $this->saveTransferModeEntity($order, $mode);

        return ['success' => true, 'msg' => 'Transfer gönderildi (tek seferde)'];
    }

    /** Persist transfer_mode ('at_once'|'partial') as an EAV entity on the order. */
    private function saveTransferModeEntity($order, string $mode): void
    {
        $conn = Sys_con_ops::where('main_id', $order->id)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', 'op-doc-order-form'))
            ->first();
        if (empty($conn)) {
            return;
        }
        $entity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'transfer_mode', 'table_tag' => 'sys_con_ops'])->first();
        if (empty($entity)) {
            $entity = new Sys_con_entities;
            $entity->conn_id = $conn->id;
            $entity->entity_tag = 'transfer_mode';
            $entity->table_tag = 'sys_con_ops';
        }
        $entity->entity_value = $mode;
        $entity->save();
    }

    /** Check if base order has active EBELN-X clones (status=1). Excludes given doc id. */
    private function hasActivePartitions(string $baseNo, int $excludeId): bool
    {
        if ($baseNo === '') return false;
        $cnt = DB::selectOne(
            "SELECT COUNT(*) as c FROM sys_con_entities sce
             JOIN sys_con_ops sco ON sco.id = sce.conn_id
             JOIN documents d ON d.id = sco.main_id
             JOIN sys_options so ON so.id = d.type_id
             WHERE so.op_key = 'op-doc-order' AND sce.entity_tag = 'order_no'
               AND sce.entity_value LIKE ? AND d.status = 1 AND d.id != ?",
            [$baseNo.'-%', $excludeId]
        );
        return ($cnt->c ?? 0) > 0;
    }

    /** Get the latest order status op_key from transactions. */
    private function getLatestOrderStatus(int $documentId): ?string
    {
        $tx = DB::table('transactions')
            ->join('sys_options', 'sys_options.id', '=', 'transactions.type_id')
            ->where('transactions.target_id', $documentId)
            ->where('transactions.op_id', 0)
            ->where('sys_options.group_key', 'op-trans-op-doc-order')
            ->orderBy('transactions.id', 'desc')
            ->first();
        return $tx->op_key ?? null;
    }

    /** Relinks a document's file entity rows and file rows to another document. */
    private function moveOrderFilesToDocument($fromDocId, $toDocId, $formKey = 'op-doc-order-form')
    {
        $toConn = Sys_con_ops::where('main_id', $toDocId)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', $formKey))
            ->first();
        if (empty($toConn)) {
            return;
        }

        $fileEntities = Sys_con_entities::where('table_tag', 'document_files')
            ->whereIn('conn_id', function ($q) use ($fromDocId) {
                $q->select('id')->from('sys_con_ops')->where('main_id', $fromDocId)->where('conn_id', 0);
            })->get();

        foreach ($fileEntities as $fe) {
            // Update the document_files.relation_id to point at the clone.
            if (is_numeric($fe->entity_value)) {
                DB::table('document_files')->where('id', (int) $fe->entity_value)
                    ->update(['relation_id' => $toDocId]);
            }
            // Relink the entity to the clone's form connection.
            $fe->conn_id = $toConn->id;
            $fe->save();
        }
    }

    /** Duplicates a single order item under a new parent order id, with optional split amount. */
    private function duplicateOrderItem($itemQnid, $newParentId, $splitAmount = 0)
    {
        $item = Documents::where('qnid', $itemQnid)->first();
        if (empty($item)) {
            return null;
        }
        $detail = $this->getFormData($item->qnid);
        $form = $detail['formFormat']['op-doc-order-item-form'] ?? [];
        $entities = [];
        foreach ($form as $connRow) {
            foreach (($connRow['entities'] ?? []) as $k => $v) {
                if (! isset($entities[$k])) {
                    $entities[$k] = $v;
                }
            }
        }
        unset($entities['qnid']);

        // If split amount provided, override quantity and store split metadata
        if ($splitAmount > 0) {
            $unit = $entities['unit'] ?? 'ST';
            $entities['quantity'] = $unit === 'ST' ? (string) (int) $splitAmount : (string) $splitAmount;
            $entities['split_from_qnid'] = $itemQnid;
            $entities['split_amount'] = (string) $splitAmount;
            $entities['original_quantity'] = (string) $splitAmount;
        }

        $payload = [
            'typeKey' => 'op-doc-order-item',
            'parent_id' => $newParentId,
            'dynamicF' => [
                'op-doc-order-item-form**new-'.microtime(true).rand() => [
                    'tag' => 'op-doc-order-item-form',
                    'entities' => $entities,
                ],
            ],
        ];
        $result = $this->registerContent(0, $payload, []);

        return ! empty($result['qnid'])
            ? Documents::where('qnid', $result['qnid'])->first()
            : null;
    }

    /** Marks the original order as having shipped some parts, keeping its own status. */
    private function recordPartiallySent($order, $transferNo)
    {
        $conn = Sys_con_ops::where('main_id', $order->id)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', 'op-doc-order-form'))
            ->first();
        if (empty($conn)) {
            return;
        }
        $entity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'partially_sent', 'table_tag' => 'sys_con_ops'])->first();
        if (empty($entity)) {
            $entity = new Sys_con_entities;
            $entity->conn_id = $conn->id;
            $entity->entity_tag = 'partially_sent';
            $entity->table_tag = 'sys_con_ops';
        }
        $existing = json_decode((string) $entity->entity_value, true) ?: [];
        $existing[] = $transferNo;
        $entity->entity_value = json_encode(array_values(array_unique($existing)));
        $entity->save();

        $descEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'transfer_no', 'table_tag' => 'sys_con_ops'])->first();
        if (empty($descEntity)) {
            $descEntity = new Sys_con_entities;
            $descEntity->conn_id = $conn->id;
            $descEntity->entity_tag = 'transfer_no';
            $descEntity->table_tag = 'sys_con_ops';
        }
        $descEntity->entity_value = $transferNo;
        $descEntity->save();
    }

    /**
     * Decrements an order item's quantity by the given split amount.
     * On first split, stores original_quantity for history.
     */
    private function decrementItemQuantity($itemQnid, $splitAmount)
    {
        $item = Documents::where('qnid', $itemQnid)->first();
        if (empty($item)) return;

        $conn = Sys_con_ops::where('main_id', $item->id)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', 'op-doc-order-item-form'))
            ->first();
        if (empty($conn)) return;

        // Get current quantity and unit
        $qtyEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'quantity', 'table_tag' => 'sys_con_ops'])->first();
        if (empty($qtyEntity)) return;

        $currentQty = (float) $qtyEntity->entity_value;
        $newQty = $currentQty - $splitAmount;

        // Store original_quantity on first split (before decrementing)
        $this->storeOriginalQuantity($conn->id, $currentQty);

        // Validate: can't go below 0
        if ($newQty < 0) $newQty = 0;

        // Get unit for type enforcement
        $unitEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'unit', 'table_tag' => 'sys_con_ops'])->first();
        $unit = $unitEntity->entity_value ?? 'ST';

        // ST = integer, KG/M = float
        $qtyEntity->entity_value = $unit === 'ST' ? (string) (int) $newQty : (string) round($newQty, 2);
        $qtyEntity->save();
    }

    /**
     * Stores the original quantity of an item before its first split.
     * Only writes if original_quantity doesn't already exist.
     */
    private function storeOriginalQuantity($connId, $originalQty)
    {
        $existing = Sys_con_entities::where([
            'conn_id' => $connId,
            'entity_tag' => 'original_quantity',
            'table_tag' => 'sys_con_ops'
        ])->first();

        if (empty($existing)) {
            Sys_con_entities::create([
                'conn_id' => $connId,
                'entity_tag' => 'original_quantity',
                'entity_value' => (string) $originalQty,
                'table_tag' => 'sys_con_ops',
            ]);
        }
    }

    /**
     * Creates serial number documents parented to an order item.
     * Each serial is its own Documents record with EAV entities.
     */
    private function createSerialEntries(int $parentItemId, array $serials): void
    {
        $serialTypeId = Sys_options::where('op_key', 'op-doc-order-serial')->first()?->id;
        $serialFormTypeId = Sys_options::where('op_key', 'op-doc-order-serial-form')->first()?->id;
        $formMainId = Sys_options::where('op_key', 'form-main')->first()?->id;

        if (empty($serialTypeId) || empty($serialFormTypeId)) {
            return;
        }

        // Mark parent item as having serials
        $this->setHasSerialsFlag($parentItemId);

        foreach ($serials as $serial) {
            $serialNo = trim($serial['serial_no'] ?? '-');
            $productionDate = trim($serial['production_date'] ?? '');
            $quantity = (float) ($serial['quantity'] ?? 0);
            $unit = trim($serial['unit'] ?? 'ST');

            if ($quantity <= 0) continue;

            // Create serial document
            $doc = new Documents();
            $doc->type_id = $serialTypeId;
            $doc->person_id = 'system';
            $doc->parent_id = $parentItemId;
            $doc->save();

            // Birth transaction
            $birthType = Sys_options::where('op_key', 'doc_trans_created')->first();
            if ($birthType) {
                $logTypeId = Sys_options::where('op_key', 'log-order-update')->first()?->id ?? 0;
                $userId = DB::table('users')->where('status', 1)->first()?->id ?? 0;
                $log = UserLog::create([
                    'user_id' => $userId,
                    'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
                    'relation' => 'documents',
                    'relation_id' => $doc->id,
                    'type_id' => $logTypeId,
                    'description' => json_encode(['desc' => 'Seri Numarası Oluşturuldu']),
                ]);
                Transactions::create([
                    'op_id' => 0,
                    'type_id' => $birthType->id,
                    'log_id' => $log->id,
                    'target_id' => $doc->id,
                    'description' => 'Seri Numarası Oluşturuldu',
                ]);
            }

            // EAV entities
            $conn = new Sys_con_ops();
            $conn->main_id = $doc->id;
            $conn->conn_id = 0;
            $conn->type_id = $serialFormTypeId;
            $conn->sub_type_id = $formMainId;
            $conn->save();

            $entities = [
                'serial_no' => $serialNo,
                'production_date' => $productionDate,
                'quantity' => $unit === 'ST' ? (string) (int) $quantity : (string) $quantity,
                'unit' => $unit,
            ];

            foreach ($entities as $tag => $value) {
                Sys_con_entities::create([
                    'conn_id' => $conn->id,
                    'entity_tag' => $tag,
                    'entity_value' => (string) $value,
                    'table_tag' => 'sys_con_ops',
                ]);
            }
        }
    }

    /** Sets the has_serials flag on an order item's EAV. */
    private function setHasSerialsFlag(int $itemId): void
    {
        $conn = Sys_con_ops::where('main_id', $itemId)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', 'op-doc-order-item-form'))
            ->first();
        if (empty($conn)) return;

        $entity = Sys_con_entities::where([
            'conn_id' => $conn->id,
            'entity_tag' => 'has_serials',
            'table_tag' => 'sys_con_ops'
        ])->first();

        if (empty($entity)) {
            Sys_con_entities::create([
                'conn_id' => $conn->id,
                'entity_tag' => 'has_serials',
                'entity_value' => '1',
                'table_tag' => 'sys_con_ops',
            ]);
        } else {
            $entity->entity_value = '1';
            $entity->save();
        }
    }

    /**
     * Increment an order item's quantity (restore after clone removal).
     * Inverse of decrementItemQuantity — adds splitAmount back to original.
     */
    private function incrementItemQuantity($itemQnid, $splitAmount): void
    {
        $item = Documents::where('qnid', $itemQnid)->first();
        if (empty($item)) return;
        $conn = Sys_con_ops::where('main_id', $item->id)
            ->where('conn_id', 0)
            ->whereHas('type', fn ($q) => $q->where('op_key', 'op-doc-order-item-form'))
            ->first();
        if (empty($conn)) return;
        $qtyEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'quantity', 'table_tag' => 'sys_con_ops'])->first();
        if (empty($qtyEntity)) return;
        $currentQty = (float) $qtyEntity->entity_value;
        $newQty = $currentQty + (float) $splitAmount;
        $unitEntity = Sys_con_entities::where(['conn_id' => $conn->id, 'entity_tag' => 'unit', 'table_tag' => 'sys_con_ops'])->first();
        $unit = $unitEntity->entity_value ?? 'ST';
        $qtyEntity->entity_value = $unit === 'ST' ? (string) (int) $newQty : (string) round($newQty, 2);
        $qtyEntity->save();
    }

    /**
     * When a clone order (EBELN-X) is removed/cancelled, restore its split quantities
     * back to the main order. Also deactivates clone serials/items.
     */
    private function restoreQuantitiesForClone(Documents $cloneDoc): void
    {
        // Resolve original order via parent_id (set at clone creation via parent_qnid)
        $originalId = (int) $cloneDoc->parent_id;
        if ($originalId === 0) {
            // Fallback: strip -X suffix and find by order_no
            try {
                $detail = $this->getFormData($cloneDoc->qnid);
                $form = $detail['formFormat']['op-doc-order-form'] ?? [];
                $ents = [];
                foreach ($form as $cr) foreach (($cr['entities'] ?? []) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
                $orderNo = $ents['order_no'] ?? '';
                $baseNo = preg_replace('/-\d+$/','',(string)$orderNo);
                if ($baseNo && $baseNo !== $orderNo) {
                    $orig = DB::table('sys_con_entities as sce')
                        ->join('sys_con_ops as sco','sco.id','=','sce.conn_id')
                        ->join('documents as d','d.id','=','sco.main_id')
                        ->join('sys_options as so','so.id','=','d.type_id')
                        ->where('so.op_key','op-doc-order')->where('sce.entity_tag','order_no')->where('sce.entity_value',$baseNo)->where('d.status',1)->value('d.id');
                    if ($orig) $originalId = (int)$orig;
                }
            } catch (\Throwable $e) {}
        }
        if ($originalId === 0) return;

        // Find all clone items parented to this clone
        $itemTypeId = Sys_options::where('op_key','op-doc-order-item')->value('id');
        $serialTypeId = Sys_options::where('op_key','op-doc-order-serial')->value('id');
        $cloneItems = Documents::where('parent_id', $cloneDoc->id)
            ->when($itemTypeId, fn($q) => $q->where('type_id', $itemTypeId))
            ->get();
        foreach ($cloneItems as $cItem) {
            // Deactivate serials of clone item first
            $serials = Documents::where('parent_id', $cItem->id)
                ->when($serialTypeId, fn($q)=>$q->where('type_id', $serialTypeId))->get();
            foreach ($serials as $s) {
                $s->status = 0; $s->save();
            }
            // Restore quantity to original item via split_from_qnid
            try {
                $cDetail = $this->getFormData($cItem->qnid);
                $cForm = $cDetail['formFormat']['op-doc-order-item-form'] ?? [];
                $cEnts = [];
                foreach ($cForm as $cr) foreach (($cr['entities'] ?? []) as $k=>$v) if(!isset($cEnts[$k])) $cEnts[$k]=$v;
                $fromQnid = $cEnts['split_from_qnid'] ?? null;
                $splitAmt = (float)($cEnts['split_amount'] ?? $cEnts['quantity'] ?? 0);
                if ($fromQnid && $splitAmt > 0) {
                    $this->incrementItemQuantity($fromQnid, $splitAmt);
                }
            } catch (\Throwable $e) {}
            // Deactivate clone item itself
            $cItem->status = 0; $cItem->save();
        }
    }

    /**
     * When a single clone ITEM is removed, restore its split amount to the original item.
     */
    private function restoreQuantityForSingleCloneItem(Documents $cloneItemDoc): void
    {
        try {
            $detail = $this->getFormData($cloneItemDoc->qnid);
            $form = $detail['formFormat']['op-doc-order-item-form'] ?? [];
            $ents = [];
            foreach ($form as $cr) foreach (($cr['entities'] ?? []) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
            $fromQnid = $ents['split_from_qnid'] ?? null;
            $splitAmt = (float)($ents['split_amount'] ?? $ents['quantity'] ?? 0);
            if ($fromQnid && $splitAmt > 0) {
                $this->incrementItemQuantity($fromQnid, $splitAmt);
            }
            // Deactivate its serials
            $serialTypeId2 = Sys_options::where('op_key','op-doc-order-serial')->value('id');
            $serials = Documents::where('parent_id', $cloneItemDoc->id)
                ->when($serialTypeId2, fn($q)=>$q->where('type_id', $serialTypeId2))->get();
            foreach ($serials as $s) { $s->status = 0; $s->save(); }
        } catch (\Throwable $e) {}
    }

    /**
     * Order System: reject & cancel a whole order. Soft-passes documents.status=0 and
     * writes a terminal rejection transaction so the list/detail both reflect it.
     */
    public function cancelOrder($id, $note = null)
    {
        $document = Documents::where('qnid', $id)->first();
        if (empty($document)) {
            return ['success' => false, 'msg' => 'Sipariş bulunamadı: '.$id];
        }
        $docType = Sys_options::where('id', $document->type_id)->first();
        if (! in_array($docType->op_key ?? null, ['op-doc-order', 'op-doc-transfer'])) {
            return ['success' => false, 'msg' => 'Bu belge tipi iptal edilemez.'];
        }
        if ((int) $document->status === 0) {
            return ['success' => false, 'msg' => 'Sipariş zaten iptal edilmiş.'];
        }

        try {
            DB::beginTransaction();
            $flipped = Documents::where('qnid', $id)->where('status', 1)->update(['status' => 0]);
            if ($flipped === 0) {
                DB::rollBack();
                return ['success' => false, 'msg' => 'Sipariş zaten iptal edilmiş.'];
            }

            $statusKey = ($docType->op_key ?? null) === 'op-doc-transfer' ? 'doc_trans_transfer_rejected' : 'doc_trans_order_rejected';
            $type = Sys_options::where('op_key', $statusKey)->first();
            if (empty($type)) {
                $type = Sys_options::where('op_key', 'doc_trans_order_rejected')->first();
            }

            $log = UserLog::create([
                'user_id' => auth('sanctum')->user()->id ?? 0,
                'sys_code' => $GLOBALS['SYS_CODE'] ?? 'CATES',
                'relation' => 'documents',
                'relation_id' => $document->id,
                'type_id' => Sys_options::where('op_key', 'log-order-update')->first()->id
                    ?? Sys_options::where('op_key', 'log-tender-update')->first()->id ?? 0,
                'description' => json_encode([
                    'after' => ['document' => ['op_key' => $docType->op_key, 'qnid' => $document->qnid]],
                    'desc' => 'Sipariş İptal Edildi / Reddedildi',
                    'note' => $note ?? '-',
                ], JSON_UNESCAPED_UNICODE),
            ]);
            Transactions::create([
                'op_id' => 0,
                'type_id' => $type->id,
                'log_id' => $log->id,
                'target_id' => $document->id,
                'note' => $note ?? '-',
                'description' => json_encode(['note' => $note], JSON_UNESCAPED_UNICODE),
            ]);

            // If this is a clone order (EBELN-X) being cancelled, restore its quantities to main
            if (($docType->op_key ?? null) === 'op-doc-order' && (int)$document->parent_id !== 0) {
                $this->restoreQuantitiesForClone($document);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true];
    }

    public function disableDocument($qnid)
    {
        try {
            $file = \App\Models\Document_files::where('qnid', $qnid)->first();
            if (!$file) {
                return [
                    'success' => false,
                    'message' => 'Dosya bulunamadı',
                ];
            }

            $file->status = 0;
            $file->save();

            return [
                'success' => true,
                'message' => 'Dosya devre dışı bırakıldı',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * this method will return rejected file warnings for client users
     */
    public function getDocumentFiles($documentId)
    {
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
            'data' => $data,
        ];
    }

    /**
     * this method will return rejected file warnings for client users
     */
    public function getRejectedClientFiles($list = [])
    {
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
                            AND d.qnid in ('".implode("','", $list)."')
                            AND lt.op_key = 'doc_file_rejected'";
        $data = DB::select($sql);

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * this method will return awaiting file warnings for client users
     */
    public function getAwaitingClientFiles($list = [])
    {
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
            'data' => $data,
        ];
    }

    /**
     * this method will update client connections for other documents
     */
    public function updatePersonClients($documentId, $clientData)
    {
        $sql = "select   sce2.entity_tag,
                        sce2.entity_value,
                        sce2.id
                    from sys_con_entities sce
                            inner join sys_con_entities as sce2 on  sce2.conn_id = sce.conn_id
                        where   sce.entity_tag like '%cliid%'
                            and sce.entity_value = '$documentId'";
        $data = DB::select($sql);

        foreach ($data as $key => $value) {
            // code...
            if (strpos($value->entity_tag, 'clicode') !== false) {
                $entity = Sys_con_entities::where('id', $value->id)->first();
                $entity->entity_value = $clientData['clicode'] ?? '';
                $entity->save();
            }

            if (strpos($value->entity_tag,'clititle') !== false) {
                $entity = Sys_con_entities::where('id',$value->id)->first();
                $entity->entity_value = $clientData['title'] ?? '';
                $entity->save();
            }
        }
    }
}
