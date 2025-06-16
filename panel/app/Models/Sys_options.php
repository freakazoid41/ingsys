<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Sys_options extends Model
{
    use HasFactory;

    static function tableList($obj){
       
        $columns = array(
            'id'        => '(case
                                    when soe.id is null then i.id
                                    else soe.id
                                end)  as  id',
            'sys_id'        => 'i.id  as  sys_id',
            'title'         => 'i.title ',
            'code'          => 'i.code ',
            'op_key'        => 'i.op_key',
            'ptitle'        => 'p.title  as  ptitle ',
            'pop_key'       => 'p.op_key  as  pop_key',
            'entity_tag'    => 'soe.entity_tag',
            'entity_value'  => 'soe.entity_value',
            'entity_id'     => 'soe.id  as  entity_id',
        );
        
        $limit = '';
        $order = '';
        $join = "   left join sys_options as p on p.id = i.parent_id
                    left join sys_con_ops as dc on dc.main_id = i.id and dc.status = '1'
                    left join sys_options as so on so.id = dc.type_id and so.op_key = 'op-sys-entity-conn' 
                    left join sys_con_entities soe on soe.conn_id = dc.id and soe.table_tag = 'sys_con_ops' and soe.entity_tag like '%side-area**%'";
        
        //$where = " where i.title!='' and soe.entity_tag is not null ";   
        $where = " where i.title!=''  ";   
        
        if (isset($obj['scale']['page']) && isset($obj['scale']['limit'])) {
            $start = (intval($obj['scale']['page']) * intval($obj['scale']['limit'])) - intval($obj['scale']['limit']);
            $limit =  " LIMIT " . $obj['scale']['limit'] . " OFFSET " . $start;
        }else{
            $obj['scale']['limit'] = 1;
        }

        if (isset($obj['order'])){
            switch($obj['order']['key']){
                default:
                    $column = explode('as  ',$columns[$obj['order']['key']])[0];
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
                    case 'group_key':
                            $where .= " and i.group_key in (".\str_replace("''","'",$nativeValue).")";
                        break;
                    case 'normal_list':
                        $columns['id'] = 'distinct i.id  as  id';
                        unset($columns['entity_tag']);
                        unset($columns['entity_value']);
                        unset($columns['entity_id']);
                        break;
                    case 'is_filled':
                            $where .= '  and soe.entity_tag is not null '; 
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
                            if($column === 'i.created_at'){
                                $where.= " TO_CHAR(".$column."::date, 'dd.mm.yyyy') ilike '%" . $value . "%' ";
                            }else{
                                $where .= " trim($column::text) ilike '%$value%' ";
                            }
                            
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('as  ',$columns[$f['key']])[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where .= " and trim($column::text) = '".$f['value']."' ";
                            }else{
                                $where .= " and trim($column::text) ilike '%".$f['value']."%'  ";
                            }
                        }
                        break;
                }
                
            }
        } 
        
       
        //create query    
        $sql = 'select '.implode(",", array_values($columns)).'
                    from sys_options as i '.$join.' ' . $where.$order.$limit ;
        $result = DB::select($sql);
        //count query
        $sql = 'select count(distinct i.id) as row from sys_options as i '.$join.' '. $where;
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
