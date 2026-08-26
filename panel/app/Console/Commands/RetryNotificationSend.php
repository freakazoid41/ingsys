<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\RetryNotificationSendJob;
use App\Models\NotificationLog;
use App\Services\MailService;
use App\Services\SmsService;

class RetryNotificationSend extends Command
{
    protected $signature = 'notification:retry {id : Notification log ID} {--queue : Dispatch retry as queued job}';
    protected $description = 'Retry a failed SMS or email notification by log ID';

    public function handle()
    {
        $id = $this->argument('id');
        $log = NotificationLog::find($id);

        if (!$log) {
            $this->error("Notification log not found: {$id}");
            return 1;
        }

        if ($this->option('queue')) {
            RetryNotificationSendJob::dispatch($log->id);
            $this->info("Queued retry for notification #{$id} ({$log->type}).");
            return 0;
        }

        $this->info("Retrying notification #{$id} ({$log->type})...");

        $result = [];
        if ($log->type === 'email') {
            $result = (new MailService())->retryNotificationLog($log);
        } elseif ($log->type === 'sms') {
            $result = (new SmsService())->retryNotificationLog($log);
        } else {
            $this->error('Unsupported notification type: ' . $log->type);
            return 2;
        }

        if (!empty($result['success'])) {
            $this->info('Retry succeeded.');
            return 0;
        }

        $this->error('Retry failed: ' . ($result['message'] ?? json_encode($result)));
        return 2;
    }
}
