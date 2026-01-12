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
            //$post->grp_code = session('grp_code') ?? 'op-apt-1';
            // add other column as well
        });

    }

    static function tableList($obj){
        $columns = array(
            'realId'         => 'i.id  as  realId',
            'id'             => 'i.qnid  as  id',
            'type'           => 'sp.op_key  as  type',
            'entity_conn_id' => 'se.conn_id  as  entity_conn_id', 
            'main_attr'      => '',
            'created_at'     => 'i.created_at',
        );

        $select  = ' distinct ';
        $limit   = '';
        $order   = '';
        $groupBy = '';
        $join    = '    inner join sys_options as sp on sp.id = i.type_id 
                        inner join sys_con_ops as so on i.id = so.main_id 
                        inner join sys_options as tso on tso.id = so.type_id
                        inner join sys_con_entities as se on so.id = se.conn_id  ';
        
       
        //here be sure requesting form connections from type group key or other connections may be come
        $where = " where i.status = '1' and tso.group_key = 'op-doc-forms' ";   
        $authWhere = '';
        
        //$where .= " and i.sys_code::text like '%".($GLOBALS['SYS_CODE'] === 'ADM' ? '5000' : '4000')."%'";


        //check if is client and this is his document
        //check for supplier permission
       
        if (isset($obj['scale']['page']) && isset($obj['scale']['limit'])) {
            $start = (intval($obj['scale']['page']) * intval($obj['scale']['limit'])) - intval($obj['scale']['limit']);
            $limit =  " LIMIT " . $obj['scale']['limit'] . " OFFSET " . $start;
            if(env('DB_CONNECTION') == 'sqlsrv'){
                $limit =  "OFFSET $start ROWS FETCH NEXT ".$obj['scale']['limit']." ROWS ONLY";
            }
        }else{
            $obj['scale']['limit'] = 1;
        }

        if (isset($obj['order'])){
            switch($obj['order']['key']){
                default:
                    if(isset($columns[$obj['order']['key']])){
                        $column = explode('as  ',$columns[$obj['order']['key']])[0];
                        $order = ' order by ' .$column. ' ' . $obj['order']['style'].' ';
                    }else{
                        $groupBy = 'group by i.qnid,
                                    sp.op_key,
                                    se.conn_id,
                                    i.created_at,
                                    i.id ';
                        $select  = ''; //remove distinct
                        $order   = " ORDER BY MAX(CASE WHEN se.entity_tag = '".$obj['order']['key']."' THEN se.entity_value END) ".$obj['order']['style']." ";
                    }
                    //$column = isset($columns[$obj['order']['key']]) ? explode('as  ',$columns[$obj['order']['key']])[0] : 'i.id';
                break;
            }
            
        }else{
            $order = ' order by i.created_at desc ';
        }
        
        if (isset($obj['filter'])){
            //$obj['filter'] = json_decode($obj['filters'],true);
            $obj['filterKeys'] = [];
            foreach($obj['filter'] as $f){
                $obj['filterKeys'][$f['key']] = noInject(strip_tags($f['value']));
            }

            if(isset($obj['filterKeys']['form-type'])){
                if(env('DB_CONNECTION') == 'sqlsrv'){
                    $columns['main_attr'] = "(SELECT    CONCAT(
                                                                '[',STRING_AGG(
                                                            CONCAT(
                                                                '{\"Key\":\"', se.entity_tag, '\",\"Value\":\"', se.entity_value, '\"}'
                                                            ), ','
                                                        ) , ']')
                                                        FROM sys_con_entities as se
                                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                                            inner join sys_options as sp on sp.id = so.type_id
                                                        where so.conn_id = 0 and sp.op_key = '".$obj['filterKeys']['form-type']."'  and so.main_id = i.id)".(env('DB_CONNECTION') == 'pgsql' ? '::text' : '')."  as  main_attr";
                }else{
                    $columns['main_attr'] = "(SELECT    ".( env('DB_CONNECTION') == 'pgsql' ? 'json_agg' : 'json_group_array' )."(
                                                                ".(env('DB_CONNECTION') == 'pgsql' ? 'json_build_object' : 'json_object')."(
                                                                    'Key',se.entity_tag,
                                                                    'Value' , se.entity_value
                                                                )
                                                            ) 
                                                        FROM sys_con_entities as se
                                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                                            inner join sys_options as sp on sp.id = so.type_id
                                                        where so.conn_id = 0 and sp.op_key = '".$obj['filterKeys']['form-type']."'  and so.main_id = i.id)".(env('DB_CONNECTION') == 'pgsql' ? '::text' : '')."  as  main_attr";
                }



                


                if(session('type_key') == 'op-pert-reseller'){
                    if($obj['filterKeys']['form-type'] == 'op-doc-facility-form') $authWhere = " and i.qnid in ('".implode("','",explode(',',session('grp_code')))."') "; 
                    if($obj['filterKeys']['form-type'] == 'op-doc-visit-form') $authWhere = " and i.grp_code in ('".implode("','",explode(',',session('grp_code')))."') "; 
                }
            }

            

            foreach($obj['filter'] as $f){
                
                $nativeValue = noInject(strip_tags($f['value']));
                if(isset($f['field'])) $f['key'] = $f['field'];
                if(isset($f['value']) && $f['key'] !== 'transactions' ) $f['value'] = noInject(strip_tags($f['value']));
                
                switch($f['key']){
                    case 'date-range':
                        $dates = explode('**',$f['value']);
                        $dates[0] = implode('-',array_reverse(explode('/',$dates[0])));
                        $dates[1] = implode('-',array_reverse(explode('/',$dates[1])));

                        if(env('DB_CONNECTION') == 'sqlsrv'){
                            $where .= " and (TRY_CAST(i.created_at AS date) BETWEEN '".$dates[0]."' AND '".$dates[1]."' ) ";
                        }else{
                            $where .= " and (se.entity_tag = 'entered_at' and (DATE(se.entity_value) between DATE('".$dates[0]."') and DATE('".$dates[1]."'))) ";
                        }
                        break;
                    case 'day-period':
                        $where .= " and (se.entity_tag = 'entered_at' and se.entity_value like '%".$f['value']."%')";
                        break;
                    case 'status-not':
                        $column = explode('as  ',$columns['status'])[0];
                        $where .= " and ".$column." not like '%".$f['value']."%' ";
                        break;
                    
                    case 'form-type':
                        $value = $f['value'];
                        if(env('DB_CONNECTION') == 'sqlsrv'){
                            $columns['main_attr'] = "(SELECT    CONCAT(
                                                            '[',STRING_AGG(
                                                        CONCAT(
                                                            '{\"Key\":\"', se.entity_tag, '\",\"Value\":\"', se.entity_value, '\"}'
                                                        ), ','
                                                    ) , ']')
                                                    FROM sys_con_entities as se
                                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                                        inner join sys_options as sp on sp.id = so.type_id
                                                    where so.conn_id = 0 and sp.op_key = '".$value."'  and so.main_id = i.id)  as  main_attr";
                        }else{
                            $columns['main_attr'] = "(SELECT    ".( env('DB_CONNECTION') == 'pgsql' ? 'json_agg' : 'json_group_array' )."(
                                                                ".(env('DB_CONNECTION') == 'pgsql' ? 'json_build_object' : 'json_object')."(
                                                                    'Key',se.entity_tag,
                                                                    'Value' , se.entity_value
                                                                )
                                                            ) 
                                                        FROM sys_con_entities as se
                                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                                            inner join sys_options as sp on sp.id = so.type_id
                                                        where so.conn_id = 0 and sp.op_key = '".$value."'  and so.main_id = i.id)".(env('DB_CONNECTION') == 'pgsql' ? '::text' : '')."  as  main_attr";
                        }
                        
                        
                        break;
                    
                    case 'free':
                    case 'all':
                        $value = $f['value'];
                        if($value != ''){
                            $where .= ' and (';
                            //set columns   
                            $i = 0;
                            foreach($columns as $k=>$v){
                                if($i!=0) $where.=' or ';
                                $column = explode('as  ',$columns[$k])[0];

                                //here performance improvement
                                if($k == 'main_attr') $column = 'se.entity_value';


                                
                                if(env('DB_CONNECTION') == 'pgsql'){
                                    $where .= " $column::text ilike '%$value%' ";
                                }else{
                                    $where .= " $column like '%$value%' ";
                                }
                            
                                $i++;
                            }
                            $where .= ' ) ';
                        }
                       
                    break;
                    default:
                        $column = explode('as  ',($columns[$f['key']] ?? $columns['main_attr']))[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where .= " and $column = '".$f['value']."' ";
                            }else{
                                if(env('DB_CONNECTION') == 'pgsql'){
                                    $where .= " $column::text ilike '%".$f['value']."%' ";
                                }else{
                                    $where .= " and (se.entity_tag = '".$f['key']."' and se.entity_value like '%".$f['value']."%') ";
                                    //$where .= " $column like '%".$f['value']."%' ";
                                }
                            }
                        }
                        break;
                }
                
            }
        } 
        
       
        //create query    
        $sql = 'select '.$select.' '.implode(",", array_values($columns)).'
                    from documents as i '.$join.' ' . $where.$authWhere.$groupBy.$order.$limit ;
        $result = DB::select($sql);


        //count query
        $sql = 'select count(distinct i.id) as row from documents as i '.$join.' '. $where.$authWhere;
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

