<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class User_logs extends Model
{
    use HasFactory;

    protected $fillable = [
        'type_id',
        'sys_code',
        'user_id',
        'relation_id',
        'relation',
        'description',
    ];

    static function tableList($obj){

        $columns = array(
            'id'           => 'i.id',
            /*'person'       => "(select CONCAT (p.name,'-',p.surname) from persons as p
                                    inner join users as u on u.person_id = p.id
                                    where u.id = i.user_id) as  person",*/
            'description'  => 'i.description',
            'relation'     => 'i.relation',
            'relation_id'  => 'i.relation_id',
            'created_at'   => 'i.created_at',
            'type_key'     => 's.op_key  as  type_key',
            'type'         => 's.title  as  type'  
        );

        $limit = '';
        $order = '';
        $join = ' inner join sys_options as s on s.id = i.type_id';
        
        $where = " where i.description!=''  and s.op_key not in ('log-login','log-logout','log-user-looked','log-delete')";    
        //$where .= ' and i.sys_code = \''.($GLOBALS['SYS_CODE'] === 'ADM' ? '5000' : '4000')."'";

        //if person is client then filter results
        /*if(checkPerm('per-07-04')){

           $where .= "and ".auth('sanctum')->user()->id." = any((case 
                            when i.relation = 'users'
							then array[i.user_id]
								
                            when i.relation = 'tender'
                            then (select ARRAY_AGG (u.id)
                                    from documents as d 	
                                        inner join persons as p on p.client_id = d.person_id
                                        inner join users as u on u.person_id = p.id
                                        where d.id = i.relation_id)
                                when i.relation = 'file'
                                then (select ARRAY_AGG (u.id) 
                                            from document_files df
                                                inner join documents d on d.id = df.relation_id
                                                inner join persons as p on p.client_id = d.person_id
                                                inner join users as u on u.person_id = p.id
                                                
                                            where df.id = i.relation_id and df.relation = 'tender')
                        end))
                        and s.op_key not in ('log-file-added','log-tender-add','log-tender-update')";
        }*/


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
                if(isset($f['field'])) $f['key'] = $f['field'];
                if(isset($f['value'])) $f['value'] = noInject(strip_tags(mb_strtoupper($f['value'])));

                switch($f['key']){
                    case 'period':
                        $where.=" and i.created_at::text ilike '%".$f['value']."%'";
                        break;
                    case 'model':
                        $where.=" and description::text ilike '%".$f['value']."%' ";
                        break;
                    case 'free':
                    case 'all':
                        $where .= ' and (';
                        //set columns   
                        $i = 0;
                        foreach($columns as $k=>$v){
                            if($i!=0) $where.=' or ';
                            $column = explode('as  ',$columns[$k])[0];
                            $where.=' '.$column.'::text ilike'."'%" . $f['value'] . "%' ";
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('as  ',$columns[$f['key']])[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where.=" and ".$column."::text ='".$f['value']."' ";
                            }else{
                                $where.=" and ".$column."::text ilike '%".$f['value']."%' ";
                            }
                        }
                        break;
                }
                
            }
        }    

        //create query    
        $sql = 'select '.implode(",", array_values($columns)).'
                    from user_logs as i '.$join.' ' . $where.$order.$limit ;


        $result = DB::select($sql);
       
        //count query
        $sql = 'select count(*) as row from user_logs as i '.$join.' '. $where;
        $total_count = DB::select($sql)[0];

        return array(
            'data'          => $result,
            'pageCount'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
            'totalCount'    => $total_count->row,
            'filteredCount' => count($result),
            'last_page'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
        );
        
        /*if(checkPerm('per-07-04') || checkPerm('per-07-03') || checkPerm('per-07-02') || checkPerm('per-07-01')){
            return array(
                'data'          => $result,
                'pageCount'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
                'totalCount'    => $total_count->row,
                'filteredCount' => count($result),
                'last_page'     => ceil(intval($total_count->row) / intval($obj['scale']['limit'])),
            );
        }else{
            return array(
                'data'          => [],
                'pageCount'     => 0,
                'totalCount'    => 0,
                'filteredCount' => 0,
                'last_page'     => 0,
            );
        }*/
    }
}
