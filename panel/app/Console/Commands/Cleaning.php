<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Jobs\ClearVisitors;;

class Cleaning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleaning:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This cron will clear not exited visitors';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        (new ClearVisitors())->clearV();
    }
}
