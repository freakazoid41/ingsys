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
                        'title'     => 'Mail Bildirimleri',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-00',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'İhale Atama Maili (Yükleniciler İçin)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-00-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Yüklenici İhale Düzenledi (İş Birimleri İçin)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-00-04',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Dosya Eklendi Bildirimi (İş Birimleri İçin)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-00-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Dosya Durum Bildirimleri (Yükleniciler İçin)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-00-03',
                            ],
                        ]
                    ], [
                        'parent_id' => 0,
                        'title'     => 'Sistem Bildirimleri',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-07',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Ofis Bildirim Grubu',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-07-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'İsg Ofis Bildirim Grubu',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-07-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Yönetici Bildirim Grubu',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-07-03',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Yüklenici Bildirim Grubu',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-07-04',
                            ]
                        ]
                    ],
                    [
                        'parent_id' => 0,
                        'title'     => 'İhaleler',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-01',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'İhale Oluşturma / Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-03',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'İhale Deaktif Etme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-13',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Ana Bilgi Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-04',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Yüklenici Atama',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-05',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Yüklenici Girdi Alanları Güncelleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-06',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'ISG Girdi Alanları Güncelleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-07',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'ISG Girdi Alanları Görüntüleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-08',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Ünvan Dosya Gereksinimleri Alanları Güncelleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-09',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Ünvan Dosya Gereksinimleri Alanları Görüntüleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-10',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Personel Girişi Yapabilme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-11',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Alt Yüklenici Ekleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-01-12',
                            ]
                        ]
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Yüklenici Kartları',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-02',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-02-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Oluşturma / Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-02-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Deaktif Etme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-02-03',
                            ]
                        ]
                    ],[
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
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Sistem Kullanıcı Kartları',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-04',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-04-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Oluşturma / Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-04-02',
                            ]
                        ]
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Sistem Dosyaları',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-05',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-05-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Düzenleme (Onay / Red)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-05-02',
                            ]
                        ]
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Vardiyalar',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-06',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-06-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-06-02',
                            ]
                        ]
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Raporlar',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-08',
                        'childs'    => []
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Sistem Değişkenler',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-09',
                        'childs'    => []
                    ],[
                        'parent_id' => 0,
                        'title'     => 'Hakediş',
                        'ttitle'    => 'Perm_con_ops',
                        'ctitle'    => 'type_id',
                        'group_key' => 'op-perm',
                        'op_key'    => 'per-10',
                        'childs'    => [
                            [
                                'parent_id' => 0,
                                'title'     => 'Listeleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-01',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Düzenleme',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-02',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Kontrol İşlemleri',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-03',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Dosya Girişi (Hakediş Kaydetme)',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-04',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'Bordro Hazırla / Fark Analizi',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-05',
                            ],[
                                'parent_id' => 0,
                                'title'     => 'İcmal Raporu',
                                'ttitle'    => 'Perm_con_ops',
                                'ctitle'    => 'type_id',
                                'op_key'    => 'per-10-06',
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
                'title'     => 'İhale Ekleme',
                'ttitle'    => 'User_logs',
                'ctitle'    => 'log_id',
                'group_key' => 'op-logs',
                'op_key'    => 'log-tender-add',
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
                'title'     => 'Daireler Ana Form',
                'ttitle'    => 'Documents',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-link-form',
                'group_key' => 'op-doc-forms',
            ],
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
                'title'     => 'Bayii',
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
                'title'     => 'Link',
                'ttitle'    => '-',
                'ctitle'    => 'type_id',
                'op_key'    => 'op-doc-link',
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
