<?php

namespace App\Classes\Jobs;

use App\Models\Sys_options;
use App\Models\Documents;
use App\Providers\DocumentServiceProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Mail;

class ClearVisitors extends \App\Classes\Utils
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {   
       
    }

    public function sendReport(){
        $filename = "list.xlsx";
        $filePath = public_path($filename);
        if(file_exists($filePath)){
            $this->infoPrint('Mail Raporu Bulundu.');
            try {
                $this->infoPrint('Mail Gönderiliyor...');
                Mail::html('<div>'.date("d.m.Y", strtotime("-1 day")).' Tarihli çıkış yapmayan kullanıcı listesi ekteki gibidir.</div>', function ($message) use ($filePath){
                    $message->from(env('MAIL_FROM_ADDRESS'),'Seç Ziyaretçi Sistemi');
                    $message->to(env('MAIL_TO_ADDRESS'));
                    $message->subject('Sistemden çıkış yapmayan kullanıcılar');
                    $message->attach($filePath);
                });
            }catch (\Exception $e) {
                $this->infoPrint('cannot send from :=> '.env('MAIL_FROM_ADDRESS').PHP_EOL);
                $this->infoPrint('cannot send to :=> '.env('MAIL_TO_ADDRESS').PHP_EOL);
                $this->infoPrint('message :=> '.$th->getMessage().PHP_EOL);
            } catch (Throwable $e) {
                $this->infoPrint('cannot send from :=> '.env('MAIL_FROM_ADDRESS').PHP_EOL);
                $this->infoPrint('cannot send to :=> '.env('MAIL_TO_ADDRESS').PHP_EOL);
                $this->infoPrint('message :=> '.$th->getMessage().PHP_EOL);
            }
        }else{
            $this->infoPrint('Mail Raporu Bulunamadı..!.');
        }
    }

    public function clearV(){
        
        try{

            date_default_timezone_set('Europe/Istanbul');
            //get data
            $data = (new Documents())->tableList(['filter' => [
                    [
                        'key'   => 'form-type',
                        'type'  => '=',
                        'value' => 'op-doc-visit-form'
                    ],[
                        'key'   => 'type',
                        'type'  => '=',
                        'value' => 'op-doc-visit'
                    ],[
                        'key'   => 'day-period',
                        'type'  => '=',
                        'value' => date('Y-m-d')
                    ]
                ]
            ])['data'];

            $notExit = [
                ['İsim','Telefon','E-Posta','Tesis','Sisteme Giriş','Tesise Giriş']
            ];
            $this->infoPrint(count($data). ' Adet giriş yapmış kayıt bulundu..');
            foreach ($data as $visitor) {
                if(strpos($visitor->main_attr,'exited_at') === false){
                    $this->infoPrint($visitor->id. ' Çıkış yapmamış kapatılıyor....');

                    //here add person to list
                    $visitor->main_attr = json_decode($visitor->main_attr);
                    foreach($visitor->main_attr as $a){
                        $visitor->{$a->Key} = $a->Value;
                    }


                    $notExit[] = [
                        $visitor->name,
                        $visitor->phone,
                        $visitor->email,
                        $visitor->facility,
                        $visitor->created_at,
                        $visitor->entered_at
                    ];
                   
                    //not yet exited..
                    //add exit info
                    $res = (new DocumentServiceProvider())->registerContent($visitor->id,[
                        "dynamicF" => [
                            "new**".$visitor->entity_conn_id => [
                                "entities" => [
                                    "exited_at" => date('Y-m-d H:i')
                                ],
                                "tag"      => "op-doc-visit-form"
                            ]
                        ]
                    ],[],'persons');
                    if($res['success']){
                        $this->infoPrint($visitor->id. ' Kapatıldı..');
                    }else{
                        $this->infoPrint($visitor->id. ' Hata Oldu !!');
                        $this->infoPrint($res);
                    }
                }
            }

            //clear old file
            $filename = "list.xlsx";
            $filePath = public_path($filename);
            if(file_exists($filePath)){
                unlink($filePath);
                $this->infoPrint('Eski Mail Raporu Temizlendi.');
            }

            if(count($notExit) > 0){
                $this->infoPrint('Mail Raporu Oluşturuluyor.');
                //here create mail file
                $spreadsheet = new Spreadsheet();
                $activeWorksheet = new Worksheet($spreadsheet, 'Export');
                $spreadsheet->addSheet($activeWorksheet, 0);
                
                
                //add datas
                for($i = 0; $i < count($notExit) ; $i++){
                    $row = $notExit[$i];
                    for($j = 0; $j < count($row); $j++){
                        $activeWorksheet->setCellValue([$j+1,$i+1],strval($row[$j]));
                    }
                }
                //autoresize columns
                foreach ($activeWorksheet->getColumnIterator() as $column) {
                    $activeWorksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
                            
                $writer = new Xlsx($spreadsheet);
                
                
                $writer->save($filePath);
                $this->infoPrint('Mail Raporu Oluşturuldu.');
            }


        }catch(Exception $e){
            $this->infoPrint($e->getMessage());
        }catch(Throwable $e){
            $this->infoPrint($e->getMessage());
        }
    }
}
