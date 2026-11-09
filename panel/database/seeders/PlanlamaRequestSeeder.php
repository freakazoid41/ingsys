<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;

// Malzeme Planlama — first form: request header + sub products (order-pattern, planlama keys).
class PlanlamaRequestSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ---- Document Types ----
            ['op_key'=>'op-doc-request', 'group_key'=>'op-doc', 'title'=>'İhtiyaç Listesi', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-request-item', 'group_key'=>'op-doc', 'title'=>'İhtiyaç Kalemi', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Forms ----
            ['op_key'=>'op-doc-request-form', 'group_key'=>'op-doc-forms', 'title'=>'İhtiyaç Listesi Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-request-item-form', 'group_key'=>'op-doc-forms', 'title'=>'İhtiyaç Kalem Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Minimal status machine ----
            ['op_key'=>'doc_trans_request_created', 'group_key'=>'op-trans-op-doc-request', 'title'=>'İhtiyaç Oluşturuldu', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- Talep Dökümanı approval chain ----
            // birth (auto on create by Talep Yükleme) -> Müdürlük onay/red (05-02) -> Standardizasyon onayı (05-04, SAP'e gönderim)
            ['op_key'=>'tr-request-man-awaiting', 'group_key'=>'op-trans-op-doc-requestf', 'title'=>'Talep Listesi Müdürlük Onayına Gönderildi', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'tr-request-man-approved', 'group_key'=>'op-trans-op-doc-requestf', 'title'=>'Talep Listesi Müdürlük Tarafından Onaylandı', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'tr-request-man-rejected', 'group_key'=>'op-trans-op-doc-requestf', 'title'=>'Talep Listesi Müdürlük Tarafından Reddedildi', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'tr-request-std-approved', 'group_key'=>'op-trans-op-doc-requestf', 'title'=>'Talep Listesi Standardizasyon Tarafından Onaylandı', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],

            // ---- Talep Dökümanı (supplier answer to an İhtiyaç Listesi) ----
            ['op_key'=>'op-doc-requestf', 'group_key'=>'op-doc', 'title'=>'Talep Dökümanı', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-requestf-item', 'group_key'=>'op-doc', 'title'=>'Talep Dökümanı Kalemi', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-requestf-form', 'group_key'=>'op-doc-forms', 'title'=>'Talep Dökümanı Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-requestf-item-form', 'group_key'=>'op-doc-forms', 'title'=>'Talep Dökümanı Kalem Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'doc_trans_requestf_created', 'group_key'=>'op-trans-op-doc-requestf', 'title'=>'Talep Dökümanı Oluşturuldu', 'ttitle'=>'Transactions', 'ctitle'=>'type_id', 'parent_id'=>0],
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

        echo "PlanlamaRequestSeeder done.\n";
    }
}
