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

        // Tender/offer tables carry commercially sensitive data. Enforce the
        // document read permission before TableList; Documents::tableList also
        // scopes reseller offers to their own bound clients.
        $tableReq = json_decode((string) ($req['tableReq'] ?? '{}'), true);
        if (! is_array($tableReq)) $tableReq = [];
        $sensitiveDocTypes = ['op-doc-ihale', 'op-doc-ihale-offer', 'op-doc-ihale-offer-item'];
        foreach (($tableReq['filter'] ?? []) as $f) {
            if (($f['key'] ?? '') !== 'type') continue;
            $docType = (string) ($f['value'] ?? '');
            // Check every type filter: TableList uses the last one, so a caller
            // must not be able to hide a tender/offer type behind a permitted one.
            if (in_array($docType, $sensitiveDocTypes, true) && ! docPermCheck($docType, 'read')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

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

    private function notificationCacheKey(): string
    {
        return 'notif:'.(auth()->id() ?? 0).':'.(session('person_id') ?? '0').':'.(session('type_key') ?? '');
    }

    public function getNotifications(){
        $cacheKey = $this->notificationCacheKey();
        $cached = app()->runningUnitTests() ? null : \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

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

        // ── PLANLAMA 3 ──
        $r = $provider->getAdminNotifications('planlama-01', $limit);
        $response['planlamaSent'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('planlama-02', $limit);
        $response['planlamaRejected'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('planlama-03', $limit);
        $response['planlamaApproved'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        // ── TENDER 5 ──
        $r = $provider->getAdminNotifications('ihale-01', $limit);
        $response['tenderActivated'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('ihale-02', $limit);
        $response['tenderCompleted'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('offer-01', $limit);
        $response['offerSubmitted'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('offer-02', $limit);
        $response['offerRejected'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        $r = $provider->getAdminNotifications('offer-03', $limit);
        $response['offerAccepted'] = $r['data'];
        $response['unreadTotal'] += $r['total'];
        if($r['total'] > 0) $response['blink'] = 1;

        // Several dashboard widgets call this endpoint on mount; a short cache
        // keeps the single-thread dev server from re-running the whole feed.
        \Illuminate\Support\Facades\Cache::put($cacheKey, $response, 15);

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
        \Illuminate\Support\Facades\Cache::forget($this->notificationCacheKey());

        return response()->json(['success'=>true]);
    }

    public function markAllNotificationsRead(\Illuminate\Http\Request $request){
        $userId = auth()->id();
        if(!$userId) return response()->json(['success'=>false,'msg'=>'Unauthorized'], 401);

        $allKeys = ['tedarik-01','tedarik-02','tedarik-03','tedarik-04','tedarik-05','tedarik-06','tedarik-07','planlama-01','planlama-02','planlama-03','ihale-01','ihale-02','offer-01','offer-02','offer-03'];
        $requested = $request->input('op_keys');
        $opKeys = (is_array($requested) && !empty($requested))
            ? array_values(array_intersect($allKeys, $requested))
            : $allKeys;
        if (empty($opKeys)) $opKeys = $allKeys;

        $provider = new ReportServiceProvider();
        foreach($opKeys as $opKey){
            // Mark-all needs the FULL unread list per category. Cache each fetch
            // briefly so repeated "tümünü okundu" clicks (and parallel widgets)
            // don't re-run 15 heavy feed queries. Correctness: read rows are
            // idempotent, so a stale cache only re-marks already-read targets.
            $result = ! app()->runningUnitTests()
                ? \Illuminate\Support\Facades\Cache::remember(
                    'notifmark:'.$userId.':'.$opKey,
                    30,
                    fn () => $provider->getAdminNotifications($opKey)
                )
                : $provider->getAdminNotifications($opKey);
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

        \Illuminate\Support\Facades\Cache::forget($this->notificationCacheKey());

        return response()->json(['success'=>true]);
    }
}