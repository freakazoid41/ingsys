<?php

namespace App\Services;

use App\Services\SmsService;
use Exception;
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
    public function sendMail(array $data): array
    {
        $validator = Validator::make($data, [
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'nullable|string',
            'html' => 'nullable|string',
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
            if (!empty($data['html'])) {
                Mail::html($data['html'], function ($message) use ($data) {
                    $message->to($data['to']);
                    $message->subject($data['subject']);
                    $message->from(config('mail.from.address'), config('mail.from.name'));
                });
            } else {
                Mail::raw($data['body'], function ($message) use ($data) {
                    $message->to($data['to']);
                    $message->subject($data['subject']);
                    $message->from(config('mail.from.address'), config('mail.from.name'));
                });
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
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