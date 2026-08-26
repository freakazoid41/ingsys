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
            if(!isset($post->grp_code))$post->grp_code = $GLOBALS['SYS_CODE'] ?? 'CATES';
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
                    //$key = $GLOBALS['SYS_CODE'] == 'CATES' ? 'Çates' : 'Yatağan';
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
            switch($obj['order']['key']){
                default:
                    $column = isset($columns[$obj['order']['key']]) ? explode('as  ',$columns[$obj['order']['key']])[0] : 'i.id';
                break;
            }
            $order = ' order by ' .$column. ' ' . $obj['order']['style'].' ';
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
                        if(noInject(strip_tags($f['value'])) == 'null'){
                            $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) is null";
                        }else{
                            $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id and so.group_key = 'op-trans-".$formType."' order by t.id desc limit 1) = '".noInject(strip_tags($f['value']))."'";
                        }
                        
                        $where .= $sql;
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

