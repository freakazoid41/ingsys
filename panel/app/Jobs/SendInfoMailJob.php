<?php

namespace App\Jobs;

use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInfoMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;
    public $header;
    public $body;
    public $sysCode;

    public function __construct($email, $header, $body, $sysCode = null)
    {
        $this->email = $email;
        $this->header = $header;
        $this->body = $body;
        $this->sysCode = $sysCode ?? $GLOBALS['SYS_CODE'] ?? null;
    }

    public function handle()
    {
        try {
            Log::debug('SendInfoMailJob starting', ['email' => $this->email, 'header' => $this->header, 'body' => $this->body]);

            if (empty($this->email)) {
                Log::warning('SendInfoMailJob aborted: no email provided');
                return;
            }

            $subject = $this->header;
            $mailService = new MailService();
            $html = $mailService->renderHtmlMessage([
                'title' => $subject,
                'header' => $subject,
                'content' => $this->body,
                'intro' => null,
            ]);

            $result = $mailService->sendMail([
                'to' => $this->email,
                'subject' => $subject,
                'html' => $html,
                'sys_code' => $this->sysCode,
            ]);

            if (empty($result['success'])) {
                Log::error('SendInfoMailJob failed to send mail', ['email' => $this->email, 'result' => $result]);
            } else {
                Log::info('SendInfoMailJob succeeded', ['email' => $this->email]);
            }
        } catch (\Throwable $e) {
            Log::error('SendInfoMailJob exception', ['email' => $this->email, 'exception' => $e]);
        }
    }
}
