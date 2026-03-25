<?php

namespace App\Services;

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
        $to = $data['to'] ?? null;
        $text = $data['desc'] ?? $data['body'] ?? '';

        if (empty($to) || empty($text)) {
            return [
                'success' => false,
                'message' => 'Gönderilecek hedef veya içerik eksik.',
            ];
        }

        return $this->sendMail([
            'to' => $to,
            'subject' => $data['subject'] ?? 'Doğrulama Kodu',
            'body' => strval($text),
        ]);
    }
}