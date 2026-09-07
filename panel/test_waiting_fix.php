<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Document_files;

$GLOBALS['SYS_CODE'] = 'GDZ';
session(['person_id' => 1]);
session(['type_key' => 'op-pert-admin']);
session(['currentStatus' => null]);

$files = Document_files::tableList(['filter'=>[['key'=>'file_status','type'=>'=','value'=>'doc_file_waiting,doc_file_refreshed']]]);
echo "combined waiting+refreshed count: ".count($files['data']??[])."\n";
foreach($files['data']??[] as $f){
  echo "  ".$f->id." | ".$f->group_key." | ".$f->created_at." | ".$f->last_status."\n";
}

// Also test via ReportServiceProvider
$rsp = (new App\Providers\ReportServiceProvider())->getAdminNotifications('tedarik-03',10);
echo "ReportServiceProvider tedarik-03 total: ".($rsp['total']??0)." data: ".count($rsp['data']??[])."\n";
foreach($rsp['data']??[] as $r){
  echo "  rsp qnid=".$r->id." | group_key=".$r->group_key." | created=".$r->created_at." | last_status=".$r->last_status."\n";
}
