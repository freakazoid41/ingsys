<?php

namespace App\Http\Controllers;

use App\Providers\ReportServiceProvider;
use App\Providers\DocumentServiceProvider;
use App\Providers\PersonsServiceProvider;
use Illuminate\Http\Request;
use App\Providers\EmailServiceProvider;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;


class DocumentController extends Controller
{
    public function index(Request $request){
        $logModel = 'documents';
        $method   = strtoupper($request->method());
        /*if(!checkPermRoute($logModel,$request->method())) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);*/
        
        /*if(session('type_key') != 'op-pert-admin' && strtoupper($request->method()) != 'GET') return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);*/
        //here get document type for permissions
        $key = null;
        if($method == 'POST'){
            $req = $request->all();
            $req = json_decode($req['data'],true);
            $key = $req['typeKey'] ?? null;
        }else{
            $key = (new DocumentServiceProvider())->getFormData($request->id);
            $key = $key['document']->op_key;
        }
        if(!docPermCheck($key,($method == 'GET' ? 'read' : 'edit'))){
             
            //here clients must be edit their own client informations so check that logic
            //also its only for PUT and GET methods
            if($key == 'op-doc-client' && session('type_key') == 'op-pert-reseller' && in_array($request->id, session('currentStatus')['clientQnidList'] ?? []) && in_array($method,['GET','PUT'])){
               //do nothing..
            }else{
                return response()->json([
                    'success' => false,
                    'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
                ],403);
            }
            
        }

        //here check if invalid user is trying to give offer
        if($key == 'op-doc-offer' && !session('currentStatus')['canResponse']){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }

        //suppliers may only reach offers belonging to the companies bound to their session.
        //POST is excluded: the document does not exist yet at creation time.
        if($key == 'op-doc-offer' && in_array($method,['GET','PUT']) && !offerOwnershipCheck($request->id)){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }

        
        //$model = 'App\\Models\\Documents';
        switch($method){
            case "GET":
                //$req = $request->all();
                $res = (new DocumentServiceProvider())->getFormData($request->id);
                $response = [
                    'success' => !empty($res),
                    'data' => $res,
                ];
                
                break;
            case "POST":
                $req = $request->all();
                $req = json_decode($req['data'],true);
                // temp-upload references arrive as plain multipart string fields, not as file
                // uploads — they never appear in $request->files. Merge them back so
                // registerContent() can finalize the pending temp files.
                $files = $request->files->all();
                foreach ($request->all() as $fkey => $value) {
                    if (strpos($fkey, 'dynamicFile') !== false && is_string($value)) {
                        $files[$fkey] = $value;
                    }
                }
                $res = (new DocumentServiceProvider())->registerContent(0,$req,$files);
                
                //here check if its offer , if it is and created successfully send informaiton mails to system users who permitted
                if($key == 'op-doc-offer' && $res['id'] > 0){
                    $offerData = $res;
                    $offerData['client'] = session('currentStatus')['clientTitle'];
                   
                    (new EmailServiceProvider())->sendOfferGiven($offerData);
                }


                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                if($key == 'op-doc-offer'){
                    $currentDoc = (new DocumentServiceProvider())->getFormData($request->id);

                    //a cancelled offer is terminal for everyone, suppliers and admins alike
                    if((int)($currentDoc['document']->document_status ?? 1) === 0){
                        return response()->json([
                            'success' => false,
                            'msg'     => 'İptal edilmiş teklif üzerinde düzenleme yapılamaz.',
                        ], 422);
                    }

                    // Suppliers may only edit offers in editable states
                    if(session('type_key') == 'op-pert-reseller'){
                        $statusHistory = json_decode($currentDoc['document']->status ?? '[]', true) ?? [];
                        $lastStatus = end($statusHistory)['op_key'] ?? 'doc_trans_created';
                        $editableStatuses = ['doc_trans_offer_revision','doc_trans_created','doc_trans_offer_draft'];
                        if(!in_array($lastStatus, $editableStatuses)){
                            return response()->json([
                                'success' => false,
                                'msg'     => 'Teklifin mevcut durumunda düzenleme yapılamaz.',
                            ], 403);
                        }
                    }
                }

                //normally we have a middleware to parse PUT multipart data but in case it fails we fallback here with custom helper
                $data = $request->all();
                if(empty($data)){
                    //not working on apache server
                    $data = parsePut();
                }

                $files = $request->files->all();
                if(empty($files)){
                    //not working on apache server
                    $files = $_FILES;
                }

                // temp-upload references arrive as plain multipart string fields; merge them
                // into $files so registerContent() can finalize the pending temp files.
                // NB: do NOT reuse $key as the loop variable — $key holds the resolved document
                // type (op-doc-order / op-doc-offer / ...) and is needed below for the transfer
                // and offer-revision logic. Overwriting it silently kills those branches.
                foreach ($data as $fkey => $value) {
                    if (strpos($fkey, 'dynamicFile') !== false && is_string($value)) {
                        $files[$fkey] = $value;
                    }
                }

                $res = (new DocumentServiceProvider())->registerContent($request->id,json_decode($data['data'],true),$files);

                // Order System: sending a transfer happens on the same order-detail save.
                // The client picks transfer mode (at_once | partial) in the form; on save we
                // either send the order itself (at-once) or clone it as EBELN-X (partial).
                $decoded = json_decode($data['data'] ?? '{}', true);
                $transferMode = $decoded['transfer_mode'] ?? null;
                if ($key == 'op-doc-order' && in_array($transferMode, ['at_once', 'partial'])) {
                    $selectedItems = $decoded['selected_items'] ?? [];
                    $itemSerials = $decoded['item_serials'] ?? [];
                    $transferRes = (new DocumentServiceProvider())->processOrderTransfer($request->id, $transferMode, $selectedItems, $itemSerials);
                    if (! empty($transferRes['transfer_no'])) {
                        $res['transfer_no'] = $transferRes['transfer_no'];
                        $res['clone_qnid'] = $transferRes['clone_qnid'] ?? null;
                    }
                    $res['transfer_msg'] = $transferRes['msg'] ?? null;
                }

                // here check if is an offer , and its last status is 'requested revision' if it is and updated make its status 'revisited'
                if($key == 'op-doc-offer'){
                    //null when the offer has no transaction in the op-trans-op-doc-offer group yet
                    $status = json_decode($res['detail']['document']->status ?? '[]', true) ?? [];
                    $lastStatus = !empty($status) ? (end($status)['op_key'] ?? null) : null;
                    if($lastStatus == 'doc_trans_offer_revision'){
                        (new DocumentServiceProvider())->setStatus($request->id, 'doc_trans_offer_revised','Müşteri Teklif Bilgilerini Revize Etti');

                        //here send mail to system users about offer revision
                        $res['type'] = 'offerRevision';
                        (new EmailServiceProvider())->sendOfferGiven($res);
                    }
                }



                //here supdate client specific information to session
                if($key == 'op-doc-client' && session('type_key') == 'op-pert-reseller'){
                    session(['currentStatus' => (new PersonsServiceProvider())->clientPermInfo(session('person_id'),session('type_key'))]);
                }

                //here we need to send all changes to clients if document is client information form and its updated by reseller or system user because clients need to refresh their data if they want to continue using system without any problem
                if($key == 'op-doc-client' ){
                    $clientInfo      = array_values($res['detail']['formFormat']['op-doc-client-form'])[0]['entities'];
                    $clientContacts  = array_filter($clientInfo, fn($key) => (str_starts_with($key, 'cont_email') || str_starts_with($key, 'cont_phone')), ARRAY_FILTER_USE_KEY);
                    //if client info is updated by system user or reseller we will send this log to both channel because its important for them
                    (new EmailServiceProvider())->sendClientChanged($clientContacts,$clientInfo);

                    //also here if client is updated we need to update person connections also for new client name and code
                    (new DocumentServiceProvider())->updatePersonClients($request->id,$clientInfo);
                }


                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
			    break;
			case "DELETE":
                //offers are never removed; they are cancelled through /v1/trans/cancel-offer,
                //which carries the ownership and terminal-state guards.
                if($key == 'op-doc-offer'){
                    return response()->json([
                        'success' => false,
                        'msg'     => 'Teklifler silinemez, yalnızca iptal edilebilir.',
                    ],403);
                }

                $res =  (new DocumentServiceProvider())->removeContent($request->id);
                $response = [
                    'success' => $res['success'],
                ];
                break;
        }

        

        return response()->json($response);
	}

    public function transaction(Request $request){
        $logModel = 'trasnactions';
        
        /*if(!checkPermRoute($logModel,$request->method())) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);*/
        
        if(session('type_key') != 'op-pert-admin' && strtoupper($request->method()) != 'GET') return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);
       
        //$model = 'App\\Models\\Documents';
        switch(strtoupper($request->method())){
            case "PUT":
                /*$data = parsePut();
                
                $res = (new DocumentServiceProvider())->registerContent($request->id,json_decode($data['data'],true),$_FILES);

                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];*/
			    break;
			case "DELETE":
                $res =  (new DocumentServiceProvider())->removeTransaction($request->id);
                $response = [
                    'success' => $res['success'],
                ];
                break;
        }

        

        return response()->json($response);
	}

    public function setStatus(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
            'op_key'   => 'required',
        ]);
               
        $key = (new DocumentServiceProvider())->getFormData($request->id);
        $key = $key['document']->op_key;


        $validated = docPermCheck($key,'status');
        if(!$validated){
            //here clients must be send their offer to system
            if($key == 'op-doc-offer' && session('type_key') == 'op-pert-reseller' && $request->op_key == 'doc_trans_offer_sended'){
               $validated = true;
            }
            
        }

        if($validateUser->fails() || !$validated){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            $response = (new DocumentServiceProvider())->setStatus($request->id,$request->op_key,$request->note);
            if(!($response['success'] ?? false)){
                return response()->json($response, 422);
            }
            if(($response['detail']['document']->op_key ?? null) == 'op-doc-offer'){
                (new EmailServiceProvider())->sendOfferStatus($response);
            }
            return $response;
        }
    }

    /**
     * Cancels an offer. Deliberately a dedicated endpoint rather than a branch of the generic
     * DELETE handler: cancelling is a controlled state change, not a removal, and it needs its own
     * ownership and terminal-state guards.
     */
    public function cancelOffer(Request $request){
        //uuid is enforced here because the id reaches getFormData's raw SQL below
        $validateUser = Validator::make($request->all(),[
            'id' => 'required|uuid',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'msg'     => 'Missing Parameters',
                'error'   => $validateUser->errors(),
            ],422);
        }

        //permission first, so an unauthorised caller cannot probe which qnids are offers
        if(!docPermCheck('op-doc-offer','edit') || !offerOwnershipCheck($request->id)){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }

        $form     = (new DocumentServiceProvider())->getFormData($request->id);
        $document = $form['document'] ?? null;

        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-offer'){
            return response()->json([
                'success' => false,
                'msg'     => 'Teklif bulunamadı veya bu belge tipi iptal edilemez.',
            ],422);
        }

        $response = (new DocumentServiceProvider())->cancelOffer($request->id,$request->note);

        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    /**
     * Yanlislikla iptal edilmis teklifi geri acar (#16). Durum degistirmez; teklif
     * iptalden onceki durumuna doner. Yetki cancelOffer ile ayni: per-08-02 + sahiplik,
     * yani tedarikci kendi firmasinin teklifini geri acabilir.
     */
    public function reopenOffer(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id' => 'required|uuid',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'msg'     => 'Missing Parameters',
                'error'   => $validateUser->errors(),
            ],422);
        }

        if(!docPermCheck('op-doc-offer','edit') || !offerOwnershipCheck($request->id)){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }

        $response = (new DocumentServiceProvider())->reopenOffer($request->id,$request->note);

        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    /**
     * Order System: reject & cancel a whole order (from order detail or list).
     */
    public function cancelOrder(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id' => 'required|uuid',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'msg'     => 'Missing Parameters',
                'error'   => $validateUser->errors(),
            ],422);
        }

        if(!docPermCheck('op-doc-order','edit')){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }

        $form     = (new DocumentServiceProvider())->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json([
                'success' => false,
                'msg'     => 'Sipariş bulunamadı veya bu belge tipi iptal edilemez.',
            ],422);
        }

        $response = (new DocumentServiceProvider())->cancelOrder($request->id,$request->note);

        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    /**
     * Tedarik Aksiyonlar: Sipariş Numarasını Düzenle (only partitioned EBELN-X).
     */
    public function renameOrder(Request $request){
        $validate = Validator::make($request->all(),[
            'id' => 'required|uuid',
            'order_no' => 'required|string|max:64',
        ]);
        if($validate->fails()){
            return response()->json([
                'success' => false,
                'msg'     => 'Missing Parameters',
                'error'   => $validate->errors(),
            ],422);
        }
        if(!docPermCheck('op-doc-order','edit')){
            return response()->json([
                'success' => false,
                'msg'     => 'İşlem için yetkiniz bulunmamaktadır...',
            ],403);
        }
        $form = (new DocumentServiceProvider())->getFormData($request->id);
        $document = $form['document'] ?? null;
        if(!is_object($document) || ($document->op_key ?? null) !== 'op-doc-order'){
            return response()->json([
                'success' => false,
                'msg'     => 'Sipariş bulunamadı veya bu belge tipi düzenlenemez.',
            ],422);
        }
        $response = (new DocumentServiceProvider())->renameOrder($request->id, $request->order_no);
        return response()->json($response, ($response['success'] ?? false) ? 200 : 422);
    }

    public function setFileStatus(Request $request){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
            'op_key'   => 'required',
        ]);

        if($validateUser->fails() || !$permissionService->has($authUser, 'per-07-02')){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            $result = (new DocumentServiceProvider())->documentFileStatus($request->id,$request->op_key,$request->note);

            //here refresh user session for all logged in users after file status change
            if($result['success']){
                refreshAllUserPermissions();
            }

            //here send information to clients about their file status
            if($result['success']){
                $payload = [
                    'type'      => 'cliFileStatus',
                    'contacts'  => [],
                    'status'    => $result['data'],
                    'fileTitle' => $result['fileTitle'],
                    'note'      => $result['note'] ?? '',
                ];

                foreach ($result['connections'] as $row) {
                    if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                        $payload['contacts'][$row->entity_tag] = $row->entity_value;
                    }
                    if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                        $payload[$row->entity_tag]= $row->entity_value;
                    }
                }

                (new EmailServiceProvider())->sendClientFileStatus($payload);

            }
            return $result;
        }
    }
    
    
    /**
     * Handles immediate temp file upload. Called when user selects a file in the form.
     * Returns a reference_id that can be sent with the form later.
     */
    public function tempUpload(Request $request){
        if(!$request->hasFile('file')){
            return response()->json([
                'success' => false,
                'msg' => 'Dosya bulunamadı'
            ], 422);
        }

        $file = $request->file('file');
        $result = tempUploadFile($file);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function setFileStatusAll(Request $request){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
            'op_key'   => 'required',
        ]);

        if($validateUser->fails() || !$permissionService->has($authUser, 'per-07-02')){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            //here get all files for given document
            $files = (new DocumentServiceProvider())->getDocumentFiles($request->id);
            $result = ['success' => false];
            $anySuccess = false;
            if($files['success']){
                foreach($files['data'] as $file){
                    $result = (new DocumentServiceProvider())->documentFileStatus($file->qnid,$request->op_key,$request->note);
                    
                    //here send information to clients about their file status
                    if($result['success']){
                        $anySuccess = true;
                        $payload = [
                            'type'      => 'cliFileStatus',
                            'contacts'  => [],
                            'status'    => $result['data'],
                            'fileTitle' => $result['fileTitle'],
                            'note'      => $result['note'] ?? '',
                        ];

                        foreach ($result['connections'] as $row) {
                            if(strpos($row->entity_tag, 'cont_email') !== false || strpos($row->entity_tag, 'cont_phone') !== false){
                                $payload['contacts'][$row->entity_tag] = $row->entity_value;
                            }
                            if(strpos($row->entity_tag, 'title') !== false || strpos($row->entity_tag, 'clicode') !== false ){
                                $payload[$row->entity_tag]= $row->entity_value;
                            }
                        }

                        (new EmailServiceProvider())->sendClientFileStatus($payload);
                    }
                }

                //here refresh user session for all logged in users after file status change (once, not per file)
                if($anySuccess){
                    refreshAllUserPermissions();
                }
            }
            return $result;
        }
    }

    public function disableDocument(Request $request){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
        ]);

        if($validateUser->fails() || !$permissionService->has($authUser, 'per-07')){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }

        $result = (new DocumentServiceProvider())->disableDocument($request->id);
        return response()->json($result, $result['success'] ? 200 : 404);
    }

}