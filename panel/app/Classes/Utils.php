<?php

namespace App\Classes;
use Illuminate\Support\Facades\Log;


class Utils
{
    public function infoPrint($message) {
        Log::channel('cron')->info($message);
        print_r(date('Y-m-d H:i').' => '.PHP_EOL);
        print_r($message);
        print_r(PHP_EOL);
    }
}
