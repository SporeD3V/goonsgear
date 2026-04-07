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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'geodb' => [
        'base_url' => env('GEODB_BASE_URL', 'http://geodb-free-service.wirefreethought.com/v1/geo'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
    ],

    'dhl' => [
        'tracking_url' => env('DHL_TRACKING_URL', 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s&submit=1'),
    ],

    'recaptcha' => [
        'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'trigger_after_attempts' => (int) env('RECAPTCHA_TRIGGER_AFTER_ATTEMPTS', 3),
    ],

    'staging' => [
        'ssh_host' => env('STAGING_SSH_HOST'),
        'ssh_port' => env('STAGING_SSH_PORT'),
        'ssh_user' => env('STAGING_SSH_USER'),
        'ssh_path' => env('STAGING_SSH_PATH'),
    ],

];
