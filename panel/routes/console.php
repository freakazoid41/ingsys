<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Persons;
use App\Models\User;

//Schedule::command('currency:cron')->hourly();


Artisan::command('fix:users', function () {
    $this->comment('Script Started..');
    $user = User::where('email','aoguzhanyukaci@hotmail.com')->first();
    $document    = Persons::where('id',$user->person_id)->first();
    
    $user->delete();  
    $document->delete();

    $user = User::where('email','aoguzhanyukaci@gmail.com')->first();
    $document    = Persons::where('id',$user->person_id)->first();
    
    $user->delete();  
    $document->delete();
    $this->comment('script Ended');
})->purpose('Update Live Database');