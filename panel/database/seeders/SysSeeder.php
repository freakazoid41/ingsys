<?php

namespace Database\Seeders;
use App\Models\Sys_options;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedStart();
    }

    private function seedStart(){


        $start = [
            [
                'parent_id' => 0,
                'title'     => 'Yüklenici',
                'ttitle'    => 'Persons',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-per-1',
                'group_key' => 'op-per-types',
            ],[
                'parent_id' => 0,
                'title'     => 'İş Birimi',
                'ttitle'    => 'Persons',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-per-2',
                'group_key' => 'op-per-types',
            ]
        ];

        $apartment = [
            [
                'parent_id' => 0,
                'title'     => 'Benim Sistemim',
                'ttitle'    => 'Sys_options',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-apt-1',
                'group_key' => 'op-apt-types',
            ]
        ];

        $permissions = [
            [
                'parent_id' => 0,
                'title'     => 'Permissions',
                'ttitle'    => '-',
                'ctitle'    => 'op_id',
                'op_key'    => 'op-perm',
                'childs'    => [
                    [
                        'parent_id' => 0,
                        'title'     => 'Yüklenici Personel Kartları',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-03',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-03-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Oluşturma / Güncelleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-03-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Deaktif Etme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-03-03',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'İzin Bakiye Aktarma',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-03-04',
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $logs = [
            [
                'parent_id' => 0,
                'title'     => 'Login',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-login',
            ],[
                'parent_id' => 0,
                'title'     => 'Logout',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-logout',
            ],[
                'parent_id' => 0,
                'title'     => 'Sistem Girdileri Güncelleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-sys-op-update',
            ],[
                'parent_id' => 0,
                'title'     => 'Notification Show',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-user-looked',
            ],[
                'parent_id' => 0,
                'title'     => 'Ziyaretçi Giriş Yaptı',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-visiter-enter',
            ],[
                'parent_id' => 0,
                'title'     => 'Url Hatası',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-url-error',
            ],[
                'parent_id' => 0,
                'title'     => 'Dosya Girişi',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-file-added',
            ],[
                'parent_id' => 0,
                'title'     => 'Dosya Durum Düzenlemesi',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-file-status-trans',
            ],[
                'parent_id' => 0,
                'title'     => 'Dosya Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-file-edited',
            ],[
                'parent_id' => 0,
                'title'     => 'Ekleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'op_key'    => 'log-post',
                'group_key' => 'op-logs',
            ],[
                'parent_id' => 0,
                'title'     => 'Güncelleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'op_key'    => 'log-put',
                'group_key' => 'op-logs',
            ],[
                'parent_id' => 0,
                'title'     => 'Silme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'op_key'    => 'log-delete',
                'group_key' => 'op-logs',
            ]
        ];

        $trans = [
            [
                'parent_id' => 0,                   //main document status
                'title'     => 'Dosya Sisteme Eklendi',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_created',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Proje Başladı',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_project_start',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Proje Tamamlandı',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_project_end',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Projede Sıkıntı Oluştu',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_project_sikinti',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Proje Ödemesi Bekleniyor',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_project_payment',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Doküman Onay Bekliyor',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_file_waiting',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Doküman Reddedildi',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_file_rejected',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Doküman Onay Yenilendi',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_file_refreshed',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Doküman Onaylandı',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_file_accepted',
            ]
        ];

        $forms = [
            [
                'parent_id' => 0,
                'title'     => 'Döküman Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-main',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Döküman TEST Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-main-test',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Döküman Ana Form Dosyaları',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-main-file',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Transaction Dosyaları',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-trans-file',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Tesis Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-facility-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Hata',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-visit-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Personel Tesis Yetki',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'per-perm-facility',
                'group_key' => 'per-perm-facility',
            ]
        ];

        $formConnTypes = [
            [
                'parent_id' => 0,
                'title'     => 'Form Ana Bağlantı',
                'ttitle'    => 'sys_con_ops',
                'ctitle'    => 'sub_type_id',
                'op_key'    => 'form-main',
            ],
            [
                'parent_id' => 0,
                'title'     => 'Form Dosya Bağlantısı',
                'ttitle'    => 'Documents',
                'ctitle'    => 'sub_type_id',
                'op_key'    => 'form-file',
            ],
            [
                'parent_id' => 0,
                'title'     => 'Personel Bağlantı',
                'ttitle'    => 'sys_con_ops',
                'ctitle'    => 'sub_type_id',
                'op_key'    => 'personnel-main',
            ],
        ];

        $personTypes = [
            [
                'parent_id' => 0,
                'title'     => 'Yönetici',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-pert-admin',
                'group_key' => 'op-pert',
            ],[
                'parent_id' => 0,
                'title'     => 'Ziyaretçi',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-pert-buyer',
                'group_key' => 'op-pert',
            ]
        ]; 

        $documentTypes = [
            [
                'parent_id' => 0,
                'title'     => 'Tesis Listesi',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-facility',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Hata',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-visit',
                'group_key' => 'op-doc',
            ]
        ];

        $curTypes = [
            [
                'parent_id' => 0,
                'title'     => 'TRY',
                'code'      => 'TRY',
                'ttitle'    => '-',
                'icon'      => '₺',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-cur-types-tr',
                'group_key' => 'op-cur-types',
            ],[
                'parent_id' => 0,
                'title'     => 'EUR',
                'code'      => 'EUR',
                'icon'      => '€',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-cur-types-eur',
                'group_key' => 'op-cur-types',
            ],[
                'parent_id' => 0,
                'title'     => 'USD',
                'code'      => 'USD',
                'icon'      => '$',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-cur-types-usd',
                'group_key' => 'op-cur-types',
            ],[
                'parent_id' => 0,
                'title'     => 'GBP',
                'code'      => 'GBP',
                'icon'      => '£',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-cur-types-gbp',
                'group_key' => 'op-cur-types',
            ]
        ];

        $start = array_merge($start,$apartment);
        $start = array_merge($start,$permissions);
        $start = array_merge($start,$logs);
        $start = array_merge($start,$trans);
        $start = array_merge($start,$forms);
        $start = array_merge($start,$formConnTypes);
        $start = array_merge($start,$personTypes);
        $start = array_merge($start,$documentTypes);
        $start = array_merge($start,$curTypes);
        //fpr start add some items to database
        foreach($start as $item){
            $this->seed($item);
            /*$i = new Sys_options(]; //The Category is the model for your migration
            $i->title     = $item['title'];
            $i->ttitle    = $item['ttitle'];
            $i->ctitle    = $item['ctitle'];
            $i->op_key    = $item['op_key'];
            if(isset($item['group_key']]] $i->group_key = $item['group_key'];
            $i->save(];

            if(isset($item['childs']]]{
                foreach($item['childs'] as $child]{
                    $j = new Sys_options(]; //The Category is the model for your migration
                    $j->parent_id = $i->id;
                    $j->group_key = $item['op_key'];
                    $j->title     = $child['title'];
                    $j->ttitle    = $child['ttitle'];
                    $j->ctitle    = $child['ctitle'];
                    $j->op_key    = $child['op_key'];
                    $j->save(];
                }
            }*/
        }

    }

    function seed($item,$groupKey = '-',$parentId = 0){
        
        //first check if key exist
        $i = Sys_options::where('op_key',$item['op_key'])->first();
        if(empty($i)){
            $i = new Sys_options(); //The Category is the model for your migration
            $i->parent_id = $parentId;
            $i->title     = $item['title'];
            $i->ttitle    = $item['ttitle'];
            $i->ctitle    = $item['ctitle'];
            $i->op_key    = $item['op_key'];
            if(isset($item['code'])) $i->code = $item['code']; 
            if(isset($item['icon'])) $i->icon = $item['icon']; 
            $i->group_key = isset($item['group_key']) ? $item['group_key'] : $groupKey ;
            $i->save();

            print_r($item['title'].' Added..'.PHP_EOL);
            
        }

        if(isset($item['childs'])){
            foreach($item['childs'] as $child){
                $this->seed($child,$item['op_key'],$i->id);
            }
        }

        return $i;
    }

    
}
