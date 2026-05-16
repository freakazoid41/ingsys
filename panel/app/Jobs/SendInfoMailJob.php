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

    public function __construct($email, $header, $body)
    {
        $this->email = $email;
        $this->header = $header;
        $this->body = $body;
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
            $html = $this->body;

            $mailService = new MailService();
            $result = $mailService->sendMail([
                'to' => $this->email,
                'subject' => $subject,
                'html' => $html,
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
