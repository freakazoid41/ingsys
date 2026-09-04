<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Providers\PersonsServiceProvider;
use App\Providers\DocumentServiceProvider;
use Illuminate\Support\Facades\DB;

class ReportServiceProvider extends ServiceProvider
{
    public function __construct() {
       
    }

    public function getAdminNotifications($notifKey){
        $personsProvider = new PersonsServiceProvider();
        $data = [];
        //means our user is in the group or not
        $permittedUsers = $personsProvider->getNotificationUsers($notifKey,session('person_id'));
        if(empty($permittedUsers)) return [];
        switch ($notifKey) {
            //tedarikçi kayıt başvuruları
            case 'notif-00':
                //lets get awaiting user request list here
                $data = $this->getAwaitingUserRequests();
                # code...
                break;
            //Tedarikçi bilgilerini güncelledi vs..
            case 'notif-01':
                //lets get client is upload new file
                $data = $this->getAwaitingClientFiles();
                break;
            case 'notif-02':
                //lets get client offers
                $data = $this->getOffers();
                break;
            case 'notif-03':
                //lets get client offers
                $data = $this->getOffers('doc_trans_offer_revised');
                break;
            default:
                # code...
                break;
        }

        return $data;
    }

    public function getUserNotifications($notifKey){
        switch ($notifKey) {
            //tedarikçi kayıt başvuruları
            case 'offer-revision-request':
                if(session('type_key') != 'op-pert-reseller') return [];
                $data = $this->getOffers('doc_trans_offer_revision');
                break;
            default:
                # code...
                break;
        }

        return $data;
    }

    public function getAwaitingUserRequests(){
        $data = \App\Models\User::tableList([
            'filter' => [
                ['key' => 'user_status', 'type' => '=','value' => '-1']
            ]
        ]);

        return $data['data'];
    }

    public function getAwaitingClientFiles(){
        $data = (new DocumentServiceProvider())->getAwaitingClientFiles();
        return $data['data'];
    }

    public function getOffers($type = 'null'){
        $data = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'transactions', 'type' => '=','value' => $type],
                ['key' => 'type', 'type' => '=','value' => 'op-doc-offer'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-offer-form'],
            ]
        ]);

        return $data['data'];
    }

    public function dashboardInfo($type,$addional){
        switch($type){
            case 'topstats':
                return $this->dashboardTopInfo();
            break;
            case 'monthlyoffers':
                return $this->dashboardMonthlyOffers($addional ?? true);
            break;
            case 'monthlydistribution':
                return $this->dashboardMonthlyDistribution();
            break;
            case 'importantinfo':
                return $this->dashboardImportantInfo();
            break;
            case 'tedarik-stats':
                return $this->tedarikStats();
            break;
            case 'tedarik-orders':
                return $this->tedarikRecentOrders();
            break;
            case 'tedarik-status':
                return $this->tedarikStatusBreakdown();
            break;
            default:
                abort(404, 'Unknown dashboard type: '.$type);
        }
    }

    /**
     * Returns monthly distribution grouped by target_type from main_attr.
     * Each item: ['name' => string, 'totalRequests' => int, 'totalOffers' => int, 'approvedOffers' => int]
     */
    public function dashboardMonthlyDistribution(){
        $monthFilter = '-'.date('m').'-';

        // get monthly requests
        $requests = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'monthly', 'type' => 'date','value' => $monthFilter],
                ['key' => 'type', 'type' => '=','value' => 'op-doc-request'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-request-form'],
            ]
        ])['data'];

        // get monthly offers (cancelled ones included, this is a historical total)
        $offers = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'monthly', 'type' => 'date','value' => $monthFilter],
                ['key' => 'type', 'type' => '=','value' => 'op-doc-offer'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-offer-form'],
                ['key' => 'with-cancelled', 'type' => '=','value' => '1'],
            ]
        ])['data'];


        $returnData = [];

        foreach ($offers as $row) {
            $raw = $row->main_attr ?? '';
            $key = '-';
            if(function_exists('mb_stripos')){
                if(mb_stripos($raw,'Çates',0,'UTF-8') !== false) $key = 'Çates';
                if(mb_stripos($raw,'ADM',0,'UTF-8') !== false) $key = 'ADM';
                if(mb_stripos($raw,'Her İkisi',0,'UTF-8') !== false) $key = 'Her İkisi';
            } else {
                if(strpos($raw,'Çates') !== false) $key = 'Çates';
                if(strpos($raw,'ADM') !== false) $key = 'ADM';
                if(strpos($raw,'Her İkisi') !== false) $key = 'Her İkisi';
            }

            if(!isset($returnData[$key])){
                $returnData[$key] = ['name' => $key, 'totalRequests' => 0, 'totalOffers' => 0];
            }
            $returnData[$key]['totalOffers'] ++;
        }

        foreach ($requests as $row) {
            $raw = $row->main_attr ?? '';
            $key = '-';
            if(function_exists('mb_stripos')){
                if(mb_stripos($raw,'Çates',0,'UTF-8') !== false) $key = 'Çates';
                if(mb_stripos($raw,'ADM',0,'UTF-8') !== false) $key = 'ADM';
                if(mb_stripos($raw,'Her İkisi',0,'UTF-8') !== false) $key = 'Her İkisi';
            } else {
                if(strpos($raw,'Çates') !== false) $key = 'Çates';
                if(strpos($raw,'ADM') !== false) $key = 'ADM';
                if(strpos($raw,'Her İkisi') !== false) $key = 'Her İkisi';
            }

            if(!isset($returnData[$key])){
                $returnData[$key] = ['name' => $key, 'totalRequests' => 0, 'totalOffers' => 0];
            }
            $returnData[$key]['totalRequests'] ++;
        }

        return $returnData;
    }

    public function dashboardTopInfo(){
        //here get monthly requests
        $requests = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'monthly', 'type' => 'date','value' => '-'.date('m').'-'],
                ['key' => 'type', 'type' => '=','value' => 'op-doc-request'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-request-form'],
            ]
        ])['data'];


        //cancelled offers belong in the historical total, so this query opts in
        $offers = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'monthly', 'type' => 'date','value' => '-'.date('m').'-'],
                ['key' => 'type', 'type' => '=','value' => 'op-doc-offer'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-offer-form'],
                ['key' => 'with-cancelled', 'type' => '=','value' => '1'],
            ]
        ])['data'];

        $approvedOffer = [];
        $todaysOffer   = [];
        foreach ($offers as $row) {
            //an offer cancelled after approval must stop counting as approved
            if((int)($row->document_status ?? 1) !== 0 && strpos((string)$row->status,'doc_trans_offer_approved') !== false){
                $approvedOffer[] = $row;
            }
            if(strpos($row->created_at,date('Y-m-d')) !== false){
                $todaysOffer[] = $row;
            }
        }

        $awaitingOffers = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'type', 'type' => '=','value' => 'op-doc-offer'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-offer-form'],
                ['key' => 'status-null' , 'type' => 'like' , 'value' => '%%']
            ]
        ])['data'];

        $clients = \App\Models\Documents::tableList([
            'filter' => [
                ['key' => 'type', 'type' => '=','value' => 'op-doc-client'],
                ['key' => 'form-type', 'type' => '=','value' => 'op-doc-client-form'],
            ]
        ])['data'];
            
       


       return [
            'totalRequests' => count($requests),
            'totalOffers' => count($offers),
            'approvedOffers' => count($approvedOffer),    
            'awaitingOffers' => count($awaitingOffers),
            'todaysOffers' => count($todaysOffer),
            'allClients' => count($clients)
       ];
    }

    public function dashboardMonthlyOffers($isMonthly = true){
        $filter = [
            ['key' => 'type', 'type' => '=','value' => 'op-doc-offer'],
            ['key' => 'form-type', 'type' => '=','value' => 'op-doc-offer-form'],
            //the chart shows a dedicated "İptal Edildi" slice, so cancelled offers are fetched too
            ['key' => 'with-cancelled', 'type' => '=','value' => '1'],
        ];

        if($isMonthly === true) $filter[] = ['key' => 'monthly', 'type' => 'date','value' => '-'.date('m').'-'];

        $offers = \App\Models\Documents::tableList([
            'filter' => $filter
        ])['data'];

        // Build initial groups from sys_options for offer transaction statuses
        $ops = \App\Models\Sys_options::where(function($q){
            $q->where('op_key', 'like', 'doc_trans_offer_%')
                ->orWhere('group_key', 'like', 'op-trans%');
        })->get();

        // define color palette for offer statuses
        $colorMap = [
            'doc_trans_offer_sended' => '#0d6efd',
            'doc_trans_offer_review' => '#ffc107',
            'doc_trans_offer_revision' => '#ff9800',
            'doc_trans_offer_revised' => '#17a2b8',
            'doc_trans_offer_approved' => '#198754',
            'doc_trans_offer_rejected' => '#e74c3c',
        ];

        $groups = [];
        foreach($ops as $op){
            // only keep relevant keys that start with doc_trans_offer_
            if(strpos($op->op_key, 'doc_trans_offer_') === 0){
                // skip draft key - we'll merge drafts/empty into 'sended'
                if($op->op_key === 'doc_trans_offer_draft') continue;
                $groups[$op->op_key] = [
                    'label' => $op->title,
                    'value' => 0,
                    'color' => isset($colorMap[$op->op_key]) ? $colorMap[$op->op_key] : null
                ];
            }
        }

        // ensure 'sended' exists (we'll map empty/draft statuses to this)
        if(!isset($groups['doc_trans_offer_sended'])){
            $s = \App\Models\Sys_options::where('op_key','doc_trans_offer_sended')->first();
            $groups['doc_trans_offer_sended'] = [
                'label' => $s? $s->title : 'Teklif Gönderidi',
                'value' => 0,
                'color' => isset($colorMap['doc_trans_offer_sended']) ? $colorMap['doc_trans_offer_sended'] : null
            ];
        } else {
            // ensure sended has a color if in colorMap
            if(isset($colorMap['doc_trans_offer_sended'])){
                $groups['doc_trans_offer_sended']['color'] = $colorMap['doc_trans_offer_sended'];
            }
        }

        //cancellation is tracked on documents.status rather than as a transaction, so the group is
        //synthesised here instead of coming from sys_options like the others
        $groups['cancelled'] = [
            'label' => 'İptal Edildi',
            'value' => 0,
            'color' => '#6c757d'
        ];

        // Count offers per status key. status field can contain the op_key.
        foreach($offers as $row){
            //cancellation overrides whatever the last transaction was
            if((int)($row->document_status ?? 1) === 0){
                $groups['cancelled']['value']++;
                continue;
            }

            $status = trim((string)($row->status ?? ''));

            // map empty or draft statuses to 'sended'
            if($status === '' || strpos($status, 'doc_trans_offer_draft') !== false){
                $status = 'doc_trans_offer_sended';
            }

            $matched = false;
            foreach($groups as $key => &$g){
                if($status !== '' && strpos($status, $key) !== false){
                    $g['value']++;
                    $matched = true;
                    break;
                }
            }
            unset($g);

            // if no specific match, but a general sended group exists, increment it
            if(!$matched){
                if(isset($groups['doc_trans_offer_sended'])){
                    $groups['doc_trans_offer_sended']['value']++;
                }
            }
        }

        // If no groups found, fallback to grouping by status raw value
        if(empty($groups)){
            $counts = [];
            foreach($offers as $row){
                $k = $row->status ?? 'unknown';
                if(!isset($counts[$k])) $counts[$k] = 0;
                $counts[$k]++;
            }
            $out = [];
            foreach($counts as $k=>$v){
                $out[] = ['label' => $k, 'value' => $v, 'color' => null];
            }
            return $out;
        }

        // Return as array of label/value objects
        $result = [];
        foreach($groups as $k => $v){
            $result[] = ['label' => $v['label'], 'value' => $v['value'], 'key' => $k, 'color' => $v['color']];
        }

        return $result;
    }

    public function dashboardImportantInfo(){
        $events = [];

        $monthToken = '-'.date('m').'-';

        $queries = [
            // requests
            ['type' => 'op-doc-request', 'form' => 'op-doc-request-form', 'attr' => 'contract_end_date', 'label' => 'Sözleşme Bitişi'],
            ['type' => 'op-doc-request', 'form' => 'op-doc-request-form', 'attr' => 'contract_start_date', 'label' => 'Sözleşme Başlangıcı'],
            ['type' => 'op-doc-request', 'form' => 'op-doc-request-form', 'attr' => 'transfer_end_date', 'label' => 'Sevkiyat Bitişi'],
            ['type' => 'op-doc-request', 'form' => 'op-doc-request-form', 'attr' => 'transfer_start_date', 'label' => 'Sevkiyat Başlangıcı'],
            // offers
            ['type' => 'op-doc-offer', 'form' => 'op-doc-offer-form', 'attr' => 'contract_end_date', 'label' => 'Sözleşme Bitişi'],
            ['type' => 'op-doc-offer', 'form' => 'op-doc-offer-form', 'attr' => 'transfer_start_date', 'label' => 'Sevkiyat Başlangıcı'],
            ['type' => 'op-doc-offer', 'form' => 'op-doc-offer-form', 'attr' => 'transfer_end_date', 'label' => 'Sevkiyat Bitişi'],
        ];

        foreach ($queries as $q) {
            $rows = \App\Models\Documents::tableList([
                'filter' => [
                    ['key' => 'attr', 'type' => $q['attr'], 'value' => '/'.date('m').'/'],
                    ['key' => 'type', 'type' => '=', 'value' => $q['type']],
                    ['key' => 'form-type', 'type' => '=', 'value' => $q['form']],
                ]
            ])['data'];

            foreach ($rows as $row) {
                $dateVal = null;
                $title = null;

                // try parse main_attr
                $main = $row->main_attr ?? '';
                if($main){
                    $decoded = @json_decode($main, true);
                    if(!is_array($decoded)){
                        // try unserialize
                        try{
                            $decoded = @unserialize($main);
                        } catch (\Throwable $e){
                            $decoded = null;
                        }
                    }
                    if(is_array($decoded)){
                        foreach($decoded as $m){
                            if((isset($m['Key']) && $m['Key'] == $q['attr']) || (isset($m['Key']) && strtolower($m['Key']) == strtolower($q['attr']))){
                                $dateVal = $m['Value'];
                            }


                           
                            if(isset($m['Key']) && in_array($m['Key'], ['title'])){
                                $title = $m['Value'];
                            }

                            if(isset($m['Key']) && in_array($m['Key'], ['req_no','offer_no'])){
                                $docNo = $m['Value'];
                            }
                        }
                    }
                }
                
                // fallback to specific fields
                if(!$title){
                    $title = $row->title ?? ($row->clititle ?? null);
                }

                if(!$dateVal){
                    // try to pick a date-like column
                    if(!empty($row->{$q['attr']})) $dateVal = $row->{$q['attr']};
                    elseif(!empty($row->contract_end_date)) $dateVal = $row->contract_end_date;
                    elseif(!empty($row->created_at)) $dateVal = $row->created_at;
                }

                $label = ($q['type'] === 'op-doc-offer') ? 'Teklif' : 'Talep';
                //$docNo = $row->doc_no ?? $row->id ?? '';

                $text = trim(($label . ' #' . $docNo . ($title ? ' — ' . $title : '') . ' — ' . $q['label']));

                // format date to Y-m-d if possible
                $date = null;
                if($dateVal){
                    $date = substr($dateVal,0,10);
                }

                $events[] = [
                    'text' => $text,
                    'date' => $date,
                    'type' => $q['attr'],
                    'event' => $q['type'],
                    'doc_id' => $row->id ?? null
                ];
            }
        }

        // sort events by date ascending (nulls last)
        usort($events, function($a,$b){
            if(empty($a['date']) && empty($b['date'])) return 0;
            if(empty($a['date'])) return 1;
            if(empty($b['date'])) return -1;
            return strcmp($a['date'],$b['date']);
        });

        return $events;
    }

    // ─────────────────────────────────────────────────────────
    // TEDARIK DASHBOARD METHODS
    // ─────────────────────────────────────────────────────────

    private function getResellerLifnrs(){
        if(session('type_key') !== 'op-pert-reseller') return [];
        $clientQnids = session('currentStatus')['clientQnidList'] ?? [];
        if(empty($clientQnids)) return [];
        $qnidIn = "'".implode("','", array_map('noInject', $clientQnids))."'";
        $lifRows = DB::select("SELECT se.entity_value as lifnr FROM sys_con_entities se INNER JOIN sys_con_ops so ON so.id = se.conn_id INNER JOIN documents d2 ON d2.id = so.main_id WHERE d2.qnid IN ($qnidIn) AND se.entity_tag = 'lifnr' AND se.table_tag = 'sys_con_ops'");
        return array_values(array_filter(array_map(fn($r)=> trim($r->lifnr ?? ''), $lifRows), fn($v)=> $v !== ''));
    }

    private function resellerOrderWhere($lifnrs){
        if(empty($lifnrs)) return " and 1=0 ";
        $lifIn = "'".implode("','", array_map('noInject', $lifnrs))."'";
        return " and (
            exists (select 1 from sys_con_entities se2 join sys_con_ops so2 on so2.id=se2.conn_id where so2.main_id=i.id and se2.entity_tag='spec_code' and se2.entity_value in ($lifIn))
            or exists (select 1 from sys_con_entities se3 join sys_con_ops so3 on so3.id=se3.conn_id join documents d3 on d3.id=so3.main_id where d3.parent_id=i.id and se3.entity_tag='spec_code' and se3.entity_value in ($lifIn))
        ) ";
    }

    public function tedarikStats(){
        $lifnrs = $this->getResellerLifnrs();
        $whereLif = $this->resellerOrderWhere($lifnrs);

        $totalOrders = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0 $whereLif
        ")->cnt ?? 0;

        $rejectedFiles = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            and (select so2.op_key from transactions t inner join sys_options so2 on so2.id=t.type_id where t.target_id=i.id and so2.group_key='op-trans-op-doc-order' order by t.id desc limit 1) = 'doc_trans_order_files_rejected'
            $whereLif
        ")->cnt ?? 0;

        $pendingFiles = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            and (select so2.op_key from transactions t inner join sys_options so2 on so2.id=t.type_id where t.target_id=i.id and so2.group_key='op-trans-op-doc-order' order by t.id desc limit 1) = 'doc_trans_order_transfer_sent'
            $whereLif
        ")->cnt ?? 0;

        $approvedOrders = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            and (select so2.op_key from transactions t inner join sys_options so2 on so2.id=t.type_id where t.target_id=i.id and so2.group_key='op-trans-op-doc-order' order by t.id desc limit 1) = 'doc_trans_order_approved'
            $whereLif
        ")->cnt ?? 0;

        $totalItems = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join documents d2 on d2.id=i.parent_id
            inner join sys_con_ops so on so.main_id=d2.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order-item' and i.status=1
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            $whereLif
        ")->cnt ?? 0;

        $todayOrders = DB::selectOne("SELECT count(*) as cnt FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and i.created_at >= '".date('Y-m-d 00:00:00')."'
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            $whereLif
        ")->cnt ?? 0;

        return [
            'totalOrders'   => (int)$totalOrders,
            'pendingFiles'  => (int)$pendingFiles,
            'rejectedFiles' => (int)$rejectedFiles,
            'approvedOrders'=> (int)$approvedOrders,
            'totalItems'    => (int)$totalItems,
            'todayOrders'   => (int)$todayOrders,
        ];
    }

    public function tedarikRecentOrders(){
        $lifnrs = $this->getResellerLifnrs();
        $whereLif = $this->resellerOrderWhere($lifnrs);

        $orders = DB::select("SELECT i.qnid as id, i.id as main_id, i.title, i.created_at,
            i.status as document_status,
            (select so2.op_key || '**' || so2.title || '**' || t.note
                from transactions t inner join sys_options so2 on so2.id=t.type_id
                where t.target_id=i.id and so2.group_key='op-trans-op-doc-order'
                order by t.id desc limit 1) as status
            FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            $whereLif
            order by i.created_at desc limit 10
        ");

        foreach($orders as $row){
            $mainAttr = DB::select("SELECT se.entity_tag, se.entity_value FROM sys_con_entities se
                inner join sys_con_ops so on so.id=se.conn_id
                where so.main_id=? and se.table_tag='sys_con_ops'", [$row->main_id]);
            $attr = [];
            foreach($mainAttr as $a){
                $tagParts = explode('**', $a->entity_tag);
                $attr[] = ['Key' => $tagParts[0] ?? $a->entity_tag, 'Value' => $a->entity_value];
            }
            $row->main_attr = json_encode($attr);
        }

        return $orders;
    }

    public function tedarikStatusBreakdown(){
        $lifnrs = $this->getResellerLifnrs();
        $whereLif = $this->resellerOrderWhere($lifnrs);

        $rows = DB::select("SELECT
            (select so2.op_key from transactions t inner join sys_options so2 on so2.id=t.type_id
             where t.target_id=i.id and so2.group_key='op-trans-op-doc-order' order by t.id desc limit 1) as last_status,
            count(*) as cnt
            FROM documents i
            inner join sys_options sp on sp.id=i.type_id
            inner join sys_con_ops so on so.main_id=i.id
            inner join sys_con_entities se on so.id=se.conn_id
            where sp.op_key='op-doc-order' and i.status=1 and i.parent_type_id=0
            and se.entity_tag='order_no' and se.table_tag='sys_con_ops'
            $whereLif group by last_status
        ");

        $colorMap = [
            'doc_trans_order_created'      => '#6b7280',
            'doc_trans_order_transfer_sent' => '#f59e0b',
            'doc_trans_order_ready_for_shipment' => '#3b82f6',
            'doc_trans_order_approved'      => '#10b981',
            'doc_trans_order_rejected'      => '#ef4444',
            'doc_trans_order_files_rejected'=> '#f97316',
        ];
        $labelMap = [
            'doc_trans_order_created'      => 'Yeni Oluşturuldu',
            'doc_trans_order_transfer_sent' => 'Dosyalar Bekleniyor',
            'doc_trans_order_ready_for_shipment' => 'Sevke Hazır',
            'doc_trans_order_approved'      => 'Onaylandı',
            'doc_trans_order_rejected'      => 'Reddedildi',
            'doc_trans_order_files_rejected'=> 'Dosya Reddedildi',
        ];

        $result = [];
        foreach($rows as $row){
            $key = $row->last_status ?? 'doc_trans_order_created';
            $result[] = [
                'label' => $labelMap[$key] ?? $key,
                'value' => (int)$row->cnt,
                'color' => $colorMap[$key] ?? '#9ca3af',
            ];
        }
        if(empty($result)){
            $result[] = ['label' => 'Sipariş Yok', 'value' => 0, 'color' => '#d1d5db'];
        }
        return $result;
    }

}
