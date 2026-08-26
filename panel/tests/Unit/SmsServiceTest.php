<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dotenv\Dotenv;
use App\Services\SmsService;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The mail side of 2FA is neutralised with MAIL_MAILER=log. This is its SMS counterpart:
     * with the flag on, no request may reach the live gateway — not even the token call.
     */
    public function test_log_only_mode_sends_no_request_to_the_gateway()
    {
        Config::set('services.iletisimmakinesi.log_only', true);
        Config::set('services.iletisimmakinesi.base_url', 'https://mock.test/api/UserGatewayWS/functions');

        Http::fake();

        $result = (new SmsService)->sendSms('5438826976', 'Doğrulama kodu: 111111');

        Http::assertNothingSent();
        $this->assertTrue($result['success']);
        $this->assertTrue($result['log_only'] ?? false);
        $this->assertDatabaseHas('notification_logs', [
            'id' => $result['notification_log_id'],
            'status' => \App\Models\NotificationLog::STATUS_SENT,
        ]);
    }

    public function test_live_mode_still_reaches_the_gateway()
    {
        Config::set('services.iletisimmakinesi.log_only', false);
        Config::set('services.iletisimmakinesi.base_url', 'https://mock.test/api/UserGatewayWS/functions');
        Config::set('services.iletisimmakinesi.originator_id', '1');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/authenticate')) {
                return Http::response(['token' => 'FAKE-TOKEN'], 200);
            }

            return Http::response('<HERMES_RESPONSE><STATUS><CODE>0</CODE><DESC>ok</DESC></STATUS></HERMES_RESPONSE>', 200, ['Content-Type' => 'application/xml']);
        });

        (new SmsService)->sendSms('5438826976', 'canlı mod');

        Http::assertSentCount(2);
    }

    public function test_it_sends_sms_with_valid_phone_number()
    {
        // gateway akisini test ediyoruz; ortamin IS_TEST degerine bagli kalmamali
        Config::set('services.iletisimmakinesi.log_only', false);
        // configure a mock base url so SmsService builds predictable endpoints
        Config::set('services.iletisimmakinesi.base_url', 'https://mock.test/api/UserGatewayWS/functions');
        Config::set('services.iletisimmakinesi.username', 'user');
        Config::set('services.iletisimmakinesi.password', 'pass');
        Config::set('services.iletisimmakinesi.vendor_id', 'vendor');
        Config::set('services.iletisimmakinesi.api_key', 'apikey');
        Config::set('services.iletisimmakinesi.customer_code', 'customer');
        Config::set('services.iletisimmakinesi.service_id', '7');

        // Fake token and sendSMS responses using URL matching to be robust
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/authenticate')) {
                return Http::response(['token' => 'FAKE-TOKEN'], 200);
            }
            if (str_contains($url, '/getOriginators')) {
                return Http::response(['originators' => [['id' => '1', 'value' => 'FAKE']]], 200);
            }
            if (str_contains($url, '/sendSMS')) {
                return Http::response(['status' => 'success'], 200);
            }
            return Http::response('', 404);
        });

        $sms = new SmsService();
        $result = $sms->sendSms('5438826976', 'Unit test message');

        $this->assertIsArray($result);
        $this->assertTrue(!empty($result['success']), 'Expected success response from mocked SMS service. Got: ' . var_export($result, true));
    }

    public function test_it_sends_sms_with_real_service_when_enabled()
    {
        if (empty(getenv('SMS_REAL_TEST'))) {
            $this->markTestSkipped('Real SMS test not enabled; set SMS_REAL_TEST=1 to run.');
        }

        // If credentials aren't in Config, attempt to load from .env and propagate to Config
        $cfg = Config::get('services.iletisimmakinesi', []);
        $required = ['username', 'password', 'vendor_id', 'api_key', 'customer_code'];

        if (empty($cfg['username'])) {
            $envPath = base_path();
            if (file_exists($envPath . DIRECTORY_SEPARATOR . '.env')) {
                try {
                    Dotenv::createImmutable($envPath)->safeLoad();
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // push env values into Config so SmsService reads them
            foreach ($required as $k) {
                $envName = 'ILETISIM_' . strtoupper($k);
                $val = getenv($envName) ?: ($_ENV[$envName] ?? null);
                if (!empty($val)) {
                    Config::set('services.iletisimmakinesi.' . $k, $val);
                }
            }
        }

        // Re-evaluate config and skip if still missing
        $cfg = Config::get('services.iletisimmakinesi', []);
        foreach ($required as $k) {
            if (empty($cfg[$k])) {
                $this->markTestSkipped('Missing SMS credential: ' . $k);
            }
        }

        $phone = getenv('SMS_REAL_PHONE') ?: '5438826976';

        $sms = new SmsService();
        $result = $sms->sendSms($phone, 'Integration test message from SmsServiceTest');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue((bool)$result['success'], 'Real SMS send failed: ' . ($result['message'] ?? json_encode($result)));
    }
}
