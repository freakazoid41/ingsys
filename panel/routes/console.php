<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reporting:cron')->dailyAt('08:00')->timezone('Europe/Istanbul');
Schedule::command('cleaning:cron')->dailyAt('23:00')->timezone('Europe/Istanbul');
//Schedule::command('currency:cron')->hourly();