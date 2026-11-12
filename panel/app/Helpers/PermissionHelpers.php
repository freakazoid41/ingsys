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
            // New system: only clients + orders (requests/offers are legacy Komur — removed)
            'op-doc-client' => [
                'edit' => 'per-06-02',
                'read' => 'per-06-01',
                'status' => 'per-06-02',
            ],
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
            ],
            // Malzeme Planlama requests: edit = İhtiyaç Oluşturma, read = all planlama roles (view list/detail), status unused
            'op-doc-request' => [
                'edit' => 'per-041-05-02',
                'read' => 'per-041-05-02,per-041-05-03,per-041-05-01,per-041-05-04',
                'status' => 'per-041-05-01',
            ],
            'op-doc-request-item' => [
                'edit' => 'per-041-05-02',
                'read' => 'per-041-05-02,per-041-05-03,per-041-05-01,per-041-05-04',
                'status' => 'per-041-05-01',
            ],
            // Talep Dökümanı (supplier answer): upload = Talep Yükleme, status = Talep Onay
            // read is shared by loader (05-03), approver (05-01) and standardizer (05-04); edit stays loader-only
            'op-doc-requestf' => [
                'edit' => 'per-041-05-03',
                'read' => 'per-041-05-03,per-041-05-01,per-041-05-04',
                'status' => 'per-041-05-01',
            ],
            'op-doc-requestf-item' => [
                'edit' => 'per-041-05-03',
                'read' => 'per-041-05-03,per-041-05-01,per-041-05-04',
                'status' => 'per-041-05-01',
            ],
            // Product categories: view = Kategori Listeleme, edit = Kategori Oluşturma/Düzenleme
            'op-doc-product-category' => [
                'edit' => 'per-09-03',
                'read' => 'per-09-01',
                'status' => 'per-09-03',
            ],
            // Ürün Oluşturma / Düzenleme
            'op-doc-product' => [
                'edit' => 'per-09-02',
                'read' => 'per-09-01',
                'status' => 'per-09-02',
            ],
            // İhale: management = İhale Oluşturma / Yönetme (create/edit/complete/review),
            // read is also open to Teklif Oluşturma holders (suppliers must see the tender).
            'op-doc-ihale' => [
                'edit' => 'per-041-03',
                'read' => 'per-041-03,per-041-03-01',
                'status' => 'per-041-03',
            ],
            // Teklif: create/edit = Teklif Oluşturma; management can read/act on offers.
            'op-doc-ihale-offer' => [
                'edit' => 'per-041-03-01',
                'read' => 'per-041-03,per-041-03-01',
                'status' => 'per-041-03-01',
            ],
            'op-doc-ihale-offer-item' => [
                'edit' => 'per-041-03-01',
                'read' => 'per-041-03,per-041-03-01',
                'status' => 'per-041-03-01',
            ],
            // kVA power definitions (system settings list, picked multi-select in file defs)
            'op-doc-kva' => [
                'edit' => 'per-09-02',
                'read' => 'per-09-01',
                'status' => 'per-09-02',
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
     * Bound client qnids for the authenticated supplier.
     *
     * SPA sessions carry currentStatus.clientQnidList. Pure Bearer-token requests
     * have no session, so fall back to the person's cliid entities. Fails closed
     * with an empty array when the role is unknown or no client is bound.
     */
    function currentSupplierQnidList(){
        $list = session('currentStatus')['clientQnidList'] ?? [];
        if (is_array($list)) {
            $list = array_values(array_filter(array_map('strval', $list)));
            if (!empty($list)) return $list;
        }

        $u = auth('sanctum')->user() ?? auth()->user();
        if (! $u || empty($u->person_id)) return [];

        $formId = \App\Models\Sys_options::where('op_key', 'op-doc-user-client-form')->value('id');
        if (! $formId) return [];

        return \Illuminate\Support\Facades\DB::table('sys_con_entities as se')
            ->join('sys_con_ops as so', 'so.id', '=', 'se.conn_id')
            ->where('so.main_id', $u->person_id)
            ->where('so.type_id', $formId)
            ->where('so.conn_id', 0)
            ->where('se.entity_tag', 'like', 'cliid%')
            ->pluck('se.entity_value')
            ->filter()
            ->values()
            ->all();
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