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

    
}