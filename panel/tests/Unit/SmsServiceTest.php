<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Dotenv\Dotenv;
use App\Services\SmsService;

class SmsServiceTest extends TestCase
{
    public function test_it_sends_sms_with_valid_phone_number()
    {
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
