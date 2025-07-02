<?php

namespace App\Classes\Currencies;
use App\Models\Currencies;
use App\Models\Sys_options;
class TCMB extends \App\Classes\Utils
{
    /**
     * Create a new class instance.
     */
    public function __construct($mainCur)
    {   
        // Check if functions are exist in system
        if (!function_exists('simplexml_load_string') || !function_exists('curl_init')) {
            info('Simplexml extension missing.');
            return false;
        }

        $this->mainCur = $mainCur;
        
        $this->infoPrint('Merkez Bankası Çalıştı , Ana Kur : '.$this->mainCur);
    }

    public function fetchCur(){
        try {
            $tcmbMirror = 'https://www.tcmb.gov.tr/kurlar/today.xml';
            $curl = curl_init($tcmbMirror);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, $tcmbMirror);

            $this->curData = curl_exec($curl);
            Currencies::truncate();
            //get value for listed currencies depending our main currency for system
            foreach (explode(',',env('SYS_CUR_INFO')) as $c){
               
                $insertData = new Currencies();
                $insertData->main_cur_id   = Sys_options::where('code',$this->mainCur)->first()->id;
                $insertData->main_cur      = $this->mainCur;
                $insertData->target_cur_id = Sys_options::where('code',$c)->first()->id;
                $insertData->target_cur    = $c;
                $insertData->amount        = $this->curConverter($this->mainCur,$c,1);
                $insertData->save();

                $this->infoPrint('Ana Kur : '.$this->mainCur.' Hedef Kur : '.$c.' => '.$this->curConverter($this->mainCur,$c,1));
            }
        } catch (Exception $e) {
            $this->infoPrint('cron')->info('Unhandled exception, maybe from cURL' . $e->getMessage());
            return false;
        }
    }

    function curConverter($from = 'TRY', $to = 'USD', $val = 1){
        $CurrencyData = [
            'from' => 1,
            'to' => 1
        ];

        // decode xml data
        $Currencies = simplexml_load_string($this->curData);

        // Search all values on data
        foreach ($Currencies->Currency as $Currency) {
            if ($from == $Currency['CurrencyCode']) $CurrencyData['from'] = $Currency->BanknoteSelling;
            if ($to == $Currency['CurrencyCode']) $CurrencyData['to'] = $Currency->BanknoteSelling;
        }

        // calculate and return rounded value
        return round(($CurrencyData['to'] / $CurrencyData['from']) * $val, 10);
    }
}
