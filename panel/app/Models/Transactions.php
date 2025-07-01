<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class Transactions extends Model
{
    use HasFactory;

    public static $rules = [
        'ref_id'      => 'required',
        'type_id'     => 'required',
        'op_id'     => 'required',
        'target_id'   => 'required',
    ];

    protected $fillable = [
        'status',
        'trans_id',
        'op_id',
        'type_id',
        'target_id',
        'log_id',
        'description',
        'note',
        'created_at',
    ];

    static function tableList($obj){
        $columns = array(
            'id'           => 'i.id  as  id',
            'type'         => 'st.title  as  type',
            'target_id'    => 'i.target_id  as  target_id',
            'sign'         => 'i.sign',
            'amount'       => 'i.amount',
            'sys_cur'      => "(select code from sys_options where code = '".env('SYS_CUR')."')  as  sys_cur",
            'sys_amount'   => "(
                                select amount from currencies where target_cur = cr.code) * 
                                CAST(
                                    (CASE
                                        when i.sign = 0 then '-' || i.amount
                                        else i.amount
                                    end) as float 
                                )  as  sys_amount ",   
            'period'       => 'i.period',
            'note'         => 'i.note',
            'created_at'   => 'i.created_at',
            'cur'          => 'cr.code  as  cur',
            'trans_files'  => "(select json_group_array(
                                            json_object(
                                                'file',df.description
                                            )
                                        ) as data
                                    FROM document_files as df 
                                            inner join sys_options as sf on sf.id = df.type_id
                                        where df.relation_id = i.id and sf.op_key = 'op-doc-trans-file')  as  trans_files",
            'conn_info'    => "(SELECT  json_group_array(
                                            json_object(
                                                'Key',se.entity_tag,
                                                'Value' , se.entity_value
                                            )
                                        ) as data
                                    FROM sys_con_entities as se
                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                        inner join sys_options as spt on spt.id = so.type_id
                                        inner join documents as d on d.id = so.main_id
                                        inner join sys_options as sp on sp.id = d.type_id
                                    where       so.conn_id = 0 
                                            and spt.op_key = (case 
                                                                when sp.op_key = 'op-doc-flat' 
                                                                then 'op-doc-flat-form' 
                                                                when sp.op_key = 'op-doc-target' 
                                                                then 'op-doc-target-form' 
                                                                when sp.op_key = 'op-doc-period' 
                                                                then 'op-doc-period-form'
                                                            end
                                                        )
                                            and d.id = i.rel_id)  as  conn_info",
            'main_info'    => "(SELECT  json_group_array(
                                            json_object(
                                                'Key',se.entity_tag,
                                                'Value' , se.entity_value
                                            )
                                        ) as data
                                    FROM sys_con_entities as se
                                        inner join sys_con_ops as so on so.id = se.conn_id 
                                        inner join sys_options as spt on spt.id = so.type_id
                                        inner join documents as d on d.id = so.main_id
                                        inner join sys_options as sp on sp.id = d.type_id
                                    where       so.conn_id = 0 
                                            and spt.op_key = (case 
                                                                when sp.op_key = 'op-doc-flat' 
                                                                then 'op-doc-flat-form' 
                                                                when sp.op_key = 'op-doc-target' 
                                                                then 'op-doc-target-form' 
                                                                when sp.op_key = 'op-doc-period' 
                                                                then 'op-doc-period-form'
                                                            end
                                                        )
                                            and d.id = i.target_id)  as  main_info"
        );
        
        $limit = '';
        $order = '';
        $join = '   inner join sys_options as st on st.id = i.type_id 
                    inner join sys_options as cr on cr.id = i.cur_id ';
        
        $where = " where st.group_key in ('op-trans-payment') and i.status = 1 ";   
        
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
                    /*case 'target_id':
                        $where = " and (target_id= '".$f['value']."' or rel_id = '".$f['value']."') ";
                        break;*/
                    default:
                        if($f['key'] == 'ref_title')  $f['key'] = 'conn_info';
                        if($f['key'] == 'main_title') $f['key'] = 'main_info';
                        
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
                    from transactions as i '.$join.' ' . $where.$order.$limit ;
        
        $result = DB::select($sql);
        //count query
        $sql = 'select count(distinct i.id) as row from transactions as i '.$join.' '. $where;
        $total_count = DB::select($sql)[0];

        //totals
        $sql = "select  sum (
                            case 
                                when i.sign = 0 
                                    then ((select amount from currencies where target_cur = cr.code) *  i.amount)
                                else 0 
                            end
                        ) as negative,
                        sum (
                            case 
                                when i.sign = 1 
                                    then ((select amount from currencies where target_cur = cr.code) *  i.amount)
                                else 0 
                            end
                        ) as positive,
                        (select code from sys_options where code = '".env('SYS_CUR')."') as cur
                    from transactions as i ".$join.' ' . $where;
        $tresult = DB::select($sql);
        
        return array(
            'data'          => $result,
            'pageCount'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
            'totalCount'    => $total_count->row,
            'filteredCount' => count($result),
            'last_page'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
            'totals'        => $tresult[0]
        );
    }
}
