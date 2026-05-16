<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class Recaptcha implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $verifyUrl = env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify');
        $secret = env('RECAPTCHA_SECRET_KEY', config('services.recaptcha.secret'));

        $response = Http::asForm()->post($verifyUrl, [
            'secret' => $secret,
            'response' => $value,
            'remoteip' => request()->ip(), // İsteğe bağlı: Kullanıcı IP'si
        ]);

        $result = $response->json();
        
        return $result['success'] ?? false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'reCAPTCHA doğrulanamadı. Lütfen tekrar deneyin.';
    }
}
