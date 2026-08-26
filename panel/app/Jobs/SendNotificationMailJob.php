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
            // other notification types can be handled here
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
            'footerText' => 'Kömür Tedarik Sistemi tarafından gönderildi.',
            'sysCode' => $options['sysCode'] ?? $options['sys_code'] ?? $this->payload['sys_code'] ?? $GLOBALS['SYS_CODE'] ?? '',
        ], $options));
    }

    //here we are finding the users who have permission for receving the client register notification and send mail to them
    public function clientRegister(array $payload)
    {
        $this->log('info', 'Client Register Triggered', $this->payload);
        $subject = 'Yeni Müşteri Kaydı';
        $html = $this->renderEmailHtml($subject, '<p>Yeni bir müşteri kaydı gerçekleşti.</p><p><strong>Müşteri Mail:</strong> ' . e($payload['email'] ?? '-') . '</p><p><strong>Müşteri Telefon:</strong> ' . e($payload['phone'] ?? '-') . '</p>');
        //after that we need to inform system users who permitted
        $this->informSystemUsers($subject, $html, 'notif-00');
        
        $this->log('info', 'SendNotificationMailJob completed');
    }

    //here we are finding the users who have permission for receving the client offer given notification and send mail to them
    public function clientOfferGive(array $payload,$isUpdate = false)
    {
        

        $offer = array_values($payload['detail']['formFormat']['op-doc-offer-form'])[0]['entities'] ?? [];
        
        $offer['offer_type'] = explode('**',$offer["offer_type"])[1];
        $this->log('info', 'Client Offer Given Triggered', $this->payload);   
        $subject = 'Yeni Teklif Verildi';
        $offer['req_no'] = $offer['req_no'] ?? '-';
        $html = $this->renderEmailHtml($subject, '<p>Yeni bir teklif verilmiştir.</p><p><strong>Müşteri:</strong> ' . e($offer['clititle'] ?? '-') . '</p><p><strong>Talep kodu:</strong> ' . e($offer['request_id'] ?? '-') . '</p><p><strong>Teklif kodu:</strong> ' . e($offer['req_no'] ?? '-') . '</p><p><strong>Teklif Türü:</strong> ' . e($offer['offer_type'] ?? '-') . '</p>');

        if($isUpdate){
            $subject = 'Teklif Revize Edildi';
            $html = $this->renderEmailHtml($subject, '<p>Bir teklif revize edilmiştir.</p><p><strong>Müşteri:</strong> ' . e($offer['clititle'] ?? '-') . '</p><p><strong>Talep kodu:</strong> ' . e($offer['request_id'] ?? '-') . '</p><p><strong>Teklif kodu:</strong> ' . e($offer['qnid'] ?? '-') . '</p><p><strong>Teklif Türü:</strong> ' . e($offer['offer_type'] ?? '-') . '</p><h2>Revize Edilen Alanlar:</h2>' . $this->buildOfferRevisionHtml($payload));
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
        
        //after that we need to inform system users who permitted
        $this->informSystemUsers($subject, $html, $isUpdate ? 'notif-03' : 'notif-02', $attachments);
        
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
        $subject = 'Müşteri Bilgi Güncellemesi';
        $html = $this->renderEmailHtml($subject, '<p>Müşteri bilgilerinde bir güncelleme gerçekleşti.</p><p><strong>Müşteri Ünvan:</strong> ' . e($payload['client']['title'] ?? '-') . '</p><p><strong>Müşteri Kod:</strong> ' . e($payload['client']['clicode'] ?? '-') . '</p>');

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

        //after that we need to inform system users who permitted
        $this->informSystemUsers($subject, $html, 'notif-01');
       
        
        
        $this->log('info', 'SendNotificationMailJob completed');
    }

    private function informSystemUsers($subject, $html, $opKey,$attachments = []){
         
        $permittedUsers = $this->personProvider->getNotificationUsers($opKey);
        $this->log('info','Permitted Users '.$opKey ,$permittedUsers);
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
        $subject = 'Müşteri Dosya Durum Güncellemesi';
        $html = $this->renderEmailHtml($subject, '<p>Müşteri dosya durumlarında güncelleme gerçekleşti.</p><p><strong>Müşteri Ünvan:</strong> ' . e($payload['title'] ?? '-') . '</p><p><strong>Müşteri Kod:</strong> ' . e($payload['clicode'] ?? '-') . '</p><p><strong>Dosya:</strong> ' . e($payload['fileTitle'] ?? '-') . '</p><p><strong>Yeni Durum:</strong> ' . e($payload['status'] ?? '-') . '</p><p><strong>Not:</strong> ' . e($payload['note'] ?? '-') . '</p>');

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

        //here we are sending information to clients about status of the offer
        $status = $payload['data'];
        $offer =  array_values($payload['detail']['formFormat']['op-doc-offer-form'])[0]['entities'] ?? [];
        
        
        
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
        $subject = 'Teklif Durum Değişikliği';
        $html = $this->renderEmailHtml($subject, '<p>Teklifinizin durumunda değişiklik yapılmıştır.</p><p><strong>Müşteri:</strong> ' . e($payload['clititle'] ?? '-') . '</p><p><strong>Talep kodu:</strong> ' . e($payload['request_id'] ?? '-') . '</p><p><strong>Teklif kodu:</strong> ' . e($payload['qnid'] ?? '-') . '</p><p><strong>Teklif Türü:</strong> ' . e($payload['offer_type'] ?? '-') . '</p><p><strong>Teklif Durumu:</strong> ' . e($payload['offer_status'] ?? '-') . '</p>');

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
