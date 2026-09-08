<?php

namespace App\Http\Controllers;

use App\Providers\DocumentServiceProvider;
use App\Providers\PersonsServiceProvider;
use App\Providers\EmailServiceProvider;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    /**
     * Order / client only — offers are legacy (old system). New system has:
     * clients, orders, order items, order serials, transactions.
     */
    public const TEDARIK_ORDER_APPROVED_STATUSES = ['doc_trans_order_approved','doc_trans_order_rejected','doc_trans_order_files_rejected'];
    public const TEDARIK_FILE_STATUSES = ['doc_file_accepted','doc_file_rejected'];

    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    protected DocumentServiceProvider $docs;
    protected EmailServiceProvider $emails;
    protected PermissionService $perms;

    public function __construct(
        DocumentServiceProvider $documentService = null,
        EmailServiceProvider $emailService = null,
        PermissionService $permissionService = null
    ) {
        $this->docs  = $documentService ?: new DocumentServiceProvider();
        $this->emails = $emailService ?: new EmailServiceProvider();
        $this->perms = $permissionService ?: new PermissionService();
    }

    private function isValidUuid(?string $v): bool
    {
        return is_string($v) && preg_match(self::UUID_REGEX, $v) === 1;
    }

    private function decodeDataField(?string $raw): ?array
    {
        if ($raw === null || $raw === '') return null;
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        return is_array($decoded) ? $decoded : null;
    }

    private function mergeTempUploadReferences(array $source, array &$files): void
    {
        foreach ($source as $fkey => $value) {
            if (strpos($fkey, 'dynamicFile') !== false && is_string($value)) {
                $files[$fkey] = $value;
            }
        }
    }

    private function resolveDocumentKey(Request $request, string $method, ?array $decodedData = null): ?string
    {
        if ($method === 'POST') {
            return $decodedData['typeKey'] ?? null;
        }
        $form = $this->docs->getFormData($request->id);
        return $form['document']->op_key ?? null;
    }

    private function buildOrderNotifPayload(string $orderQnid, array $extra = []): array
    {
        try {
            $detail = $this->docs->getFormData($orderQnid);
            $entities = [];
            foreach (($detail['formFormat']['op-doc-order-form'] ?? []) as $cr) {
                foreach (($cr['entities'] ?? []) as $k => $v) if (!isset($entities[$k])) $entities[$k] = $v;
            }
            $sys = $entities['sys_code'] ?? ($detail['document']->grp_code ?? ($GLOBALS['SYS_CODE'] ?? 'GDZ'));
            return array_merge([
                'order_no' => $entities['order_no'] ?? $orderQnid,
                'spec_code' => $entities['spec_code'] ?? '',
                'sys_code' => $sys,
                'bukrs' => $sys,
                'BUKRS' => $sys,
                'ctitle' => $entities['ctitle'] ?? '',
                'qnid' => $orderQnid,
                'order_qnid' => $orderQnid,
                'order_spec' => $entities['spec_code'] ?? '',
                'order_sys_code' => $sys,
            ], $extra);
        } catch (\Throwable $e) {
            return array_merge(['qnid'=>$orderQnid,'order_qnid'=>$orderQnid,'sys_code'=>'GDZ','bukrs'=>'GDZ'], $extra);
        }
    }

    public function index(Request $request)
    {
        $method = strtoupper($request->method());

        if (in_array($method, ['GET','PUT','DELETE'], true) && !empty($request->id) && !$this->isValidUuid($request->id)) {
            return response()->json(['success'=>false,'msg'=>'Geçersiz belge kimliği (uuid bekleniyor)'],422);
        }

        $decodedData = null;
        if ($method === 'POST') {
            $decodedData = $this->decodeDataField($request->input('data'));
            if ($decodedData === null) {
                return response()->json(['success'=>false,'msg'=>'Geçersiz data JSON'],422);
            }
        } elseif ($method === 'PUT') {
            $rawAll = $request->all();
            if (empty($rawAll)) {
                $rawAll = function_exists('parsePut') ? parsePut() : [];
            }
            $decodedData = $this->decodeDataField($rawAll['data'] ?? null);
            $request->attributes->set('_put_raw', $rawAll);
            $request->attributes->set('_put_decoded', $decodedData);
        }

        $key = $this->resolveDocumentKey($request, $method, $decodedData);

        if (!docPermCheck($key, ($method === 'GET' ? 'read' : 'edit'))) {
            if (!($key === 'op-doc-client' && session('type_key') === 'op-pert-reseller' && in_array($request->id, session('currentStatus')['clientQnidList'] ?? [], true) && in_array($method, ['GET','PUT'], true))) {
                return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır...'],403);
            }
        }

        return match($method) {
            'GET'    => $this->handleShow($request),
            'POST'   => $this->handleStore($request, $decodedData, $key),
            'PUT'    => $this->handleUpdate($request, $key),
            'DELETE' => $this->handleDestroy($request, $key),
            default  => response()->json(['success'=>false,'msg'=>'Desteklenmeyen metod'],405),
        };
    }

    private function handleShow(Request $request)
    {
        $res = $this->docs->getFormData($request->id);
        return response()->json(['success'=>!empty($res), 'data'=>$res]);
    }

    private function handleStore(Request $request, ?array $decoded, ?string $key)
    {
        $files = $request->files->all();
        $this->mergeTempUploadReferences($request->all(), $files);
        $res = $this->docs->registerContent(0, $decoded, $files);
        return response()->json(['success'=>($res['id'] ?? 0) > 0, 'data'=>$res]);
    }

    private function handleUpdate(Request $request, ?string $key)
    {
        $data = $request->attributes->get('_put_raw') ?? $request->all();
        if (empty($data) && function_exists('parsePut')) {
            $data = parsePut();
        }
        $decoded = $request->attributes->get('_put_decoded');
        if ($decoded === null) {
            $decoded = $this->decodeDataField($data['data'] ?? null);
            if ($decoded === null) {
                return response()->json(['success'=>false,'msg'=>'Geçersiz data JSON'],422);
            }
        }

        $files = $request->files->all();
        $this->mergeTempUploadReferences($data, $files);

        $res = $this->docs->registerContent($request->id, $decoded, $files);

        $transferMode = $decoded['transfer_mode'] ?? null;
        if ($key === 'op-doc-order' && in_array($transferMode, ['at_once','partial'], true)) {
            $selectedItems = $decoded['selected_items'] ?? [];
            $itemSerials   = $decoded['item_serials'] ?? [];
            $transferRes = $this->docs->processOrderTransfer($request->id, $transferMode, $selectedItems, $itemSerials);
            if (!empty($transferRes['transfer_no'])) {
                $res['transfer_no'] = $transferRes['transfer_no'];
                $res['clone_qnid']  = $transferRes['clone_qnid'] ?? null;
            }
            $res['transfer_msg'] = $transferRes['msg'] ?? null;

            if (($transferRes['success'] ?? false) === true) {
                try {
                    $orderQnidForNotif = $transferRes['clone_qnid'] ?? $request->id;
                    $payloadNotif = $this->buildOrderNotifPayload($orderQnidForNotif, [
                        'transfer_no'   => $transferRes['transfer_no'] ?? $orderQnidForNotif,
                        'transfer_mode' => $transferMode,
                        'fileTitle'     => 'Transfer dosyaları',
                    ]);
                    $this->emails->sendTedarikOrderSent($payloadNotif);
                    $this->emails->sendTedarikFileWaiting($payloadNotif);
                } catch (\Throwable $e) {
                    Log::warning('tedarik-02/03 dispatch failed', ['msg'=>$e->getMessage(), 'qnid'=>$request->id]);
                }
            }
        }

        if ($key === 'op-doc-client' && session('type_key') === 'op-pert-reseller') {
            session(['currentStatus' => (new PersonsServiceProvider())->clientPermInfo(session('person_id'), session('type_key'))]);
        }

        if ($key === 'op-doc-client') {
            $clientInfo = array_values($res['detail']['formFormat']['op-doc-client-form'])[0]['entities'] ?? [];
            $clientContacts = array_filter($clientInfo, fn($k) => str_starts_with($k, 'cont_email') || str_starts_with($k, 'cont_phone'), ARRAY_FILTER_USE_KEY);
            $this->emails->sendClientChanged($clientContacts, $clientInfo);
            $this->docs->updatePersonClients($request->id, $clientInfo);
        }

        return response()->json(['success'=>($res['id'] ?? 0) > 0, 'data'=>$res]);
    }

    private function handleDestroy(Request $request, ?string $key)
    {
        if ($key === 'op-doc-order' && !docPermCheck('op-doc-order','cancel')) {
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-04 İptal / Parça Sil)...'],403);
        }
        $res = $this->docs->removeContent($request->id);
        return response()->json(['success'=>$res['success'] ?? false]);
    }

    public function transaction(Request $request){
        if(session('type_key') != 'op-pert-admin' && strtoupper($request->method()) != 'GET') return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);
       
        switch(strtoupper($request->method())){
            case "PUT":
                break;
            case "DELETE":
                $res =  $this->docs->removeTransaction($request->id);
                return response()->json(['success' => $res['success']]);
        }
        return response()->json(['success'=>false,'msg'=>'Desteklenmeyen metod'],405);
    }

    public function setStatus(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required|uuid',
            'op_key'   => 'required',
        ]);
                
        if (!$this->isValidUuid($request->id)) {
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }

        $formTmp = $this->docs->getFormData($request->id);
        $docOpKey = $formTmp['document']->op_key ?? null;
        if (!$docOpKey) {
            return response()->json(['success'=>false,'msg'=>'Belge bulunamadı'],404);
        }

        $validated = docPermCheck($docOpKey,'status');
        if($validateUser->fails() || !$validated){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],422);
        }

        $response = $this->docs->setStatus($request->id,$request->op_key,$request->note);
        if(!($response['success'] ?? false)){
            return response()->json($response, 422);
        }
        if(($response['detail']['document']->op_key ?? null) == 'op-doc-order' && in_array($request->op_key, self::TEDARIK_ORDER_APPROVED_STATUSES, true)){
            try{
                $detail = $response['detail'] ?? $this->docs->getFormData($request->id);
                $payloadNotif = $this->buildOrderNotifPayload($request->id, [
                    'order_no'  => $this->extractOrderNo($detail) ?? $request->id,
                    'note'      => $request->note ?? '',
                ]);
                if(in_array($request->op_key, ['doc_trans_order_rejected','doc_trans_order_files_rejected'], true)){
                    $this->emails->sendTedarikOrderRejected($payloadNotif);
                } else {
                    $this->emails->sendTedarikOrderApproved($payloadNotif);
                }
            }catch(\Throwable $e){
                Log::warning('tedarik-06/07 dispatch failed', ['msg'=>$e->getMessage(), 'id'=>$request->id]);
            }
        }
        return $response;
    }

    private function extractOrderNo(array $detail): ?string
    {
        $ents = [];
        foreach(($detail['formFormat']['op-doc-order-form'] ?? []) as $cr){
            foreach(($cr['entities'] ?? []) as $k=>$v) if(!isset($ents[$k])) $ents[$k]=$v;
        }
        return $ents['order_no'] ?? null;
    }

    // Legacy offers — removed in new system (clients/orders/items only).
    // Stub keeps old routes from 500-ing; new code must not call these.
    public function cancelOffer(Request $request){ return response()->json(['success'=>false,'msg'=>'Offers removed in new system'],410); }
    public function reopenOffer(Request $request){ return response()->json(['success'=>false,'msg'=>'Offers removed in new system'],410); }

    public function cancelOrder(Request $request){
        $validateUser = Validator::make($request->all(),['id' => 'required|uuid']);
        if($validateUser->fails()){
            return response()->json(['success'=>false,'msg'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        if(!docPermCheck('op-doc-order','cancel')){
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-04 İptal / Parça Sil)...'],403);
        }
        $form = $this->docs->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı veya bu belge tipi iptal edilemez.'],422);
        }
        $response = $this->docs->cancelOrder($request->id,$request->note);
        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    public function renameOrder(Request $request){
        $validate = Validator::make($request->all(),['id' => 'required|uuid','order_no' => 'required|string|max:64']);
        if($validate->fails()){
            return response()->json(['success'=>false,'msg'=>'Missing Parameters','error'=>$validate->errors()],422);
        }
        if(!docPermCheck('op-doc-order','rename')){
            return response()->json(['success'=>false,'msg'=>'İşlem için yetkiniz bulunmamaktadır (per-05-05 Numara Düzenle)...'],403);
        }
        $form = $this->docs->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı veya bu belge tipi düzenlenemez.'],422);
        }
        $response = $this->docs->renameOrder($request->id, $request->order_no);
        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    public function setFileStatus(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required','op_key'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07-02')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $result = $this->docs->documentFileStatus($request->id,$request->op_key,$request->note);
        if($result['success']){
            refreshAllUserPermissions();
        }
        if($result['success']){
            $payload = ['type'=>'cliFileStatus','contacts'=>[],'status'=>$result['data'],'fileTitle'=>$result['fileTitle'],'note'=>$result['note'] ?? ''];
            foreach ($result['connections'] as $row) {
                if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                    $payload['contacts'][$row->entity_tag] = $row->entity_value;
                }
                if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                    $payload[$row->entity_tag]= $row->entity_value;
                }
            }
            $this->emails->sendClientFileStatus($payload);
        }
        if($result['success'] && in_array($request->op_key, self::TEDARIK_FILE_STATUSES, true)){
            try{
                $fileRow = \App\Models\Document_files::where('qnid', $request->id)->first();
                if($fileRow){
                    $relDoc = \App\Models\Documents::find((int)$fileRow->relation_id);
                    if($relDoc){
                        $orderDoc = $relDoc;
                        if((int)$relDoc->parent_id !== 0){
                            $maybeOrder = \App\Models\Documents::find($relDoc->parent_id);
                            if($maybeOrder) $orderDoc = $maybeOrder;
                        }
                        $orderType = \App\Models\Sys_options::find($orderDoc->type_id);
                        if(($orderType->op_key ?? null) === 'op-doc-order'){
                            $payloadNotif = $this->buildOrderNotifPayload($orderDoc->qnid, [
                                'fileTitle' => $result['fileTitle'] ?? 'Dosya',
                                'note'      => $result['note'] ?? '',
                            ]);
                            if($request->op_key === 'doc_file_accepted'){
                                $this->emails->sendTedarikFileApproved($payloadNotif);
                            } else {
                                $this->emails->sendTedarikFileRejected($payloadNotif);
                            }
                        }
                    }
                }
            }catch(\Throwable $e){
                Log::warning('tedarik-04/05 dispatch failed', ['msg'=>$e->getMessage(), 'file'=>$request->id]);
            }
        }
        return $result;
    }
    
    
    public function tempUpload(Request $request){
        if(!$request->hasFile('file')){
            return response()->json(['success'=>false,'msg'=>'Dosya bulunamadı'],422);
        }
        $file = $request->file('file');
        $result = tempUploadFile($file);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function setFileStatusAll(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required','op_key'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07-02')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $files = $this->docs->getDocumentFiles($request->id);
        $result = ['success' => false];
        $anySuccess = false;
        $tedarikPending = [];
        if($files['success']){
            foreach($files['data'] as $file){
                $result = $this->docs->documentFileStatus($file->qnid,$request->op_key,$request->note);
                if($result['success']){
                    $anySuccess = true;
                    $payload = ['type'=>'cliFileStatus','contacts'=>[],'status'=>$result['data'],'fileTitle'=>$result['fileTitle'],'note'=>$result['note'] ?? ''];
                    foreach ($result['connections'] as $row) {
                        if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                            $payload['contacts'][$row->entity_tag] = $row->entity_value;
                        }
                        if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                            $payload[$row->entity_tag]= $row->entity_value;
                        }
                    }
                    $this->emails->sendClientFileStatus($payload);

                    if(in_array($request->op_key, self::TEDARIK_FILE_STATUSES, true)){
                        try{
                            $fileRow = \App\Models\Document_files::where('qnid', $file->qnid)->first();
                            if($fileRow){
                                $relDoc = \App\Models\Documents::find((int)$fileRow->relation_id);
                                if($relDoc){
                                    $orderDoc = $relDoc;
                                    if((int)$relDoc->parent_id !== 0){
                                        $maybeOrder = \App\Models\Documents::find($relDoc->parent_id);
                                        if($maybeOrder) $orderDoc = $maybeOrder;
                                    }
                                    $orderType = \App\Models\Sys_options::find($orderDoc->type_id);
                                    if(($orderType->op_key ?? null) === 'op-doc-order'){
                                        $payloadTmp = $this->buildOrderNotifPayload($orderDoc->qnid, [
                                            'note' => $request->note ?? '',
                                            'fileTitle' => $result['fileTitle'] ?? 'Dosya',
                                        ]);
                                        $qnidKey = $orderDoc->qnid;
                                        if(!isset($tedarikPending[$qnidKey])){
                                            $tedarikPending[$qnidKey] = array_merge($payloadTmp, ['fileTitles'=>[]]);
                                        }
                                        $tedarikPending[$qnidKey]['fileTitles'][] = $result['fileTitle'] ?? 'Dosya';
                                    }
                                }
                            }
                        }catch(\Throwable $e){
                            Log::warning('bulk tedarik-04/05 collect failed', ['msg'=>$e->getMessage(), 'file'=>$file->qnid]);
                        }
                    }
                }
            }
            if(!empty($tedarikPending)){
                foreach($tedarikPending as $payloadNotif){
                    $titles = array_values(array_unique(array_filter($payloadNotif['fileTitles'])));
                    $payloadNotif['fileTitle'] = count($titles) > 1 ? count($titles).' dosya: '.implode(', ', array_slice($titles,0,3)).(count($titles)>3?' …':'') : ($titles[0] ?? 'Dosya');
                    unset($payloadNotif['fileTitles']);
                    try{
                        if($request->op_key === 'doc_file_accepted'){
                            $this->emails->sendTedarikFileApproved($payloadNotif);
                        } else {
                            $this->emails->sendTedarikFileRejected($payloadNotif);
                        }
                    }catch(\Throwable $e){
                        Log::warning('bulk tedarik-04/05 dispatch failed', ['msg'=>$e->getMessage()]);
                    }
                }
            }
            if($anySuccess){
                refreshAllUserPermissions();
            }
        }
        return $result;
    }

    public function disableDocument(Request $request){
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),['id'=>'required']);
        if($validateUser->fails() || !$this->perms->has($authUser, 'per-07')){
            return response()->json(['success'=>false,'message'=>'Missing Parameters','error'=>$validateUser->errors()],422);
        }
        $result = $this->docs->disableDocument($request->id);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

    public function fileDetail(Request $request){
        $fileQnid = $request->id;
        if(empty($fileQnid)) return response()->json(['success'=>false,'msg'=>'Missing id'],422);
        if(!$this->isValidUuid($fileQnid)) return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);

        $bundle = $this->docs->fetchFileDetailBundle($fileQnid);
        if(!$bundle) return response()->json(['success'=>false,'msg'=>'Belge bulunamadı'],404);
        if(isset($bundle['forbidden']) && $bundle['forbidden'] === true) return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);

        return response()->json([
            'success'=>true,
            'data'=>[
                'file_qnid' => $bundle['file_qnid'],
                'order' => $bundle['orderHeader'],
                'items' => $bundle['items'],
                'files' => $bundle['files'],
            ]
        ]);
    }

    public function downloadAllOrderFiles(Request $request, $qnid = null){
        $orderQnid = $qnid ?? $request->input('qnid') ?? $request->input('id') ?? $request->route('qnid');
        if(empty($orderQnid)) return response()->json(['success'=>false,'msg'=>'Sipariş qnid gerekli'],422);
        if(!$this->isValidUuid($orderQnid)){
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }
        $order = $this->docs->resolveOrderFromQnid($orderQnid);
        if(!$order || ($order->type_key ?? null) !== 'op-doc-order') return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı'],404);

        if(!$this->docs->canCurrentResellerAccessOrder($order)){
            return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);
        }

        $orderId = $order->id;
        $files = $this->docs->fetchOrderFilesForDownload($orderId);
        if(empty($files)){
            return response()->json(['success'=>false,'msg'=>'İndirilecek form bulunamadı'],404);
        }

        $orderNo = $this->docs->fetchOrderNoById($orderId);
        $zipBase = preg_replace('/[^A-Za-z0-9_\-]/','-', $orderNo ?? $orderQnid);
        $zipName = 'order-'.$zipBase.'-tum-formlar.zip';
        $zipPath = sys_get_temp_dir().'/'.$zipName.'-'.uniqid().'.zip';
        $zip = new \ZipArchive();
        if($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true){
            return response()->json(['success'=>false,'msg'=>'ZIP oluşturulamadı'],500);
        }
        $enc = new \App\Providers\EncryptionProvider();
        $used = [];
        $added = 0;
        $skipped = 0;
        try {
            foreach($files as $f){
                try{
                    $plain = $enc->decrypt($f->file_desc);
                }catch(\Throwable $e){
                    $skipped++; continue;
                }
                $path = storage_path('app/public/documents/'.$plain);
                if(!file_exists($path)){
                    $alt = storage_path('app/public/temp/'.$plain);
                    if(file_exists($alt)) $path = $alt;
                    else { $skipped++; continue; }
                }
                $ext = pathinfo($plain, PATHINFO_EXTENSION);
                if(!$ext) $ext = 'pdf';
                $typeSlug = preg_replace('/[^A-Za-z0-9_\-]/','-', $f->file_type ?? 'form');
                $typeSlug = trim($typeSlug,'-');
                if($typeSlug==='') $typeSlug='form';
                $base = $typeSlug.'-'.substr($f->file_qnid,0,8).'.'.$ext;
                $name = $base; $c=1;
                while(isset($used[$name])){
                    $name = pathinfo($base, PATHINFO_FILENAME).'-'.$c++.'.'.$ext;
                }
                $used[$name]=true;
                $zip->addFile($path, $name);
                $added++;
            }
            $zip->close();
            if($added===0){
                @unlink($zipPath);
                return response()->json(['success'=>false,'msg'=>'Dosyalar diskte bulunamadı'],404);
            }
            Log::info('downloadAllOrderFiles', ['order_qnid'=>$orderQnid,'order_no'=>$orderNo ?? '', 'added'=>$added,'skipped'=>$skipped]);
            return response()->download($zipPath, 'order-'.$zipBase.'-tum-formlar.zip')->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
    }

    public function listOrderFiles(Request $request, $qnid = null){
        $orderQnid = $qnid ?? $request->input('qnid') ?? $request->input('id') ?? $request->route('qnid');
        if(empty($orderQnid)) return response()->json(['success'=>false,'msg'=>'Sipariş qnid gerekli'],422);
        if(!$this->isValidUuid($orderQnid)){
            return response()->json(['success'=>false,'msg'=>'Geçersiz qnid'],422);
        }
        $order = $this->docs->resolveOrderFromQnid($orderQnid);
        if(!$order || ($order->type_key ?? null) !== 'op-doc-order') return response()->json(['success'=>false,'msg'=>'Sipariş bulunamadı'],404);

        if(!$this->docs->canCurrentResellerAccessOrder($order)){
            return response()->json(['success'=>false,'msg'=>'Yetki yok'],403);
        }

        $orderId = $order->id;
        $files = $this->docs->fetchOrderFilesWithStatus($orderId);
        foreach($files as &$f){
            if(is_string($f->last_status)){
                try{ $f->last_status = json_decode($f->last_status, true) ?? json_decode($f->last_status); }catch(\Throwable $e){}
            }
            try{
                $enc = new \App\Providers\EncryptionProvider();
                $plain = $enc->decrypt($f->file_desc);
                $f->display_name = $plain;
            }catch(\Throwable $e){ $f->display_name = $f->file_qnid; }
        }
        unset($f);
        return response()->json(['success'=>true,'data'=>['files'=>$files]]);
    }

}
