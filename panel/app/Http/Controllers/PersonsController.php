<?php

namespace App\Http\Controllers;

use App\Providers\PersonsServiceProvider;
use App\Models\UserLog;
use App\Models\Sys_options;
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
                $res = (new PersonsServiceProvider())->getPerson($request->id,null,true);
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
                
                //here check user keys
                if(
                    !checkPerm('per-04-02') && 
                    (
                        $request->has('user_password') || 
                        $request->has('user_username') || 
                        $request->has('permissions')
                    )
                ) return response()->json([
                    'success' => false,
                    'msg'     => 'not valid for system user...',
                ],401);



                $res = (new PersonsServiceProvider())->setPerson(0,json_decode($req['data'],true),$request->files->all(),'persons');
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                $data = parsePut();
                //here check user keys
                if(
                    !checkPerm('per-04-02') && 
                    (
                        isset($data['user_password']) || 
                        isset($data['user_username']) || 
                        isset($data['permissions'])
                    )
                ) return response()->json([
                    'success' => false,
                    'msg'     => 'not valid for system user...',
                ],401);
                
                $res = (new PersonsServiceProvider())->setPerson($request->id,json_decode($data['data'] ?? '{}',true),$_FILES,'persons',$data);

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

    public function uindex(Request $request){
        if(strtoupper($request->method()) != "GET" && !checkPerm('per-04-02')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);

        return $this->index($request);
    }

    public function changeBackground(Request $request){
        $res = (new PersonsServiceProvider())->setPerson(auth('sanctum')->user()->person_id,[],$request->files->all(),'persons');

        return response()->json([
            'success' => $res['id'] > 0,
            'data' => $res,
        ]);
    }

    public function rolesTemplate(Request $request, $id = null){

        if(!\checkPerm('per-04-03')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);


        $personService = new PersonsServiceProvider();

        if ($request->isMethod('get')) {
            $data = $personService->getRoleTemplates();
            return response()->json(['success' => true, 'data' => $data]);
        }

        if ($request->isMethod('post')) {
            $roles = $request->input('roles');
            if (is_string($roles)) {
                $decoded = json_decode($roles, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $roles = $decoded;
                }
            }

            if (!is_array($roles)) {
                return response()->json(['success' => false, 'message' => 'Invalid payload, expected roles array'], 422);
            }

            foreach ($roles as $item) {
                if (!is_array($item) || empty($item['name']) || !isset($item['permissions']) || !is_array($item['permissions'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid role structure'], 422);
                }else{
                    //here for role update appended permissions to users with that role
                    $personService->updateUserPermissions($item['id'],$item['permissions']);
                }
            }

            $saved = $personService->saveRoleTemplates($roles);
            if (!$saved) {
                return response()->json(['success' => false, 'message' => 'Unable to save roles'], 500);
            }

            $userId = auth('sanctum')->user()->id ?? auth()->id() ?? 0;
            $sysCode = $GLOBALS['SYS_CODE'] ?? 'unknown';
            $typeId = Sys_options::where('op_key', 'log-role-update')->first()->id ?? 0;
            
            UserLog::create([
                'user_id' => $userId,
                'sys_code' => $sysCode,
                'relation' => 'users',
                'relation_id' => $userId,
                'type_id' => $typeId,
                'description' => json_encode([ 'desc' => 'Rol şablonları güncellendi', 'payload' => $roles ], JSON_UNESCAPED_UNICODE),
            ]);




            return response()->json(['success' => true, 'data' => $roles]);
        }

        if ($request->isMethod('delete')) {
            if ($id === null) {
                return response()->json(['success' => false, 'message' => 'Role ID required'], 422);
            }

            $deleted = $personService->deleteRoleTemplate($id);
            if ($deleted === null) {
                return response()->json(['success' => false, 'message' => 'Rol bulunamadı veya silinemedi'], 404);
            }

            $userId = auth('sanctum')->user()->id ?? auth()->id() ?? 0;
            $sysCode = $GLOBALS['SYS_CODE'] ?? 'unknown';
            $typeId = Sys_options::where('op_key', 'log-role-update')->first()->id ?? 0;
            UserLog::create([
                'user_id' => $userId,
                'sys_code' => $sysCode,
                'relation' => 'users',
                'relation_id' => $userId,
                'type_id' => $typeId,
                'description' => json_encode([ 'desc' => 'Rol şablonu silindi', 'deleted_role_id' => $id, 'deleted_role' => $deleted ], JSON_UNESCAPED_UNICODE),
            ]);

            return response()->json(['success' => true, 'data' => $deleted]);
        }

        return response()->json(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    /**
     * this function is for resetting user credentials password , this will be used by admin in case of user forgot his/her credentials or for security reasons
     */
    public function resetUserCradentals(Request $request, $id){
        if(strtoupper($request->method()) != "GET" && !checkPerm('per-04-02')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);

        $data = [
            'user_password' => bin2hex(random_bytes(6)),
            'user_needs_refresh' => '1'
        ];

        $res = (new PersonsServiceProvider())->setPerson($id,$data,[],'persons',$data);
        
        $response = [
            'success' => $res['id'] > 0,
            'data' => $res,
        ];
        
        if($response['success'] && $res['user']->email){
            (new PersonsServiceProvider())->sendresetMail($res['user']['email'], $data['user_password']);
        }

        return response()->json($response);
    }

}