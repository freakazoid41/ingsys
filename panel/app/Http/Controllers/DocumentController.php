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
                $res = (new DocumentServiceProvider())->registerContent(0,$req,$request->files->all());
                
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
                // Suppliers may only edit offers in editable states
                if($key == 'op-doc-offer' && session('type_key') == 'op-pert-reseller'){
                    $currentDoc = (new DocumentServiceProvider())->getFormData($request->id);
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

                $res = (new DocumentServiceProvider())->registerContent($request->id,json_decode($data['data'],true),$files);
                
                // here check if is an offer , and its last status is 'requested revision' if it is and updated make its status 'revisited'
                if($key == 'op-doc-offer'){
                    $status = json_decode($res['detail']['document']->status, true) ?? [];
                    if(end($status)['op_key'] == 'doc_trans_offer_revision'){
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
            if($response['detail']['document']->op_key == 'op-doc-offer'){
                (new EmailServiceProvider())->sendOfferStatus($response);
            }
            return $response;
        }
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
            if($files['success']){
                foreach($files['data'] as $file){
                    $result = (new DocumentServiceProvider())->documentFileStatus($file->qnid,$request->op_key,$request->note);
                    
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
                }
            }
            return $result;
        }
    }

}