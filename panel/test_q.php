<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$orderId = 325;
$sql = "
            SELECT i.qnid as file_qnid, i.id as file_id, i.status as file_status, i.created_at as file_created_at,
                   i.description as file_desc,
                   sf.title as file_type, sf.op_key as file_type_key, se.entity_tag,
                   d.qnid as relation_qnid, dt.op_key as relation_type,
                    (SELECT json_build_object('op_key', sot.op_key, 'title', sot.title, 'name', p.name, 'note', t.description, 'created_at', t.created_at)
                     FROM transactions t
                     JOIN sys_options sot ON sot.id = t.type_id
                     JOIN user_logs ul ON ul.id = t.log_id
                     JOIN users u ON u.id = ul.user_id
                     JOIN persons p ON p.id = u.person_id
                     WHERE t.target_id = i.id AND t.op_id = 1 ORDER BY t.id DESC LIMIT 1) as last_status
            FROM document_files i
            JOIN sys_con_entities se ON se.entity_value = i.id::text AND se.table_tag = 'document_files'
            JOIN documents d ON d.id = i.relation_id::int
            JOIN sys_options dt ON dt.id = d.type_id
            JOIN sys_options sf ON sf.op_key = 'op-'||split_part(se.entity_tag,'**',1)
            WHERE ((d.id = ? AND dt.op_key = 'op-doc-order') OR (d.parent_id = ? AND dt.op_key = 'op-doc-order-item'))
              AND se.entity_tag NOT LIKE '%item_images_file%'
              AND i.description != ''
            ORDER BY i.created_at DESC
";
try {
    $res = Illuminate\Support\Facades\DB::select($sql, [$orderId, $orderId]);
    echo "OK count=".count($res).PHP_EOL;
    foreach($res as $r) echo $r->file_qnid.PHP_EOL;
} catch (Throwable $e) {
    echo "ERR: ".$e->getMessage().PHP_EOL;
}
