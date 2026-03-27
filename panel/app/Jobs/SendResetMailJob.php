<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendResetMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;
    public $password;

    public function __construct($email, $password = null)
    {
        $this->email = $email;
        $this->password = $password;
    }

    public function handle()
    {
        try {
            Log::debug('SendResetMailJob starting', ['email' => $this->email]);

            if (empty($this->email)) {
                Log::warning('SendResetMailJob aborted: no email provided');
                return;
            }

            $subject = 'Şifreniz Sıfırlandı';
            $html = '<p>Merhaba,</p><p>Şifreniz sistem tarafından sıfırlandı.</p>';

            if (!empty($this->password)) {
                $html .= '<p>Yeni şifreniz: <strong>' . e($this->password) . '</strong></p>';
            }

            $html .= '<p>Lütfen giriş yaptıktan sonra şifrenizi değiştirin.</p>';

            $mailService = new MailService();
            $result = $mailService->sendMail([
                'to' => $this->email,
                'subject' => $subject,
                'html' => $html,
            ]);

            if (empty($result['success'])) {
                Log::error('SendResetMailJob failed to send mail', ['email' => $this->email, 'result' => $result]);
            } else {
                Log::info('SendResetMailJob succeeded', ['email' => $this->email]);
            }
        } catch (\Throwable $e) {
            Log::error('SendResetMailJob exception', ['email' => $this->email, 'exception' => $e]);
        }
    }
}
