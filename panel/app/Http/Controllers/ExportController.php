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
use App\Services\ExportService;
use PDF;    
use App\Models\Documents;

class ExportController extends Controller
{
    public function index(Request $request,$model,$type = null){
        $data = [];
        $headers = [];
        $filename = 'export.xlsx';
        $rowCallback = null;
        $filter = $request->all();
        if(!empty($filter)){
            foreach ($filter as $i => $item) {
                $filter[$i] = json_decode($item,true);
            }
        }
        switch($model){
            case 'clients':
                $filename = 'cariler.xlsx';
                $headers = [
                    'title'       => 'Firma Ünvanı',
                    'clicode'     => 'Cari Kodu',
                    'created_at'  => 'Oluşturulma Tarihi'
                ];
                
                $data = (array)\App\Models\Documents::tableList([
                    'filter' => array_merge($filter,[
                        ['key'=>'form-type','type'=>'=','value'=>'op-doc-client-form'],
                        ['key'=>'type','type'=>'=','value'=>'op-doc-client']
                    ])
                ])['data'];

                foreach ($data as $key => $value) {
                    $mainAttr = \json_decode($value->main_attr, true);
                    foreach($mainAttr as $ma){
                        $value->{$ma['Key']} = $ma['Value'];
                    }
                }


                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;
                   
                    switch($key){
                        case 'created_at':
                            return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            case 'documents':
                $filename = 'belgeler.xlsx';
                $headers = [
                    'relation_type' => 'Belge Tipi',
                    'file_type'     => 'Belge Başlık',
                    'relation_qnid' => 'Belge İlişki',
                    'last_status'   => 'Güncel Durum',
                    'created_at'    => 'Oluşturulma Tarihi'
                ];
                $data = (array)\App\Models\Document_files::tableList([
                    'filter' => $filter
                ])['data'];

                foreach ($data as $key => $value) {
                    $status = \json_decode($value->last_status, true);
                    $value->last_status = $status['title'] ?? '-';
                }

                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;

                    switch($key){
                        case 'relation_type':
                            switch($row['relation_type']){
                                default :
                                    return '-';
                                    break;
                                case 'op-doc-client':
                                    return 'Cari Belgesi';
                                    break;
                                case  'op-doc-offer':
                                    return 'Teklif Belgesi';
                                    break;
                            }
                            break;
                        case 'last_attempt_at':
                            return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            case 'offers':
                $filename = 'teklifler.xlsx';
                $headers = [
                    'clititle'    => 'Cari',
                    'target_type' => 'Santral',
                    'offer_type'  => 'Teklif Tipi',
                    'date'        => 'Belge Tarihi',
                    'request_id'  => 'Talep Kodu',
                    'qnid'        => 'Teklif Kodu',
                    'coal_type'   => 'Kömür Cinsi',
                    'status'      => 'Güncel Durum',
                    'created_at'  => 'Oluşturulma Tarihi'
                ];
                $data = (array)\App\Models\Documents::tableList([
                    'filter' => array_merge($filter,[
                        ['key'=>'form-type','type'=>'=','value'=>'op-doc-offer-form'],
                        ['key'=>'type','type'=>'=','value'=>'op-doc-offer'],
                        //cancelled offers are part of the historical export
                        ['key'=>'with-cancelled','type'=>'=','value'=>'1']
                    ])
                ])['data'];

                foreach ($data as $key => $value) {
                    $mainAttr = \json_decode($value->main_attr, true);
                    foreach($mainAttr as $ma){
                        $value->{$ma['Key']} = $ma['Value'];
                    }
                }

                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;
                   
                    //offers can be sparse (drafts, cancelled ones), so every branch tolerates missing keys
                    switch($key){
                        case 'offer_type':
                            return explode('**', (string)($row[$key] ?? ''))[1] ?? '';
                        break;
                        case 'status':
                            //cancellation overrides whatever the last transaction was
                            if((int)($row['document_status'] ?? 1) === 0) return 'İptal Edildi';

                            if(empty($row[$key]) || strpos($row[$key], 'doc_trans_offer_draft') !== false) return 'Taslak';

                            return explode('**', $row[$key])[1] ?? '';
                            break;
                        case 'date':
                        case 'created_at':
                            return empty($row[$key]) ? '' : \Carbon\Carbon::parse($row[$key])->format('d.m.Y');
                    }

                    return $row[$key] ?? '';
                };

                break;
            case 'requests':
                $filename = 'talepler.xlsx';
                $headers = [
                    'req_no'       => 'Talep Kodu',
                    'title'        => 'Talep Başlık',
                    'target_type'  => 'Santral',
                    'order_radius' => 'Sipariş Kapsamı',
                    'contract_start_date' => 'İhale Başlangıç',
                    'contract_end_date'   => 'İhale Bitiş',
                    'transfer_start_date' => 'Sevkiyat Başlangıç',
                    'transfer_end_date'   => 'Sevkiyat Bitiş',
                    'status'       => 'Güncel Durum',
                    'created_at'   => 'Oluşturulma Tarihi'
                ];
                
                $data = (array)\App\Models\Documents::tableList([
                    'filter' => array_merge($filter,[
                        ['key'=>'form-type','type'=>'=','value'=>'op-doc-request-form'],
                        ['key'=>'type','type'=>'=','value'=>'op-doc-request']
                    ])
                ])['data'];

                foreach ($data as $key => $value) {
                    $mainAttr = \json_decode($value->main_attr, true);
                    foreach($mainAttr as $ma){
                        $value->{$ma['Key']} = $ma['Value'];
                    }
                }


                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;
                   
                    switch($key){
                        case 'status':
                            if(empty($row[$key]) || strpos($row[$key], 'doc_trans_created') !== false) return 'Taslak';
                            
                            return explode('**', $row[$key])[1];
                            break;
                        case 'date':
                        case 'created_at':
                            if(strpos($row[$key],'/')) {
                                $date = explode('/', $row[$key]);
                                $row[$key] = $date[2].'-'.$date[1].'-'.$date[0];
                            }
                            return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            case 'notificationlogs':
                $filename = 'bildirimler.xlsx';
                $headers = [
                    'type' => 'Tip',
                    'to'   => 'Hedef',
                    'subject' => 'Konu',
                    'status' => 'Durum',
                    'last_attempt_at' => 'Tarih',
                    'detail' => 'Detay'
                ];
                
                
                $data = (array)\App\Models\NotificationLog::tableList([
                    'filter' => $filter
                ])['data'];

                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;

                    switch($key){
                        case 'status':
                            return $row[$key] == 'sent' ? 'Başarılı' : 'Başarısız';
                            break;
                        case 'last_attempt_at':
                            return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            case 'users':
                $filename = 'kullanicilar.xlsx';
                $headers = [
                    'name' => 'İsim',
                    'username' => 'Kullanıcı Adı',
                    'role_title' => 'Rol',
                    'user_status' => 'Durum',
                    'created_at' => 'Tarih',
                    'permissions' => 'Yetkiler'
                ];
               
                $data = (array)\App\Models\User::tableList([
                    'filter' => $filter
                ])['data'];

                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;

                    switch($key){
                        case 'user_status':
                            return $row[$key] == 1 ? 'Aktif' : 'Pasif';
                            break;
                        case 'created_at':
                            return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            case 'userlogs':
                $filename = 'kullanici_loglari.xlsx';
                $headers = [
                    'title' => 'Tip',
                    'form_type' => 'Belge Tipi',
                    'name' => 'İsim',
                    'email' => 'Kullanıcı',
                    'role'  => 'Rol',
                    'ip'    => 'IP Adresi',
                    'created_at' => 'Tarih',
                    'description' => 'Açıklama'
                ];
                
                
                $data = (array)\App\Models\Userlog::tableList([
                    'filter' => $filter
                ])['data'];

                $rowCallback = function ($row, $key, $label, $rowIndex, $columnIndex) {
                    $row = (array) $row;
                    if ($key === 'created_at') {
                        return \Carbon\Carbon::parse($row[$key])->format('d.m.Y H:i:s');
                    }

                    return $row[$key] ?? '';
                };
                break;
            
        }

        if (empty($data)) {
            return response()->json(['success' => false, 'msg' => 'No export data found.'], 404);
        }

        

        return (new ExportService())->exportExcel($headers, $data, $filename,$rowCallback);
    }

    

    public function offerPdf1(Request $request){
        $req = $request->all();
        if(!isset($req['id'])) return response()->json(['success' => false, 'msg' => 'Teklif ID bilgisi eksik.']);
        
        //get offer info
        $offer = (new DocumentServiceProvider())->getFormData($req['id']);
        if(!$offer) return response()->json(['success' => false, 'msg' => 'Teklif bulunamadı.']);
        




        $document = $offer['document'];
        $formRows = $offer['formFormat']['op-doc-offer-form'] ?? [];
        $formData = !empty($formRows) ? current($formRows)['entities'] ?? [] : [];

        $statusHistory = [];
        if(!empty($document->status)) {
            $statusHistory = json_decode($document->status, true);
            if(!is_array($statusHistory)) {
                $statusHistory = [];
            }
        }

        $latestStatus = '';
        if(!empty($statusHistory)) {
            $lastItem = end($statusHistory);
            $latestStatus = $lastItem['op_title'] ?? $lastItem['title'] ?? $lastItem['note'] ?? '';
        }

        //cancellation overrides whatever the last transaction was
        if((int)($document->document_status ?? 1) === 0) {
            $latestStatus = 'İptal Edildi';
        }

        $data = [
            'document'     => $document,
            'form'         => $formData,
            'latestStatus' => $latestStatus,
            'statusHistory'=> $statusHistory,
        ];

        $filename = 'offer-'.$document->qnid.'.pdf';
        $pdf = PDF::loadView('exports.offer', $data)->setPaper('a4', 'portrait');
        return $pdf->download($filename);
    }

    /**
     * Create a ZIP containing the generated offer PDF and additional attachments, then download it.
     */
    public function offerPdf(Request $request)
    {
        $req = $request->all();
        if (!isset($req['id'])) return response()->json(['success' => false, 'msg' => 'Teklif ID bilgisi eksik.']);

        $offer = (new DocumentServiceProvider())->getFormData($req['id']);
        if (!$offer) return response()->json(['success' => false, 'msg' => 'Teklif bulunamadı.']);


        
        $document = $offer['document'];
        $formRows = $offer['formFormat']['op-doc-offer-form'] ?? [];
        $formData = !empty($formRows) ? current($formRows)['entities'] ?? [] : [];
        $statusHistory = json_decode($offer['document']->status, true) ?? [];
        if (!empty($document->status)) {
            $statusHistory = json_decode($document->status, true);
            if (!is_array($statusHistory)) $statusHistory = [];
        }

        $latestStatus = '';
        if (!empty($statusHistory)) {
            $lastItem = end($statusHistory);
            $latestStatus = $lastItem['op_title'] ?? $lastItem['title'] ?? $lastItem['note'] ?? '';
        }

        //cancellation overrides whatever the last transaction was
        if ((int)($document->document_status ?? 1) === 0) {
            $latestStatus = 'İptal Edildi';
        }

        $data = [
            'document' => $document,
            'form' => $formData,
            'latestStatus' => $latestStatus,
            'statusHistory' => $statusHistory,
        ];

        // prepare zip
        $zipPath = sys_get_temp_dir() . '/offer_bundle_' . ($document->qnid ?? 'doc') . '_' . time() . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['success' => false, 'msg' => 'ZIP oluşturulamadı.']);
        }

        // generate PDF content
        //if offer is not file-based, generate PDF from view. If it is file-based, just download files
        //offer_type is absent on sparse offers (drafts, cancelled ones)
        if(strpos((string)($formData['offer_type'] ?? ''),'op-doc-offer-file') === false) {
            $pdf = PDF::loadView('exports.offer', $data)->setPaper('a4', 'portrait');
            $pdfContent = $pdf->output();

            // add generated PDF
            $zip->addFromString('offer-' . ($document->qnid ?? 'doc') . '.pdf', $pdfContent);
        } 
        
        // try to include attachments listed on the document if available
        $attachments = [];
        $enc = new \App\Providers\EncryptionProvider();
        foreach ($formData as $key => $value) {
            if (strpos($key, 'offer_otherdocs_file**') !== false) {
                $value = json_decode($value, true);
                
                $path = storage_path('app/public') . '/documents/' . $enc->decrypt($value['description']);
                $attachments[] = $path;
                if (file_exists($path)) {
                    $zip->addFile($path, basename($path));
                }
            }
        }
        $zip->close();

        return response()->download($zipPath, 'offer-' . ($document->qnid ?? 'doc') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Generate Malzeme Kabul Formu PDF for an order.
     * POST /v1/export/malzeme-kabul
     * Body: { qnid, items: [{prod_code, title, unit, quantity, accept_quantity}], transfer_mode }
     */
    public function malzemeKabul(Request $request)
    {
        $req = $request->all();
        if (empty($req['qnid'])) {
            return response()->json(['success' => false, 'msg' => 'Sipariş bilgisi eksik.']);
        }

        $provider = new DocumentServiceProvider();
        $formData = $provider->getFormData($req['qnid']);
        if (!$formData || empty($formData['document'])) {
            return response()->json(['success' => false, 'msg' => 'Sipariş bulunamadı.']);
        }

        $document = $formData['document'];
        $formRows = $formData['formFormat']['op-doc-order-form'] ?? [];
        $entities = !empty($formRows) ? current($formRows)['entities'] ?? [] : [];

        $items = json_decode($req['items'] ?? '[]', true);

        // Always use DB entities as primary — they have the real saved values.
        // Only override with request if the user actually typed something.
        $orderDesc = $entities['order_desc'] ?? '';
        $imalatci = $entities['imalatci_firma_adi'] ?? '';
        if (!empty(trim($req['order_desc'] ?? ''))) $orderDesc = $req['order_desc'];
        if (!empty(trim($req['imalatci_firma_adi'] ?? ''))) $imalatci = $req['imalatci_firma_adi'];

        $data = [
            'buying_no'         => $req['buying_no'] ?? $entities['buying_no'] ?? '',
            'order_no'          => $req['order_no'] ?? $entities['order_no'] ?? '',
            'created_at'        => $req['created_at'] ?? $entities['created_at'] ?? '',
            'order_desc'        => $orderDesc,
            'imalatci_firma_adi'=> $imalatci,
            'items'             => $items,
        ];

        $filename = 'malzeme-kabul-' . ($data['order_no'] ?: $document->qnid) . '.pdf';
        $pdf = PDF::loadView('exports.malzeme-kabul', $data)->setPaper('a4', 'portrait');
        return $pdf->download($filename);
    }
}