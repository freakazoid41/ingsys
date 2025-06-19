<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use App\Providers\ReportServiceProvider;
use App\Providers\DocumentServiceProvider;
use PDF;    
use App\Models\Documents;

class ExportController extends Controller
{
    public function index(Request $request,$model,$type = null){
        $data = [];


        switch($model){
            case 'documents':
                $data = (new DocumentServiceProvider())->getExportData($type)['data'];
                break;
            case 'transactions':
                $data = (new DocumentServiceProvider())->getTransExportData($type)['data'];
                break;
        }
        
        try {
            $spreadsheet = new Spreadsheet();
            $activeWorksheet = new Worksheet($spreadsheet, 'Export');
            $spreadsheet->addSheet($activeWorksheet, 0);
            
            
            //add datas
            for($i = 0; $i < count($data) ; $i++){
                $row = $data[$i];
                for($j = 0; $j < count($row); $j++){
                    $activeWorksheet->setCellValue([$j+1,$i+1],strval($row[$j]));
                }
            }
            foreach ($activeWorksheet->getColumnIterator() as $column) {
                $activeWorksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
                        
            $writer = new Xlsx($spreadsheet);
            $filename = "export.xlsx";

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=".$filename);
            $writer->save('php://output');
            
        } catch (\Throwable $th) {
            print_r($th->getMessage());
        }

        die;
    }

    function reporticmal(Request $request){
        
        $req = $request->all();

       

        $dates = explode(' - ',$req['dates']);
        $dates[0] = implode('-',array_reverse(explode('/',$dates[0])));
        $dates[1] = implode('-',array_reverse(explode('/',$dates[1])));
        
        try {

            // get main accounts 
            $accounts = Documents::tableList(json_decode('{"filter":[
                                                                {"key":"form-type","type":"=","value":"op-doc-target-form"},
                                                                {"key":"type","type":"=","value":"op-doc-target"},
                                                                {"key":"balance-date","type":"=","value":"'.implode('/',$dates).'"}
                                                            ]}',true));
            
            //get income and outcomes
            $incomes  = (new ReportServiceProvider())->dashboardInfo('income',$dates);
            $outcomes = (new ReportServiceProvider())->dashboardInfo('outcome',$dates);

            

            // Create new Spreadsheet object
            $spreadsheet = new Spreadsheet();
            $activeWorksheet = new Worksheet($spreadsheet, 'Rapor');
            $spreadsheet->addSheet($activeWorksheet, 0);
            

            $data = [
                'dates'    => implode(' - ',explode(' => ',$req['dates'])),
                'accounts' => $accounts,
                'incomes'  => $incomes,
                'outcomes' => $outcomes,
                //'note'     => $req['note']
            ];

           $pdf = PDF::loadView('exports.icmal', $data);
            return $pdf->download('icmal.pdf');
            //return view('exports.icmal', $data); 
        } catch (\Throwable $th) {
            print_r($th->getMessage());
        }
        die;
    }
}