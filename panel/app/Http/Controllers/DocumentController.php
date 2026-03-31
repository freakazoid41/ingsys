<?php

namespace App\Http\Controllers;

use App\Providers\ReportServiceProvider;
use App\Providers\DocumentServiceProvider;
use Illuminate\Http\Request;

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

        if(!docPermCheck($key,'edit')){
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
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
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

        if($validateUser->fails() || !docPermCheck($key,'edit')){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return (new DocumentServiceProvider())->setStatus($request->id,$request->op_key,$request->note);
        }
    }

    public function setFileStatus(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
            'op_key'   => 'required',
        ]);

        if($validateUser->fails() || !checkPerm('per-07-02')){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return (new DocumentServiceProvider())->documentFileStatus($request->id,$request->op_key,$request->note);
        }
    }
    
    
    

}