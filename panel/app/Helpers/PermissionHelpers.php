<?php
use App\Providers\EncryptionProvider;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Session;


if(!function_exists('checkPerm')){
    function checkPerm($key){
        $user = auth()->user();
        if(!$user){
            return false;
        }
       
        $permissionService = new PermissionService();
        return $permissionService->has($user, $key);
    }

    function loadUserPermissionsToSession($user){
        return (new PermissionService())->loadPermissionsToSession($user);
    }

    function ensurePermissionSessionFreshness(){
        $user = auth()->user();
        if(!$user){
            return false;
        }

        return (new PermissionService())->ensureSessionFreshness($user);
    }

    function docPermCheck($type,$job){
        $map = [
            'op-doc-request' => [
                'edit' => 'per-05-02',
                'read' => 'per-05-01',
                'status' => 'per-05-02',
            ],
            'op-doc-client' => [
                'edit' => 'per-06-02',
                'read' => 'per-06-01',
                'status' => 'per-06-02',
            ],
            'op-doc-offer' => [
                // per-08 removed (old Teklif) — map to order perms per-05
                'edit'   => 'per-05-02',
                'read'   => 'per-05-01',
                'status' => 'per-05-03',
            ],
            // Order Management System — granular per-05
            'op-doc-order' => [
                'edit' => 'per-05-02',
                'read' => 'per-05-01',
                'status' => 'per-05-03',   // Sevkiyata Gönderme / Onaylama
                'cancel' => 'per-05-04',   // İptal / Parça Sil
                'rename' => 'per-05-05',   // Numara Düzenle
            ],
            'op-doc-order-item' => [
                'edit' => 'per-05-02',
                'read' => 'per-05-01',
                'status' => 'per-05-03',
                'cancel' => 'per-05-04',
                'rename' => 'per-05-05',
            ]
        ];
        
        
        $tags = explode(',',$map[$type][$job] ?? '');
        foreach($tags as $tag){
            if(checkPerm($tag)){
                return true;
            }
        }

        return false;
        
        //return checkPerm($map[$type][$job] ?? null) ?? false;
    }

    /**
     * Resolves the acting person's type. session('type_key') is only populated for stateful
     * (SPA) requests — a plain token-authenticated call starts no session — so fall back to
     * the authenticated principal instead of treating "unknown" as a role.
     */
    function currentPersonTypeKey(){
        $sessionType = session('type_key');
        if(!empty($sessionType)) return $sessionType;

        $user = auth()->user();
        if(!$user || empty($user->person_id)) return null;

        return \Illuminate\Support\Facades\DB::table('persons')
            ->join('sys_options','sys_options.id','=','persons.type_id')
            ->where('persons.id',$user->person_id)
            ->value('op_key');
    }

    /**
     * Suppliers may only touch offers belonging to the companies bound to their session.
     * The reseller-only scoping mirrors Documents::tableList, which applies the client filter
     * for 'op-pert-reseller' sessions only — admins stay limited by their offer permission alone.
     *
     * Fails closed twice over: an undeterminable role is denied (never treated as admin), and
     * a supplier with no bound company is denied.
     */
    function offerOwnershipCheck($qnid){
        $typeKey = currentPersonTypeKey();

        if($typeKey === 'op-pert-admin') return true;
        if($typeKey !== 'op-pert-reseller') return false;

        $clientList = session('currentStatus')['clientQnidList'] ?? [];
        if(empty($clientList)) return false;

        $form     = (new \App\Providers\DocumentServiceProvider())->getFormData($qnid);
        $formRows = $form['formFormat']['op-doc-offer-form'] ?? [];

        //getFormData has no ORDER BY, so cliid may sit on any of the form rows
        $clientQnid = null;
        foreach($formRows as $row){
            if(!empty($row['entities']['cliid'])){
                $clientQnid = $row['entities']['cliid'];
                break;
            }
        }

        return in_array($clientQnid, $clientList, true);
    }

    function refreshAllUserPermissions(){
        // Invalidate permission cache for all users
        // This forces next request to reload permissions
        
        $service = new PermissionService();
        foreach (\Illuminate\Support\Facades\DB::table('users')->whereNotNull('person_id')->pluck('person_id') as $personId) {
            $service->bumpUserPermissionVersion($personId);
        }
        
        // Refresh current user immediately
        if(auth()->user()){
            loadUserPermissionsToSession(auth()->user());
        }
        
        return true;
    }
}



if(!function_exists('preUp')){
    function preUp($str){
        $str = str_replace('i', 'İ', $str);
        $str = str_replace('ı', 'I', $str);
        $str = str_replace('ö', 'Ö', $str);
        $str = str_replace('o', 'O', $str);
        $str = str_replace('ü', 'Ü', $str);
        $str = str_replace('u', 'U', $str);
        /*$str = str_replace('ş', 'S', $str);
        $str = str_replace('Ş', 'S', $str);*/
        return $str;
    }
}



if(!function_exists('noInject')){
    
    function noInject($kelime) {
        $kelime = str_replace("'","''",$kelime);
        $kelime = str_replace("--","_",$kelime);
        $kelime = str_replace("/*"," ",$kelime);
        $kelime = str_replace("*/"," ",$kelime);
        $kelime = str_replace(";"," ",$kelime);
        $kelime = str_replace('"',"''",$kelime);
        $kelime = str_replace(" or "," kor ",$kelime);
        
        $kelime = str_replace(" OR "," KOR ",$kelime);
        
        $kelime = str_replace(" Or "," Kor ",$kelime);
       
        $kelime = str_replace(" drop "," drp ",$kelime);
        $kelime = str_replace(" DROP "," DRP ",$kelime);
        $kelime = str_replace(" Drop "," DRP ",$kelime);
        $kelime = str_replace(" alter "," atr ",$kelime);
        $kelime = str_replace(" ALTER "," atr ",$kelime);
        $kelime = str_replace(" Alter "," atr ",$kelime);

        $kelime = str_replace(" AND "," an-d ",$kelime);
        $kelime = str_replace(" And "," an-d ",$kelime);
        $kelime = str_replace(" and "," an-d ",$kelime);

        $kelime = str_replace(" FROM "," frm ",$kelime);
        $kelime = str_replace(" From "," frm ",$kelime);
        $kelime = str_replace(" from "," frm ",$kelime);

        $kelime = str_replace(" select "," slct ",$kelime);
        $kelime = str_replace(" Select "," slct ",$kelime);
        $kelime = str_replace(" SELECT "," slct ",$kelime);

        $kelime = str_replace(" INSERT "," intt ",$kelime);
        $kelime = str_replace(" UPDATE "," upt ",$kelime);
        $kelime = str_replace(" DELETE "," dlt ",$kelime);

        $kelime = str_replace("HTTP://","url:",$kelime);
        $kelime = str_replace("http://","url:",$kelime);
        $kelime = str_replace("href=","falan=",$kelime);
        return $kelime;
    }
}

if(!function_exists('checkPermRoute')){
    
}

if(!function_exists('hasMailPerm')){
    function hasMailPerm($personId,$key){
        $sql = "select * from document_con_ops uco 
                    inner join sys_options so on so.id = uco.type_id 
                    inner join sys_options so2 on so2.id = uco.conn_id
                    inner join users u on u.id = uco.main_id 
                        where so.op_key = 'op-perm' and so2.op_key = '".$key."' and u.person_id = '".$personId."'";
        $results = \Illuminate\Support\Facades\DB::select($sql);
       
        return [
            'success' => count($results) !== 0
        ]; 
    }
}



?>