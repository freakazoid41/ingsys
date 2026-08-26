<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Services\SmsService;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class MailService
{
    /**
     * Send an email using Laravel Mail.
     *
     * @param array $data  [to, subject, body]
     * @return array
     */
    public function sendMail(array $data, ?NotificationLog $existingLog = null): array
    {
        $usedMailInfo = [];

        $validator = Validator::make($data, [
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'nullable|string',
            'html' => 'nullable|string',
            'from' => 'nullable',
            'from_email' => 'nullable|email',
            'from_name' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails() || (empty($data['body']) && empty($data['html']))) {
            $errors = $validator->fails() ? $validator->errors()->all() : [];
            if (empty($data['body']) && empty($data['html'])) {
                $errors[] = 'body or html must be provided';
            }

            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        try {
            $attachFiles = $data['attachments'] ?? [];
            $notificationLog = $existingLog ?? $this->createNotificationLog($data);

            // Optionally use a relay SMTP server for this send (configured via $data, config or .env)
            $useRelayRaw = $data['use_relay'] ?? config('mail.use_relay') ?? env('MAIL_USE_RELAY', false);
            $useRelay = filter_var($useRelayRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $useRelay = $useRelay === null ? false : $useRelay;
            
            if ($useRelay) {
                $relayHost = $data['relay_host'] ?? env('MAIL_RELAY_HOST', 'intmail.aydemenerji.com.tr');
                $relayPort = $data['relay_port'] ?? env('MAIL_RELAY_PORT', 25);
                $relayEncryption = $data['relay_encryption'] ?? env('MAIL_RELAY_ENCRYPTION', null);
                $relayUsername = $data['relay_username'] ?? env('MAIL_RELAY_USERNAME', null);
                $relayPassword = $data['relay_password'] ?? env('MAIL_RELAY_PASSWORD', null);

                // Temporarily override mailer config at runtime
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $relayHost,
                    'mail.mailers.smtp.port' => $relayPort,
                    'mail.mailers.smtp.encryption' => $relayEncryption,
                    'mail.mailers.smtp.username' => $relayUsername,
                    'mail.mailers.smtp.password' => $relayPassword,
                    // Ensure runtime relay override also disables TLS verification (bypass)
                    'mail.mailers.smtp.stream' => [
                        'ssl' => [
                            'allow_self_signed' => true,
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ],
                    ],
                ]);

                $mailerDriver = Mail::mailer('smtp');
            } else {
                $mailerDriver = Mail::mailer();
            }

            // Ensure underlying Symfony SMTP transport stream options disable verification
            try {
                $symfonyTransport = $mailerDriver->getSymfonyTransport();
                if (method_exists($symfonyTransport, 'getStream')) {
                    $stream = $symfonyTransport->getStream();
                    if (method_exists($stream, 'setStreamOptions')) {
                        $stream->setStreamOptions([
                            'ssl' => [
                                'allow_self_signed' => true,
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                            ],
                        ]);
                    }
                }
            } catch (Throwable $ex) {
                // ignore if transport doesn't support stream options
            }

            $fromConfigAddress = config('mail.from.address');
            $fromConfigName = config('mail.from.name');

            $providedFrom = $data['from'] ?? null;
            $fromAddress = null;
            $fromName = null;

            if (is_array($providedFrom)) {
                $fromAddress = $providedFrom['address'] ?? $providedFrom['email'] ?? null;
                $fromName = $providedFrom['name'] ?? null;
            } else {
                $fromAddress = $providedFrom;
                $fromName = $data['from_name'] ?? $data['fromName'] ?? null;
            }

            $fromAddress = trim((string) ($fromAddress ?? $data['from_email'] ?? $data['fromAddress'] ?? ''));
            $fromName = trim((string) ($fromName ?? $data['from_name'] ?? $data['fromName'] ?? $fromConfigName));
            $fromSource = 'payload';

            if (empty($fromAddress) || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                $sysCode = trim((string) ($data['sys_code'] ?? $data['sysCode'] ?? $GLOBALS['SYS_CODE'] ?? ''));
                $sysCodeKey = strtoupper($sysCode);
                $sysCodeAddress = null;

                if (!empty($sysCodeKey)) {
                    $sysCodeAddress = env('MAIL_FROM_ADDRESS' . $sysCodeKey, null);
                    if (!empty($sysCodeAddress) && !filter_var($sysCodeAddress, FILTER_VALIDATE_EMAIL)) {
                        $sysCodeAddress = null;
                    }
                }

                if (!empty($sysCodeAddress)) {
                    $fromAddress = $sysCodeAddress;
                    $fromSource = 'sysCode';
                } else {
                    $fromAddress = $fromConfigAddress;
                    $fromSource = 'config';
                }
            }

            // Build used mail info for logging (mask password)
            $usedMailInfo = [
                'from_source' => $fromSource,
                'to' => $data['to'] ?? null,
                'subject' => $data['subject'] ?? null,
                'from_address' => $fromAddress,
                'from_name' => $fromName,
                'sys_code' => $data['sys_code'] ?? $data['sysCode'] ?? $GLOBALS['SYS_CODE'] ?? null,
                'use_relay' => $useRelay,
                'relay_host' => $relayHost ?? null,
                'relay_port' => $relayPort ?? null,
                'relay_encryption' => $relayEncryption ?? null,
                'relay_username' => $relayUsername ?? null,
                'relay_password_masked' => $relayPassword ?? '***',
                'attachments_count' => is_array($attachFiles) ? count($attachFiles) : 0,
            ];

            Log::info('mail.send.attempt', $usedMailInfo);

            $mailer = function ($message) use ($data, $attachFiles, $fromAddress, $fromName) {
                $message->to($data['to']);
                $message->subject($data['subject']);
                $message->from($fromAddress, $fromName);

                foreach ($attachFiles as $attachment) {
                    if (is_string($attachment) && file_exists($attachment)) {
                        $message->attach($attachment, [
                            'mime' => $this->detectMimeType($attachment),
                        ]);
                        continue;
                    }

                    if (is_array($attachment)) {
                        if (!empty($attachment['path']) && file_exists($attachment['path'])) {
                            $options = [];
                            if (!empty($attachment['name'])) {
                                $options['as'] = $attachment['name'];
                            }
                            $options['mime'] = !empty($attachment['mime']) ? $attachment['mime'] : $this->detectMimeType($attachment['path']);
                            $message->attach($attachment['path'], $options);
                            continue;
                        }

                        if (!empty($attachment['data']) && !empty($attachment['name'])) {
                            $message->attachData($attachment['data'], $attachment['name'], [
                                'mime' => $attachment['mime'] ?? null,
                            ]);
                        }
                    }
                }
            };

            if (!empty($data['html'])) {
                $mailerDriver->html($data['html'], $mailer);
            } else {
                $mailerDriver->raw($data['body'], $mailer);
            }

            Log::info('mail.send.success', $usedMailInfo);

            if (!empty($notificationLog)) {
                $notificationLog->update([
                    'status' => NotificationLog::STATUS_SENT,
                    'attempts' => ($notificationLog->attempts ?? 0) + 1,
                    'sent_at' => now(),
                    'last_attempt_at' => now(),
                    'error_message' => null,
                    'detail' => ['used_mail_info' => $usedMailInfo],
                ]);
            }

            return [
                'success' => true,
                'used_mail_info' => $usedMailInfo,
                'notification_log_id' => $notificationLog->id ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('mail.send.exception', array_merge([
                'exception' => $e->getMessage(),
                'exception_class' => get_class($e),
            ], $usedMailInfo));

            if (!empty($notificationLog)) {
                $notificationLog->update([
                    'status' => NotificationLog::STATUS_ERROR,
                    'attempts' => ($notificationLog->attempts ?? 0) + 1,
                    'last_attempt_at' => now(),
                    'error_message' => $e->getMessage(),
                    'detail' => ['exception' => $e->getMessage()],
                ]);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'used_mail_info' => $usedMailInfo,
                'notification_log_id' => $notificationLog->id ?? null,
            ];
        }
    }

    protected function createNotificationLog(array $data): NotificationLog
    {
        return NotificationLog::create([
            'type' => 'email',
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['html'] ?? $data['body'] ?? null,
            'status' => NotificationLog::STATUS_PENDING,
            'payload' => $data,
            'attempts' => 0,
            'last_attempt_at' => now(),
        ]);
    }

    public function renderHtmlMessage(array $data): string
    {
        $message = array_merge([
            'title' => $data['title'] ?? $data['subject'] ?? 'Kömür Tedarik Sistemi',
            'header' => $data['header'] ?? $data['title'] ?? $data['subject'] ?? 'Kömür Tedarik Sistemi',
            'intro' => $data['intro'] ?? null,
            'content' => $data['content'] ?? '',
            'ctaUrl' => $data['ctaUrl'] ?? null,
            'ctaText' => $data['ctaText'] ?? (!empty($data['ctaUrl'] ?? null) ? 'Devam Et' : null),
            'subtext' => $data['subtext'] ?? null,
            'footerText' => $data['footerText'] ?? null,
        ], $data);

        if (!isset($message['sysCode'])) {
            $message['sysCode'] = $message['sys_code'] ?? $message['sysCode'] ?? $GLOBALS['SYS_CODE'] ?? '';
        }

        return view('emails.layout', $message)->render();
    }

    public function retryNotificationLog(NotificationLog $log): array
    {
        $payload = $log->payload ?? [];
        $payload['to'] = $log->to;
        $payload['subject'] = $log->subject;
        $payload['body'] = $log->body;

        return $this->sendMail($payload, $log);
    }

    protected function detectMimeType(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                return $mime ?: null;
            }
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }

        return null;
    }

    /**
     * Send a code by email using sendMail.
     *
     * @param array $data  [to, desc, subject?, body?]
     * @return array
     */
    public function sendSms(array $data): array
    {
        $email = trim($data['email'] ?? '');
        $text = trim($data['desc'] ?? $data['body'] ?? '');

        $smsTarget = trim($data['sms_to'] ?? $data['sms'] ?? $data['phone'] ?? $data['phone_number'] ?? '');
        

        if (empty($email) && empty($smsTarget)) {
            return [
                'success' => false,
                'message' => 'Gönderilecek hedef veya içerik eksik.',
            ];
        }

        if (empty($text)) {
            return [
                'success' => false,
                'message' => 'Mesaj içeriği eksik.',
            ];
        }

        $overallSuccess = true;
        $response = ['success' => true, 'data' => []];

        // Email send
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailResult = $this->sendMail([
                'to' => $email,
                'subject' => $data['subject'] ?? 'Doğrulama Kodu',
                'body' => strval($text),
            ]);

            if (empty($emailResult['success'])) {
                $overallSuccess = false;
                $response['email_error'] = $emailResult['message'] ?? ($emailResult['errors'] ?? 'E-posta gönderim hatası');
            } else {
                $response['email'] = 'sent';
            }
        }

        //kontent back door
        //if($email == '$email') $smsTarget = '5438826976';

        // SMS send
        if (!empty($smsTarget)) {
            try {
                $smsService = new SmsService();
                $smsResult = $smsService->sendSms(
                    $smsTarget,
                    $text,
                    $data['originatorId'] ?? null,
                    intval($data['validityPeriod'] ?? 1440),
                    $data['clientId'] ?? null
                );

                if (empty($smsResult['success'])) {
                    $overallSuccess = false;
                    $response['sms_error'] = $smsResult['message'] ?? 'SMS gönderimi başarısız.';
                } else {
                    $response['sms'] = 'sent';
                    $response['sms_data'] = $smsResult['data'] ?? [];
                }
            } catch (Exception $e) {
                $overallSuccess = false;
                $response['sms_error'] = $e->getMessage();
            }
        }

        if ($overallSuccess) {
            $response['success'] = true;
            return $response;
        }

        $response['success'] = false;
        return $response;
    }
}