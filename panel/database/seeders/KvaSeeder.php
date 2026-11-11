<?php

namespace Database\Seeders;

use App\Models\Sys_options;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KvaSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ---- Document Type ----
            ['op_key'=>'op-doc-kva', 'group_key'=>'op-doc', 'title'=>'Güç (kVA) Tanımı', 'ttitle'=>'-', 'ctitle'=>'type_id', 'parent_id'=>0],
            // ---- Form ----
            ['op_key'=>'op-doc-kva-form', 'group_key'=>'op-doc-forms', 'title'=>'Güç (kVA) Formu', 'ttitle'=>'Documents', 'ctitle'=>'type_id', 'parent_id'=>0],
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

        // ---- Standard power ratings as editable documents (users manage the rest from UI) ----
        $typeId = Sys_options::where('op_key', 'op-doc-kva')->value('id');
        $formId = Sys_options::where('op_key', 'op-doc-kva-form')->value('id');
        $mainId = Sys_options::where(['ctitle' => 'sub_type_id', 'op_key' => 'form-main'])->value('id');
        $standards = ['50','100','160','250','400','630','1000','1250','1600'];
        foreach ($standards as $s) {
            $exists = DB::table('sys_con_entities')->where('entity_tag', 'code')->where('entity_value', $s)
                ->whereIn('conn_id', DB::table('sys_con_ops')->where('type_id', $formId)->select('id'))->exists();
            if ($exists) { echo "kVA {$s} exists, skip..\n"; continue; }
            $now = now();
            $qnid = (string) Str::uuid();
            $docId = DB::table('documents')->insertGetId([
                'type_id' => $typeId, 'person_id' => 'system', 'title' => $s.' kVA',
                'grp_code' => $GLOBALS['SYS_CODE'] ?? 'GDZ', 'qnid' => $qnid,
                'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $conn = DB::table('sys_con_ops')->insertGetId([
                'main_id' => $docId, 'conn_id' => 0, 'type_id' => $formId,
                'sub_type_id' => $mainId, 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (['qnid' => $qnid, 'title' => 'KVA', 'code' => $s] as $tag => $val) {
                DB::table('sys_con_entities')->insert([
                    'conn_id' => $conn, 'entity_tag' => $tag, 'entity_value' => (string) $val,
                    'table_tag' => 'sys_con_ops', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            echo "kVA {$s} Added..\n";
        }

        // legacy dict rows (unused — documents are the source of truth now)
        DB::table('sys_options')->where('group_key', 'op-kva')->delete();

        echo "KvaSeeder done.\n";
    }
}
