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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // The IMIS portal that signs staff in (ImisSsoController). Unset = off.
    'imis' => [
        'sso_url' => env('IMIS_SSO_URL'),
        // Where "Back to IMIS" goes; defaults to the root of the SSO host.
        'portal_url' => env('IMIS_PORTAL_URL'),
        // This app's own host[:port], as registered in IMIS (SSO_HUB_HOSTS).
        'audience' => env('IMIS_SSO_AUDIENCE', parse_url((string) env('APP_URL'), PHP_URL_HOST).(parse_url((string) env('APP_URL'), PHP_URL_PORT) ? ':'.parse_url((string) env('APP_URL'), PHP_URL_PORT) : '')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
