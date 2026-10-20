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

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
    ],

    // Venue location finder (OpenStreetMap Nominatim). Set GEOCODING_EMAIL to a contact address in production.
    'geocoding' => [
        'url' => env('GEOCODING_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('GEOCODING_USER_AGENT'),
        'email' => env('GEOCODING_EMAIL'),
        'country_codes' => env('GEOCODING_COUNTRY_CODES', 'ph'),
        'timeout' => (float) env('GEOCODING_TIMEOUT', 8),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'timeout' => (float) env('BREVO_API_TIMEOUT', 15),
        'connect_timeout' => (float) env('BREVO_API_CONNECT_TIMEOUT', 5),
    ],

];
