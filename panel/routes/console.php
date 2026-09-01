<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Persons;
use App\Models\User;

//Schedule::command('currency:cron')->hourly();


Artisan::command('check:db', function () {
    $this->comment('Script Started..');
   
    $this->comment('script Ended');
})->purpose('Update Live Database');