<?php

declare(strict_types=1);

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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'curve' => [
        'client_id' => env('CURVE_CLIENT_ID'),
        'client_secret' => env('CURVE_CLIENT_SECRET'),
        'instance' => env('CURVE_INSTANCE'),
        'token_url' => env('CURVE_TOKEN_URL', 'https://sso.curvehero.com/connect/token'),
        'api_base' => env('CURVE_API_BASE', 'https://api.curvehero.com/v1'),
        'scope' => env('CURVE_SCOPE'),
        'sync_key' => env('CURVE_SYNC_KEY'),
    ],

    'curve' => [
        'client_id'     => env('CURVE_CLIENT_ID'),
        'client_secret' => env('CURVE_CLIENT_SECRET'),
        'instance'      => env('CURVE_INSTANCE'),
        'token_url'     => env('CURVE_TOKEN_URL', 'https://sso.curvehero.com/connect/token'),
        'api_base'      => env('CURVE_API_BASE', 'https://api.curvehero.com/v1'),
        'scope'         => env('CURVE_SCOPE'),
        'sync_key'      => env('CURVE_SYNC_KEY'),
        'appointment_start_date' => env('CURVE_APPOINTMENT_START_DATE', '2000-01-01 00:00:00'),
    ],

];
