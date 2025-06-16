<?php

namespace App\Http\Controllers;

/*use App\Models\Sys_options;
use App\Models\User_logs;
use App\Models\Document_con_ops;
use App\Models\Document_files;
use App\Models\Sys_con_entities;
use App\Providers\SmsProvider;
use App\Providers\SystemSettingsService;
use App\Providers\MailService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Upload;
use Illuminate\Support\Facades\Validator;*/
use Illuminate\Http\Request;

class SystemController extends Controller
{
    
    /***
	 * this method will contains table operations model
	 */
    public function table($model,Request $request){
        $req = $request->all();


        if(isset($req['page']))     $req['scale']['page']  = $req['page'];
        if(isset($req['size']))     $req['scale']['limit'] = $req['size'];
        if(isset($req['sort'])){    $req['order']['style'] = $req['sort'][0]['dir'];
                                    $req['order']['key']   = $req['sort'][0]['field'];
                                }
       
        if(!isset($req['tableReq'])) $req['tableReq']       = json_encode($req);


        $model = 'App\\Models\\'.ucfirst($model);
		
        $response = $model::tableList(json_decode($req['tableReq'],true));
        return json_encode($response, true);
    }


}