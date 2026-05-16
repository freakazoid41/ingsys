<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class NotificationLog extends Model
{
    protected $fillable = [
        'type',
        'to',
        'subject',
        'body',
        'status',
        'error_message',
        'detail',
        'payload',
        'attempts',
        'last_attempt_at',
        'sent_at',
    ];

    protected $casts = [
        'detail' => 'array',
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    static function tableList($obj){
       
        $columns = [
            'id'              => 'i.id  as  id',
            'row_id'          => 'i.id  as  row_id',
            'type'            => 'i.type  as  type',
            'to'              => 'i.to  as  to',
            'subject'         => 'i.subject  as  subject',
            'body'            => 'i.body  as  body',
            'status'          => 'i.status  as  status',
            'error_message'   => 'i.error_message  as  error_message',
            'detail'          => 'i.detail  as  detail',
            'payload'         => 'i.payload  as  payload',
            'attempts'        => 'i.attempts  as  attempts',
            'last_attempt_at' => 'i.last_attempt_at  as  last_attempt_at',
            'sent_at'         => 'i.sent_at  as  sent_at',
        ];




        $limit = '';
        $order = '';
        $join = '   ';
        
        $where = " where i.type != ''  ";  
        

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
                    from notification_logs as i '.$join.' ' . $where.$order.$limit ;
        $result = DB::select($sql);
       
        //count query
        $sql = 'select count(*) as row from notification_logs as i '.$join.' '. $where;
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
