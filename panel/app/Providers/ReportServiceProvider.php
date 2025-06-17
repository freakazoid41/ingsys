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

        $systemCur = Sys_options::where('code' , env('SYS_CUR'))->first();
        switch($type){
            case 'monthlyEvents':
                $data = Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-calendar-form"},
                                                                {"key":"type","type":"=","value":"op-doc-calendar"},
                                                                {"key":"month-period","type":"=","value":"'.($period ?? date('Y-m')).'"}
                                                            ]}',true));
                break;
            case 'updatedstatus':
                $sql = "select  (SELECT     json_group_array(
                                                json_object(
                                                    'key',se.entity_tag,
                                                    'value' , se.entity_value
                                                )
                                            ) 
                                        FROM sys_con_entities as se
                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                            inner join sys_options as sp on sp.id = so.type_id
                                        where so.conn_id = 0 and sp.op_key = 'op-doc-meeting-form'  and so.main_id = d.id)  as  main_attr,
                                (select icon from sys_options where code = '".env('SYS_CUR')."') as cur,
                                (SELECT sum(
                                                t.amount *
                                                (select amount from currencies where target_cur = cur.code)
                                        ) FROM transactions as t
                                            inner join sys_options as cur on cur.id = t.cur_id
                                            inner join sys_options as so on so.id = t.type_id
                                    where t.sign = '0' and so.op_key = 'doc_acc_aidat' and t.period='".date('Y-m')."') as total_income,
                                (SELECT sum(
                                                t.amount *
                                                (select amount from currencies where target_cur = cur.code)
                                        ) FROM transactions as t
                                            inner join sys_options as cur on cur.id = t.cur_id
                                            inner join sys_options as so on so.id = t.type_id
                                    where t.sign = '0' and so.op_key = 'doc_acc_rent' and t.period='".date('Y-m')."') as total_rent,
                                (select count(*) from documents as pd 
                                    inner join sys_options as spd on spd.id = pd.type_id
                                        where spd.op_key = 'op-doc-project')  as  project_count,
                                (select count(*) from documents as pd 
                                    inner join sys_options as spd on spd.id = pd.type_id
                                        where spd.op_key = 'op-doc-flat')  as  flat_count      

                                from documents as d
                        inner join sys_options as so on so.id = d.type_id

                            where   so.op_key = 'op-doc-meeting' order by d.id desc limit 1";
                $rows = DB::select($sql);
                if(isset($rows[0])){
                    $row = json_decode($rows[0]->main_attr);
                   
                    foreach($row as $r){
                        $data[$r->key] = $r->value;
                    }
                }
                $data = array_merge($data,(array)($rows[0] ?? []));
                break;
            case 'getOngoingTasks':
                $data = Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-project-form"},
                                                                {"key":"type","type":"=","value":"op-doc-project"},
                                                                {"key":"status-not","type":"=","value":"doc_trans_project_end"}
                                                            ]}',true));
                break;
            case 'incomestatus':
                $monthlySql = "(select  count(t.id) 
                                        from transactions t 
                                    inner join sys_options as so on so.id = t.type_id
                                where t.target_id = i.id and so.op_key = 'doc_acc_aidat' and t.sign = '0' and t.period='".date('Y-m')."')";
                $sql = "select  $monthlySql  as  has_monthly_income ,
                                (select icon from sys_options where code = '".env('SYS_CUR')."')  as  cur,
                                (select      Sum(
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
                                )  as  balance,
                                (SELECT    json_group_array(
                                                                json_object(
                                                                    'Key',se.entity_tag,
                                                                    'Value' , se.entity_value
                                                                )
                                                            ) 
                                                        FROM sys_con_entities as se
                                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                                            inner join sys_options as sp on sp.id = so.type_id
                                                        where so.conn_id = 0 and sp.op_key = 'op-doc-flat-form'  and so.main_id = i.id)  as  main_attr
                            from documents as i
                                inner join sys_options as sp on sp.id = i.type_id
                            where i.status = '1' and sp.op_key   = 'op-doc-flat' and $monthlySql = 0";
                $data = DB::select($sql);
                break;
            case 'updatedmeeting':
                $sql = "select  (SELECT     json_group_array(
                                                json_object(
                                                    'key',se.entity_tag,
                                                    'value' , se.entity_value
                                                )
                                            ) 
                                        FROM sys_con_entities as se
                                            inner join sys_con_ops as so on so.id = se.conn_id 
                                            inner join sys_options as sp on sp.id = so.type_id
                                        where so.conn_id = 0 and sp.op_key = 'op-doc-meeting-form'  and so.main_id = d.id)  as  main_attr

                                from documents as d
                        inner join sys_options as so on so.id = d.type_id

                            where   so.op_key = 'op-doc-meeting' order by d.id desc limit 1";
                $row = DB::select($sql);
                if(isset($row[0])){
                    $row = json_decode($row[0]->main_attr);
                   
                    foreach($row as $r){
                        $data[$r->key] = $r->value;
                    }
                }
            case 'income': // this month
            case 'outcome': // this month
                $sql = "select      Sum( 
                                        (select amount from currencies where target_cur = cur.code) * t.amount
                                    ) as amount,
                                    (select icon from sys_options where code = '".env('SYS_CUR')."') as cur,
                                    t.amount as amount_pure,
                                    cur.code as cur_pure,
                                    st.op_key,
                                    st.title,
                                    t.note,
                                    t.id,
                                    (SELECT  json_group_array(se.entity_value) as data
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
                                            and d.id = ".($type == 'income' ? 't.rel_id' : 't.target_id').")  as  main_info,
                                    (SELECT  json_group_array(se.entity_value) as data
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
                                            and d.id = ".($type == 'income' ? 't.target_id' : 't.rel_id').")  as  acc_info
                                    from documents as d
                        inner join sys_options as so on so.id = d.type_id
                        inner join transactions as t on t.target_id = d.id
                        inner join sys_options as cur on cur.id= t.cur_id
                        inner join sys_options as st on st.id = t.type_id

                            where   so.op_key = 'op-doc-target' and t.status = 1 and 
                                    t.sign = ".($type == 'income' ? '1' : '0')." and 
                                    st.group_key in ('op-trans-payment')
                                    ";
                if($period != null){
                    $data['list']  = DB::select($sql." and t.period >= '".$period[0]."' and t.period <= '".$period[1]."' GROUP BY t.id");
                    $data['ratio'] = [];
                }else{
                    $data['list']  = DB::select($sql." and t.period = '".(date('Y-m'))."' GROUP BY t.id");
                    $data['ratio'] = DB::select($sql." and t.period = '".(date("Y-m",strtotime("-1 month")))."' GROUP BY t.id");
                }
               
                $data['cur']  = $systemCur->icon;
                $data['data'] = 0;
                foreach($data['list'] as $row){
                    if($row->op_key != 'doc_acc_dept') $data['data'] += floatval($row->amount);
                }

                $ratio = 0;
                foreach($data['ratio'] as $row){
                    if($row->op_key != 'doc_acc_dept') $ratio+= floatval($row->amount);
                }
                $data['ratio'] = $ratio == 0 ? 0 : ((floatval($data['data'] ?? 1) - floatval($ratio ?? 1)) / floatval($ratio ?? 1)) * 100;
                                        
                break;   
            case 'flatcount':
                $data['data'] = DB::select("SELECT count(d.id) as fcount 
                                                        from documents as d
                                                            inner join sys_options as sp on sp.id = d.type_id
                                                                where sp.op_key = 'op-doc-flat'")[0]->fcount;
                break;
            default:
                $data = array_merge($this->{$type}(),$data);
            break;
        }

        return $data;
    }

    public function contacts(){
        $list = [];
        $sql  = "SELECT     
                            json_object(
                                'key',se.entity_tag,
                                'value' , se.entity_value
                            ) as main_attr,
                            d.id as document
                                        
                        FROM sys_con_entities as se
                            inner join sys_con_ops as so on so.id = se.conn_id 
                            inner join sys_options as sp on sp.id = so.type_id
                            inner join documents as d on d.id = so.main_id
                            inner join sys_options as sd on sd.id = d.type_id
                        where so.conn_id = 0  and sp.op_key in ('op-doc-flat-form','op-doc-meeting-form','op-doc-project-form')";
        $rows = DB::select($sql);
        foreach($rows as $r){
            $data = json_decode($r->main_attr);
            if(!isset($list[$r->document])) $list[$r->document] = [];
            if(strpos($data->key,'title') !== false) $list[$r->document]['title'] = $data->value;
            
            if(strpos($data->key,'contgroup') !== false){
                $key = explode('**',$data->key);
                
                if(!isset($list[$r->document][$key[2]])) $list[$r->document][$key[2]] = [
                    'group' => explode('contgroup',$key[1])[0]
                ];
                
                $list[$r->document][$key[2]][$key[0]] = $data->value;
                
               
            }
        }

        $shapedList = [];
        foreach ($list as $doc => $row) {
            foreach ($row as $key => $value) {
                if($key != 'title'){
                    if(isset($row['title'])) $value['title'] = $row['title'];
                    $shapedList[] = $value;
                } 
            }
            
        }

        return ['list' => array_values($shapedList)];
    }
    
    public function totalBalance(){
        $data = [];
        $sqlTotal = "select Sum(
                                (select amount from currencies where target_cur = cur.code) * 
                                CAST(
                                    (CASE
                                        when t.sign = 0 then '-' || t.amount
                                        else t.amount
                                    end) as float 
                                )
                            ) as total,
                            (select icon from sys_options where code = '".env('SYS_CUR')."') as cur
                            from documents as d
                inner join sys_options as so on so.id = d.type_id
                inner join transactions as t on t.target_id = d.id
                inner join sys_options as cur on cur.id= t.cur_id
                inner join sys_options as st on st.id = t.type_id

                    where   so.op_key = 'op-doc-target' and  t.status = 1 and
                            st.group_key in ('op-trans-payment') ";


        
        
        $data['data'] = DB::select($sqlTotal)[0];
        $data['currency'] = $data['data']->cur;
        $data['data']     = $data['data']->total;

        $charList = [];
        for ($i=1; $i<=12 ; $i++) { 
            $month = ($i < 10 ? '0'.$i : $i);
            $charList[date('Y-'.$month)] = ($i <= intval(date('m')) ? 
                            DB::select(  $sqlTotal .
                                        "and DATE(concat(t.period,'-01')) <= '".date('Y-'.$month.'-01')."' and
                                                DATE(concat(t.period,'-01')) >= '".date('Y-01-01')."'  ")[0]->total ?? 0 : 0);
        }
        
        $data['chart'] = $charList;

        return $data;
    }
}
