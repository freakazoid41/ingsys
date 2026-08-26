<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmsService;

class SendTestSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test
                            {to? : Recipient phone number (defaults to 5438826976)}
                            {--message= : SMS message body}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test SMS using SmsService';

    public function handle(SmsService $smsService)
    {
        $to = $this->argument('to') ?: '5438826976';
        $message = $this->option('message') ?: "Test SMS from CoalApp at " . date('c');

        $this->info("Sending test SMS to {$to}...");

        $result = $smsService->sendSms($to, $message);

        if (empty($result['success'])) {
            $this->error('SMS send failed: ' . ($result['message'] ?? json_encode($result)));
            return 2;
        }

        $this->info('SMS sent successfully.');
        $this->line('Response: ' . json_encode($result['data'] ?? $result, JSON_PRETTY_PRINT));

        return 0;
    }
}
