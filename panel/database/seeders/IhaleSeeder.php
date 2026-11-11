<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;

class IhaleSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ---- Document Type ----
            ['op_key'=>'op-doc-ihale', 'group_key'=>'op-doc', 'title'=>'İhale', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- Form ----
            ['op_key'=>'op-doc-ihale-form', 'group_key'=>'op-doc-forms', 'title'=>'İhale Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- File type (İlgili Teknik Şartname) ----
            ['op_key'=>'op-ihale_spec_file', 'group_key'=>'op-file-types', 'title'=>'İhale Teknik Şartname Dosyası', 'ttitle'=>'document_files', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- Teklif (offer) ----
            ['op_key'=>'op-doc-ihale-offer', 'group_key'=>'op-doc', 'title'=>'İhale Teklifi', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-ihale-offer-form', 'group_key'=>'op-doc-forms', 'title'=>'İhale Teklif Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
            ['op_key'=>'op-doc-ihale-offer-item-form', 'group_key'=>'op-doc-forms', 'title'=>'İhale Teklif Kalem Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
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

        echo "IhaleSeeder done.\n";
    }
}
