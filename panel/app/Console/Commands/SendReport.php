<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Jobs\ClearVisitors;;

class SendReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporting:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron will send list of the exited visitors to some mail';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        (new ClearVisitors())->sendReport();
    }
}
