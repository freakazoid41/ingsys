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
    public $sysCode;

    public function __construct($email, $password = null, $sysCode = null)
    {
        $this->email = $email;
        $this->password = $password;
        $this->sysCode = $sysCode ?? $GLOBALS['SYS_CODE'] ?? null;
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
            $content = '<p>Merhaba,</p><p>Şifreniz sistem tarafından sıfırlandı.</p>';

            if (!empty($this->password)) {
                $content .= '<p>Yeni şifreniz: <strong>' . e($this->password) . '</strong></p>';
            }

            $content .= '<p>Lütfen giriş yaptıktan sonra şifrenizi değiştirin.</p>';

            $mailService = new MailService();
            $result = $mailService->sendMail([
                'to' => $this->email,
                'subject' => $subject,
                'html' => $mailService->renderHtmlMessage([
                    'title' => $subject,
                    'header' => $subject,
                    'content' => $content,
                    'intro' => 'Şifre sıfırlama işleminiz tamamlanmıştır.',
                ]),
                'sys_code' => $this->sysCode,
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
