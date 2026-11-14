<?php

return [

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

    /*
     * Optional AI layer. Leave AI_API_KEY empty and the app uses the
     * deterministic rule-based explainer instead. No key is required.
     */
    'ai' => [
        'key' => env('AI_API_KEY'),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
    ],

];
