<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\System_settings;
use App\Classes\Currencies\TCMB;
use Illuminate\Support\Facades\DB;
use App\Classes\DataSources\Navision;

class CurrencyCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //first check system main currency information
        /*$settings = System_settings::first();
        $this->clearOld();
        //now run cur target class
        switch ($settings->cur_target){
            case 'TCMB':
                (new TCMB($settings->main_cur))->fetchCur();
                break;
            case 'NAVISION':
                (new Navision())->fetchCurrencies($settings->main_cur);
                break;
        }*/
        $this->clearOld();
        (new TCMB(env('SYS_CUR')))->fetchCur();

    }

    public function clearOld(){
        //ve are clearing records older the 15 days
        DB::select("delete from currencies ");
    }

    
}
