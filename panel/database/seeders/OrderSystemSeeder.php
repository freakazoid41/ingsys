<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;

class OrderSystemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ---- Document Types ----
            ['op_key'=>'op-doc-order', 'group_key'=>'op-doc', 'title'=>'Sipariş', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-order-item', 'group_key'=>'op-doc', 'title'=>'Sipariş Kalemi', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-order-serial', 'group_key'=>'op-doc', 'title'=>'Seri Numarası', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Forms ----
            ['op_key'=>'op-doc-order-form', 'group_key'=>'op-doc-forms', 'title'=>'Sipariş Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-order-item-form', 'group_key'=>'op-doc-forms', 'title'=>'Sipariş Kalem Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-order-serial-form', 'group_key'=>'op-doc-forms', 'title'=>'Seri Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Order status machine (op-trans-op-doc-order) ----
            // Order lifecycle: created -> transfer_sent (client) -> admin review -> approved/rejected
            ['op_key'=>'doc_trans_order_created', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Sipariş Oluşturuldu', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_order_transfer_sent', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Dosyalar Kontrol Ediliyor', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_order_ready_for_shipment', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Sipariş Sevke Hazır', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_order_approved', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Kalite Onayı Verildi', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_order_rejected', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Sipariş Reddedildi', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_order_files_rejected', 'group_key'=>'op-trans-op-doc-order', 'title'=>'Reddedilen Dosyalar Mevcut', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- File type dictionary (op-file-types) for the 4 file groups you confirmed ----
            ['op_key'=>'op-transfer_kabul_file', 'group_key'=>'op-file-types', 'title'=>'Malzeme Kabul Formu', 'ttitle'=>'document_files', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-transfer_cins_file', 'group_key'=>'op-file-types', 'title'=>'Malzeme Cins-Miktar Kabul Formu', 'ttitle'=>'document_files', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-item_test_file', 'group_key'=>'op-file-types', 'title'=>'Ürün Test Dokümanı', 'ttitle'=>'document_files', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-item_images_file', 'group_key'=>'op-file-types', 'title'=>'Ürün Görsel', 'ttitle'=>'document_files', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Logs for new flows ----
            ['op_key'=>'log-order-update', 'group_key'=>'op-logs', 'title'=>'Sipariş Güncelleme', 'ttitle'=>'User_logs', 'ctitle'=>'log_id', 'parent_id'=>0],
            ['op_key'=>'log-transfer-update', 'group_key'=>'op-logs', 'title'=>'Transfer Güncelleme', 'ttitle'=>'User_logs', 'ctitle'=>'log_id', 'parent_id'=>0],
        ];

        foreach ($items as $item) {
            $exists = Sys_options::where('op_key', $item['op_key'])->first();
            if (empty($exists)) {
                $row = new Sys_options();
                $row->parent_id = $item['parent_id'];
                $row->title = $item['title'];
                $row->ttitle = $item['ttitle'];
                $row->ctitle = $item['ctitle'];
                $row->op_key = $item['op_key'];
                $row->group_key = $item['group_key'];
                $row->save();
                echo $item['title'] . " Added..\n";
            } else {
                echo $item['title'] . " already exists, skip..\n";
            }
        }

        // Ensure file types file list is complete
        echo "OrderSystemSeeder done.\n";
    }
}
