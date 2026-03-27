<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected string $vendorId;
    protected string $apiKey;
    protected string $customerCode;
    protected string $serviceId;
    protected ?string $originatorId;
    protected ?string $clientId;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('services.iletisimmakinesi.base_url', 'https://live.iletisimmakinesi.com/api/UserGatewayWS/functions'), '/');
        $this->username = Config::get('services.iletisimmakinesi.username');
        $this->password = Config::get('services.iletisimmakinesi.password');
        $this->vendorId = Config::get('services.iletisimmakinesi.vendor_id');
        $this->apiKey = Config::get('services.iletisimmakinesi.api_key');
        $this->customerCode = Config::get('services.iletisimmakinesi.customer_code');
        $this->serviceId = Config::get('services.iletisimmakinesi.service_id', '7');
        $this->originatorId = Config::get('services.iletisimmakinesi.originator_id');
        $this->clientId = Config::get('services.iletisimmakinesi.client_id');

        $this->getToken();
    }

    protected function validateConfig(): void
    {
        if (empty($this->username) || empty($this->password) || empty($this->vendorId) || empty($this->apiKey) || empty($this->customerCode)) {
            throw new Exception('SMS service is not configured properly. Check config/services.php and .env');
        }
    }

    public function getToken(): string
    {
        $this->validateConfig();

        return Cache::remember('sms::iletisimmakinesi::token', 60 * 24, function () {
            $url = $this->baseUrl . '/authenticate';
            $params = [
                'userName' => urlencode($this->username),
                'userPass' => urlencode($this->password),
                'customerCode' => urlencode($this->customerCode),
                'apiKey' => urlencode($this->apiKey),
                'vendorCode' => urlencode($this->vendorId),
            ];

            $response = Http::timeout(30)->get($url, $params);
            if ($response->failed()) {
                $message = $response->body();
                Log::error('SMS token request failed', ['url' => $url, 'response' => $message]);
                throw new Exception('SMS token request failed: ' . $message);
            }

            $body = $response->body();
            $contentType = $response->header('Content-Type', '');

            Log::debug('SMS token body', ['content_type' => $contentType, 'body' => $body]);

            $token = null;

            // JSON response (if any)
            try {
                $payload = $response->json();
                if (is_array($payload) || is_object($payload)) {
                    $token = data_get($payload, 'token') ?: data_get($payload, 'Token') ?: data_get($payload, 'response.token') ?: data_get($payload, 'tokenCode');
                }
            } catch (Exception $e) {
                // ignore JSON parse issue, fallback to XML
            }

            // XML response
            if (empty($token) && stripos($contentType, 'xml') !== false) {
                try {
                    $xml = @simplexml_load_string($body);
                    if ($xml !== false) {
                        $tokenNode = $xml->xpath('//AUTHORIZATION_WITH_TOKEN/TOKEN_NO');
                        if (!empty($tokenNode) && isset($tokenNode[0])) {
                            $token = trim((string) $tokenNode[0]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('SMS token xml parse error', ['message' => $e->getMessage()]);
                }
            }

            if (empty($token)) {
                // attempt regex from any body string
                if (preg_match('/<TOKEN_NO>(.*?)<\/TOKEN_NO>/', $body, $m)) {
                    $token = trim($m[1]);
                }
            }

            if (empty($token)) {
                Log::error('SMS token parse failed', ['body' => $body]);
                throw new Exception('SMS token response did not contain token');
            }

            return $token;
        });
    }

    public function getServicePermissions(): array
    {
        try {
            $token = $this->getToken();
            $url = $this->baseUrl . '/getServicePermissions';
            $response = Http::timeout(30)->get($url, ['token' => urlencode($token)]);
            if ($response->failed()) {
                throw new Exception('Failed to getServicePermissions: ' . $response->body());
            }

            $contentType = $response->header('Content-Type', '');
            $body = $response->body();

            if (stripos($contentType, 'xml') !== false || str_contains($body, '<HERMES_RESPONSE')) {
                $perms = [];
                try {
                    $xml = @simplexml_load_string($body);
                    if ($xml !== false) {
                        $nodes = $xml->xpath('//PERMISSIONS/PERMISSION');
                        foreach ($nodes as $node) {
                            $perms[] = [
                                'service_id' => (string) $node->SERVICE['id'],
                                'service_name' => trim((string) $node->SERVICE),
                                'plan_type' => trim((string) $node->PLAN_TYPE),
                                'plan_type_id' => (string) $node->PLAN_TYPE['id'],
                            ];
                        }
                    }
                } catch (Exception $e) {
                    Log::error('SMS getServicePermissions xml parse error', ['message' => $e->getMessage(), 'body' => $body]);
                }

                return ['success' => true, 'permissions' => $perms, 'raw' => $body];
            }

            $json = $response->json();
            return is_array($json) ? $json : ['success' => false, 'message' => 'Unexpected getServicePermissions response'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOriginators(): array
    {
        try {
            $token = $this->getToken();
            $url = $this->baseUrl . '/getOriginators';
            $response = Http::timeout(30)->get($url, [
                'token' => urlencode($token),
                'serviceId' => urlencode($this->serviceId),
            ]);

            if ($response->failed()) {
                throw new Exception('Failed to getOriginators: ' . $response->body());
            }

            $contentType = $response->header('Content-Type', '');
            $body = $response->body();

            if (stripos($contentType, 'xml') !== false || str_contains($body, '<HERMES_RESPONSE')) {
                $originators = [];
                try {
                    $xml = @simplexml_load_string($body);
                    if ($xml !== false) {
                        $nodes = $xml->xpath('//ORIGINATORS/ORIGINATOR');
                        foreach ($nodes as $node) {
                            $originators[] = [
                                'id' => (string) $node['id'],
                                'service_id' => (string) $node['service_id'],
                                'paymentProfile_id' => (string) $node['paymentProfile_id'],
                                'value' => trim((string) $node),
                            ];
                        }
                    }
                } catch (Exception $e) {
                    Log::error('SMS getOriginators xml parse error', ['message' => $e->getMessage(), 'body' => $body]);
                }

                return ['success' => true, 'originators' => $originators, 'raw' => $body];
            }

            $json = $response->json();
            return is_array($json) ? $json : ['success' => false, 'message' => 'Unexpected getOriginators response'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendSms(string $to, string $message, ?string $originatorId = null, int $validityPeriod = 1440, ?string $clientId = null): array
    {
        try {
            $token = $this->getToken();
            $originatorId = $originatorId ?: $this->originatorId;
            if (empty($originatorId)) {
                $originatorResult = $this->getOriginators();
                $originatorId = data_get($originatorResult, 'originators.0.id') ?: data_get($originatorResult, 'data.0.originatorId') ?: null;
            }

            if (empty($originatorId)) {
                throw new Exception('Originator ID missing. Set ILETISIM_ORIGINATOR_ID or configure the service to return one.');
            }

            $clientId = $clientId ?? $this->clientId ?? $this->vendorId;

            $url = str_replace('UserGatewayWS', 'SMSGatewayWS', $this->baseUrl) . '/sendSMS';
            $clientTransactionId = $clientId . '-' . microtime(true) . '-' . rand(1000, 9999);

            $query = [
                'token' => $token,
                'phoneNumbers' => json_encode([$to]),
                'templateText' => $message,
                'originatorId' => $originatorId,
                'isUTF8Allowed' => 'false',
                'isNLSSAllowed' => 'false',
                'validityPeriod' => (string) $validityPeriod,
                'clientTransactionId' => $clientTransactionId,
                'serviceId' => $this->serviceId,
            ];

            $response = Http::timeout(30)->asForm()->post($url, $query);
            if ($response->failed()) {
                $body = $response->body();
                Log::error('SMS sendSms request failed', ['url' => $url, 'query' => $query, 'response' => $body]);
                return ['success' => false, 'message' => 'SMS gönderilemedi: ' . substr($body, 0, 400)];
            }

            $body = $response->body();
            Log::debug('SMS sendSms response', ['body' => $body]);

            // SMS Gateway XML response
            $payload = null;
            if (stripos($response->header('Content-Type', ''), 'xml') !== false || str_contains($body, '<HERMES_RESPONSE')) {
                $xml = @simplexml_load_string($body);
                if ($xml !== false) {
                    $code = (string) ($xml->STATUS->CODE ?? '');
                    $desc = (string) ($xml->STATUS->DESC ?? '');
                    $payload = ['code' => $code, 'desc' => $desc, 'raw' => $body];
                    if ($code === '0') {
                        return ['success' => true, 'data' => $payload];
                    }
                    return ['success' => false, 'message' => $desc ?: 'SMS gönderimi başarısız', 'data' => $payload];
                }
            }

            try {
                $json = $response->json();
                if (is_array($json)) {
                    $payload = $json;
                    if (data_get($json, 'status') === 'success' || data_get($json, 'status') === 0) {
                        return ['success' => true, 'data' => $payload];
                    }
                    return ['success' => false, 'message' => data_get($json, 'message', 'SMS gönderimi başarısız'), 'data' => $payload];
                }
            } catch (Exception $e) {
                // ignore
            }

            return ['success' => false, 'message' => 'SMS gönderimi yapılamadı, beklenmeyen yanıt: ' . substr($body, 0, 400), 'data' => $payload];
        } catch (Exception $e) {
            Log::error('SMS sendSms exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
