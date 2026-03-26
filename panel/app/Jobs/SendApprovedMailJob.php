<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendApprovedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function handle()
    {
        try {
            Log::debug('SendApprovedMailJob starting', ['email' => $this->email]);

            if (empty($this->email)) {
                Log::warning('SendApprovedMailJob aborted: no email provided');
                return;
            }

            $subject = 'Kullanıcı Bilgileriniz Aktif Edilmiştir';
            $html = '<p>Merhaba,</p><p>Kullanıcı bilgileriniz sistem tarafından aktif hale getirilmiştir.</p><p>Giriş yapabilirsiniz.</p>';

            $mailService = new MailService();
            $result = $mailService->sendMail([
                'to' => $this->email,
                'subject' => $subject,
                'html' => $html,
            ]);

            if (empty($result['success'])) {
                Log::error('SendApprovedMailJob failed to send mail', ['email' => $this->email, 'result' => $result]);
            } else {
                Log::info('SendApprovedMailJob succeeded', ['email' => $this->email]);
            }
        } catch (\Throwable $e) {
            Log::error('SendApprovedMailJob exception', ['email' => $this->email, 'exception' => $e]);
        }
    }
}
