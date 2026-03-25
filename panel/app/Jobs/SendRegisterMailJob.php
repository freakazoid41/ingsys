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
use Illuminate\Support\Facades\DB;

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
            Log::debug('SendRegisterMailJob starting', [
                'person_id' => $this->personId,
                'email' => $this->email,
                'phone' => $this->phone,
            ]);

            //here take receipents from database
            $sql = "select  u.email
                                from sys_con_entities se
                                    inner join sys_con_ops as so on so.id = se.conn_id
                                    inner join sys_options as sp on sp.id = so.type_id
                                    inner join persons as p on p.id = so.main_id
                                    inner join users as u on u.person_id = p.id
                        where   se.entity_value like '%per-00%' and
                                se.entity_value like '%per-00-01%' and
                                so.conn_id = 0 and
                                sp.op_key = 'op-doc-user-permission-form';";
            $result = DB::select($sql);
            $list = [];
            foreach ($result as $row) {
                if (isset($row->email) && !empty($row->email)) {
                    $list[] = $row->email;        
                }     
            }

            if (empty($list)) {
                // fallback to the default from address if none configured
                $list = [config('mail.from.address')];
                Log::warning('SendRegisterMailJob recipient list was empty, using fallback from address', ['fallback' => $list]);
            }

            Log::debug('SendRegisterMailJob recipients final', ['recipients' => $list]);

            $subject = 'Tearikçi Kayıt Bildirimi - ' . ($this->email ?? '-');

            // load html template from public folder
            $templatePath = base_path('panel/public/coaltheme/mail/register_notification.html');
            $html = null;
            if (file_exists($templatePath)) {
                $html = file_get_contents($templatePath);
                $html = str_replace('{{email}}', $this->email ?? '-', $html);
                $html = str_replace('{{phone}}', $this->phone ?? '-', $html);
                $html = str_replace('{{date}}', date('Y-m-d H:i:s'), $html);
            } else {
                $html = "<p>Bilgiler Aşağıdaki Gibidir</p><p>E-Posta: " . ($this->email ?? '-') . "</p><p>Telefon: " . ($this->phone ?? '-') . "</p>";
            }

            Log::debug('SendRegisterMailJob sending mail', ['subject' => $subject, 'recipient_count' => count($list)]);

            Mail::html($html, function ($message) use ($list, $subject) {
                $message->to($list)->subject($subject);
            });

            Log::info('SendRegisterMailJob completed mail send', ['subject' => $subject, 'recipient_count' => count($list)]);
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
