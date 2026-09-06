<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Providers\ReportServiceProvider;
use App\Models\NotificationLog;
use App\Models\NotificationRead;
use App\Models\UserLog;
use App\Services\MailService;
use App\Services\PermissionService;
use App\Services\SmsService;
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


        $permissionService = new PermissionService();
        $authUser = auth()->user();

        //here make little permission check for data listing request
        switch($model){
            case 'user':
                if(!$permissionService->has($authUser, 'per-04-01') || !$permissionService->has($authUser, 'per-04') ) return json_encode(['message' => 'Unauthorized'], 403);
            break;
            case 'document_files':
                if(!$permissionService->has($authUser, 'per-07-01') && !$permissionService->has($authUser, 'per-07') ) return json_encode(['message' => 'Unauthorized'], 403);
            break;
                
        }
        
        $model = ucfirst($model);
        if($model == 'Userlog') $model = 'UserLog';
        if($model == 'Notificationlog') $model = 'NotificationLog';


        $model = 'App\\Models\\'.ucfirst($model);
		$response = $model::tableList(json_decode($req['tableReq'],true));
        return json_encode($response, true);
    }

    public function retriggerNotification($id)
    {
        $log = NotificationLog::find($id);
        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Notification log not found.'], 404);
        }

        try {
            if ($log->type === 'email') {
                $result = (new MailService())->retryNotificationLog($log);
            } elseif ($log->type === 'sms') {
                $result = (new SmsService())->retryNotificationLog($log);
            } else {
                return response()->json(['success' => false, 'message' => 'Unsupported notification type.'], 400);
            }

            return response()->json(array_merge(['success' => !empty($result['success'])], $result));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getNotifications(){
        $response = ['blink' => 0, 'unreadTotal' => 0];
        $provider = new ReportServiceProvider();
        $limit = 10; // show only 10 unread per category

        // ── TEDARIK 7 ── each checks getNotificationUsers membership (op-doc-user-notification-form)
        $r = $provider->getAdminNotifications('tedarik-01', $limit);
        $response['orderImported'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-02', $limit);
        $response['orderSent'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-03', $limit);
        $response['pendingFiles'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-04', $limit);
        $response['fileApproved'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-05', $limit);
        $response['fileRejected'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-06', $limit);
        $response['orderApproved'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('tedarik-07', $limit);
        $response['orderRejected'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        return $response;

    }

    public function markNotificationRead(\Illuminate\Http\Request $request){
        $userId = auth()->id();
        if(!$userId) return response()->json(['success'=>false,'msg'=>'Unauthorized'], 401);

        $opKey = $request->input('op_key') ?? $request->get('op_key') ?? ($request->all()['op_key'] ?? null);
        $targetQnid = $request->input('target_qnid') ?? $request->get('target_qnid') ?? ($request->all()['target_qnid'] ?? null);

        if(empty($opKey) || empty($targetQnid)){
            return response()->json(['success'=>false,'msg'=>'op_key and target_qnid required','received'=>$request->all()], 422);
        }

        \App\Models\NotificationRead::updateOrCreate(
            ['user_id'=>$userId, 'op_key'=>$opKey, 'target_qnid'=>$targetQnid],
            ['read_at'=>now()]
        );

        return response()->json(['success'=>true]);
    }

    public function markAllNotificationsRead(){
        $userId = auth()->id();
        if(!$userId) return response()->json(['success'=>false,'msg'=>'Unauthorized'], 401);

        $opKeys = ['tedarik-01','tedarik-02','tedarik-03','tedarik-04','tedarik-05','tedarik-06','tedarik-07'];
        foreach($opKeys as $opKey){
            $provider = new ReportServiceProvider();
            $result = $provider->getAdminNotifications($opKey);
            $items = is_array($result) ? ($result['data'] ?? []) : $result;
            foreach($items as $item){
                $qnid = $item->qnid ?? $item->id ?? null;
                if($qnid){
                    \App\Models\NotificationRead::updateOrCreate(
                        ['user_id'=>$userId, 'op_key'=>$opKey, 'target_qnid'=>$qnid],
                        ['read_at'=>now()]
                    );
                }
            }
        }

        return response()->json(['success'=>true]);
    }
}