<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Documents extends Model
{
    use HasFactory;
    public static $rules = [
        'person_id',
        'type_id',
        'hk_type_id',
        'hkc_type_id',
        'grp_code',
        'starting_at',
        'ending_at',
        'title',
    ];

    protected $fillable = [
        'status',
        'person_id',
        'parent_type_id',
        'parent_id',
        'type_id',
        'grp_code',
        'starting_at',
        'ending_at',
        'title',
        'qnid'
    ];

    protected static function boot() {
        parent::boot();

        static::creating(function ($post) {
            $post->qnid = (string) Str::uuid();
            if(!isset($post->grp_code))$post->grp_code = $GLOBALS['SYS_CODE'] ?? 'GDZ';
            // add other column as well
        });

    }

    static function tableList($obj){
        //her edetect document type for fowarding its coming with filters also regroup and clean filters
        $obj['filterKeys'] = [];
        $formType = null;
        $withCancelled = false;
        foreach($obj['filter'] as $f){
            $obj['filterKeys'][$f['key']] = noInject(strip_tags($f['value']));

            if($f['key'] == 'type' && !empty($f['value'])) {
                $formType = $f['value'];
            }

            //opt-in flag, see the status predicate below
            if($f['key'] == 'with-cancelled' && $f['value'] == '1') {
                $withCancelled = true;
            }
        }

        if($formType == null){
            return array(
                'data'          => [],
                'pageCount'     => 0,
                'totalCount'    => 0,
                'filteredCount' => 0,
                'last_page'     => 0,
            );
        }



        $columns = array(
            'main_id'      => 'i.id  as  main_id',
            'id'           => 'i.qnid  as  id',
            'type'         => 'sp.op_key  as  type',
            'created_at'   => 'i.created_at  as  created_at',
            'status'       => "(select  so.op_key || '**' || so.title || '**' || t.note
                                from transactions as t
                                    inner join sys_options so on so.id = t.type_id
                                where target_id = i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1)  as  status",
            //document activeness (documents.status) is a separate axis from the transaction status above.
            //for offers 0 means "cancelled", for other document types it keeps its "passive" meaning.
            'document_status' => 'i.status  as  document_status',
            'main_attr'    => 'i.id  as  main_attr', // default main_attr column for search and filter, this will be changed with filters if needed
            'addional'     => "'-'  as  addional" // this is for showing some additional info if needed like person name or something else, this will be changed with filters if needed
                        
        );
        
        $limit = '';
        $order = '';
        $join = '   inner join sys_options as sp on sp.id = i.type_id 
                    inner join sys_con_ops as so on i.id = so.main_id 
                    inner join sys_con_entities as se on so.id = se.conn_id ';
        
       
        //i.parent_type_id for free documents, if parent_type_id is 0, it means this document is not connected to any other table so it is a main document. if parent_type_id is not 0, it means this document is connected to another table like users or persons, so it is a sub document. we only want to list main documents in the table list, so we will add this condition to the where clause.
        //cancelled offers (documents.status = 0) are revealed only when the caller explicitly opts in, so
        //dashboards, notification badges and every other document type keep their active-only behaviour.
        $statusPredicate = ($withCancelled && $formType === 'op-doc-offer') ? "i.status in ('0','1')" : "i.status = '1'";
        $where = " where ".$statusPredicate." and i.parent_type_id = 0";
       

       
        //here we must limit things for some users like client filtered users (can be clients or some unique office workers)
        //only listing limit is here for client selections for user so either its client card and we will look their qnid or on some attribue it have cliId section and we will lokk there
        if(session('currentStatus') != null && !empty(session('currentStatus')['clientQnidList'])) {
            //means user is limited with clients so we will add that condition to where clause
            //this is custom filters for client accounts
            if(session('type_key') == 'op-pert-reseller') {
                switch($formType){
                    case 'op-doc-request':
                        $where .= " and (select  so.op_key 
                                from transactions as t
                                    inner join sys_options so on so.id = t.type_id
                                where target_id = i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) in ('doc_trans_request_start','doc_trans_request_end')";
                    break;
                    case 'op-doc-offer':
                        //$where .= " and i.person_id = '".session('person_id')."'";
                        //check for clients offers
                        $where .= " and (
                                        se.entity_value in ('".implode("','",session('currentStatus')['clientQnidList'])."') 
                                        and se.entity_tag like '%cliid%'
                                        and se.table_tag = 'sys_con_ops'
                                    ) ";
                        

                    break;
                    case 'op-doc-order':
                    case 'op-doc-order-item':
                    case 'op-doc-order-serial':
                        // INGSYS Tedarik: reseller sees only orders where spec_code (lifnr) matches one of his bound clients
                        // clientQnidList holds qnids → resolve to lifnrs via client docs
                        $resellerQnids = array_values(array_unique(session('currentStatus')['clientQnidList']));
                        $qnidIn = "'".implode("','", array_map('noInject', $resellerQnids))."'";
                        $lifRows = DB::select("SELECT se.entity_value as lifnr FROM sys_con_entities se INNER JOIN sys_con_ops so ON so.id = se.conn_id INNER JOIN documents d2 ON d2.id = so.main_id WHERE d2.qnid IN ($qnidIn) AND se.entity_tag = 'lifnr' AND se.table_tag = 'sys_con_ops'");
                        $lifnrs = array_values(array_filter(array_map(fn($r)=> trim($r->lifnr ?? ''), $lifRows), fn($v)=> $v !== ''));
                        if(empty($lifnrs)){
                            // no lifnr resolved → force empty result (fails closed)
                            $where .= " and 1=0 ";
                        } else {
                            $lifIn = "'".implode("','", array_map('noInject', $lifnrs))."'";
                            // spec_code on order holds lifnr string (keep leading zeros)
                            $where .= " and (
                                exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='spec_code' and se2.entity_value in ($lifIn))
                                or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='lifnr' and se2.entity_value in ($lifIn))
                                or i.qnid in ($qnidIn)
                            ) ";
                        }
                    break;
                    case 'op-doc-client':
                        $where .= " and (i.qnid in ('".implode("','",session('currentStatus')['clientQnidList'])."') ";

                        $where .= " or (
                                        se.entity_value in ('".implode("','",session('currentStatus')['clientQnidList'])."') 
                                        and se.entity_tag like '%cliid**%'
                                        and se.table_tag = 'sys_con_ops'
                                    ) ";

                        $where .= ')';
                        
                    break;
                }
            }
           
        }else{
            //also if user is reseller even its not limited we will still hide everything because its reseller account
            if(session('type_key') == 'op-pert-reseller') {
                return array(
                    'data'          => [],
                    'pageCount'     => 0,
                    'totalCount'    => 0,
                    'filteredCount' => 0,
                    'last_page'     => 0,
                );
            }
        }
        //here we want to show some datas  for just spesific system
        switch($formType){
            case 'op-doc-request':
            case 'op-doc-offer':
                if(isset($GLOBALS['SYS_CODE']) &&  session('type_key') !== 'op-pert-admin'){
                    //$key = $GLOBALS['SYS_CODE'] == 'GDZ' ? 'Çates' : 'ADM';
                    /*$where .= " and (
                                    main_attr.main_attr ilike '%{\"Key\" : \"target_type\", \"Value\" : \"$key\"}%' 
                                    or main_attr.main_attr ilike '%{\"Key\" : \"target_type\", \"Value\" : \"her ikisi\"}%'
                                ) ";  */

                    $where .= ' and (';
                    
                    if(isset($GLOBALS['SYS_CODE'])) $where .= " i.grp_code ilike '%".$GLOBALS['SYS_CODE']."%' ";

                    if($formType == 'op-doc-request'){
                        $where .= " or main_attr.main_attr ilike '%{\"Key\" : \"her_ikisi\", \"Value\" : \"1\"}%' ";
                    }

                    $where .= ' )';
                }
                 
            break;
            default:
                if(isset($GLOBALS['SYS_CODE'])) $where .= " and i.grp_code ilike '%".$GLOBALS['SYS_CODE']."%'";
            break;
        }
        

        //check if is client and this is his document
        //check for supplier permission
       
        if (isset($obj['scale']['page']) && isset($obj['scale']['limit'])) {
            $start = (intval($obj['scale']['page']) * intval($obj['scale']['limit'])) - intval($obj['scale']['limit']);
            $limit =  " LIMIT " . $obj['scale']['limit'] . " OFFSET " . $start;
        }else{
            $obj['scale']['limit'] = 1;
        }

        if (isset($obj['order'])){
            $ordKey = $obj['order']['key'];
            $ordStyle = strtolower($obj['order']['style']) === 'asc' ? 'asc' : 'desc';
            if($ordKey === 'alim_kodu'){
                $column = "(SELECT se2.entity_value FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id WHERE so2.main_id=i.id AND se2.entity_tag='buying_no' LIMIT 1)";
                $order = ' order by ' .$column. ' ' . $ordStyle.' ';
            } elseif($ordKey === 'siparis_kodu'){
                $column = "(SELECT se2.entity_value FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id WHERE so2.main_id=i.id AND se2.entity_tag='order_no' LIMIT 1)";
                $order = ' order by ' .$column. ' ' . $ordStyle.' ';
            } elseif($ordKey === 'stok_kodu'){
                $column = "(SELECT sce2.entity_value FROM documents di JOIN sys_con_ops sco2 ON sco2.main_id=di.id AND sco2.conn_id=0 JOIN sys_con_entities sce2 ON sce2.conn_id=sco2.id WHERE di.parent_id=i.id AND di.status=1 AND sce2.entity_tag='prod_code' LIMIT 1)";
                $order = ' order by ' .$column. ' ' . $ordStyle.' ';
            } else {
                switch($ordKey){
                    default:
                        $column = isset($columns[$ordKey]) ? explode('as  ',$columns[$ordKey])[0] : 'i.id';
                    break;
                }
                $order = ' order by ' .$column. ' ' . $ordStyle.' ';
            }
        }else{
            $order = ' order by i.id desc ';
        }
        
        if (isset($obj['filter'])){
            //$obj['filter'] = json_decode($obj['filters'],true);
            

            if(isset($obj['filterKeys']['form-type'])){
                $columns['main_attr'] = 'main_attr.main_attr  as  main_attr';
                $join .= " LEFT JOIN LATERAL (
                                                SELECT 
                                                    json_agg(
                                                        json_build_object('Key', se.entity_tag, 'Value', se.entity_value)
                                                    )::text AS main_attr
                                                FROM sys_con_entities AS se
                                                INNER JOIN sys_con_ops AS so2 
                                                    ON so2.id = se.conn_id
                                                INNER JOIN sys_options AS sp2 
                                                    ON sp2.id = so2.type_id
                                                WHERE so2.conn_id = 0 
                                                AND sp2.op_key = '".$obj['filterKeys']['form-type']."' 
                                                AND so2.main_id = i.id
                                            ) main_attr ON true";
                if($obj['filterKeys']['form-type'] == 'op-doc-offer-form'){
                    $join .= "
                                -- Request attributes (complex nested part)
                                LEFT JOIN LATERAL (
                                    SELECT 
                                        json_agg(
                                            json_build_object('Key', se2.entity_tag, 'Value', se2.entity_value)
                                        )::text AS request_attr
                                    FROM sys_con_entities AS se2
                                    INNER JOIN sys_con_ops AS so3 
                                        ON so3.id = se2.conn_id
                                    INNER JOIN sys_options AS sp3 
                                        ON sp3.id = so3.type_id
                                    INNER JOIN documents AS d1 
                                        ON d1.id = so3.main_id
                                    WHERE so3.conn_id = 0 
                                    AND sp3.op_key = 'op-doc-request-form'
                                    AND d1.qnid = (
                                            -- Find request_id once per i (not per so)
                                            SELECT se1.entity_value
                                            FROM sys_con_entities AS se1
                                            WHERE se1.conn_id IN (
                                                SELECT so_inner.id
                                                FROM sys_con_ops AS so_inner
                                                WHERE so_inner.main_id = i.id
                                            )
                                            AND se1.entity_tag = 'request_id'
                                            ORDER BY se1.id DESC          -- or whatever logic you prefer
                                            LIMIT 1
                                    )
                                ) request_attr ON true";

                    $columns['addional'] = " request_attr.request_attr  as  addional"; 
                }
            }


            $expiredKeyReaded = false;
            foreach($obj['filter'] as $f){
                
                $nativeValue = noInject(strip_tags($f['value']));
                if(isset($f['field'])) $f['key'] = $f['field'];
                if(isset($f['value']) && $f['key'] !== 'transactions' ) $f['value'] = noInject(strip_tags($f['value']));
                
                switch($f['key']){
                    case 'showExpired':
                        if($expiredKeyReaded) break;
                        if($f['value'] == 'false'){
                            $where .= " and (
                                            to_date(se.entity_value, 'DD/MM/YYYY') > current_date 
                                            and se.entity_tag = 'contract_end_date'
                                            and se.table_tag = 'sys_con_ops'
                                        ) ";
                        }
                        $expiredKeyReaded = true;
                        break;
                    case 'today-ended':
                        $where .= " and (
                                            (
                                               main_attr.main_attr like '%\"Key\" : \"contract_end_date\", \"Value\" : \"".date('d/m/Y')."\"%'
                                            ) 
                                        ) ";
                        break;
                    case 'is-rodevans':
                            if($f['value'] == 'true'){
                                $where .= " and (
                                                se.entity_value = '1' 
                                                and se.entity_tag = 'request_type'
                                                and se.table_tag = 'sys_con_ops'
                                            ) ";
                            }else{
                                $where .= " and (
                                            (
                                               main_attr.main_attr not like '%\"Key\" : \"request_type\", \"Value\" : \"1\"%'
                                            ) 
                                        ) ";
                            }



                        break;
                    case 'monthly':
                        $where .= " and i.created_at::text like '%".$f['value']."%'";
                        break;
                    case 'form-type':
                        break;
                    //already consumed by the status predicate above; must stay here so it never falls
                    //through to the default branch, which would turn it into a main_attr filter.
                    case 'with-cancelled':
                        break;
                    case 'attr':
                        $where .= " and (
                                        se.entity_value ilike '%".$f['value']."%'
                                        and se.entity_tag like '%".$f['type']."%'
                                    ) ";
                        break;
                    case 'month-period':
                        $columns['status'] = "'-'  as  status"; // unneccesery
                       

                        $query =  explode('as  ',$columns['main_attr'])[0];

                        $where .= " and (".$query." like '%".$f['value']."%' or  ".$query." like '%".date('Y-m',(strtotime('next month',strtotime($f['value'].'-01'))))."%' )";

                       

                        break;
                    case 'status-null':
                        $column = explode('as  ',$columns['status'])[0];
                        $where .= " and ".$column." is null ";
                        break;
                    case 'status-not':
                        $column = explode('as  ',$columns['status'])[0];
                        $where .= " and ".$column." not like '%".$f['value']."%' ";
                        break;
                    case 'transactions':
                    case 'onay_durumu':
                        $rawV = noInject(strip_tags($f['value']));
                        if($rawV == 'null'){
                            $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) is null";
                        } else if(strpos($rawV, ',') !== false){
                            $parts = array_filter(array_map('trim', explode(',', $rawV)));
                            $escaped = array_map(fn($p)=> "'".noInject($p)."'", $parts);
                            $in = implode(',', $escaped);
                            $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) in (".$in.")";
                        } else {
                            $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) = '".$rawV."'";
                        }
                        
                        $where .= $sql;
                        break;
                    // Tedarik detailed search — order list filters (op-doc-order)
                    case 'stok_kodu':
                        // product code lives on order-item (child of order)
                        $where .= " and exists (select 1 from documents di join sys_con_ops sco2 on sco2.main_id=di.id and sco2.conn_id=0 join sys_con_entities sce2 on sce2.conn_id=sco2.id where di.parent_id=i.id and di.status=1 and sce2.entity_tag='prod_code' and sce2.entity_value ilike '%".noInject($f['value'])."%') ";
                        break;
                    case 'siparis_kodu':
                        $where .= " and exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='order_no' and se2.entity_value ilike '%".noInject($f['value'])."%') ";
                        break;
                    case 'alim_kodu':
                        $where .= " and exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='buying_no' and se2.entity_value ilike '%".noInject($f['value'])."%') ";
                        break;
                    case 'seri_no':
                        // serial_no on op-doc-order-serial whose parent item's parent is this order
                        $where .= " and exists (select 1 from documents ds join sys_con_ops sco_s on sco_s.main_id=ds.id and sco_s.conn_id=0 join sys_con_entities sce_s on sce_s.conn_id=sco_s.id where ds.status=1 and sce_s.entity_tag='serial_no' and sce_s.entity_value ilike '%".noInject($f['value'])."%' and ds.parent_id in (select id from documents where parent_id=i.id and status=1)) ";
                        break;
                    case 'uretim_tarihi':
                        $where .= " and exists (select 1 from documents ds join sys_con_ops sco_s on sco_s.main_id=ds.id and sco_s.conn_id=0 join sys_con_entities sce_s on sce_s.conn_id=sco_s.id where ds.status=1 and sce_s.entity_tag='production_date' and sce_s.entity_value ilike '%".noInject($f['value'])."%' and ds.parent_id in (select id from documents where parent_id=i.id and status=1)) ";
                        break;
                    case 'sirket':
                    case 'tedarikci':
                    case 'sirket_kodu':
                    case 'tedarikci_kodu':
                        // supports comma-separated multi-select (e.g. from Şirkete Göre Arama submenu)
                        $rawV = $f['value'];
                        if(strpos($rawV, ',') !== false){
                            $parts = array_filter(array_map('trim', explode(',', $rawV)));
                            $ors = [];
                            foreach($parts as $p){
                                $p = noInject($p);
                                if($p==='') continue;
                                $ors[] = "se2.entity_value ilike '%".$p."%'";
                            }
                            if(count($ors)){
                                $cond = implode(' OR ', $ors);
                                $where .= " and (exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='spec_code' and (".$cond.")) or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='ctitle' and (".$cond."))) ";
                            }
                        } else {
                            $v = noInject($rawV);
                            $where .= " and (exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='spec_code' and se2.entity_value ilike '%".$v."%') or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='ctitle' and se2.entity_value ilike '%".$v."%')) ";
                        }
                        break;
                    case 'tarih_araligi':
                    case 'tarih_baslangic':
                        // flatpickr sends Y-m-d range "2026-09-01|2026-09-10" — must match both document created_at AND Sipariş Tarihi (entity created_at DD/MM/YYYY)
                        // also handle legacy " - " / " to " / " — " separators if frontend didn't pipe
                        $v = noInject($f['value']);
                        $sep = null;
                        if(strpos($v, '|') !== false) $sep='|';
                        elseif(strpos($v, ' - ') !== false) $sep=' - ';
                        elseif(strpos($v, ' to ') !== false) $sep=' to ';
                        elseif(strpos($v, ' — ') !== false) $sep=' — ';
                        if($sep !== null){
                            [$s,$e] = explode($sep, $v, 2);
                            $s = trim($s); $e = trim($e);
                            $sEsc = noInject($s); $eEsc = noInject($e);
                            $entityDate = "to_date(se2.entity_value,'DD/MM/YYYY')";
                            if($s !== '' && $e !== ''){
                                $where .= " and (i.created_at::date between '".$sEsc."'::date and '".$eEsc."'::date or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='created_at' and se2.entity_value ~ '^[0-9]{2}/[0-9]{2}/[0-9]{4}' and ".$entityDate." between '".$sEsc."'::date and '".$eEsc."'::date)) ";
                            } elseif($s !== ''){
                                $where .= " and (i.created_at::date >= '".$sEsc."'::date or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='created_at' and se2.entity_value ~ '^[0-9]{2}/[0-9]{2}/[0-9]{4}' and ".$entityDate." >= '".$sEsc."'::date)) ";
                            } elseif($e !== ''){
                                $where .= " and (i.created_at::date <= '".$eEsc."'::date or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='created_at' and se2.entity_value ~ '^[0-9]{2}/[0-9]{2}/[0-9]{4}' and ".$entityDate." <= '".$eEsc."'::date)) ";
                            }
                        } else {
                            $where .= " and (i.created_at::text ilike '%".$v."%' or exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='created_at' and se2.entity_value ilike '%".$v."%')) ";
                        }
                        break;
                    case 'parent_id':
                        $where .= " and i.parent_id = '".intval($f['value'])."' ";
                        break;
                    case 'free':
                    case 'all':
                        $value = $f['value'];
                        $where .= ' and (';
                        //set columns
                        $i = 0;
                        foreach($columns as $k=>$v){
                            //document_status is an internal flag, not searchable text: every active
                            //row holds '1', so including it would make a "1" search match everything
                            if($k == 'document_status') continue;

                            if($i!=0) $where.=' or ';
                            $column = explode('as  ',$columns[$k])[0];

                            if($k == 'main_attr') $column = 'main_attr.main_attr';

                            $where .= " $column::text ilike '%$value%' ";
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('as  ',($columns[$f['key']] ?? $columns['main_attr']))[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where .= " and $column = '".$f['value']."' ";
                            }else{
                                $where .= " and $column::text ilike '%".$f['value']."%' ";
                            }
                        }
                        break;
                }
                
            }
        } 
        
       
        //create query    
        $sql = 'select distinct '.implode(",", array_values($columns)).'
                    from documents as i '.$join.' ' . $where.$order.$limit ;
        
        $result = DB::select($sql);
        
        //count query
        $sql = 'select count(distinct i.id) as row from documents as i '.$join.' '. $where;
        $total_count = DB::select($sql)[0];
        
        
        return array(
            'data'          => $result,
            'pageCount'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
            'totalCount'    => $total_count->row,
            'filteredCount' => count($result),
            'last_page'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
        );
    }
}

