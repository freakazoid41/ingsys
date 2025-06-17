<?php

namespace App\Http\Controllers;

use App\Providers\PersonsServiceProvider;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;


class PersonsController extends Controller
{
    public function index(Request $request){
        $logModel = 'persons';
        
        /*if(!checkPermRoute($logModel,$request->method())) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);*/
        
        
        //$model = 'App\\Models\\Documents';
        switch(strtoupper($request->method())){
            case "GET":
                //$req = $request->all();
                $res = (new PersonsServiceProvider())->getPerson($request->id);
                $response = [
                    'success' => !empty($res),
                    'data' => $res['person'][0] ?? [],
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
                
                $res = (new PersonsServiceProvider())->setPerson(0,json_decode($req['data'],true),$request->files->all(),'persons');
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                $data = parsePut();
                
                $res = (new PersonsServiceProvider())->setPerson($request->id,json_decode($data['data'],true),$_FILES,'persons');

                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
			    break;
			case "DELETE":
                $res =  (new PersonsServiceProvider())->removeContent($request->id);
                $response = [
                    'success' => $res['success'],
                ];
                break;
        }

        

        return response()->json($response);
	}

    public function changeBackground(Request $request){
        $res = (new PersonsServiceProvider())->setPerson(auth('sanctum')->user()->person_id,[],$request->files->all(),'persons');

        return response()->json([
            'success' => $res['id'] > 0,
            'data' => $res,
        ]);
    }

}