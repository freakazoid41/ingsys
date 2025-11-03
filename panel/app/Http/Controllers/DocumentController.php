<?php

namespace App\Http\Controllers;

use App\Providers\ReportServiceProvider;
use App\Providers\DocumentServiceProvider;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;
use App\Models\User_logs;
use App\Models\Sys_options;


class DocumentController extends Controller
{
    public function index(Request $request){
        $logModel = 'documents';
        
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
            case "GET":
                //$req = $request->all();
                $res = (new DocumentServiceProvider())->getFormData($request->id);
                $response = [
                    'success' => !empty($res),
                    'data' => $res,
                ];
                /*$res = [];
                if($id != 0){
                    $res = $model::where('id',$id)->first();
                }else{
                    $res = $model::all();
                }
				$res = $res->toarray();
                //get request for data getting
                $response = [
                    'success' => !empty($res),
                    'data' => $res,
                ];*/
                break;
            case "POST":
                $req = $request->all();
                $res = (new DocumentServiceProvider())->registerContent(0,json_decode($req['data'],true),$request->files->all());
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                $data = parsePut();
                $res = (new DocumentServiceProvider())->registerContent($request->id,json_decode($data['data'],true),$_FILES);

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

    public function getFacility(Request $request){
        $validateUser = Validator::make($request->all(),[
            'code'     => 'required',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return  response()->json((new DocumentServiceProvider())->getFacility($request->all()));
        }
    }

    public function getPersonInventories(Request $request){
        $validateUser = Validator::make($request->all(),[
            'code'     => 'required',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return  response()->json((new DocumentServiceProvider())->getInventory($request->all()['code']));
        }
    }

    public function newVisit(Request $request){
        $logModel = 'documents';
        
        //$model = 'App\\Models\\Documents';
        switch(strtoupper($request->method())){
            case "POST":
                $req  = $request->all();
                $data = json_decode($req['data'],true);
                $data['typeKey'] = "op-doc-visit";

                $res = (new DocumentServiceProvider())->registerContent(0,$data,$request->files->all());
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                $data = parsePut();
                $data = json_decode($data['data'],true);
                $data['typeKey'] = "op-doc-visit";
                $res = (new DocumentServiceProvider())->registerContent($request->id,$data,$_FILES);

                if($res['id'] > 0 && isset(array_values($data['dynamicF'])[0]['entities']['exited_at'])){
                    User_logs::create([
                        'user_id'     => 0,
                        'grp_code'    => $res['grpCode'],
                        'relation'    => 'visitors',
                        'relation_id' => $res['id'],
                        'type_id'     => Sys_options::select('id')->where('op_key', 'log-visiter-exit')->first()->id,
                        'description' => json_encode(array(
                            'desc' => $res['allEntities']['name'].' tesisten çıkış yaptı',
                        ),JSON_UNESCAPED_UNICODE)
                    ]);
                }

                if($res['id'] > 0 && isset(array_values($data['dynamicF'])[0]['entities']['inventory-taken'])){
                    User_logs::create([
                        'user_id'     => 0,
                        'grp_code'    => $res['grpCode'],
                        'relation'    => 'visitors',
                        'relation_id' => $res['id'],
                        'type_id'     => Sys_options::select('id')->where('op_key', 'log-visiter-enter')->first()->id,
                        'description' => json_encode(array(
                            'desc' => $res['allEntities']['name'].' tesise giriş yaptı',
                        ),JSON_UNESCAPED_UNICODE)
                    ]);
                }

                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
			    break;
        }

        

        return response()->json($response);
    }
}