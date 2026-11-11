<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ---- Document Type ----
            ['op_key'=>'op-doc-product', 'group_key'=>'op-doc', 'title'=>'Ürün', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- Form ----
            ['op_key'=>'op-doc-product-form', 'group_key'=>'op-doc-forms', 'title'=>'Ürün Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
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

        echo "ProductSeeder done.\n";
    }
}
