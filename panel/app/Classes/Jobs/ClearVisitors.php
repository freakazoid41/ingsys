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
        $filename = "list.json";
        $filePath = public_path('/mailfiles/'.$filename);
        if(file_exists($filePath)){
            try {
                $this->infoPrint('Mail Raporu Bulundu.');

                //decode and get info for every facility
                $data = json_decode(file_get_contents($filePath));
                
                $headers = [['İsim','Telefon','E-Posta','Tesis','Sisteme Giriş','Tesise Giriş']];
                if(!empty($data)){
                    foreach($data as $k => $list){
                        $facility = (new DocumentServiceProvider())->getFormData($k);
                        $entities = array_values(array_values($facility['formFormat'])[0])[0]['entities'];
                        $this->infoPrint($entities['title'].' İçin Mail raporu Gönderiliyor..');
                        //here create excel file 
                        $spreadsheet = new Spreadsheet();
                        $activeWorksheet = new Worksheet($spreadsheet, 'Export');
                        $spreadsheet->addSheet($activeWorksheet, 0);
                        //person list
                        $list = array_merge($headers,$list);

                        
                        //add datas to excel file
                        for($i = 0; $i < count($list) ; $i++){
                            $row = $list[$i];
                            for($j = 0; $j < count($row); $j++){
                                $activeWorksheet->setCellValue([$j+1,$i+1],strval($row[$j]));
                            }
                        }
                        //autoresize columns
                        foreach ($activeWorksheet->getColumnIterator() as $column) {
                            $activeWorksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                        }
                                    
                        $writer    = new Xlsx($spreadsheet);
                        $excelPath = public_path('/mailfiles/'.$k.'.xlsx');
                        $writer->save($excelPath);

                        //here send mail foreach contact
                        foreach($entities as $ekey => $email){
                            if(strpos($ekey,'contact_mail**facilitycontactgroup') !== false){
                                $this->infoPrint($entities['title'].' İçin Mail raporu '.$email.' Adresine Gönderiliyor..');
                                
                                Mail::html('<div>'.date("d.m.Y", strtotime("-1 day")).' Tarihli çıkış yapmayan ziyaretçi listesi ektedir.</div>', function ($message) use ($excelPath,$email){
                                    $message->from(env('MAIL_FROM_ADDRESS'),'Seç Ziyaretçi Sistemi');
                                    $message->to($email);
                                    $message->subject('Kontent Kontrol Sisteminden çıkış yapmayan ziyaretçiler');
                                    $message->attach($excelPath);
                                });
                            }
                        }

                        //here clear excel file
                        @unlink($excelPath);
                    }
                }
             }catch (\Exception $e) {
                $this->infoPrint('message :=> '.$e->getMessage().PHP_EOL);
            } catch (Throwable $e) {
                $this->infoPrint('message :=> '.$e->getMessage().PHP_EOL);
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
            $jsonExit = [];
            $this->infoPrint(count($data). ' Adet giriş yapmış kayıt bulundu..');
            foreach ($data as $visitor) {
                if(strpos($visitor->main_attr,'exited_at') === false){
                    $this->infoPrint($visitor->id. ' Çıkış yapmamış kapatılıyor....');

                    
                    /*$visitor->main_attr = str_replace('""','"',$visitor->main_attr);
                    $visitor->main_attr = str_replace('"{','{',$visitor->main_attr);
                    $visitor->main_attr = str_replace('}"','}',$visitor->main_attr);*/
                       
                    //here add person to list
                    $visitor->main_attr = json_decode($visitor->main_attr);
                    //here clear for mssql,
                    if($visitor->main_attr === null){
                        //check if is fixable (json is valid accualy but mssql is braking a bit (because string methods))
                        $visitor->main_attr = repair_json_string($visitor->main_attr ?? '{}',true);
                        if($visitor->main_attr == false) continue;
                    }
                    
                    foreach($visitor->main_attr as $a){
                        $visitor->{$a->Key} = $a->Value;
                    }

                    if(!isset($jsonExit[$visitor->facility_id])) $jsonExit[$visitor->facility_id] = [];


                    $data      = [
                        $visitor->name,
                        $visitor->phone,
                        $visitor->email ?? '-',
                        $visitor->facility,
                        $visitor->created_at,
                        $visitor->entered_at
                    ];
                    $notExit[]                         = $data;
                    $jsonExit[$visitor->facility_id][] = $data;
                   
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

            

            $jsonName = "list.json";
            $jsonPath = public_path('/mailfiles/'.$jsonName);
            if(file_exists($jsonPath)){
                unlink($jsonPath);
                $this->infoPrint('Eski JSON Raporu Temizlendi.');
            }

            if(count($notExit) > 0){
                $this->infoPrint('Mail Raporu Oluşturuluyor.');
                //here create mail file
                file_put_contents($jsonPath, json_encode($jsonExit));

                $this->infoPrint('Mail Raporu Oluşturuldu.');
            }


        }catch(Exception $e){
            $this->infoPrint($e->getMessage());
        }catch(Throwable $e){
            $this->infoPrint($e->getMessage());
        }
    }
}
