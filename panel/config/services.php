<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
        'test_token' => env('RECAPTCHA_TEST_TOKEN'),
        'min_score' => env('RECAPTCHA_MIN_SCORE', 0.5),
    ],

    'iletisimmakinesi' => [
        'base_url' => env('ILETISIM_BASE_URL', 'https://live.iletisimmakinesi.com/api/UserGatewayWS/functions'),
        'username' => env('ILETISIM_USERNAME'),
        'password' => env('ILETISIM_PASSWORD'),
        'api_key' => env('ILETISIM_API_KEY'),
        'vendor_id' => env('ILETISIM_VENDOR_ID'),
        'customer_code' => env('ILETISIM_CUSTOMER_CODE'),
        'service_id' => env('ILETISIM_SERVICE_ID', '7'),
        'originator_id' => env('ILETISIM_ORIGINATOR_ID'),
        'client_id' => env('ILETISIM_CLIENT_ID'),
    ],
];
