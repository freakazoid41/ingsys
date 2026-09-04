<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Providers\ReportServiceProvider;
use App\Models\NotificationLog;
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
        $response = ['blink' => 0];
        $provider = new ReportServiceProvider();
        // here we are getting live notifications for user targeted or general ones
        // here check for new client inserted notifications
        $response['awaitingUsers'] = $provider->getAdminNotifications('notif-00');
        if(!empty($response['awaitingUsers'])) $response['blink'] = 1;

        $response['clientChanges'] = $provider->getAdminNotifications('notif-01');
        if(!empty($response['clientChanges'])) $response['blink'] = 1;

        $response['newOffer'] = $provider->getAdminNotifications('notif-02');
        if(!empty($response['newOffer'])) $response['blink'] = 1;

        $response['offerRevisionRequests'] = $provider->getUserNotifications('offer-revision-request');
        if(!empty($response['offerRevisionRequests'])) $response['blink'] = 1;

        $response['offerChanges'] = $provider->getAdminNotifications('notif-03');
        if(!empty($response['offerChanges'])) $response['blink'] = 1;

        return $response;

    }
}