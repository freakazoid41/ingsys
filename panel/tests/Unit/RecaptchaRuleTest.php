<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Rules\Recaptcha;

class RecaptchaRuleTest extends TestCase
{
    public function test_recaptcha_rule_passes_when_google_returns_success()
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $rule = new Recaptcha();

        $this->assertTrue(
            $rule->passes('g-recaptcha-response', 'test-token'),
            'Expected Recaptcha rule to pass when the verification endpoint returns success.'
        );
    }

    public function test_recaptcha_rule_fails_when_google_returns_failure()
    {
        Http::fake([
            '*' => Http::response(['success' => false], 200),
        ]);

        $rule = new Recaptcha();

        $this->assertFalse(
            $rule->passes('g-recaptcha-response', 'bad-token'),
            'Expected Recaptcha rule to fail when the verification endpoint returns failure.'
        );
    }

    public function test_recaptcha_rule_message_is_translatable_and_user_facing()
    {
        $rule = new Recaptcha();

        $this->assertSame(
            'reCAPTCHA doğrulanamadı. Lütfen tekrar deneyin.',
            $rule->message()
        );
    }
}
