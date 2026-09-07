<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$orderNo = '3510004400-1';
$order = DB::selectOne("SELECT d.id, d.qnid, d.grp_code, so.op_key as type FROM documents d JOIN sys_options so ON so.id=d.type_id WHERE d.status=1 AND EXISTS (SELECT 1 FROM sys_con_entities se JOIN sys_con_ops so2 ON so2.id=se.conn_id WHERE so2.main_id=d.id AND se.entity_tag='order_no' AND se.entity_value=?) LIMIT 1", [$orderNo]);
if(!$order){ echo "order $orderNo not found\n"; exit; }
echo "Order $orderNo: id={$order->id} qnid={$order->qnid} grp={$order->grp_code} type={$order->type}\n";
$trans = DB::select("SELECT t.id, so.op_key, so.title, t.created_at, ul.description as note FROM transactions t JOIN sys_options so ON so.id=t.type_id JOIN user_logs ul ON ul.id=t.log_id WHERE t.target_id=? AND t.op_id=0 ORDER BY t.id ASC", [$order->id]);
echo "\nOrder transactions (op0):\n";
foreach($trans as $t){
  echo sprintf("%4d | %-30s | %-20s | %s | note=%.80s\n", $t->id, $t->op_key, $t->title, $t->created_at, $t->note??'');
}
$trans1 = DB::select("SELECT t.id, so.op_key, t.created_at FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=? AND t.op_id=1 ORDER BY t.id DESC LIMIT 5", [$order->id]);
echo "\nOrder file transactions (op1) for order id? (should be none, files are separate):\n";
foreach($trans1 as $t) echo "  {$t->id} {$t->op_key} {$t->created_at}\n";

echo "\nFiles for order $orderNo (via relation):\n";
$files = DB::select("SELECT df.qnid, df.id as fid, df.created_at, df.status, se.entity_tag, d.qnid as rel_qnid, (SELECT so.op_key FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=df.id AND t.op_id=1 ORDER BY t.id DESC LIMIT 1) as last_op, (SELECT t.created_at FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=df.id AND t.op_id=1 ORDER BY t.id DESC LIMIT 1) as last_at FROM document_files df JOIN sys_con_entities se ON se.entity_value=df.id::text AND se.table_tag='document_files' JOIN documents d ON d.id=df.relation_id::int WHERE (d.id=? OR d.parent_id=?) AND df.status IN (0,1) ORDER BY df.id DESC", [$order->id, $order->id]);
foreach($files as $f){
  echo sprintf(" file %s | fid %3d | rel %s | status %d | last_op %-22s | last_at %s | created %s | tag %s\n", substr($f->qnid,0,8), $f->fid, substr($f->rel_qnid,0,8), $f->status, $f->last_op??'none', $f->last_at??'?', $f->created_at, $f->entity_tag);
  $hist = DB::select("SELECT t.id, so.op_key, t.created_at FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=? AND t.op_id=1 ORDER BY t.id ASC", [$f->fid]);
  foreach($hist as $h) echo "    -> trans {$h->id} {$h->op_key} {$h->created_at}\n";
}

echo "\nNotification source check (ReportServiceProvider):\n";
$GLOBALS['SYS_CODE']='GDZ';
session(['person_id'=>1]); session(['type_key'=>'op-pert-admin']); session(['currentStatus'=>null]);
$rsp = (new App\Providers\ReportServiceProvider())->getAdminNotifications('tedarik-06',10);
echo "tedarik-06 (Kalite Onayi) total: ".($rsp['total']??0)." \n";
foreach($rsp['data']??[] as $o) echo "  order ".$o->id." | ".substr($o->main_attr??'',0,80)."\n";
$rsp4 = (new App\Providers\ReportServiceProvider())->getAdminNotifications('tedarik-04',10);
echo "tedarik-04 (Dosya Onaylandi) total: ".($rsp4['total']??0)."\n";
foreach($rsp4['data']??[] as $r) echo "  file ".$r->id." | group ".$r->group_key." | last_status ".substr($r->last_status??'',0,60)." | created ".$r->created_at."\n";
