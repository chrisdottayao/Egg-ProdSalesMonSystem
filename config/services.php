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
        'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],
    
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    // Generic OpenAI-compatible chat completions provider for the AI Insight
    // card. AI_PROVIDER/AI_BASE_URL/AI_MODEL/AI_API_KEY are what the service
    // actually reads; OPENAI_API_KEY/OPENAI_MODEL are kept as fallbacks so an
    // existing .env doesn't break, and switching providers (Groq, OpenAI, or
    // any other OpenAI-compatible endpoint) is a .env change, not a code one.
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
        'api_key'  => env('AI_API_KEY', env('OPENAI_API_KEY')),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'model'    => env('AI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),
    ],

];
