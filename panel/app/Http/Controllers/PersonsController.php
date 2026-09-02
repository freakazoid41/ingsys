<?php

namespace App\Http\Controllers;
use App\Providers\EmailServiceProvider;
use App\Providers\PersonsServiceProvider;
use App\Models\UserLog;
use App\Models\Sys_options;
use App\Services\PermissionService;
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
        
        $personsService = (new PersonsServiceProvider());
        //$model = 'App\\Models\\Documents';
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();

        switch(strtoupper($request->method())){
            case "GET":
                //$req = $request->all();
                $res = $personsService->getPerson($request->id,null,true);
                $response = [
                    'success' => !empty($res),
                    'data' => $res['person'][0] ?? [],
                ];
                break;
            case "POST":
                $req = $request->all();
                
                //here check user keys
                if(
                    !$permissionService->has($authUser, 'per-04-02') && 
                    (
                        $request->has('user_password') || 
                        $request->has('user_username') || 
                        $request->has('permissions')
                    )
                ) return response()->json([
                    'success' => false,
                    'msg'     => 'not valid for system user...',
                ],401);

                $res = $personsService->setPerson(0,json_decode($req['data'],true),$request->files->all(),'persons');
                
                $response = [
                    'success' => $res['id'] > 0,
                    'data' => $res,
                ];
                break;
            case "PUT":
                $data = parsePut();
                //here check user keys
                if(
                    !$permissionService->has($authUser, 'per-04-02') && 
                    (
                        isset($data['user_password']) || 
                        isset($data['user_username']) || 
                        isset($data['permissions'])
                    )
                ){
                    //user may trying to update user information without permission for himself (they must) check
                    
                    if($request->id != session('person_id')){
                        return response()->json([
                            'success' => false,
                            'msg'     => 'not valid for system user...',
                        ],401);
                    }else{
                        //they still cannot update some informations like permissions , roles ,types ,statusses
                        unset($data['permissions']);
                        unset($data['user_role']);
                        unset($data['user_status']);
                        unset($data['status']);
                        unset($data['main_status']);
                        unset($data['type_key']);
                    }
                    
                } 
                
                $decodedData = json_decode($data['data'] ?? '{}',true);
                $res = $personsService->setPerson($request->id,$decodedData,$_FILES,'persons',$data);
                
                //here check if person is activating and type of that person is client add client info for it and send activated mails
                if($res['success'] && $res['type'] == 'op-pert-reseller' && $decodedData['user_status'] == '1'){
                    //here also check if have client information before adding new client information for person
                    $clientInfo = $personsService->clientPermInfo($res['data']->qnid,'op-pert-reseller');
                    //did come from login page register form
                    if(empty($clientInfo['clientQnidList'])){
                        $user   = $res['user'];
                        $person = $res['data']; 
                        //here add client information and update our user client informations also send approved mail
                        $personsService->setClientToPerson($person,$user);
                    }
                    //else created by client user request form
                }

                //here send user activating mail
                if($res['success'] && $decodedData['user_status'] == '1'){
                    (new EmailServiceProvider())->sendapproveMails($res['user']->email);
                }

                $response = [
                    'success' => isset($res['id']) && intval($res['id']) > 0,
                    'data' => $res,
                ];
			    break;
			case "DELETE":
                $res =  $personsService->removeContent($request->id);
                $response = [
                    'success' => $res['success'],
                ];
                break;
        }

        

        return response()->json($response);
	}

    public function uindex(Request $request){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();

        if(strtoupper($request->method()) != "GET" && !$permissionService->has($authUser, 'per-04-02')){
            //user may trying to update its own informations
            if(strtoupper($request->method()) != "PUT") return response()->json([
                'success' => false,
                'msg'     => 'not valid for system user...',
            ],401);
        } 

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
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();

        if(!$permissionService->has($authUser, 'per-04-03')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);


        $personService = new PersonsServiceProvider();

        if ($request->isMethod('get')) {
            $data = $personService->roleTemplateTrans('get');
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

            // helper: normalize a name to op_key-like string (same as frontend generator)
            $normalizeOpKey = function ($name) {
                return strtolower(preg_replace('/[^a-z0-9\-çğıöşüâêîôûüığ]/i', '', preg_replace('/\s+/', '-', $name)));
            };

            // Normalize and validate incoming roles, build change list vs existing templates
            $existing = $personService->roleTemplateTrans('get');
            $existingMapById = [];
            $existingMapByOp = [];
            foreach ($existing as $er) {
                if (isset($er['id'])) $existingMapById[$er['id']] = $er;
                if (!empty($er['op_key'])) $existingMapByOp[$er['op_key']] = $er;
                if (empty($er['op_key']) && !empty($er['name'])) {
                    $k = $normalizeOpKey($er['name']);
                    $existingMapByOp[$k] = $er;
                }
            }

            $changes = [];
            foreach ($roles as $item) {
                if (!is_array($item) || empty($item['name']) || !isset($item['permissions']) || !is_array($item['permissions'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid role structure'], 422);
                }

                // find matching existing role by id, then op_key, then name
                $before = null;
                if (isset($item['id']) && isset($existingMapById[$item['id']])) {
                    $before = $existingMapById[$item['id']];
                } elseif (!empty($item['op_key']) && isset($existingMapByOp[$item['op_key']])) {
                    $before = $existingMapByOp[$item['op_key']];
                } else {
                    // try normalize name
                    $nk = $normalizeOpKey($item['name']);
                    if (isset($existingMapByOp[$nk])) $before = $existingMapByOp[$nk];
                }

                // canonicalize permissions for comparison
                $newPerms = array_values($item['permissions']);
                sort($newPerms);

                $isChanged = false;
                if ($before === null) {
                    $isChanged = true;
                    $changes[] = ['action' => 'added', 'before' => null, 'after' => $item];
                } else {
                    $oldPerms = isset($before['permissions']) && is_array($before['permissions']) ? $before['permissions'] : [];
                    $oldPerms = array_values($oldPerms);
                    sort($oldPerms);
                    // compare permissions or other fields (name, description, op_key)
                    if ($oldPerms !== $newPerms
                        || (isset($before['name']) && $before['name'] !== $item['name'])
                        || (isset($before['description']) && ($before['description'] ?? '') !== ($item['description'] ?? ''))
                        || (isset($before['op_key']) && ($before['op_key'] ?? '') !== ($item['op_key'] ?? ''))
                    ) {
                        $isChanged = true;
                        $changes[] = ['action' => 'updated', 'before' => $before, 'after' => $item];
                    }
                }

                // apply permission propagation only for updated roles where op_key is present (or fallback to id)
                if ($isChanged) {
                    $roleIdentifier = $item['op_key'] ?? $item['id'] ?? null;
                    if ($roleIdentifier !== null) {
                        $personService->updateUserPermissions($roleIdentifier, $item['permissions']);
                    }
                }
            }
           
            $saved = $personService->roleTemplateTrans('save', null, $roles);
            if (!$saved) {
                return response()->json(['success' => false, 'message' => 'Unable to save roles'], 500);
            }

            $userId = auth('sanctum')->user()->id ?? auth()->id() ?? 0;
            $sysCode = $GLOBALS['SYS_CODE'] ?? 'GDZ';
            $typeId = Sys_options::where('op_key', 'log-role-update')->first()->id ?? 0;
            
            $payloadToLog = !empty($changes) ? $changes : [];
            UserLog::create([
                'user_id' => $userId,
                'sys_code' => $sysCode,
                'relation' => 'users',
                'relation_id' => $userId,
                'type_id' => $typeId,
                'description' => json_encode([ 'desc' => 'Rol şablonları güncellendi', 'payload' => $payloadToLog ], JSON_UNESCAPED_UNICODE),
            ]);




            return response()->json(['success' => true, 'data' => $roles]);
        }

        if ($request->isMethod('delete')) {
            if ($id === null) {
                return response()->json(['success' => false, 'message' => 'Role ID required'], 422);
            }

            $deleted = $personService->roleTemplateTrans('delete', $id);
            if ($deleted === null) {
                return response()->json(['success' => false, 'message' => 'Rol bulunamadı veya silinemedi'], 404);
            }

            $userId = auth('sanctum')->user()->id ?? auth()->id() ?? 0;
            $sysCode = $GLOBALS['SYS_CODE'] ?? 'GDZ';
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

    public function rolesItems(Request $request, $id = null){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        if(!$permissionService->has($authUser, 'per-04-03')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);

        $service = new \App\Services\RoleTemplateService();
        $data = $service->getPermissionCatalogs();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function notificationGroups(Request $request){
        $service = new \App\Services\RoleTemplateService();
        $data = $service->getNotificationTypes();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function saveNotificationGroups(Request $request){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        if (!$permissionService->has($authUser, 'per-00-01')) {
            return response()->json([
                'success' => false,
                'msg' => 'not valid for system user...',
            ], 401);
        }

        $assigned = $request->input('assigned');
        $assignedData = is_string($assigned) ? json_decode($assigned, true) : $assigned;
        
        //here we will update users notification connections according to received data
        $res = (new PersonsServiceProvider())->updateUserNotificationGroups($assignedData);

        $userId = auth('sanctum')->user()->id ?? auth()->id() ?? 0;
        $sysCode = $GLOBALS['SYS_CODE'] ?? 'GDZ';
        $typeId = Sys_options::where('op_key', 'log-notification-group-update')->first()->id ?? 0;
        
        UserLog::create([
            'user_id' => $userId,
            'sys_code' => $sysCode,
            'relation' => 'users',
            'relation_id' => $userId,
            'type_id' => $typeId,
            'description' => json_encode([ 'desc' => 'Notifikasyon şablonları güncellendi', 'payload' => $assignedData ], JSON_UNESCAPED_UNICODE),
        ]);

        if (!is_array($assignedData)) {
            return response()->json([
                'success' => $res['success'] ?? false,
                'msg' => 'Invalid payload',
            ], 422);
        }

        // TODO: implement persistence logic for notification group assignments.
        return response()->json([
            'success' => true,
            'data' => $assignedData,
        ]);
    }

    public function getNotificationUsers(){
        try {
            $data = (new PersonsServiceProvider())->getNotificationUsers();
            return response()->json(['success' => true, 'data' => (is_array($data) ? $data : [])]);
        } catch (\Throwable $e) {
            \Log::error('PersonsController::getNotificationUsers error', [
                'exception' => $e,
            ]);
            return response()->json(['success' => false, 'data' => []], 500);
        }
    }

    /**
     * this function is for resetting user credentials password , this will be used by admin in case of user forgot his/her credentials or for security reasons
     */
    public function resetUserCradentals(Request $request, $id){
        $permissionService = new PermissionService();
        $authUser = auth('sanctum')->user() ?? auth()->user();
        if(strtoupper($request->method()) != "GET" && !$permissionService->has($authUser, 'per-04-02')) return response()->json([
            'success' => false,
            'msg'     => 'not valid for system user...',
        ],401);

        $data = [
            'user_password' => bin2hex(random_bytes(8)),
            'user_needs_refresh' => '1'
        ];

        $res = (new PersonsServiceProvider())->setPerson($id,$data,[],'persons',$data);
        
        $response = [
            'success' => $res['id'] > 0,
            'data' => $res,
        ];
        
        if($response['success'] && $res['user']->email){
            (new PermissionService())->forceLogoutPerson($res['id'], 'Şifreniz Sıfırlandı. Lütfen Yeniden Giriş Yapınız.');
            //here add user log for new password request
            UserLog::create([
                'user_id'     => auth()->check() ? auth()->user()->id : 0,
                'sys_code'    => $GLOBALS['SYS_CODE'] ?? 0,
                'relation'    => 'users',
                'relation_id' => auth()->check() ? auth()->user()->id : 0,
                'type_id'     => Sys_options::where('op_key', 'log-user-status-update')->value('id') ?? 0,
                'description' => json_encode(array(
                    'desc' => (session('ptitle') ?? '-').' Kullanıcısı , '. $res['user']->email .' için yeni şifre talebinde bulundu.',
                ),JSON_UNESCAPED_UNICODE)
            ]);


            (new EmailServiceProvider())->sendresetMail($res['user']['email'], $data['user_password']);
        }

        return response()->json($response);
    }
}
