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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'kku_sso' => [
        'enabled' => env('KKU_SSO_ENABLED', false),
        'app_id' => env('KKU_SSO_APP_ID'),
        'client_id' => env('KKU_SSO_CLIENT_ID'),
        'client_secret' => env('KKU_SSO_CLIENT_SECRET'),
        'web_url' => env('KKU_SSO_WEB_URL', 'https://sso-uat-web.kku.ac.th'),
        'api_url' => env('KKU_SSO_API_URL', 'https://sso-uat-api.kku.ac.th'),
        'redirect_url' => env('KKU_SSO_REDIRECT_URL', rtrim((string) env('APP_URL'), '/').'/auth/kku/callback'),
        'password_login_enabled' => env('KKU_SSO_PASSWORD_LOGIN_ENABLED', true),
        'timeout' => env('KKU_SSO_TIMEOUT', 10),
    ],

];
