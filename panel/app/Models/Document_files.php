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
            $post->grp_code = $GLOBALS['SYS_CODE'] ?? 'CATES';
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
            
            
        );

        $limit = '';
        $order = '';
        $join = "   inner join sys_options so on so.id = i.type_id 
                    inner join sys_con_entities se on se.entity_value = i.id::text
                    inner join documents as d on d.id = i.relation_id::int
                    inner join sys_options as dt on dt.id = d.type_id
                    inner join sys_options as sf on sf.op_key = 'op-'|| SPLIT_PART(se.entity_tag, '**', 1)";
        
        $where  = " where i.description!='' and i.status = 1 and i.grp_code='".($GLOBALS['SYS_CODE'] ?? 'CATES')."'"; 

        $where  .= " and sf.op_key not in ('op-offer_otherdocs_file') ";
        
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
                        $column = explode('  as  ',$columns[$f['key']])[0];
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
