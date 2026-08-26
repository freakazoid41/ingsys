<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\RetryNotificationSend;
use App\Console\Commands\RequestAutoclose;
use App\Console\Commands\CleanActiveSessions;
use App\Console\Commands\SendTestMail;
use App\Console\Commands\SendTestSms;
use App\Console\Commands\VerifyRecaptcha;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SendTestMail::class,
        SendTestSms::class,
        RetryNotificationSend::class,
        RequestAutoclose::class,
        CleanActiveSessions::class,
        VerifyRecaptcha::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    {
        $schedule->command('request:autoclose')->dailyAt('01:00');
        $schedule->command('active-sessions:clean')->dailyAt('02:00');

        // temp uploads are linked to a document only when the form is saved; files selected
        // but never saved stay in storage/app/public/temp/ and are purged here after 24h.
        $schedule->call(function () {
            cleanupTempFiles();
        })->dailyAt('03:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        //
    }
}
