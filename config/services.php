<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'aba' => [
        'merchant_id' => env('ABA_PAYWAY_MERCHANT_ID'),
        'api_key'     => env('ABA_PAYWAY_API_KEY'),
        'url'         => env('ABA_PAYWAY_API_URL'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'tokovoucher' => [
        'member_code'       => env('TOKOVOUCHER_MEMBER_CODE'),
        'secret_key'        => env('TOKOVOUCHER_SECRET_KEY'),
        'url'               => env('TOKOVOUCHER_API_URL', 'https://api.tokovoucher.net/v1'),
        'signature_default' => env('TOKOVOUCHER_SIGNATURE_DEFAULT'),
    ],
];
