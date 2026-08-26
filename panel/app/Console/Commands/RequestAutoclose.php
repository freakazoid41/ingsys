<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RequestAutoclose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'request:autoclose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close stale requests that are eligible for auto-close.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('RequestAutoclose started.');

        // TODO: implement actual autoclose logic.
        // Example placeholder logic below; adjust table/column names to your application.
        try {
            $data = (array)\App\Models\Documents::tableList([
                'filter' => [
                    ['key'=>'form-type','type'=>'=','value'=>'op-doc-request-form'],
                    ['key'=>'type','type'=>'=','value'=>'op-doc-request'],
                    //['key'=>'main_attr','type'=>'like','value'=> 'contract_end_date'],
                    ['key'=>'today-ended','type'=>'like','value'=> '{"Key" : "contract_end_date", "Value" : "'.date('d/m/Y').'"']
                ]
            ])['data'];

            if(!empty($data) && strpos($data[0]->status, 'doc_trans_request_end') === false) {
                //set status to ended
                (new \App\Providers\DocumentServiceProvider())->setStatus($data[0]->id,'doc_trans_request_end','Talep Süresi Bitti');
            } else {
                $this->info('No requests found that are eligible for autoclose.');
            }

        } catch (\Exception $e) {
            $this->error('RequestAutoclose failed: ' . $e->getMessage().' '.$e->getFile().' '.$e->getLine());
            return 1;
        }

        $this->info('RequestAutoclose completed.');
        return 0;
    }
}
