<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'parent_id',
        'person_id',
        'qnid',
        'status',
        'grp_code',
        'role',
        'needs_refresh',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

    protected static function boot() {
        parent::boot();

        static::creating(function ($post) {
            $post->qnid = (string) Str::uuid();
            $post->grp_code = $GLOBALS['SYS_CODE'] ?? 'CATES';
            // add other column as well
        });

    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    static function tableList($obj){
       
        $columns = array(
            'is_active'     => "((select count(id) from active_sessions as au where au.user_id = u.id and last_seen >= now() - interval '1 minutes') > 0)   as   is_active",
            'status'        => 'i.status',
            'id'            => 'i.qnid  as  id',
            'name'          => 'i.name',
            'type_title'    => 't.title  as  type_title',
            'type_key'      => 't.op_key  as  type_key',
            'username'      => 'u.email  as  username',
            'user_status'   => 'u.status  as  user_status',
            'needs_refresh' => 'u.needs_refresh  as  needs_refresh',
            'created_at'    => 'u.created_at',
            'role_title'    => 'r.name  as  role_title',
            'permissions'   => "(SELECT string_agg(sr.title, ', ')
                                    FROM sys_con_entities as se
                                        INNER JOIN sys_con_ops as so ON so.id = se.conn_id
                                        INNER JOIN sys_options as sp ON sp.id = so.type_id
                                        INNER JOIN LATERAL (
                                            SELECT jsonb_array_elements_text(se.entity_value::jsonb) as code
                                        ) as codes ON true
                                        INNER JOIN sys_permission_catalogs as sr ON sr.code = codes.code
                                    WHERE so.conn_id = 0
                                        AND sp.op_key = 'op-doc-user-permission-form'
                                        AND so.main_id = i.id
                                        GROUP BY se.id)  as  permissions"
        );




        $limit = '';
        $order = '';
        $join = '   inner join sys_options as t on t.id = i.type_id 
                    inner join users as u on u.person_id = i.id
                    inner join sys_role_templates as r on r.op_key = u.role ';
        
        $where = " where i.name != '' and u.email != 'kadir@kontent.com.tr' ";  
        

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
                if(isset($f['value'])) $f['value'] = noInject(strip_tags($f['value']));
                switch($f['key']){
                    case 'free':
                    case 'all':
                        $where .= ' and (';
                        //set columns   
                        $i = 0;
                        foreach($columns as $k=>$v){
                            if($i!=0) $where.=' or ';
                            $column = explode('  as  ',$columns[$k])[0];
                            $where.=' '.$column."::text ilike '%" . $f['value'] . "%' ";
                            $i++;
                        }
                        $where .= ' ) ';
                    break;
                    default:
                        $column = explode('  as  ',$columns[$f['key']])[0];
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
