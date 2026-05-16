<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VerifyRecaptcha extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recaptcha:test
                            {token? : reCAPTCHA response token to verify}
                            {--remote-ip= : Optional client IP to send to the verification service}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify a reCAPTCHA token against the configured reCAPTCHA service';

    public function handle()
    {
        $token = $this->argument('token') ?: env('RECAPTCHA_TEST_TOKEN');
        $token = $token ?: config('services.recaptcha.test_token');
        $remoteIp = $this->option('remote-ip');

        if (empty($token)) {
            $this->error('A reCAPTCHA response token is required. Set RECAPTCHA_TEST_TOKEN in your .env or provide the token argument.');
            return 1;
        }

        $verifyUrl = config('services.recaptcha.verify_url');
        $secret = config('services.recaptcha.secret');

        if (empty($verifyUrl) || empty($secret)) {
            $this->error('reCAPTCHA verify_url or secret is not configured in services.php.');
            return 2;
        }

        $this->info('Verifying reCAPTCHA token...');

        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];

        if (!empty($remoteIp)) {
            $payload['remoteip'] = $remoteIp;
        }

        $response = Http::asForm()->post($verifyUrl, $payload);
        $result = $response->json();

        $success = $result['success'] ?? false;

        if ($success) {
            $this->info('reCAPTCHA verification passed.');
        } else {
            $this->error('reCAPTCHA verification failed.');
        }

        $this->line('Response: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $success ? 0 : 3;
    }
}
