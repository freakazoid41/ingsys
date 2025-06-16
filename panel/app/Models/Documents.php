<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        'hk_type_id',
        'hkc_type_id',
        'leave_type_id',
        'grp_code',
        'starting_at',
        'ending_at',
        'title',
        's_sgk_code',
        's_nace_code',
        's_isn_code',
        's_title',
        's_tax_code',
        's_tm_number',
        's_tm_amount',
        's_starting_at',
        's_tem_ending_at',
        's_rsp_type_id',
    ];


    static function tableList($obj){
        $columns = array(
            'id'           => 'i.id  as  id',
            'type'         => 'sp.op_key  as  type',
            'cur'          => " (select icon from sys_options where code = '".env('SYS_CUR')."')  as  cur ",
            'balance_pure' => " (select Sum(
                                            CAST(
                                                (CASE
                                                    when t.sign = 0 then '-' || t.amount
                                                    else t.amount
                                                end) as float 
                                            )
                                        ) as total
                                        from documents as d
                                            inner join sys_options as so on so.id = d.type_id
                                            inner join transactions as t on t.target_id = d.id
                                            inner join sys_options as cur on cur.id= t.cur_id
                                            inner join sys_options as st on st.id = t.type_id

                                where   ---so.op_key = 'op-doc-target' and  
                                        d.id = i.id and t.status = 1 and
                                        st.group_key in ('op-trans-payment')
                            )  as  balance_pure",
            'balance'      => " (select      Sum(
                                                (select amount from currencies where target_cur = cur.code) * 
                                                CAST(
                                                    (CASE
                                                        when t.sign = 0 then '-' || t.amount
                                                        else t.amount
                                                    end) as float 
                                                )
                                            ) as total
                                            from documents as d
                                                inner join sys_options as so on so.id = d.type_id
                                                inner join transactions as t on t.target_id = d.id
                                                inner join sys_options as cur on cur.id= t.cur_id
                                                inner join sys_options as st on st.id = t.type_id

                                    where   ---so.op_key = 'op-doc-target' and  
                                            d.id = i.id and t.status = 1 and
                                            st.group_key in ('op-trans-payment')
                                )  as  balance",
            'status'       => "(select  concat(so.op_key,'**',so.title,'**',t.note)
                                from transactions as t
                                    inner join sys_options so on so.id = t.type_id
                                where target_id = i.id and so.group_key = 'op-trans' order by t.id desc limit 1)  as  status",
            'main_attr'    => '',
                        
        );
        
        $limit = '';
        $order = '';
        $join = ' inner join sys_options as sp on sp.id = i.type_id ';
        
        $where = " where i.status = '1'";   
        //$where .= " and i.sys_code::text like '%".($GLOBALS['SYS_CODE'] === 'ADM' ? '5000' : '4000')."%'";


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
            foreach($obj['filter'] as $f){
                
                $nativeValue = noInject(strip_tags($f['value']));
                if(isset($f['field'])) $f['key'] = $f['field'];
                if(isset($f['value']) && $f['key'] !== 'transactions' ) $f['value'] = noInject(strip_tags($f['value']));
                
                switch($f['key']){
                    case 'month-period':
                        $columns['status'] = "'-'  as  status"; // unneccesery
                        $columns['balance'] = "'-'  as  balance"; // unneccesery
                        $columns['balance_pure'] = "'-'  as  balance_pure"; // unneccesery
                        $columns['cur'] = "'-'  as  cur"; // unneccesery
                        $where .= " and ".$column = explode('as  ',$columns['main_attr'])[0]." like '%".$f['value']."%'";
                        break;
                    case 'status-not':
                        $column = explode('as  ',$columns['status'])[0];
                        $where .= " and ".$column." not like '%".$f['value']."%' ";
                        break;
                    case 'balance-date':
                        $dates = explode('/',$f['value']);
                        $columns['balance'] = " (select      Sum(
                                        (select amount from currencies where target_cur = cur.code) * 
                                        CAST(
                                            (CASE
                                                when t.sign = 0 then '-' || t.amount
                                                else t.amount
                                            end) as float 
                                        )
                                    ) as total
                                    from documents as d
                                        inner join sys_options as so on so.id = d.type_id
                                        inner join transactions as t on t.target_id = d.id
                                        inner join sys_options as cur on cur.id= t.cur_id
                                        inner join sys_options as st on st.id = t.type_id

                            where   ----so.op_key = 'op-doc-target' and  
                                    d.id = i.id and t.status = 1 and
                                    st.group_key in ('op-trans-payment') and
                                    t.period >= '".$dates[0]."' and t.period <= '".$dates[1]."'

                        )  as  balance";
                        $columns['balance_pure'] = " (select      Sum(
                                        CAST(
                                            (CASE
                                                when t.sign = 0 then '-' || t.amount
                                                else t.amount
                                            end) as float 
                                        )
                                    ) as total
                                    from documents as d
                                        inner join sys_options as so on so.id = d.type_id
                                        inner join transactions as t on t.target_id = d.id
                                        inner join sys_options as cur on cur.id= t.cur_id
                                        inner join sys_options as st on st.id = t.type_id

                            where   ----so.op_key = 'op-doc-target' and  
                                    d.id = i.id and t.status = 1 and
                                    st.group_key in ('op-trans-payment') and
                                    t.period >= '".$dates[0]."' and t.period <= '".$dates[1]."'

                        )  as  balance_pure";
                        break;
                    case 'form-type':
                        $value = $f['value'];
                        $columns['main_attr'] = "(SELECT    json_group_array(
                                                                json_object(
                                                                    'Key',se.entity_tag,
                                                                    'Value' , se.entity_value
                                                                )
                                                            ) 
                                                        FROM sys_con_entities as se
                                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                                            inner join sys_options as sp on sp.id = so.type_id
                                                        where so.conn_id = 0 and sp.op_key = '".$value."'  and so.main_id = i.id)  as  main_attr";
                        break;
                    case 'transactions':
                        $sql = "  and (select    so.op_key
                                                from transactions as t
                                                    inner join sys_options so on so.id = t.type_id
                                                where target_id=i.id order by t.id desc limit 1) = '".noInject(strip_tags($f['value']))."'";
                        $where .= $sql;
                        break;
                    case 'free':
                    case 'all':
                        $value = $f['value'];
                        $where .= ' and (';
                        //set columns   
                        $i = 0;
                        foreach($columns as $k=>$v){
                            if($i!=0) $where.=' or ';
                            $column = explode('as  ',$columns[$k])[0];
                            /*if($column === 'i.created_at'){
                                $where.= env('DB_CONNECTION') == 'pgsql' ? 
                                " TO_CHAR(".$column."::date, 'dd.mm.yyyy') like '%" . $value . "%' " :
                                 ' convert(varchar, '.$column.', 104) like'."'%" . $value . "%' ";
                            }else{
                                $where .= " $column like '%$value%' ";
                            }*/
                            $where .= " $column like '%$value%' ";
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('as  ',$columns[$f['key']])[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where .= " and $column = '".$f['value']."' ";
                            }else{
                                $where .= " and $column like '%".$f['value']."%' ";
                            }
                        }
                        break;
                }
                
            }
        } 
        
       
        //create query    
        $sql = 'select '.implode(",", array_values($columns)).'
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

