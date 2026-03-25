<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Persons;

class SendRegisterMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;
    public $phone;
    public $personId;

    public function __construct($email = null, $phone = null, $personId = null)
    {
        $this->email = $email;
        $this->phone = $phone;
        $this->personId = $personId;
    }

    public function handle()
    {
        try {
            $recipients = env('REGISTER_MAIL_LIST', 'kadir@kontent.com.tr');
            $list = array_filter(array_map('trim', explode(',', $recipients)));

            if (empty($list)) {
                // fallback to the default from address if none configured
                $list = [config('mail.from.address')];
            }

            $person = null;
            if ($this->personId) {
                $person = Persons::find($this->personId);
            }

            $subject = 'Yeni kullanıcı kaydı';

            // load html template from public folder
            $templatePath = base_path('panel/public/coaltheme/mail/register_notification.html');
            $html = null;
            if (file_exists($templatePath)) {
                $html = file_get_contents($templatePath);
                $html = str_replace('{{email}}', $this->email ?? '-', $html);
                $html = str_replace('{{phone}}', $this->phone ?? '-', $html);
                $html = str_replace('{{date}}', date('Y-m-d H:i:s'), $html);
            } else {
                $html = "<p>Yeni kullanıcı kaydı</p><p>E-Posta: " . ($this->email ?? '-') . "</p><p>Telefon: " . ($this->phone ?? '-') . "</p>";
            }

            Mail::html($html, function ($message) use ($list, $subject) {
                $message->to($list)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('SendRegisterMailJob failed', [
                'exception' => $e,
                'person_id' => $this->personId,
                'email' => $this->email,
                'phone' => $this->phone
            ]);
        }
    }
}
