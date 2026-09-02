<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
class UserLog extends Model
{
    use HasFactory;
    protected static function boot() {
        parent::boot();

        static::creating(function ($post) {
            $post->sys_code = $GLOBALS['SYS_CODE'] ?? 'GDZ';
            $post->ip = request()->ip();
            // add other column as well
        });

    }
    protected $table = 'user_logs';

    protected $fillable = [
        'sys_code',
        'user_id',
        'type_id',
        'relation_id',
        'relation',
        'ip',
        'description',
    ];

    protected $casts = [
        'sys_code' => 'integer',
        'user_id' => 'integer',
        'type_id' => 'integer',
        'relation_id' => 'integer',
    ];

    static function tableList($obj){
       
        $columns = [
            'description'        => 'i.description',
            'title'              => 'so.title',
            'op_key'             => 'so.op_key',
            'name'               => 'p.name',
            'email'              => 'u.email',
            'role'               => 'srt.name  as  role',
            'created_at'         => "((i.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Istanbul')::timestamp without time zone)  as  created_at",
            'ip'                 => 'i.ip',
            'relation_id'        => 'i.relation_id',
            'relation'           => 'i.relation',
            'form_type'          => "(select
                                            title
                                                from sys_options as top
                                            where top.op_key = (i.description::jsonb -> 'after' -> 'document' ->> 'op_key')
                                            )  as  form_type"
        ];




        $limit = '';
        $order = '';
        $join = '   inner join sys_options as so on so.id = i.type_id 
                    inner join users as u on u.id = i.user_id
                    inner join persons as p on p.id = u.person_id
                    inner join sys_role_templates as srt on srt.op_key = u.role';
        
        $where = " where i.description != ''  ";  
        

        if (isset($obj['scale']['page']) && isset($obj['scale']['limit'])) {
            $start = (intval($obj['scale']['page']) * intval($obj['scale']['limit'])) - intval($obj['scale']['limit']);
            $limit = " LIMIT " . $obj['scale']['limit'] . " OFFSET " . $start;
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
                    case 'relation_id':
                        if(trim($f['value']) != ''){
                            $where .= " and i.relation_id = " . intval($f['value']);
                            if(isset($f['relation'])) $where .= " and i.relation = '" . noInject(strip_tags($f['relation'])) . "'";
                        }
                    break;
                    case 'doc_qnid':
                        if(trim($f['value']) != ''){
                            $qnid = strtolower(noInject(strip_tags($f['value'])));
                            $where .= " and lower(i.description::jsonb -> 'after' -> 'document' ->> 'qnid') = '" . $qnid . "'";
                        }
                    break;
                    case 'free':
                    case 'all':
                        $where .= ' and (';
                        //set columns   
                        $i = 0;
                        foreach($columns as $k=>$v){
                            if($i!=0) $where.=' or ';
                            $column = explode('as  ',$columns[$k])[0];
                            $where.=' upper(trim(CAST('.$column.' as varchar))) like'."'%" . $f['value'] . "%' ";
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('as  ',$columns[$f['key']])[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where.=" and upper(trim(CAST(".$column." as varchar))) ='".$f['value']."' ";
                            }else{
                                $where.=" and upper(trim(CAST(".$column." as varchar))) like '%".$f['value']."%' ";
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
    }
}
