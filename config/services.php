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

    'office_ku' => [
        'url' => env('OFFICE_KU_API_URL'),
        'timeout' => (int) env('OFFICE_KU_API_TIMEOUT', 30),
        'verify_ssl' => filter_var(
            env('OFFICE_KU_API_VERIFY_SSL', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'personnel_api' => [
        'base_path' => '/kukps-eng-personnel-api/api/portal-student-map',

        'endpoints' => [
            'all_department' => '/all-department',
            'all_faculty' => '/all-faculty',
        ],
    ],

    'academic_calendar_api' => [
        'endpoint' => '/kukps-eng-leavesystem-api/academic-calendars',
    ],

];
