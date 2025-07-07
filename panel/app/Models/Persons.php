<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Persons extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'surname',
        'title',
        'spec_code',
        'sys_code',
        'parent_id',
        'type_id',
        'phone',
        'address',
        'parent_id',
        'email_approved',
        'approved',
        'status'
    ];

    public static $rules = [
        'surname' => 'required',
        'name'    => 'required',
        'type_id' => 'required',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        /*static::deleting(function ($person) {
            $user = Users::where('person_id',$person->id)->first();
            
            $deps = Person_con_ops::where('person_id',$person->id)->get();
            foreach ($deps as $d) {$d->delete();}

            if(!empty($user)){
                $perm = User_con_ops::where('user_id',$user->id)->get();
                foreach ($perm as $p) {$p->delete();}
                $user->delete();
            }
        });*/
    }

    static function tableList($obj){
       
        $columns = array(
            'status'       => 'i.status',
            'id'           => 'i.id',
            'name'         => 'i.name',
            'username'     => 'u.email  as  username',
        );




        $limit = '';
        $order = '';
        $join = '   ---inner join sys_options as o on o.id = i.type_id 
                    inner join users as u on u.person_id = i.id';
        
        $where = " where i.name != '' and u.email != 'kbbozat41@hotmail.com'";  
        

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
            $order = ' order by i.id asc ';
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
                    from persons as i '.$join.' ' . $where.$order.$limit ;
        $result = DB::select($sql);
       
        //count query
        $sql = 'select count(*) as row from persons as i '.$join.' '. $where;
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
