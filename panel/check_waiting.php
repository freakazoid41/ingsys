<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Document_files;

$files = Document_files::tableList(['filter'=>[['key'=>'file_status','type'=>'=','value'=>'doc_file_waiting']]]);
echo "waiting files count: ".count($files['data']??[]).PHP_EOL;
foreach(array_slice($files['data']??[],0,5) as $f){
  echo "file qnid=".$f->id." | group_key=".$f->group_key." | created_at=".$f->created_at." | last_status=".substr($f->last_status??'',0,120).PHP_EOL;
  $df = Document_files::where('qnid',$f->id)->first();
  if($df){
    $rel = DB::table('documents')->where('id',(int)$df->relation_id)->first();
    if($rel){
      $order = DB::table('documents')->where('id', $rel->parent_id !=0 ? $rel->parent_id : $rel->id)->first();
      if($order){
        $sys = DB::selectOne("SELECT se.entity_value as v FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id WHERE so.main_id=? AND se.entity_tag='sys_code' LIMIT 1", [$order->id]);
        $spec = DB::selectOne("SELECT se.entity_value as v FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id WHERE so.main_id=? AND se.entity_tag='spec_code' LIMIT 1", [$order->id]);
        $ordNo = DB::selectOne("SELECT se.entity_value as v FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id WHERE so.main_id=? AND se.entity_tag='order_no' LIMIT 1", [$order->id]);
        echo "  -> order qnid=".$order->qnid." | order_no=".($ordNo->v??'')." | spec=".($spec->v??'')." | sys=".($sys->v??'')." | grp=".$order->grp_code.PHP_EOL;
      }
    }
  }
}

echo "\n--- kadir BUKRS check ---\n";
$notifRows = (new App\Providers\ReportServiceProvider())->getAdminNotifications('tedarik-03',10);
echo "tedarik-03 for kadir (current auth maybe guest): total=".($notifRows['total']??0)." data count=".count($notifRows['data']??[]).PHP_EOL;
foreach($notifRows['data']??[] as $r){
  echo "  qnid=".$r->id." | group_key=".$r->group_key." | created=".$r->created_at.PHP_EOL;
}

// Check recent file transactions for kbbozat refresh
echo "\n--- recent doc_file_waiting transactions ---\n";
$recent = DB::select("SELECT t.id, t.target_id as file_id, so.op_key, t.created_at, df.qnid as file_qnid FROM transactions t JOIN sys_options so ON so.id=t.type_id JOIN document_files df ON df.id=t.target_id WHERE so.op_key='doc_file_waiting' ORDER BY t.id DESC LIMIT 5");
foreach($recent as $r){
  echo "trans ".$r->id." | file_qnid=".$r->file_qnid." | file_id=".$r->file_id." | op=".$r->op_key." | at=".$r->created_at.PHP_EOL;
}
