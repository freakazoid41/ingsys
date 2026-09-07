<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Document_files;

echo "--- recent file transactions (any) ---\n";
$recent = DB::select("SELECT t.id, t.target_id, so.op_key, t.created_at, df.qnid, df.description FROM transactions t JOIN sys_options so ON so.id=t.type_id JOIN document_files df ON df.id=t.target_id WHERE t.op_id=1 ORDER BY t.id DESC LIMIT 10");
foreach($recent as $r){
  echo sprintf("%5d | qnid=%s | op=%-22s | at=%s | desc=%s\n", $r->id, substr($r->qnid,0,8), $r->op_key, $r->created_at, substr($r->description??'',0,20));
}

echo "\n--- recent user_logs for files ---\n";
$logs = DB::select("SELECT ul.id, ul.created_at, so.op_key, so.title, ul.relation, ul.relation_id, u.email FROM user_logs ul JOIN sys_options so ON so.id=ul.type_id LEFT JOIN users u ON u.id=ul.user_id WHERE so.op_key LIKE 'doc_file_%' OR so.op_key LIKE 'log-file%' ORDER BY ul.id DESC LIMIT 10");
foreach($logs as $l){
  echo sprintf("%5d | %s | %-22s | rel=%s:%s | user=%s\n", $l->id, $l->created_at, $l->op_key, $l->relation, $l->relation_id, $l->email??'');
}

echo "\n--- check specific file that kbbozat refreshed? Look at document_files recent created ---\n";
$dfs = DB::select("SELECT qnid, relation_id, created_at, description, status FROM document_files ORDER BY id DESC LIMIT 10");
foreach($dfs as $d){
  $rel = DB::table('documents')->where('id',(int)$d->relation_id)->first();
  $grp = $rel ? $rel->grp_code : '?';
  $qn = $rel ? $rel->qnid : '?';
  echo sprintf("file %s | rel=%s (%s) grp=%s | status=%s | at=%s\n", substr($d->qnid,0,8), $d->relation_id, substr($qn,0,8), $grp, $d->status, $d->created_at);
  // check last transaction
  $last = DB::selectOne("SELECT so.op_key FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=? AND t.op_id=1 ORDER BY t.id DESC LIMIT 1", [$d->id ?? 0]);
  // need file id, not qnid
  $fid = DB::table('document_files')->where('qnid',$d->qnid)->value('id');
  $last2 = DB::selectOne("SELECT so.op_key FROM transactions t JOIN sys_options so ON so.id=t.type_id WHERE t.target_id=? AND t.op_id=1 ORDER BY t.id DESC LIMIT 1", [$fid]);
  echo "  last op: ".($last2->op_key??'none')."\n";
}

echo "\n--- check notification for tedarik-03 directly via tableList with session for kadir BOTH ---\n";
// Simulate kadir session
session(['person_id' => 1]);
session(['type_key' => 'op-pert-admin']);
session(['currentStatus' => null]); // admin has no clientQnidList restriction? Let's check
$GLOBALS['SYS_CODE'] = 'GDZ';
$files = Document_files::tableList(['filter'=>[['key'=>'file_status','type'=>'=','value'=>'doc_file_waiting']]]);
echo "as kadir BOTH, waiting count: ".count($files['data']??[])."\n";
foreach($files['data']??[] as $f){
  echo "  ".$f->id." | ".$f->group_key." | ".$f->created_at."\n";
}

// Also try as kbbozat
session(['person_id' => '2']);
session(['type_key' => 'op-pert-reseller']);
// need to set currentStatus with clientQnidList for kbbozat
$person = DB::table('persons')->where('id',2)->first();
$cRows = DB::select("SELECT se.entity_value as qnid FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id JOIN sys_options sp ON sp.id=so.type_id WHERE so.main_id=? AND sp.op_key='op-doc-user-client-form' AND se.entity_tag LIKE '%cliid**%'", [$person->id]);
$qnids = array_map(fn($r)=>$r->qnid, $cRows);
session(['currentStatus'=>['clientQnidList'=>$qnids]]);
$GLOBALS['SYS_CODE'] = 'GDZ';
$files2 = Document_files::tableList(['filter'=>[['key'=>'file_status','type'=>'=','value'=>'doc_file_waiting']]]);
echo "as kbbozat GDZ reseller, waiting count: ".count($files2['data']??[])."\n";
foreach($files2['data']??[] as $f){
  echo "  ".$f->id." | ".$f->group_key." | ".$f->created_at."\n";
}
