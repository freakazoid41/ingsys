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
                'title'     => 'Cates Sistem',
                'ttitle'    => 'Sys_options',
                'ctitle'    => 'type_id',
                'op_key'    => 'CATES',
                'group_key' => 'op-apt-types',
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
                'title'     => 'Rol Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-role-update',
            ],[
                'parent_id' => 0,
                'title'     => 'İhale Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-tender-update',
            ],[
                'parent_id' => 0,
                'title'     => 'İhale Hakediş Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-tender-period-update',
            ],[
                'parent_id' => 0,
                'title'     => 'Sistem Girdileri Güncelleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-sys-op-update',
            ],[
                'parent_id' => 0,
                'title'     => 'İhale Hakediş Kapatıldı',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-tender-period-close',
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
                'title'     => 'Personel İzin Girişi',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-leave-added',
            ],[
                'parent_id' => 0,
                'title'     => 'Dosya Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-file-edited',
            ],[
                'parent_id' => 0,
                'title'     => 'İhale Başlatma',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-tender-start',
            ],[
                'parent_id' => 0,
                'title'     => 'Yüklenici Personel Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-personnel-update',
            ],[
                'parent_id' => 0,
                'title'     => 'Yüklenici Personel Ekleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-personnel-add',
            ],[
                'parent_id' => 0,
                'title'     => 'Yüklenici Çoklu Personel Ekleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-personnel-add-multiple',
            ],[
                'parent_id' => 0,
                'title'     => 'Yüklenici Ekleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-client-add',
            ],[
                'parent_id' => 0,
                'title'     => 'Yüklenici Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-client-update',
            ],[
                'parent_id' => 0,
                'title'     => 'Vardiya Düzenleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-shift-updated',
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
                'title'     => 'Talep Başladı',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_request_start',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Talep Tamamlandı',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_request_end',
            ],[
                'parent_id' => 0,                   //main document status
                'title'     => 'Talep İptal Edildi',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans',
                'op_key'    => 'doc_trans_request_cancelled',
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
            ],[
                'parent_id' => 0,                   
                'title'     => 'Aidat',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_aidat',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Demirbaş',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_sometinguntransable',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Kira',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_rent',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Yakıt',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_fuel',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Diğer',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_other',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Borç Giriş',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_dept',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Borç Ödeme',  //bu giriş bir yere gitmek zorunda ayrıca denk gelen borç hareketinide 
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_dept_payment',
            ],[
                'parent_id' => 0,                   
                'title'     => 'Para Transferi',
                'ttitle'    => 'Transactions',
                'ctitle'    => 'type_id',
                'group_key' => 'op-trans-payment',
                'op_key'    => 'doc_acc_transfer',
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
                'title'     => 'Talep Formu',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-request-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Cari Formu',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-client-form',
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
                'title'     => 'Dönemler Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-period-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Daireler Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-flat-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Kasalar Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-target-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Toplantı Sonuç Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-meeting-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Proje Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-project-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Aktivite Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-calendar-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Envanter Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-inventory-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Apartman Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-apartment-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Kullanıcı İletişim Listesi',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-user-contact-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Kullanıcı Yetki Listesi',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-user-permission-form',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Kullanıcı Cari Listesi',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-user-client-form',
                'group_key' => 'op-doc-forms',
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
            ],[
                'parent_id' => 0,
                'title'     => 'Personel Bağlantı',
                'ttitle'    => 'sys_con_ops',
                'ctitle'    => 'sub_type_id',
                'op_key'    => 'personnel-main',
            ]
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
                'title'     => 'Müşteri',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-pert-buyer',
                'group_key' => 'op-pert',
            ],[
                'parent_id' => 0,
                'title'     => 'Tedarikçi',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-pert-reseller',
                'group_key' => 'op-pert',
            ],[
                'parent_id' => 0,
                'title'     => 'Satıcı',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-pert-seller',
                'group_key' => 'op-pert',
            ]
        ]; 

        $documentTypes = [
            [
                'parent_id' => 0,
                'title'     => 'Apartman Listesi',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-apartment',
                'group_key' => 'op-doc-forms',
            ],[
                'parent_id' => 0,
                'title'     => 'Period',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-period',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Talep',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-request',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Cari Ana Kart',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-client-main',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Cari',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-client',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Flat',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-flat',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Aktivite',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-calendar',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Account',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-target',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Meeting',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-meeting',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Project',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-project',
                'group_key' => 'op-doc',
            ],[
                'parent_id' => 0,
                'title'     => 'Inventory',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-inventory',
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
