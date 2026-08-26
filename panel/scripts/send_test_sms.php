<?php
// Usage: php scripts/send_test_sms.php 5438826976 "Message text"

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the framework
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\SmsService $smsService */
$smsService = $app->make(\App\Services\SmsService::class);

$argv = $_SERVER['argv'];
array_shift($argv); // script name

$to = $argv ? array_shift($argv) : '5438826976';
$message = $argv ? array_shift($argv) : "Test SMS from CoalApp at " . date('c');

echo "Sending test SMS to {$to}...\n";

$result = $smsService->sendSms($to, $message);

echo "Result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";

if (empty($result['success'])) {
    exit(2);
}

exit(0);
