<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sys_options;
use App\Models\Documents;

use App\Models\Sys_con_entities;
use App\Models\Sys_con_ops;
use App\Models\Transactions;
use Illuminate\Support\Facades\DB;

class ReportServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function dashboardInfo($type,$period = null){
        $data = [
            'success' => true
        ];

        switch ($type) {
            default:
                $data = array_merge($this->{$type}(),$data);
                break;
        }

        return $data;
    }

    public function getLastNotifications($others = false) {
        $sql = "select      
                            (select ul.id from user_logs as ul2   
                                where  ul2.relation_id = ul.id and ul2.user_id  = '".(auth('sanctum')->user()->id ?? 0)."' and ul2.relation = 'user_logs' limit 1)  as  is_new,
                            (select count(ul4.id) from user_logs ul4
	                                left join user_logs ul5 on ul5.relation_id  = ul4.id and ul5.relation = 'user_logs' and ul5.user_id = '".(auth('sanctum')->user()->id ?? 0)."'
	                                inner join sys_options as so on so.id = ul4.type_id
	                            where   ul4.user_id != 0 and 
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
                            so.op_key in ('log-visiter-exit','log-visiter-enter')
                order by ul.id desc limit 21";
        
        $data = DB::select($sql);
        //return Sys_options::where(['group_key' => 'op-pert'])->get();
        return [
            'success' => count($data) > 0,
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

    public function getInventories(){
       
        return Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-inventory-form"},
                                                                {"key":"type","type":"=","value":"op-doc-inventory"}
                                                            ]}',true));
    }

    public function dailyVisit(){
        return Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-visit-form"},
                                                                {"key":"type","type":"=","value":"op-doc-visit"},
                                                                {"key":"day-period","type":"=","value":"'.(date('Y-m-d')).'"}
                                                            ]}',true));
    }
}
