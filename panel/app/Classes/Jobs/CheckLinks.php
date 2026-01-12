<?php

namespace App\Classes\Jobs;

use App\Models\Sys_options;
use App\Models\Documents;
use App\Providers\DocumentServiceProvider;
use App\Providers\ReportServiceProvider;
use Illuminate\Support\Facades\Mail;
use App\Models\User_logs;
use App\Classes\Crawler;

class CheckLinks extends \App\Classes\Utils
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {   
        $this->crawler = new Crawler();
        $this->lib     = new DocumentServiceProvider();
    }

    public function getList($id = 0){
        return (new ReportServiceProvider())->dashboardInfo('webSites' , $id);
    }

    public function checkLink($baseUrl,$relationId = 0){
        $result = $this->crawler->crawl($baseUrl);

        //save log data here
        $connType = Sys_options::select('id')->where('op_key', 'log-url-error')->first()->id;
        foreach(['500','404'] as $code){
            foreach ($result['errors'.$code] as $key => $value) {
                User_logs::create([
                    'user_id'     => 0,
                    'sys_code'    => '-',
                    'relation'    => 'documnents',
                    'relation_id' => $relationId,
                    'type_id'     => $connType,
                    'description' => json_encode(array(
                        'desc'        => $code . ' Error Link - ' . $value,
                        'url'         => $value,
                        'root_url'    => $baseUrl,
                        'relation_id' => $relationId,
                        'status_code' => (int)$code,
                    ),JSON_UNESCAPED_UNICODE)
                ]);
            }
        }
        


        //also send email here if needed
        if(!env('IS_TEST')){
            $email = env('ADMIN_EMAIL', 'admin@example.com'); // Define the email recipient
            Mail::raw('Link check completed. Errors: ' . json_encode($result['errors500']), function ($message) use ($email) {
                $message->from(env('MAIL_FROM_ADDRESS'),'Kontent Kontrol Sistemi');
                $message->to($email);
                $message->subject('Kontent Kontrol Sisteminde Tespit Edilen 500 Hataları..');
            });
        }

        return $result;
    }
}
