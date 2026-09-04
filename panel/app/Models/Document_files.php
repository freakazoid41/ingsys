<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class Document_files extends Model
{
    use HasFactory;

    public static $rules = [
        'description'    => 'required',
        'type_id'        => 'required',
    ];

    protected $fillable = [
        'description',
        'type_id',
        'selected_at',
        'title',
        'qnid',
        'grp_code',
        
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            $post->qnid = (string) Str::uuid();
            $post->grp_code = $GLOBALS['SYS_CODE'] ?? 'GDZ';
            // add other column as well
        });
        
        static::deleting(function ($document) {
            /*//get transactions
            $trans = Transactions::where([
                'ref_id' => $document->id,
                'target_id' => 2
            ])->get();
            //remove transactions
            if(isset($trans[0])) { foreach($trans as $tr){ $tr->delete(); }}*/
            //remove file from storage
            $enc = new \App\Providers\EncryptionProvider();
            $path = 'documents/' . $enc->decrypt($document->description);
            Storage::disk('public')->delete($path);
        });
    }

    static function tableList($obj){
        
        $columns = array(
            'id'                => 'i.qnid  as  id',
            'file'              => 'i.description  as  file',
            'type_title'        => 'so.title  as  type_title',
            'type_key'          => 'so.op_key  as  type_key',
            'file_type'         => 'sf.title  as  file_type',
            'file_type_key'     => 'sf.op_key  as  file_type_key',
            'relation'          => 'i.relation',
            'relation_qnid'     => 'd.qnid  as  relation_qnid',
            'relation_type'     => 'dt.op_key  as  relation_type',
            'entity_tag'        => 'se.entity_tag  as  entity_tag',
            'created_at'        => 'i.created_at   as  created_at',
            'relation_detail'   => "(SELECT    json_agg(
                                            json_build_object(
                                                'Key',se1.entity_tag,
                                                'Value' , se1.entity_value
                                            )
                                        )
                                    FROM sys_con_entities as se1
                                    where se1.conn_id = se.conn_id)  as  relation_detail",
            
            'old_versions'      => "(select     json_agg(
                                                    json_build_object(
                                                        'description',df2.description,
                                                        'qnid'       ,df2.qnid,
                                                        'created_at' , df2.created_at
                                                    )
                                                )
                                    from sys_con_entities se2
                                        inner join document_files as df2 on df2.id = se2.entity_value::int
                                    where se2.entity_tag = se.entity_tag)  as  old_versions",
            'last_status'       => "(select     json_build_object(
                                                    'op_key',sot.op_key,
                                                    'title' , sot.title,
                                                    'name'  , p.name,
                                                    'note' , t.description
                                                ) 
                                                    from transactions t 
                                            
                                                inner join sys_options sot on sot.id = t.type_id
                                                inner join user_logs ul on ul.id = t.log_id
                                                inner join users u on u.id = ul.user_id
                                                inner join persons p on p.id = u.person_id
                                            where t.target_id = i.id and op_id = 1 order by t.id desc limit 1)  as  last_status",
            'title'             => "COALESCE(
                                        (SELECT sce.entity_value FROM sys_con_entities sce
                                         WHERE sce.conn_id = se.conn_id AND sce.entity_tag = 'title' AND sce.table_tag = 'sys_con_ops' LIMIT 1),
                                        ''
                                    )  as  title",
            'group_key'         => "COALESCE(
                                        -- For order-level files: order_no from same conn (clone's own number)
                                        (SELECT sce.entity_value FROM sys_con_entities sce
                                         WHERE sce.conn_id = se.conn_id AND sce.entity_tag = 'order_no'
                                         AND sce.table_tag = 'sys_con_ops' LIMIT 1),
                                        -- For item files: order_no from parent document (clone order's number)
                                        (SELECT sce.entity_value FROM sys_con_entities sce
                                         INNER JOIN sys_con_ops sco ON sco.id = sce.conn_id
                                         INNER JOIN documents pd ON pd.id = sco.main_id
                                         WHERE sce.entity_tag = 'order_no' AND pd.id = d.parent_id
                                         AND sce.table_tag = 'sys_con_ops' LIMIT 1),
                                        ''
                                    )  as  group_key",
            // Product name for test documents — the item document's title entity
            'product_name'      => "(SELECT sce2.entity_value FROM sys_con_entities sce2
                                     INNER JOIN sys_con_ops sco2 ON sco2.id = sce2.conn_id
                                     WHERE sco2.main_id = d.id AND sce2.entity_tag = 'title'
                                     AND sce2.table_tag = 'sys_con_ops' LIMIT 1)  as  product_name",
            
            
        );

        $limit = '';
        $order = '';
        $join = "   inner join sys_options so on so.id = i.type_id 
                    inner join sys_con_entities se on se.entity_value = i.id::text
                    inner join documents as d on d.id = i.relation_id::int
                    inner join sys_options as dt on dt.id = d.type_id
                    inner join sys_options as sf on sf.op_key = 'op-'|| SPLIT_PART(se.entity_tag, '**', 1)";
        
        $where  = " where i.description!='' and i.status = 1 "; //  and i.grp_code='".($GLOBALS['SYS_CODE'] ?? 'GDZ')."'"; 
        //all documents in document list are belong the clients so split company from clients
        $where  .= " and d.grp_code='".($GLOBALS['SYS_CODE'] ?? 'GDZ')."' and d.status = 1 ";

        $where  .= " and sf.op_key not in ('op-offer_otherdocs_file') ";
        // Exclude product images from files listing — they are per-item and not actionable
        $where  .= " and se.entity_tag not like '%item_images_file%' ";

        // Tedarik supplier scope: reseller sees only own files (LIFNR-matched orders/items + own client docs)
        // Mirrors Documents::tableList clientQnidList gating — fails closed for reseller, open for admin
        if (session('currentStatus') !== null && !empty(session('currentStatus')['clientQnidList'])) {
            if (session('type_key') == 'op-pert-reseller') {
                $qnids = session('currentStatus')['clientQnidList'];
                $qnidIn = "'" . implode("','", array_map('noInject', $qnids)) . "'";
                // resolve LIFNRs for these client qnids (op-doc-client lifnr)
                $lifRows = [];
                try {
                    $lifRows = DB::select("SELECT se.entity_value as lifnr FROM sys_con_entities se INNER JOIN sys_con_ops so ON so.id = se.conn_id INNER JOIN documents d2 ON d2.id = so.main_id WHERE d2.qnid IN ($qnidIn) AND se.entity_tag = 'lifnr' AND se.table_tag = 'sys_con_ops'");
                } catch (\Throwable $e) { $lifRows = []; }
                $lifnrs = array_values(array_filter(array_map(fn($r)=> trim($r->lifnr ?? ''), $lifRows), fn($v)=> $v !== ''));
                if (!empty($lifnrs)) {
                    $lifList = "'" . implode("','", array_map('noInject', $lifnrs)) . "'";
                    $where .= " and ( d.qnid IN ($qnidIn)"
                             . " or exists (select 1 from sys_con_entities sce3 join sys_con_ops sco3 on sco3.id=sce3.conn_id where sco3.main_id=d.id and sce3.entity_tag='spec_code' and sce3.entity_value IN ($lifList) )"
                             . " or exists (select 1 from documents pd join sys_con_ops sco_p on sco_p.main_id=pd.id and sco_p.conn_id=0 join sys_con_entities sce_p on sce_p.conn_id=sco_p.id where pd.id = d.parent_id and pd.status=1 and sce_p.entity_tag='spec_code' and sce_p.entity_value IN ($lifList) )"
                             . " or exists (select 1 from sys_con_entities sce4 join sys_con_ops sco4 on sco4.id=sce4.conn_id where sco4.main_id=d.id and sce4.entity_tag='lifnr' and sce4.entity_value IN ($lifList) )"
                             . " ) ";
                } else {
                    // no LIFNR yet — at least allow own client doc files (imza sirküleri etc)
                    $where .= " and d.qnid IN ($qnidIn) ";
                }
            }
        } else {
            if (session('type_key') == 'op-pert-reseller') {
                return array(
                    'data'          => [],
                    'pageCount'     => 0,
                    'totalCount'    => 0,
                    'filteredCount' => 0,
                    'last_page'     => 0,
                );
            }
        }
        
        if (isset($obj['scale']['page']) && isset($obj['scale']['limit'])) {
            $start = (intval($obj['scale']['page']) * intval($obj['scale']['limit'])) - intval($obj['scale']['limit']);
            $limit =  " LIMIT " . $obj['scale']['limit'] . " OFFSET " . $start ;
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
                if(isset($f['value'])) $f['value'] = noInject(strip_tags($f['value']));
                switch($f['key']){
                    case 'seri_no':
                        $v = noInject($f['value']);
                        if($v !== ''){
                            $where .= " and ( exists (select 1 from documents ds join sys_con_ops sco_s on sco_s.main_id=ds.id and sco_s.conn_id=0 join sys_con_entities sce_s on sce_s.conn_id=sco_s.id where ds.status=1 and sce_s.entity_tag='serial_no' and sce_s.entity_value ilike '%".$v."%' and ds.parent_id = d.id) or exists (select 1 from documents ds2 join sys_con_ops sco_s2 on sco_s2.main_id=ds2.id and sco_s2.conn_id=0 join sys_con_entities sce_s2 on sce_s2.conn_id=sco_s2.id where ds2.status=1 and sce_s2.entity_tag='serial_no' and sce_s2.entity_value ilike '%".$v."%' and ds2.parent_id in (select id from documents where parent_id=d.id and status=1)) or exists (select 1 from documents ds3 join sys_con_ops sco_s3 on sco_s3.main_id=ds3.id and sco_s3.conn_id=0 join sys_con_entities sce_s3 on sce_s3.conn_id=sco_s3.id where ds3.status=1 and sce_s3.entity_tag='serial_no' and sce_s3.entity_value ilike '%".$v."%' and ds3.parent_id = d.parent_id) ) ";
                        }
                        break;
                    case 'siparis_kodu':
                    case 'group_key':
                        $v = noInject($f['value']);
                        if($v !== ''){
                            $where .= " and ( exists (select 1 from sys_con_entities scex join sys_con_ops scox on scox.id=scex.conn_id where scox.main_id=d.id and scex.entity_tag='order_no' and scex.entity_value ilike '%".$v."%' and scex.table_tag='sys_con_ops') or exists (select 1 from documents pd join sys_con_ops sco_p on sco_p.main_id=pd.id and sco_p.conn_id=0 join sys_con_entities sce_p on sce_p.conn_id=sco_p.id where pd.id=d.parent_id and sce_p.entity_tag='order_no' and sce_p.entity_value ilike '%".$v."%') or exists (select 1 from sys_con_entities se2 where se2.conn_id=se.conn_id and se2.entity_tag='order_no' and se2.entity_value ilike '%".$v."%') ) ";
                        }
                        break;
                    case 'tarih_araligi':
                        $v = noInject($f['value']);
                        if(strpos($v, '|') !== false){
                            [$s,$e] = explode('|', $v, 2);
                            $s = trim($s); $e = trim($e);
                            $sEsc = noInject($s); $eEsc = noInject($e);
                            if($s !== '' && $e !== ''){
                                $where .= " and i.created_at::date between '".$sEsc."'::date and '".$eEsc."'::date ";
                            } elseif($s !== ''){
                                $where .= " and i.created_at::date >= '".$sEsc."'::date ";
                            } elseif($e !== ''){
                                $where .= " and i.created_at::date <= '".$eEsc."'::date ";
                            }
                        } elseif(strpos($v, ' - ') !== false){
                            [$s,$e] = explode(' - ', $v, 2);
                            $s = trim($s); $e = trim($e);
                            $sEsc = noInject($s); $eEsc = noInject($e);
                            if($s !== '' && $e !== ''){
                                $where .= " and i.created_at::date between '".$sEsc."'::date and '".$eEsc."'::date ";
                            } elseif($s !== ''){
                                $where .= " and i.created_at::date >= '".$sEsc."'::date ";
                            } elseif($e !== ''){
                                $where .= " and i.created_at::date <= '".$eEsc."'::date ";
                            }
                        } elseif(strpos($v, ' to ') !== false){
                            [$s,$e] = explode(' to ', $v, 2);
                            $s = trim($s); $e = trim($e);
                            $sEsc = noInject($s); $eEsc = noInject($e);
                            if($s !== '' && $e !== ''){
                                $where .= " and i.created_at::date between '".$sEsc."'::date and '".$eEsc."'::date ";
                            } elseif($s !== ''){
                                $where .= " and i.created_at::date >= '".$sEsc."'::date ";
                            } elseif($e !== ''){
                                $where .= " and i.created_at::date <= '".$eEsc."'::date ";
                            }
                        } elseif(strpos($v, ' — ') !== false){
                            [$s,$e] = explode(' — ', $v, 2);
                            $s = trim($s); $e = trim($e);
                            $sEsc = noInject($s); $eEsc = noInject($e);
                            if($s !== '' && $e !== ''){
                                $where .= " and i.created_at::date between '".$sEsc."'::date and '".$eEsc."'::date ";
                            } elseif($s !== ''){
                                $where .= " and i.created_at::date >= '".$sEsc."'::date ";
                            } elseif($e !== ''){
                                $where .= " and i.created_at::date <= '".$eEsc."'::date ";
                            }
                        } else if($v !== ''){
                            $where .= " and i.created_at::text ilike '%".$v."%' ";
                        }
                        break;
                    case 'sirket':
                    case 'tedarikci':
                    case 'sirket_kodu':
                        $rawV = $f['value'];
                        if(strpos($rawV, ',') !== false){
                            $parts = array_filter(array_map('trim', explode(',', $rawV)));
                            $ors = [];
                            foreach($parts as $p){
                                $p = noInject($p);
                                if($p==='') continue;
                                $ors[] = "se2.entity_value ilike '%".$p."%'";
                            }
                            if(count($ors)){
                                $cond = implode(' OR ', $ors);
                                $where .= " and ( exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=d.id and se2.entity_tag in ('spec_code','ctitle','lifnr') and (".$cond.")) or exists (select 1 from documents pd join sys_con_ops sco_p on sco_p.main_id=pd.id and sco_p.conn_id=0 join sys_con_entities se2 on se2.conn_id=sco_p.id where pd.id=d.parent_id and se2.entity_tag in ('spec_code','ctitle','lifnr') and (".$cond.")) ) ";
                            }
                        } else {
                            $v = noInject($rawV);
                            if($v !== ''){
                                $where .= " and ( exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=d.id and se2.entity_tag in ('spec_code','ctitle','lifnr') and se2.entity_value ilike '%".$v."%' ) or exists (select 1 from documents pd join sys_con_ops sco_p on sco_p.main_id=pd.id and sco_p.conn_id=0 join sys_con_entities se2 on se2.conn_id=sco_p.id where pd.id=d.parent_id and se2.entity_tag in ('spec_code','ctitle','lifnr') and se2.entity_value ilike '%".$v."%') ) ";
                            }
                        }
                        break;
                    case 'file_status':
                        $rawV = noInject($f['value']);
                        if($rawV !== ''){
                            // support comma-separated IN
                            if(strpos($rawV, ',') !== false){
                                $parts = array_filter(array_map('trim', explode(',', $rawV)));
                                $inList = "'" . implode("','", array_map('noInject', $parts)) . "'";
                                $where .= " and exists (select 1 from transactions t join sys_options so on so.id=t.type_id where t.target_id=i.id and t.op_id=1 and so.op_key in (".$inList.") and t.id = (select max(t2.id) from transactions t2 where t2.target_id=i.id and t2.op_id=1)) ";
                            } else {
                                $where .= " and exists (select 1 from transactions t join sys_options so on so.id=t.type_id where t.target_id=i.id and t.op_id=1 and so.op_key='".$rawV."' and t.id = (select max(t2.id) from transactions t2 where t2.target_id=i.id and t2.op_id=1)) ";
                            }
                        }
                        break;
                    case 'free':
                    case 'all':
                        $value = $f['value'];
                        $where .= ' and (';
                        //set columns   
                        $i = 0;
                        foreach($columns as $k=>$v){
                            if($k !== 'file'){
                                if($i!=0) $where.=' or ';
                                $column = explode('as  ',$columns[$k])[0];
                                if($column === 'i.created_at'){
                                    " ".$column."::text ilike '%" . $value . "%' " ;
                                }else{
                                    $where.=' '.$column.'::text ilike '."'%" . $value . "%' ";
                                }
                                
                                $i++;
                            }
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('  as  ',$columns[$f['key']] ?? $columns['relation_detail'])[0];
                        if(trim($f['value']) != ''){
                            if($f['type'] != 'like'){
                                $where.=" and ".$column." ='".$f['value']."' ";
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
                    from document_files as i '.$join.' ' . $where.$order.$limit ;
        $result = DB::select($sql);
        
        //count query
        $sql = 'select count(*) as row from document_files as i '.$join.' '. $where;
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
