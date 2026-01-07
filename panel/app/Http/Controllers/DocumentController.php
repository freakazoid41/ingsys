<?php

namespace App\Http\Controllers;

use App\Providers\ReportServiceProvider;
use App\Providers\DocumentServiceProvider;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;
use Riverline\MultiPartParser\StreamedPart;


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
                error_reporting(E_ALL);
                ini_set('display_errors', 1);

                require 'vendor/autoload.php';


$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    die('Raw body empty – likely consumed early');
}

// Parse the streamed body
$part = StreamedPart::parse(fopen('php://input', 'r'), $_SERVER['CONTENT_TYPE'] ?? '');

$fields = [];
$files = [];

foreach ($part->getParts() as $subPart) {
    $name = $subPart->getFieldName();
    if ($subPart->isFile()) {
        $filename = $subPart->getFileName();
        $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpPath, $subPart->getBody());
        $files[$name] = [
            'name' => $filename,
            'tmp_name' => $tmpPath,
            'size' => $subPart->getSize(),
            'error' => UPLOAD_ERR_OK,
        ];
    } else {
        $fields[$name] = $subPart->getBody();
    }
}

print_r($fields);
print_r($files);
                

                die;
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

    public function preparePayment(Request $request){
        return [
            'success' => true,
            'data' => (new DocumentServiceProvider())->paymentInfo(),
        ];
    }

    public function setPayment(Request $request){
        $validateUser = Validator::make($request->all(),[
            'amount'     => 'required',
            'currency'   => 'required',
            'note'       => 'required',
            'period'     => 'required',
        ]);

        if(session('type_key') != 'op-pert-admin' && strtoupper($request->method()) != 'GET') return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return (new DocumentServiceProvider())->setPayment($request->all(),$request->trans_file);
        }

        
    }

    public function setStatus(Request $request){
        $validateUser = Validator::make($request->all(),[
            'id'       => 'required',
            'op_key'   => 'required',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return (new DocumentServiceProvider())->setStatus($request->id,$request->op_key,$request->note);
        }
    }

    public function getAparments(){
        return (new ReportServiceProvider())->getAparments();
    }

    public function setAparments(Request $request){
        $validateUser = Validator::make($request->all(),[
            'title'     => 'required',
        ]);

        if(session('type_key') != 'op-pert-admin' ) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],403);

        if($validateUser->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Missing Parameters',
                'error'   => $validateUser->errors()
            ],401);
        }else{
            return (new DocumentServiceProvider())->setAparments($request->all());
        }

        
    }

}