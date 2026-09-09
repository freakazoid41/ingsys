<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Providers\PersonsServiceProvider;
use App\Providers\DocumentServiceProvider;

use App\Services\MailService;

class SendNotificationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;
    public bool $smsEnabled;

    public function __construct($payload = [])
    {
        $this->payload = $payload;
        if (empty($this->payload['sys_code']) && isset($GLOBALS['SYS_CODE'])) {
            $this->payload['sys_code'] = $GLOBALS['SYS_CODE'];
        }
    }

    public function handle()
    {
        $this->log('info', 'SendNotificationMailJob started', ['payload' => $this->payload]);

        if (empty($this->payload)) {
            $this->log('warning', 'SendNotificationMailJob payload is empty');
            return;
        }

        $this->personProvider = new PersonsServiceProvider();
        $this->smsEnabled     = $this->payload['sendSms'] ?? false;
        switch ($this->payload['type'] ?? '') {
            case 'offerRevision':
                $this->clientOfferGive($this->payload,true);
                break;
            case 'offerGiven':
                $this->clientOfferGive($this->payload);
                break;
            case 'offerStatus':
                $this->clientOfferStatus($this->payload);
                break;
            case 'register':
                $this->clientRegister($this->payload);
                break;
            case 'activation':
                $this->clientActivation($this->payload);
                break;
            case 'clientUpdate':
                $this->clientChanged($this->payload);
                break;
            case 'cliFileStatus':
                $this->clientFileStatus($this->payload);
                break;
            // ── TEDARIK 7 ──
            case 'tedarikOrderImported':
                $this->tedarikOrderImported($this->payload);
                break;
            case 'tedarikOrderSent':
                $this->tedarikOrderSent($this->payload);
                break;
            case 'tedarikFileWaiting':
                $this->tedarikFileWaiting($this->payload);
                break;
            case 'tedarikFileApproved':
                $this->tedarikFileApproved($this->payload);
                break;
            case 'tedarikFileRejected':
                $this->tedarikFileRejected($this->payload);
                break;
            case 'tedarikOrderApproved':
                $this->tedarikOrderApproved($this->payload);
                break;
            case 'tedarikOrderRejected':
                $this->tedarikOrderRejected($this->payload);
                break;
            default:
                $this->log('warning', 'SendNotificationMailJob received unknown type', ['type' => $this->payload['type'] ?? null]);
                break;
        }
        

        $this->log('info', 'SendNotificationMailJob completed', $this->payload);
    }

    protected function log(string $level, string $message, array $context = [])
    {
        Log::{$level}($message, $context);

        if (app()->runningInConsole() || PHP_SAPI === 'cli') {
            echo strtoupper($level) . ': ' . $message . PHP_EOL;
            if (!empty($context)) {
                $formatted = [];
                foreach ($context as $key => $value) {
                    if ($value instanceof \Throwable) {
                        $formatted[$key] = [
                            'message' => $value->getMessage(),
                            'file' => $value->getFile(),
                            'line' => $value->getLine(),
                        ];
                    } else {
                        $formatted[$key] = $value;
                    }
                }
                echo json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            }
        }
    }

    public function failed(\Throwable $exception)
    {
        $this->log('error', 'SendNotificationMailJob failed', [
            'exception' => $exception,
        ]);
    }

    protected function renderEmailHtml(string $subject, string $content, array $options = []): string
    {
        $mailService = new MailService();

        return $mailService->renderHtmlMessage(array_merge([
            'subject' => $subject,
            'title' => $subject,
            'header' => $subject,
            'intro' => null,
            'content' => $content,
            'footerText' => 'Tedarik Yönetim Sistemi tarafından gönderildi.',
            'sysCode' => $options['sysCode'] ?? $options['sys_code'] ?? $this->payload['sys_code'] ?? $GLOBALS['SYS_CODE'] ?? '',
        ], $options));
    }

    /** Rich tedarik-panel card mail with detail table + deep-link CTA. Turkish only. */
    protected function tedarikCard(string $type, string $subject, array $rows, ?string $ctaUrl, string $ctaText, ?string $note = null, array $extra = []): string
    {
        $H = \App\Services\TedarikMailHelper::class;
        $meta = $H::metaFor($type);
        $sysCode = $H::sysCode(array_merge($this->payload, $extra));
        $content = $H::detailTable($rows) . $H::noteBox($note);
        $preheader = $meta['pill'] . ' — ' . strip_tags($rows[0][1] ?? $subject);
        return $this->renderEmailHtml($subject, $content, array_merge([
            'intro' => $extra['intro'] ?? $meta['intro'],
            'ctaUrl' => $ctaUrl,
            'ctaText' => $ctaText,
            'pillText' => $meta['pill'],
            'pillColor' => $meta['color'],
            'pillBg' => $meta['bg'],
            'sysCode' => $sysCode,
            'logoUrl' => $H::logoUrl($sysCode),
            'preheader' => $preheader,
            'footerText' => 'Tedarik Yönetim Sistemi tarafından gönderildi.',
        ], $extra));
    }

    protected function orderLink(array $payload): ?string
    {
        $H = \App\Services\TedarikMailHelper::class;
        $qnid = $payload['order_qnid'] ?? $payload['qnid'] ?? null;
        return $H::orderUrl($qnid);
    }

    protected function fileLink(array $payload): ?string
    {
        $H = \App\Services\TedarikMailHelper::class;
        $qnid = $payload['file_qnid'] ?? $payload['fileQnid'] ?? null;
        if ($qnid) return $H::fileUrl($qnid);
        // bulk / fallback: point at the order when no single file qnid exists
        return $this->orderLink($payload);
    }

    //here we are finding the users who have permission for receving the client register notification and send mail to them
    // LEGACY: notif-00 removed → now tedarik-01 (Sipariş Sisteme Geldi SAP) — keep for backward, new flow uses tedarikOrderImported
    public function clientRegister(array $payload)
    {
        $this->log('info', 'Client Register Triggered', $this->payload);
        $subject = 'Yeni Tedarikçi Kaydı';
        $H = \App\Services\TedarikMailHelper::class;
        $html = $this->tedarikCard('register', $subject, [
            ['E-posta', e($payload['email'] ?? '-')],
            ['Telefon', e($payload['phone'] ?? '-')],
            ['Sistem', e($H::sysCode($this->payload))],
        ], null, '', null, ['intro' => 'Yeni bir tedarikçi kaydı alındı. Onay için kullanıcı listesini kontrol edin.', 'pillText' => 'Yeni Kayıt', 'pillColor' => '#154B91', 'pillBg' => '#eff6ff']);
        //after that we need to inform system users who permitted
        $this->informSystemUsers($subject, $html, 'tedarik-01');

        $this->log('info', 'SendNotificationMailJob completed');
    }

    // LEGACY offer flow (op-doc-offer removed). Kept guarded so old queue payloads never 500.
    public function clientOfferGive(array $payload,$isUpdate = false)
    {
        $offer = [];
        try {
            $offer = array_values($payload['detail']['formFormat']['op-doc-offer-form'] ?? [])[0]['entities'] ?? [];
        } catch (\Throwable $e) { $offer = []; }

        if (isset($offer['offer_type']) && str_contains((string) $offer['offer_type'], '**')) {
            $parts = explode('**', (string) $offer['offer_type']);
            $offer['offer_type'] = $parts[1] ?? $parts[0];
        }
        $this->log('info', 'Client Offer Given Triggered', $this->payload);
        $subject = 'Yeni Teklif Verildi';
        $offer['req_no'] = $offer['req_no'] ?? '-';
        $html = $this->tedarikCard('offerGiven', $subject, [
            ['Müşteri', e($offer['clititle'] ?? '-')],
            ['Talep Kodu', e($offer['request_id'] ?? '-')],
            ['Teklif Kodu', e($offer['req_no'] ?? '-')],
            ['Teklif Türü', e($offer['offer_type'] ?? '-')],
        ], null, '', null, ['intro' => 'Yeni bir teklif alındı.', 'pillText' => 'Teklif', 'pillColor' => '#154B91', 'pillBg' => '#eff6ff']);

        if($isUpdate){
            $subject = 'Teklif Revize Edildi';
            $html = $this->tedarikCard('offerGiven', $subject, [
                ['Müşteri', e($offer['clititle'] ?? '-')],
                ['Talep Kodu', e($offer['request_id'] ?? '-')],
                ['Teklif Kodu', e($offer['qnid'] ?? '-')],
                ['Teklif Türü', e($offer['offer_type'] ?? '-')],
            ], null, '', null, ['intro' => 'Bir teklif revize edildi. Değişen alanlar sistem kaydında.', 'pillText' => 'Revize', 'pillColor' => '#b45309', 'pillBg' => '#fef3c7'])
                . $this->buildOfferRevisionHtml($payload);
        }

        //here also add addional files about offer if exist in payload
        $attachments = [];
        $enc = new \App\Providers\EncryptionProvider();
        foreach ($offer as $key => $value) {
            if(strpos($key , 'offer_otherdocs_file**') !== false && !empty($value)) {
                
                $fileInfo = \json_decode($value,true);
                
                $path = storage_path('app/public') . '/documents/' . $enc->decrypt($fileInfo['description']);
                $attachments[] = $path;
            }
        }
        
        //after that we need to inform system users who permitted — LEGACY notif-02/03 → tedarik-02 (Sipariş Onaya Gönderildi)
        $this->informSystemUsers($subject, $html, 'tedarik-02', $attachments);
        
        $this->log('info', 'SendNotificationMailJob completed');
    }

    protected function buildOfferRevisionHtml(array $payload): string
    {
        $beforeEntities = array_values($payload['before']['formFormat']['op-doc-offer-form'] ?? [])[0]['entities'] ?? [];
        $afterEntities = array_values($payload['after']['formFormat']['op-doc-offer-form'] ?? [])[0]['entities'] ?? [];

        if (empty($beforeEntities) || empty($afterEntities)) {
            return '<p>Revize edilmiş alan bilgisi bulunamadı.</p>';
        }

        $changed = [];
        $keys = array_unique(array_merge(array_keys($beforeEntities), array_keys($afterEntities)));
        foreach ($keys as $key) {
            $beforeValue = $beforeEntities[$key] ?? null;
            $afterValue = $afterEntities[$key] ?? null;

            if ($beforeValue !== $afterValue) {
                $changed[$key] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        if (empty($changed)) {
            return '<p>Revize edilmiş alan bulunamadı.</p>';
        }

        $html = '<ul style="margin:0;padding-left:16px;">';
        foreach ($changed as $field => $values) {
            $html .= '<li style="margin-bottom:8px;">';
            $html .= '<strong>' . $this->formatOfferFieldLabel($field) . '</strong>:<br>';
            $html .= 'Önce: ' . $this->formatOfferFieldValue($field, $values['before']) . '<br>';
            $html .= 'Sonra: ' . $this->formatOfferFieldValue($field, $values['after']);
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    protected function formatOfferFieldLabel(string $field): string
    {
        $baseField = explode('**', $field)[0];

        $labels = [
            'request_id' => 'Talep kodu',
            'offer_type' => 'Teklif Türü',
            'clititle' => 'Teklif Veren Cari',
            'cliid' => 'Teklif Veren Cari Kodu',
            'qnid' => 'Teklif Kodu',
            'date' => 'Belge Tarihi',
            'rev_date' => 'Revizyon Tarihi',
            'target_type' => 'Alıcı',
            'order_radius' => 'Sipariş Kapsamı',
            'contract_start_date' => 'Teklif Başlangıç Tarihi',
            'contract_end_date' => 'Teklif Bitiş Tarihi',
            'unload_area' => 'Ürün Boşaltma Yeri',
            'coal_size' => 'Ebat Değeri',
            'coal_hgi' => 'HGİ Değeri',
            'coal_ucucu' => 'Uçucu Madde Değeri',
            'coal_type' => 'Cinsi Değeri',
            'calory' => 'Kalori Değeri',
            'humidity' => 'Nem Değeri',
            'ash_content' => 'Kül Değeri',
            'sulfur' => 'Kükürt Değeri',
            'fuel_price_impact' => 'Birim Fiyatın Akaryakıt (Artış/Azalış) Etkilenme Oranı',
            'tiufe_price_impact' => 'Birim Fiyatın  ((Tİ-ÜFE +TÜFE)/2 ) Etkilenme Oranı',
            'fuel_price_impact_2' => 'Birim Fiyatın Akaryakıttan Etkilenmeyecek Oranı',
            'prime_condition_is' => 'Kalori Aralığı Başlangıç( Kcal)',
            'prime_condition_is_bellow' => 'Kalori Aralığı Bitiş (Kcal)',
            'prime_unit_price' => 'Birim Fiyat',
            'amount' => 'Miktar',
            'payment_periods' => 'Hakediş Dönemleri',
            'payment_desc' => 'Hakediş Açıklama',
            'payment_due' => 'Ödeme Vadesi',
            'transfer_start_date' => 'Sevkiyata Başlangıç Tarihi',
            'transfer_end_date' => 'Sevkiyat Bitiş Tarihi',
            'desc' => 'Ek Açıklama',
            'offer_otherdocs_file_text' => 'Belge İsmi',
            'offer_otherdocs_file' => 'Belge Dosyası',
        ];

        return $labels[$baseField] ?? ucwords(str_replace('_', ' ', $baseField));
    }

    protected function formatOfferFieldValue(string $field, $value): string
    {
        if ($this->isFilePayloadValue($field, $value)) {
            return 'Dosya Güncellemesi Yapıldı';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if ($field === 'offer_type' && is_string($value) && strpos($value, '**') !== false) {
            $value = explode('**', $value, 2)[1];
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function isFilePayloadValue(string $field, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $fileFields = [
            'offer_otherdocs_file',
            'offer_otherdocs_file_text',
            'cont_otherdocs_file',
            'cont_otherdocs_file_text',
            'description',
        ];

        if (in_array($field, $fileFields, true)) {
            return true;
        }

        if (strpos($value, '"description"') !== false && preg_match('/"description"\s*:\s*"[A-Za-z0-9_\-\/]+=*"/', $value)) {
            return true;
        }

        if (strlen($value) > 200 && preg_match('/^[A-Za-z0-9+/=\s]+$/', $value)) {
            return true;
        }

        return false;
    }

    //here we are sending mail to user who awaiting activation
    public function clientActivation(array $payload)
    {
        $this->log('info', 'Client Activation Triggered', $this->payload);
        $subject = 'Hesap Aktivasyonu';
        //this path renders the view directly instead of going through
        //MailService::renderHtmlMessage, so the tenant code has to be threaded in by hand
        $html = view('emails.verify-email', [
            'name' => $payload['name'] ?? 'Müşteri',
            'ctaUrl' => $payload['verify_url'] ?? config('app.url'),
            'sysCode' => $this->payload['sys_code'] ?? $GLOBALS['SYS_CODE'] ?? '',
        ])->render();

        $mailService = new MailService();
        $result = $mailService->sendMail([
            'to' => $payload['email'] ?? null,
            'subject' => $subject,
            'html' => $html,
            'sys_code' => $this->payload['sys_code'] ?? null,
        ]);
        $this->log('info', 'Sending notification email to user', ['email' => $payload['email'] ?? null]);
        $this->log('info', 'SendNotificationMailJob completed', $this->payload);
    }
        
    //here we are finding the users who have permission for receving the client form update notification permission and send mail to them
    public function clientChanged(array $payload)
    {
        $this->log('info', 'Client Changed Triggered', $this->payload);
        $subject = 'Firma Bilgisi Güncellendi';
        $clientQnid = $payload['client']['qnid'] ?? $payload['client_qnid'] ?? null;
        $cta = $clientQnid ? \App\Services\TedarikMailHelper::gateway('/coalpanel/client/form/' . $clientQnid) : null;
        $html = $this->tedarikCard('clientUpdate', $subject, [
            ['Firma Ünvanı', e($payload['client']['title'] ?? '-')],
            ['Firma Kodu', e($payload['client']['clicode'] ?? '-')],
        ], $cta, 'Firma Kartını Aç', null, ['intro' => 'Firma bilgilerinde güncelleme yapıldı.', 'pillText' => 'Firma', 'pillColor' => '#475569', 'pillBg' => '#f1f5f9']);

        //first send mail to client contacts
        foreach ($payload['contacts'] as $key => $value) {
            # code...
            if(strpos($key ?? '', 'cont_email') !== false){
                //send mail to this contact
                $mailService = new MailService();
                $result = $mailService->sendMail([
                    'to' => $value ?? null,
                    'subject' => $subject,
                    'html' => $html,
                    'sys_code' => $this->payload['sys_code'] ?? null,
                ]);
                $this->log('info', 'Sending notification email to user', ['name' => $payload['client']['title'], 'email' => $value ?? null]);
            }

            if(strpos($key ?? '', 'cont_phone') !== false && $this->smsEnabled){
                //send sms to this contact
                $this->log('info', 'Sending notification SMS to user', ['name' => $payload['client']['title'], 'phone' => $value ?? null]);
            }
        }

        //after that we need to inform system users who permitted — LEGACY notif-01 → tedarik-03 (İnceleme Bekleyen Dosyalar)
        $this->informSystemUsers($subject, $html, 'tedarik-03');
        
        
        
        $this->log('info', 'SendNotificationMailJob completed');
    }

    private function bukrsToSystem($bukrs): string {
        $b = strtoupper(trim((string)$bukrs));
        if($b === '' ) return 'GDZ';
        if(in_array($b, ['GDZ','4000','1000','G4000'])) return 'GDZ';
        if(in_array($b, ['ADM','5000','A5000'])) return 'ADM';
        if(in_array($b, ['BOTH','HER_IKISI','GDZ,ADM'])) return 'BOTH';
        if(strpos($b,'GDZ')!==false) return 'GDZ';
        if(strpos($b,'ADM')!==false) return 'ADM';
        return $b;
    }
    // ── TEDARIK notification handlers ──
    public function tedarikOrderImported(array $payload){
        $orderNo = $payload['order_no'] ?? $payload['transfer_no'] ?? '-';
        $subject = 'Yeni Sipariş Geldi: ' . $orderNo;
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $html = $this->tedarikCard('tedarikOrderImported', $subject, [
            ['Sipariş No', e($orderNo)],
            ['Tedarikçi', e(($payload['ctitle'] ?? '-') . ' (' . ($payload['spec_code'] ?? '-') . ')')],
            ['Sistem', e($bukrsSys . ($bukrs && $bukrs !== $bukrsSys ? ' (' . $bukrs . ')' : ''))],
            ['Alım Kodu', e($payload['buying_no'] ?? '-')],
        ], $this->orderLink($payload), 'Siparişi Aç');
        // BUKRS-aware dispatch: only users whose grp_code is BOTH or == bukrsSys
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-01');
        if(empty($permittedUsers['tedarik-01'])){
            $this->log('info','No permitted users for tedarik-01');
            return;
        }
        $filtered = [];
        foreach($permittedUsers['tedarik-01'] as $u){
            try{
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row){
                    // try direct person_id is id
                    $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                }
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filtered[] = $u;
                else $this->log('info','Skipped user due BUKRS mismatch', ['user'=>$u['person_id'], 'userSys'=>$userSys, 'bukrsSys'=>$bukrsSys, 'bukrs'=>$bukrs]);
            }catch(\Throwable $e){ $filtered[] = $u; }
        }
        if(empty($filtered)){
            $this->log('info','All users filtered out by BUKRS for tedarik-01', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys]);
            return;
        }
        // send only to filtered
        $this->log('info','Filtered tedarik-01 recipients by BUKRS', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys, 'total'=>count($permittedUsers['tedarik-01']), 'filtered'=>count($filtered)]);
        foreach($filtered as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??null];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending filtered tedarik-01 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null, 'bukrsSys'=>$bukrsSys]);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikOrderSent(array $payload){
        $orderNo = $payload['order_no'] ?? $payload['transfer_no'] ?? '-';
        $mode = $payload['transfer_mode'] ?? '-';
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $subject = 'Onaya Gönderildi: ' . $orderNo;
        $modeLabel = $mode === 'partial' ? 'Parçalı Sevkiyat' : ($mode === 'at_once' ? 'Tek Parça Sevkiyat' : $mode);
        $html = $this->tedarikCard('tedarikOrderSent', $subject, [
            ['Sipariş No', e($orderNo)],
            ['Sevkiyat Tipi', e($modeLabel)],
            ['Tedarikçi', e(($payload['ctitle'] ?? '-') . ' (' . ($payload['spec_code'] ?? '-') . ')')],
            ['Sistem', e($bukrsSys . ($bukrs && $bukrs !== $bukrsSys ? ' (' . $bukrs . ')' : ''))],
        ], $this->orderLink($payload), 'Siparişi İncele');
        // BUKRS-aware dispatch: only users whose grp_code is BOTH or == order sys_code
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-02');
        if(empty($permittedUsers['tedarik-02'])){
            $this->log('info','No permitted users for tedarik-02');
            return;
        }
        $filtered = [];
        foreach($permittedUsers['tedarik-02'] as $u){
            try{
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row){
                    $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                }
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filtered[] = $u;
                else $this->log('info','Skipped user due BUKRS mismatch tedarik-02', ['user'=>$u['person_id'], 'userSys'=>$userSys, 'bukrsSys'=>$bukrsSys, 'bukrs'=>$bukrs]);
            }catch(\Throwable $e){ $filtered[] = $u; }
        }
        if(empty($filtered)){
            $this->log('info','All users filtered out by BUKRS for tedarik-02', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys]);
            return;
        }
        $this->log('info','Filtered tedarik-02 recipients by BUKRS', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys, 'total'=>count($permittedUsers['tedarik-02']), 'filtered'=>count($filtered)]);
        foreach($filtered as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending filtered tedarik-02 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null, 'bukrsSys'=>$bukrsSys]);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikFileWaiting(array $payload){
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $orderNo = $payload['order_no'] ?? $payload['transfer_no'] ?? '-';
        $subject = 'İnceleme Bekliyor: ' . $orderNo;
        $html = $this->tedarikCard('tedarikFileWaiting', $subject, [
            ['Sipariş No', e($orderNo)],
            ['Dosya', e($payload['fileTitle'] ?? 'Transfer dosyaları')],
            ['Tedarikçi', e(($payload['ctitle'] ?? '-') . ' (' . ($payload['spec_code'] ?? '-') . ')')],
            ['Sistem', e($bukrsSys)],
        ], $this->fileLink($payload) ?? $this->orderLink($payload), 'Dosyayı İncele');
        // BUKRS-aware: same gate as tedarik-02 (triggers together)
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-03');
        if(empty($permittedUsers['tedarik-03'])){
            $this->log('info','No permitted users for tedarik-03');
            return;
        }
        $filtered = [];
        foreach($permittedUsers['tedarik-03'] as $u){
            try{
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row){
                    $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                }
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filtered[] = $u;
                else $this->log('info','Skipped user due BUKRS mismatch tedarik-03', ['user'=>$u['person_id'], 'userSys'=>$userSys, 'bukrsSys'=>$bukrsSys]);
            }catch(\Throwable $e){ $filtered[] = $u; }
        }
        if(empty($filtered)){
            $this->log('info','All users filtered out by BUKRS for tedarik-03', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys]);
            return;
        }
        $this->log('info','Filtered tedarik-03 recipients by BUKRS', ['bukrs'=>$bukrs, 'bukrsSys'=>$bukrsSys, 'total'=>count($permittedUsers['tedarik-03']), 'filtered'=>count($filtered)]);
        foreach($filtered as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending filtered tedarik-03 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null]);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikFileApproved(array $payload){
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? $payload['spec_code_sys'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $orderSpec = trim((string)($payload['spec_code'] ?? $payload['order_spec'] ?? ''));
        // fallback: try to derive spec_code from order if not in payload (via file's order)
        if($orderSpec === '' && !empty($payload['qnid'])){
            try{
                $doc = \App\Models\Documents::where('qnid', $payload['qnid'])->first();
                if($doc) {
                    $tmp = (new \App\Providers\DocumentServiceProvider())->getFormData($payload['qnid']);
                    foreach(($tmp['formFormat']['op-doc-order-form'] ?? []) as $cr){
                        foreach(($cr['entities'] ?? []) as $k=>$v) if($k==='spec_code' && $orderSpec==='') $orderSpec = trim((string)$v);
                    }
                }
            }catch(\Throwable $e){}
        }
        $subject = 'Dosya Onaylandı: ' . ($payload['order_no'] ?? '-');
        $html = $this->tedarikCard('tedarikFileApproved', $subject, [
            ['Sipariş No', e($payload['order_no'] ?? '-')],
            ['Dosya', e($payload['fileTitle'] ?? '-')],
            ['Sistem', e($bukrsSys)],
        ], $this->fileLink($payload) ?? $this->orderLink($payload), 'Dosyayı Gör', $payload['note'] ?? null);
        // ── Recipients: (A) assigned non-tedarik BUKRS-gated + (B) matching resellers by LIFNR+BUKRS even if not assigned
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-04');
        $assigned = $permittedUsers['tedarik-04'] ?? [];
        $filteredAssigned = [];
        foreach($assigned as $u){
            try{
                // check person type — skip tedarikçi here, they go via LIFNR path
                $ptype = \Illuminate\Support\Facades\DB::table('persons as p')->join('sys_options as sp','sp.id','=','p.type_id')->where('p.qnid',$u['person_id'])->value('sp.op_key');
                if($ptype === 'op-pert-reseller') continue;
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row) $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filteredAssigned[] = $u;
                else $this->log('info','Skipped assigned non-tedarik due BUKRS mismatch tedarik-04', ['user'=>$u['person_id'],'userSys'=>$userSys,'bukrsSys'=>$bukrsSys]);
            }catch(\Throwable $e){ $filteredAssigned[] = $u; }
        }
        // (B) resellers whose one of client LIFNRs equals order spec_code AND BUKRS matches
        $matchingResellers = [];
        if($orderSpec !== ''){
            try{
                $resellers = \Illuminate\Support\Facades\DB::select("SELECT p.qnid, p.name, u.email as username, u.grp_code, p.id as pid FROM persons p JOIN sys_options sp ON sp.id=p.type_id AND sp.op_key='op-pert-reseller' JOIN users u ON u.person_id=p.id WHERE p.status=1 AND u.status=1");
                foreach($resellers as $r){
                    $userSys = $this->bukrsToSystem($r->grp_code ?? 'GDZ');
                    if(!($userSys === 'BOTH' || $userSys === $bukrsSys)) continue;
                    // get this reseller's lifnrs via client qnids
                    $crows = \Illuminate\Support\Facades\DB::select("SELECT se.entity_value as client_qnid FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id JOIN sys_options sp ON sp.id=so.type_id WHERE so.main_id=? AND sp.op_key='op-doc-user-client-form' AND se.entity_tag LIKE '%cliid**%'", [$r->pid]);
                    $lifnrs = [];
                    foreach($crows as $cr){
                        $q = trim($cr->client_qnid ?? '');
                        if($q==='') continue;
                        $lr = \Illuminate\Support\Facades\DB::selectOne("SELECT se2.entity_value as lifnr FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id JOIN documents d2 ON d2.id=so2.main_id WHERE d2.qnid=? AND se2.entity_tag='lifnr' AND se2.table_tag='sys_con_ops' LIMIT 1", [$q]);
                        if($lr && trim($lr->lifnr ?? '') !== '') $lifnrs[] = trim($lr->lifnr);
                    }
                    // also try direct lifnr on person if any
                    if(in_array($orderSpec, $lifnrs, true)){
                        $matchingResellers[] = ['person_id'=>$r->qnid, 'name'=>$r->name ?? $r->qnid, 'username'=>$r->username ?? $r->qnid];
                    }
                }
            }catch(\Throwable $e){ $this->log('warning','reseller LIFNR match failed tedarik-04', ['e'=>$e->getMessage()]); }
        }
        $this->log('info','tedarik-04 recipients', ['assignedFiltered'=>count($filteredAssigned),'matchingResellers'=>count($matchingResellers),'orderSpec'=>$orderSpec,'bukrsSys'=>$bukrsSys]);
        // merge deduplicated by person_id
        $merged = [];
        $seen = [];
        foreach(array_merge($filteredAssigned, $matchingResellers) as $u){
            $pid = $u['person_id'] ?? null;
            if(!$pid || isset($seen[$pid])) continue;
            $seen[$pid]=true;
            $merged[]=$u;
        }
        if(empty($merged)){
            $this->log('info','No recipients for tedarik-04 after BUKRS+LIFNR gate', ['bukrs'=>$bukrs,'bukrsSys'=>$bukrsSys,'orderSpec'=>$orderSpec]);
            return;
        }
        foreach($merged as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending tedarik-04 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null,'kind'=> in_array($user,$matchingResellers,true) ? 'reseller-lifnr' : 'assigned']);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikFileRejected(array $payload){
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $orderSpec = trim((string)($payload['spec_code'] ?? $payload['order_spec'] ?? ''));
        if($orderSpec === '' && !empty($payload['qnid'])){
            try{
                $doc = \App\Models\Documents::where('qnid', $payload['qnid'])->first();
                if($doc) {
                    $tmp = (new \App\Providers\DocumentServiceProvider())->getFormData($payload['qnid']);
                    foreach(($tmp['formFormat']['op-doc-order-form'] ?? []) as $cr){
                        foreach(($cr['entities'] ?? []) as $k=>$v) if($k==='spec_code' && $orderSpec==='') $orderSpec = trim((string)$v);
                    }
                }
            }catch(\Throwable $e){}
        }
        $subject = 'Düzeltme İstendi: ' . ($payload['order_no'] ?? '-');
        $html = $this->tedarikCard('tedarikFileRejected', $subject, [
            ['Sipariş No', e($payload['order_no'] ?? '-')],
            ['Dosya', e($payload['fileTitle'] ?? '-')],
            ['Sistem', e($bukrsSys)],
        ], $this->fileLink($payload) ?? $this->orderLink($payload), 'Dosyayı Yükle', $payload['note'] ?? null);
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-05');
        $assigned = $permittedUsers['tedarik-05'] ?? [];
        $filteredAssigned = [];
        foreach($assigned as $u){
            try{
                $ptype = \Illuminate\Support\Facades\DB::table('persons as p')->join('sys_options as sp','sp.id','=','p.type_id')->where('p.qnid',$u['person_id'])->value('sp.op_key');
                if($ptype === 'op-pert-reseller') continue;
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row) $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filteredAssigned[] = $u;
                else $this->log('info','Skipped assigned non-tedarik due BUKRS mismatch tedarik-05', ['user'=>$u['person_id'],'userSys'=>$userSys,'bukrsSys'=>$bukrsSys]);
            }catch(\Throwable $e){ $filteredAssigned[] = $u; }
        }
        $matchingResellers = [];
        if($orderSpec !== ''){
            try{
                $resellers = \Illuminate\Support\Facades\DB::select("SELECT p.qnid, p.name, u.email as username, u.grp_code, p.id as pid FROM persons p JOIN sys_options sp ON sp.id=p.type_id AND sp.op_key='op-pert-reseller' JOIN users u ON u.person_id=p.id WHERE p.status=1 AND u.status=1");
                foreach($resellers as $r){
                    $userSys = $this->bukrsToSystem($r->grp_code ?? 'GDZ');
                    if(!($userSys === 'BOTH' || $userSys === $bukrsSys)) continue;
                    $crows = \Illuminate\Support\Facades\DB::select("SELECT se.entity_value as client_qnid FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id JOIN sys_options sp ON sp.id=so.type_id WHERE so.main_id=? AND sp.op_key='op-doc-user-client-form' AND se.entity_tag LIKE '%cliid**%'", [$r->pid]);
                    $lifnrs = [];
                    foreach($crows as $cr){
                        $q = trim($cr->client_qnid ?? '');
                        if($q==='') continue;
                        $lr = \Illuminate\Support\Facades\DB::selectOne("SELECT se2.entity_value as lifnr FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id JOIN documents d2 ON d2.id=so2.main_id WHERE d2.qnid=? AND se2.entity_tag='lifnr' AND se2.table_tag='sys_con_ops' LIMIT 1", [$q]);
                        if($lr && trim($lr->lifnr ?? '') !== '') $lifnrs[] = trim($lr->lifnr);
                    }
                    if(in_array($orderSpec, $lifnrs, true)){
                        $matchingResellers[] = ['person_id'=>$r->qnid, 'name'=>$r->name ?? $r->qnid, 'username'=>$r->username ?? $r->qnid];
                    }
                }
            }catch(\Throwable $e){ $this->log('warning','reseller LIFNR match failed tedarik-05', ['e'=>$e->getMessage()]); }
        }
        $this->log('info','tedarik-05 recipients', ['assignedFiltered'=>count($filteredAssigned),'matchingResellers'=>count($matchingResellers),'orderSpec'=>$orderSpec,'bukrsSys'=>$bukrsSys]);
        $merged = []; $seen=[];
        foreach(array_merge($filteredAssigned, $matchingResellers) as $u){
            $pid = $u['person_id'] ?? null;
            if(!$pid || isset($seen[$pid])) continue;
            $seen[$pid]=true; $merged[]=$u;
        }
        if(empty($merged)){
            $this->log('info','No recipients for tedarik-05 after BUKRS+LIFNR gate', ['bukrs'=>$bukrs,'bukrsSys'=>$bukrsSys,'orderSpec'=>$orderSpec]);
            return;
        }
        foreach($merged as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending tedarik-05 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null,'kind'=> in_array($user,$matchingResellers,true) ? 'reseller-lifnr' : 'assigned']);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikOrderApproved(array $payload){
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $orderSpec = trim((string)($payload['spec_code'] ?? $payload['order_spec'] ?? ''));
        if($orderSpec === '' && !empty($payload['qnid'])){
            try{
                $tmp = (new \App\Providers\DocumentServiceProvider())->getFormData($payload['qnid']);
                foreach(($tmp['formFormat']['op-doc-order-form'] ?? []) as $cr){
                    foreach(($cr['entities'] ?? []) as $k=>$v) if($k==='spec_code' && $orderSpec==='') $orderSpec = trim((string)$v);
                }
            }catch(\Throwable $e){}
        }
        $subject = 'Kalite Onayı: ' . ($payload['order_no'] ?? '-');
        $html = $this->tedarikCard('tedarikOrderApproved', $subject, [
            ['Sipariş No', e($payload['order_no'] ?? '-')],
            ['Tedarikçi', e(($payload['ctitle'] ?? '-') . ' (' . ($payload['spec_code'] ?? $payload['order_spec'] ?? '-') . ')')],
            ['Sistem', e($bukrsSys)],
        ], $this->orderLink($payload), 'Siparişi Gör');
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-06');
        $assigned = $permittedUsers['tedarik-06'] ?? [];
        $filteredAssigned = [];
        foreach($assigned as $u){
            try{
                $ptype = \Illuminate\Support\Facades\DB::table('persons as p')->join('sys_options as sp','sp.id','=','p.type_id')->where('p.qnid',$u['person_id'])->value('sp.op_key');
                if($ptype === 'op-pert-reseller') continue;
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row) $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filteredAssigned[] = $u;
                else $this->log('info','Skipped assigned non-tedarik due BUKRS mismatch tedarik-06', ['user'=>$u['person_id'],'userSys'=>$userSys,'bukrsSys'=>$bukrsSys]);
            }catch(\Throwable $e){ $filteredAssigned[] = $u; }
        }
        $matchingResellers = [];
        if($orderSpec !== ''){
            try{
                $resellers = \Illuminate\Support\Facades\DB::select("SELECT p.qnid, p.name, u.email as username, u.grp_code, p.id as pid FROM persons p JOIN sys_options sp ON sp.id=p.type_id AND sp.op_key='op-pert-reseller' JOIN users u ON u.person_id=p.id WHERE p.status=1 AND u.status=1");
                foreach($resellers as $r){
                    $userSys = $this->bukrsToSystem($r->grp_code ?? 'GDZ');
                    if(!($userSys === 'BOTH' || $userSys === $bukrsSys)) continue;
                    $crows = \Illuminate\Support\Facades\DB::select("SELECT se.entity_value as client_qnid FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id JOIN sys_options sp ON sp.id=so.type_id WHERE so.main_id=? AND sp.op_key='op-doc-user-client-form' AND se.entity_tag LIKE '%cliid**%'", [$r->pid]);
                    $lifnrs = [];
                    foreach($crows as $cr){
                        $q = trim($cr->client_qnid ?? '');
                        if($q==='') continue;
                        $lr = \Illuminate\Support\Facades\DB::selectOne("SELECT se2.entity_value as lifnr FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id JOIN documents d2 ON d2.id=so2.main_id WHERE d2.qnid=? AND se2.entity_tag='lifnr' AND se2.table_tag='sys_con_ops' LIMIT 1", [$q]);
                        if($lr && trim($lr->lifnr ?? '') !== '') $lifnrs[] = trim($lr->lifnr);
                    }
                    if(in_array($orderSpec, $lifnrs, true)){
                        $matchingResellers[] = ['person_id'=>$r->qnid, 'name'=>$r->name ?? $r->qnid, 'username'=>$r->username ?? $r->qnid];
                    }
                }
            }catch(\Throwable $e){ $this->log('warning','reseller LIFNR match failed tedarik-06', ['e'=>$e->getMessage()]); }
        }
        $this->log('info','tedarik-06 recipients', ['assignedFiltered'=>count($filteredAssigned),'matchingResellers'=>count($matchingResellers),'orderSpec'=>$orderSpec,'bukrsSys'=>$bukrsSys]);
        $merged=[]; $seen=[];
        foreach(array_merge($filteredAssigned, $matchingResellers) as $u){
            $pid=$u['person_id']??null;
            if(!$pid||isset($seen[$pid])) continue;
            $seen[$pid]=true; $merged[]=$u;
        }
        if(empty($merged)){
            $this->log('info','No recipients for tedarik-06 after BUKRS+LIFNR gate', ['bukrs'=>$bukrs,'bukrsSys'=>$bukrsSys,'orderSpec'=>$orderSpec]);
            return;
        }
        foreach($merged as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending tedarik-06 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null,'kind'=> in_array($user,$matchingResellers,true) ? 'reseller-lifnr' : 'assigned']);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }
    public function tedarikOrderRejected(array $payload){
        $bukrs = $payload['bukrs'] ?? $payload['sys_code'] ?? $payload['BUKRS'] ?? $payload['order_sys_code'] ?? '';
        $bukrsSys = $this->bukrsToSystem($bukrs);
        $orderSpec = trim((string)($payload['spec_code'] ?? $payload['order_spec'] ?? ''));
        if($orderSpec === '' && !empty($payload['qnid'])){
            try{
                $tmp = (new \App\Providers\DocumentServiceProvider())->getFormData($payload['qnid']);
                foreach(($tmp['formFormat']['op-doc-order-form'] ?? []) as $cr){
                    foreach(($cr['entities'] ?? []) as $k=>$v) if($k==='spec_code' && $orderSpec==='') $orderSpec = trim((string)$v);
                }
            }catch(\Throwable $e){}
        }
        $subject = 'Sipariş Reddedildi: ' . ($payload['order_no'] ?? '-');
        $html = $this->tedarikCard('tedarikOrderRejected', $subject, [
            ['Sipariş No', e($payload['order_no'] ?? '-')],
            ['Sistem', e($bukrsSys)],
        ], $this->orderLink($payload), 'Siparişi Gör', $payload['note'] ?? null);
        $permittedUsers = $this->personProvider->getNotificationUsers('tedarik-07');
        $assigned = $permittedUsers['tedarik-07'] ?? [];
        $filteredAssigned = [];
        foreach($assigned as $u){
            try{
                $ptype = \Illuminate\Support\Facades\DB::table('persons as p')->join('sys_options as sp','sp.id','=','p.type_id')->where('p.qnid',$u['person_id'])->value('sp.op_key');
                if($ptype === 'op-pert-reseller') continue;
                $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', function($q) use ($u){
                    $q->select('id')->from('persons')->where('qnid', $u['person_id'])->limit(1);
                })->first();
                if(!$row) $row = \Illuminate\Support\Facades\DB::table('users')->where('person_id', $u['person_id'])->first();
                $userSys = $this->bukrsToSystem($row->grp_code ?? 'GDZ');
                if($userSys === 'BOTH' || $userSys === $bukrsSys) $filteredAssigned[] = $u;
                else $this->log('info','Skipped assigned non-tedarik due BUKRS mismatch tedarik-07', ['user'=>$u['person_id'],'userSys'=>$userSys,'bukrsSys'=>$bukrsSys]);
            }catch(\Throwable $e){ $filteredAssigned[] = $u; }
        }
        $matchingResellers = [];
        if($orderSpec !== ''){
            try{
                $resellers = \Illuminate\Support\Facades\DB::select("SELECT p.qnid, p.name, u.email as username, u.grp_code, p.id as pid FROM persons p JOIN sys_options sp ON sp.id=p.type_id AND sp.op_key='op-pert-reseller' JOIN users u ON u.person_id=p.id WHERE p.status=1 AND u.status=1");
                foreach($resellers as $r){
                    $userSys = $this->bukrsToSystem($r->grp_code ?? 'GDZ');
                    if(!($userSys === 'BOTH' || $userSys === $bukrsSys)) continue;
                    $crows = \Illuminate\Support\Facades\DB::select("SELECT se.entity_value as client_qnid FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id JOIN sys_options sp ON sp.id=so.type_id WHERE so.main_id=? AND sp.op_key='op-doc-user-client-form' AND se.entity_tag LIKE '%cliid**%'", [$r->pid]);
                    $lifnrs = [];
                    foreach($crows as $cr){
                        $q = trim($cr->client_qnid ?? '');
                        if($q==='') continue;
                        $lr = \Illuminate\Support\Facades\DB::selectOne("SELECT se2.entity_value as lifnr FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id JOIN documents d2 ON d2.id=so2.main_id WHERE d2.qnid=? AND se2.entity_tag='lifnr' AND se2.table_tag='sys_con_ops' LIMIT 1", [$q]);
                        if($lr && trim($lr->lifnr ?? '') !== '') $lifnrs[] = trim($lr->lifnr);
                    }
                    if(in_array($orderSpec, $lifnrs, true)){
                        $matchingResellers[] = ['person_id'=>$r->qnid, 'name'=>$r->name ?? $r->qnid, 'username'=>$r->username ?? $r->qnid];
                    }
                }
            }catch(\Throwable $e){ $this->log('warning','reseller LIFNR match failed tedarik-07', ['e'=>$e->getMessage()]); }
        }
        $this->log('info','tedarik-07 recipients', ['assignedFiltered'=>count($filteredAssigned),'matchingResellers'=>count($matchingResellers),'orderSpec'=>$orderSpec,'bukrsSys'=>$bukrsSys]);
        $merged=[]; $seen=[];
        foreach(array_merge($filteredAssigned, $matchingResellers) as $u){
            $pid=$u['person_id']??null;
            if(!$pid||isset($seen[$pid])) continue;
            $seen[$pid]=true; $merged[]=$u;
        }
        if(empty($merged)){
            $this->log('info','No recipients for tedarik-07 after BUKRS+LIFNR gate', ['bukrs'=>$bukrs,'bukrsSys'=>$bukrsSys,'orderSpec'=>$orderSpec]);
            return;
        }
        foreach($merged as $user){
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new \App\Services\MailService();
                $template = ['to'=>null,'subject'=>$subject,'html'=>$html,'attachments'=>$payload['attachments']??[],'sys_code'=>$this->payload['sys_code']??$bukrsSys];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        $template['to']= $contact['Value'] ?? null;
                        $mailService->sendMail($template);
                        $this->log('info','Sending tedarik-07 mail', ['name'=>$user['name'],'email'=>$contact['Value']??null,'kind'=> in_array($user,$matchingResellers,true) ? 'reseller-lifnr' : 'assigned']);
                    }
                }
                if($template['to']===null && strpos($person->email ?? '', '@')!==false){
                    $template['to']=$person->email ?? null;
                    $mailService->sendMail($template);
                }
            }
        }
        return;
    }

    private function informSystemUsers($subject, $html, $opKey,$attachments = []){
         
        $permittedUsers = $this->personProvider->getNotificationUsers($opKey);
        $this->log('info','Permitted Users '.$opKey ,$permittedUsers);
        if(empty($permittedUsers[$opKey])) return;
        foreach($permittedUsers[$opKey] as $user){
            //send mail to user
            //here get users contact informations
            $person = $this->personProvider->getPerson($user['person_id'],null,true);
            $this->log('info','Person Founded.. ' ,$person);
            if($person['success']){
                $person = $person['person'][0];
                $contacts = json_decode($person->contacts ?? '[]', true);
                $mailService = new MailService();
                $template = [
                    'to' => null,
                    'subject' => $subject,
                    'html' => $html,
                    'attachments' => $attachments,
                    'sys_code' => $this->payload['sys_code'] ?? null,
                ];
                foreach($contacts as $contact){
                    if(strpos($contact['Key'] ?? '', 'contmail') !== false){
                        //send mail to this contact
                        $template['to']= $contact['Value'] ?? null;
                        $result = $mailService->sendMail($template);
                        $this->log('info', 'Sending notification email to user', ['name' => $user['name'], 'email' => $contact['Value'] ?? null]);
                    }


                    if(strpos($contact['Key'] ?? '', 'contphone') !== false && $this->smsEnabled){
                        //send sms to this contact
                        $this->log('info', 'Sending notification SMS to user', ['name' => $user['name'], 'phone' => $contact['Value'] ?? null]);
                    }
                }

                //here send also its email
                if($template['to'] === null && strpos($person->email ?? '', '@') !== false){
                    $template['to'] = $person->email ?? null;
                    $this->log('info', 'Sending notification email to user', ['name' => $user['name'], 'email' => $person->email ?? null]);
                    $result = $mailService->sendMail($template);
                    $this->log('info', 'Email Result', $result);
                }
            }
        }
    }

    public function clientFileStatus(array $payload)
    {
        $this->log('info', 'Client File Status Triggered', $this->payload);
        $subject = 'Dosya Durumu: ' . ($payload['fileTitle'] ?? 'Güncelleme');
        $html = $this->tedarikCard('cliFileStatus', $subject, [
            ['Firma', e(($payload['title'] ?? '-') . ' (' . ($payload['clicode'] ?? '-') . ')')],
            ['Dosya', e($payload['fileTitle'] ?? '-')],
            ['Yeni Durum', e(is_array($payload['status'] ?? null) ? json_encode($payload['status'], JSON_UNESCAPED_UNICODE) : ($payload['status'] ?? '-'))],
        ], null, '', $payload['note'] ?? null, ['intro' => 'Dosya durumunda güncelleme yapıldı.', 'pillText' => 'Dosya', 'pillColor' => '#475569', 'pillBg' => '#f1f5f9']);

        //first send mail to client contacts
        foreach ($payload['contacts'] as $key => $value) {
            # code...
            if(strpos($key ?? '', 'cont_email') !== false){
                //send mail to this contact
                $mailService = new MailService();
                $result = $mailService->sendMail([
                    'to' => $value ?? null,
                    'subject' => $subject,
                    'html' => $html,
                    'sys_code' => $this->payload['sys_code'] ?? null,
                ]);
                $this->log('info', 'Sending notification email to user', ['name' => $payload['title'], 'email' => $value ?? null]);
            }

            if(strpos($key ?? '', 'cont_phone') !== false && $this->smsEnabled){
                //send sms to this contact
                $this->log('info', 'Sending notification SMS to user', ['name' => $payload['title'], 'phone' => $value ?? null]);
            }
        }
       
        
        
        $this->log('info', 'SendNotificationMailJob completed');
    }

    public function clientOfferStatus(array $payload)
    {

        // LEGACY offer flow — guarded, new system has no op-doc-offer
        $status = $payload['data'] ?? '-';
        $offer = [];
        try {
            $offer = array_values($payload['detail']['formFormat']['op-doc-offer-form'] ?? [])[0]['entities'] ?? [];
        } catch (\Throwable $e) { $offer = []; }
        
        
        
        $payload = [
            'type'            => 'offerStatus',
            'contacts'        => [],
            'offer_status'    => $status,
            'offer_type'      => explode('**',$offer["offer_type"] ?? '')[1] ?? '',
            'clititle'        => $offer['clititle'] ?? '',
            'request_id'      => $offer['request_id'] ?? '',
            'qnid'            => $offer['qnid'] ?? ''
        ];

        //for connections
        $client = (new DocumentServiceProvider())->getFormData($offer['cliid']);
        $client = array_values($client['formFormat']['op-doc-client-form'])[0]['entities'] ?? [];
       
        
        foreach ($client as $key => $value) {
            if(strpos($key, 'cont_email') !== false || strpos($key, 'cont_phone') !== false){
                $payload['contacts'][$key] = $value;
            }
            if(strpos($key, 'title') !== false || strpos($key, 'clicode') !== false ){
                $payload[$key]= $value;
            }
        }


        $this->log('info', 'Client Offer Status Triggered', $this->payload);
        $subject = 'Teklif Durumu: ' . (is_string($payload['offer_status'] ?? null) ? $payload['offer_status'] : 'Güncelleme');
        $html = $this->tedarikCard('offerStatus', $subject, [
            ['Müşteri', e($payload['clititle'] ?? '-')],
            ['Talep Kodu', e($payload['request_id'] ?? '-')],
            ['Teklif Kodu', e($payload['qnid'] ?? '-')],
            ['Teklif Türü', e($payload['offer_type'] ?? '-')],
            ['Teklif Durumu', e(is_string($payload['offer_status'] ?? null) ? $payload['offer_status'] : '-')],
        ], null, '', null, ['intro' => 'Teklif durumunda değişiklik yapıldı.', 'pillText' => 'Teklif', 'pillColor' => '#154B91', 'pillBg' => '#eff6ff']);

        //first send mail to client contacts
        foreach ($payload['contacts'] as $key => $value) {
            # code...
            if(strpos($key ?? '', 'cont_email') !== false){
                //send mail to this contact
                $mailService = new MailService();
                $result = $mailService->sendMail([
                    'to' => $value ?? null,
                    'subject' => $subject,
                    'html' => $html,
                    'sys_code' => $this->payload['sys_code'] ?? null,
                ]);
                $this->log('info', 'Sending notification email to user', ['name' => $payload['clititle'], 'email' => $value ?? null]);
            }

            if(strpos($key ?? '', 'cont_phone') !== false && $this->smsEnabled){
                //send sms to this contact
                $this->log('info', 'Sending notification SMS to user', ['name' => $payload['clititle'], 'phone' => $value ?? null]);
            }
        }
       
        
        
        $this->log('info', 'SendNotificationMailJob completed');
    }
}
