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

        //first remove old ones
        User_logs::where('relation', 'documents')
            ->where('relation_id', $relationId)
            ->where('type_id', $connType)
            ->delete();

        $errorsData = [];
        foreach(['500','404'] as $code){
            foreach ($result['errors'.$code] as $key => $value) {
                User_logs::create([
                    'user_id'     => 0,
                    'sys_code'    => '-',
                    'relation'    => 'documents',
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
                $errorsData[] = [
                    'Status Code' => $code,
                    'Error URL' => $value,
                    'Root URL' => $baseUrl,
                ];
            }
        }

        // Generate Excel file
        $fileName = 'link_errors_' . date('Y-m-d_H-i-s') . '.xlsx';
        $filePath = storage_path('app/temp/' . $fileName);
        // Use PhpSpreadsheet to create Excel with custom headers
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set custom headers
        $sheet->setCellValue('A1', 'Durum Kodu');
        $sheet->setCellValue('B1', 'Hata URL');
        $sheet->setCellValue('C1', 'Kök URL');
        
        // Add data rows
        $row = 2;
        foreach ($errorsData as $data) {
            $sheet->setCellValue('A' . $row, $data['Status Code']);
            $sheet->setCellValue('B' . $row, $data['Error URL']);
            $sheet->setCellValue('C' . $row, $data['Root URL']);
            $row++;
        }
        
        // Save the file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filePath);

        // Set file path for email attachment
        $this->excelFile = $filePath;
        
        //also send email here if needed
        if(/*!env('IS_TEST') && */(count($errorsData) > 0)){
            $email = env('MAIL_TO_ADDRESS', 'kadir@kontent.com.tr'); // Define the email recipient
            try {
                Mail::html($baseUrl.' için link kontrolleri tamamlandı.', function ($message) use ($email, $filePath) {
                    $message->from(env('MAIL_FROM_ADDRESS'),'Kontent Kontrol Sistemi');
                    $message->to($email);
                    $message->subject('Kontent Kontrol Sisteminde Tespit Edilen Hatalar..');
                    $message->attach($filePath);
                });

                // Log success if needed
                $this->infoPrint('Email sent successfully for ' . $baseUrl);
            } catch (\Exception $e) {
                // Log the error
                $this->infoPrint('Failed to send email: ' . $e->getMessage());
            }
        }

        return $result;
    }
}
