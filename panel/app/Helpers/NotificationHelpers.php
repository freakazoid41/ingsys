<?php 

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

if(!function_exists('checkTenderIsg')){
    function checkTenderIsg($user = 0){
        //first get all isg file list
        $types = DB::select("select so.op_key from sys_options so
                                where group_key in ('op-doc-ih-isg-risk',
                                                    'op-doc-ih-isg-emerg',
                                                    'op-doc-ih-isg-isgh',
                                                    'op-doc-ih-isg-isgk',
                                                    'op-doc-ih-isg-work',
                                                    'op-doc-ih-isg-equipm',
                                                    'op-doc-ih-isg-chem',
                                                    'op-doc-ih-isg-status',
                                                    'op-doc-ih-isg-proc') ");

       

        $query = [];
        foreach ($types as $key => $value) {
            $query[] = "'".$value->op_key."'";
        }

        $query = implode(', ', $query);
        //then check if any tender not have all of them
        $sql = "WITH file_check AS  (select  	d.id,
			                        d.title,
			                        d.person_id,
			                        d.created_at,
                                    array(select u.id from persons as p 
	                            			inner join users as u on u.person_id = p.id
	                            		where p.client_id = d.person_id )::text[] as users,
			                        array(select        so3.op_key
                                                    from document_con_ops dco 
                                            inner join sys_options so on so.id = dco.type_id
                                            
                                            left join sys_con_entities dce on dce.conn_id = dco.id and dce.table_tag = 'document_con_ops'
                                            left join document_con_ops dco2 on dco2.main_id = dco.id 
                                            left join sys_options so2 on so2.id = dco2.type_id 
                                            left join document_files df on df.id = dco2.conn_id and df.status='1'
                                            left join sys_options so3 on so3.id = df.type_id
                                            
                                                where so.op_key = 'op-doc-ih-isg' and df.status='1' and so2.op_key='op-doc-ih' and dco.main_id = d.id)::text[]
                                        as file_list
			
			                    from documents d where d.status = '1')
                SELECT id,title,person_id,created_at,users,file_list from file_check


                where   (ARRAY[".$query."] <@ file_list) = false  and 
                        date_trunc('month', created_at) = date_trunc('month', current_date - interval '1 month') ";

        if($user != 0){
            $sql.= " and (array['$user'] <@ users) = true";
        }

        return DB::select($sql);
    }
}
