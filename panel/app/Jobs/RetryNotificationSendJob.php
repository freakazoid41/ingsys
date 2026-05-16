<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\MailService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryNotificationSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $notificationLogId;

    public function __construct(int $notificationLogId)
    {
        $this->notificationLogId = $notificationLogId;
    }

    public function handle()
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (!$log) {
            Log::error('RetryNotificationSendJob failed: notification log not found', ['id' => $this->notificationLogId]);
            return;
        }

        Log::info('RetryNotificationSendJob starting', ['id' => $this->notificationLogId, 'type' => $log->type]);

        if ($log->type === 'email') {
            $result = (new MailService())->retryNotificationLog($log);
        } elseif ($log->type === 'sms') {
            $result = (new SmsService())->retryNotificationLog($log);
        } else {
            Log::error('RetryNotificationSendJob unsupported notification type', ['id' => $this->notificationLogId, 'type' => $log->type]);
            return;
        }

        if (!empty($result['success'])) {
            Log::info('RetryNotificationSendJob succeeded', ['id' => $this->notificationLogId]);
        } else {
            Log::error('RetryNotificationSendJob failed', ['id' => $this->notificationLogId, 'result' => $result]);
        }
    }
}
