<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Jobs\CheckLinks;

class Checking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checklinks:cron {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron will check links';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lib      = new CheckLinks();
       
        $id       = $this->argument('id') ?? '0';

        $linkList = $lib->getList($id);
        foreach($linkList['data'] as $link){
            $data = json_decode($link->main_attr);
            $keyValue = [];
            foreach ($data as $item) {
                $keyValue[$item->Key] = $item->Value;
            }
            //put to queue
            if (!\DB::table('jobs')->where('payload', 'like', '%checklinks:cron ' . $link->id . '%')->exists()) {
                \Artisan::queue('checklinks:cron ' . $link->id);
            }

            //$lib->checkLink($keyValue['root_url'],$link->realId);
        }



    }
}
