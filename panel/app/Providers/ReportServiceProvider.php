<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Documents;
use App\Models\User_logs;
use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Transactions;
use Illuminate\Support\Facades\DB;

class ReportServiceProvider extends ServiceProvider
{
    private $period;
    public function __construct() {
       
    }

    public function dashboardInfo($type,$period = null){
        $data = [
            'success' => true
        ];

        $this->period = $period;

        switch ($type) {
            default:
                $result = $this->{$type}();
                $data = array_merge($result,$data);
                if(isset($result['success'])) $data['success'] = $result['success'];
                break;
        }

        return $data;
    }

    public function getLastNotifications($others = false) {
        $sql = "select      
                            (select ".((env('DB_CONNECTION') == 'sqlsrv') ? 'TOP 1' : '')." ul.id from user_logs as ul2   
                                where  ul2.relation_id = ul.id and ul2.user_id  = '".(auth('sanctum')->user()->id ?? 0)."' and ul2.relation = 'user_logs' ".((env('DB_CONNECTION') == 'sqlsrv') ? '' : 'limit 1').")  as  is_new,
                            (select count(ul4.id) from user_logs ul4
	                                left join user_logs ul5 on ul5.relation_id  = ul4.id and ul5.relation = 'user_logs' and ul5.user_id = '".(auth('sanctum')->user()->id ?? 0)."'
	                                inner join sys_options as so on so.id = ul4.type_id
	                            where   --ul4.user_id != 0 and 
                                        ul4.user_id != '".auth('sanctum')->user()->id."' and 
                                        ul5.id is null  and so.op_key not in ('log-login','log-logout','log-user-looked','log-delete'))  as  new_count,
                            so.title,
                            so.op_key,
                            ul.relation,
                            ul.relation_id,
                            ul.description,
                            ul.created_at,
                            ul.id
                                    from user_logs as ul
                        inner join sys_options as so on so.id = ul.type_id
                    
                    where   ul.user_id != '".auth('sanctum')->user()->id."' and 
                            ".(session('type_key') == 'op-pert-reseller' ? " ul.grp_code='".session('grp_code')."' and " : '')."
                            so.op_key in ('log-visiter-exit','log-visiter-enter')
                order by ul.id desc ".((env('DB_CONNECTION') == 'sqlsrv') ? 'OFFSET 0 ROWS FETCH NEXT 6 ROWS ONLY' : 'limit 6');
        $data = DB::select($sql);
        //return Sys_options::where(['group_key' => 'op-pert'])->get();
        return [
            'success' => (count($data) > 0),
            'data'    => $data,
            'newCount' => $data[0]->new_count ?? 0,
        ];
    }

    
    public function getFacilities(){
       
        return Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-facility-form"},
                                                                {"key":"type","type":"=","value":"op-doc-facility"}
                                                            ]}',true));
    }

    public function getDailyLogs(){
       
        return User_logs::tableList(json_decode('{"filter":[
                                                                {"key":"period","type":"=","value":"'.date('Y-m-d').'"},
                                                                {"key":"type_key","type":"=","value":"log-url-error"}
                                                            ]}',true));
    }

    public function webSites(){
        $query = '{"filter":[
                    {"key":"form-type","type":"=","value":"op-doc-facility-form"},
                    {"key":"type","type":"=","value":"op-doc-facility"}'.($this->period ? ',{"key":"id","type":"=","value":"'.$this->period.'"}' : '').'
                ]}';

        return Documents::tableList(json_decode($query,true));
    }
}
