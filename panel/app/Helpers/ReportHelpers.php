<?php 
if(!function_exists('getMonths')){
    function getMonths(){
        return ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    }
}

if(!function_exists('infoPrint')){
    function infoPrint($message) {
        Log::channel('cron')->info($message);
        print_r(date('Y-m-d H:i').' => '.PHP_EOL);
        print_r($data);
        print_r(PHP_EOL);
    }
}