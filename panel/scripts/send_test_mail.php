<?php
// Usage: php scripts/send_test_mail.php recipient@example.com "Subject" "Body text" [--use-relay]

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the framework
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\MailService $mailService */
$mailService = $app->make(\App\Services\MailService::class);

$argv = $_SERVER['argv'];
array_shift($argv); // script name

if (count($argv) < 1) {
    echo "Usage: php scripts/send_test_mail.php recipient@example.com \"Subject\" \"Body\" [--use-relay]\n";
    exit(1);
}

$to = array_shift($argv);
$subject = $argv ? array_shift($argv) : 'Test email from CoalApp';
$body = $argv ? array_shift($argv) : "This is a test email sent at " . date('c');

$useRelay = in_array('--use-relay', $argv, true) || env('MAIL_USE_RELAY', false);

$payload = [
    'to' => $to,
    'subject' => $subject,
    'body' => $body,
    'use_relay' => $useRelay,
];

echo "Sending test mail to {$to} (use_relay=" . ($useRelay ? 'true' : 'false') . ")...\n";

$result = $mailService->sendMail($payload);

echo "Result:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";

if (empty($result['success'])) {
    exit(2);
}

exit(0);
