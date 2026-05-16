<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MailService;

class SendTestMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // php artisan mail:test kadir@kontent.com.tr --subject="Test" --body="Hello from Laravel" --use-relay

    protected $signature = 'mail:test
                            {to? : Recipient email (defaults to MAIL_TO_ADDRESS)}
                            {--subject= : Email subject}
                            {--body= : Email body}
                            {--use-relay : Use relay settings from .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using MailService';

    public function handle(MailService $mailService)
    {
        $to = $this->argument('to') ?: config('mail.to.address') ?: env('MAIL_TO_ADDRESS');
        if (empty($to)) {
            $this->error('No recipient specified and MAIL_TO_ADDRESS not set.');
            return 1;
        }

        $subject = $this->option('subject') ?: 'Test email from CoalApp';
        $body = $this->option('body') ?: "This is a test email sent at " . date('c');
        $useRelay = $this->option('use-relay') ? true : (bool) env('MAIL_USE_RELAY', false);

        $this->info("Sending test mail to {$to} (use_relay=" . ($useRelay ? 'true' : 'false') . ")...");

        $result = $mailService->sendMail([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'use_relay' => $useRelay,
        ]);

        // Show used mail info if available
        if (!empty($result['used_mail_info'])) {
            $this->info('Used mail configuration:');
            foreach ($result['used_mail_info'] as $k => $v) {
                $this->line("  {$k}: " . (is_null($v) ? 'null' : (is_bool($v) ? ($v ? 'true' : 'false') : $v)));
            }
        }

        if (empty($result['success'])) {
            $this->error('Send failed: ' . ($result['message'] ?? json_encode($result['errors'] ?? $result)));
            return 2;
        }

        $this->info('Email sent successfully.');

        return 0;
    }
}
