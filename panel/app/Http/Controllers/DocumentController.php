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
                $raw = file_get_contents('php://input');  // Do this FIRST if stream is still available

if (strlen($raw) === 0) {
    die('Raw body empty — something consumed it early');
}

// Extract boundary from header
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
preg_match('/boundary=(.*)$/', $contentType, $matches);
if (empty($matches[1])) {
    die('Missing boundary');
}
$boundary = '--' . $matches[1];

// Simple multipart parser (expand for full needs)
$parts = explode($boundary, $raw);
// Skip first and last empty/close parts
array_shift($parts);
array_pop($parts);

$fields = [];
$files = [];
foreach ($parts as $part) {
    if (trim($part) === '--') break;
    // Split headers and body
    [$headers, $body] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
    // Parse Content-Disposition
    preg_match('/name="([^"]+)"/', $headers, $nameMatch);
    $name = $nameMatch[1] ?? null;
    if (preg_match('/filename="([^"]+)"/', $headers, $fileMatch)) {
        // File part — save to temp
        $filename = $fileMatch[1];
        $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tmpPath, $body);
        $files[$name] = [
            'name' => $filename,
            'tmp_name' => $tmpPath,
            'size' => strlen($body),
            'error' => UPLOAD_ERR_OK,
        ];
    } else {
        // Text field
        $fields[$name] = trim($body);
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