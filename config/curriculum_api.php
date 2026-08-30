<?php

return [
    'driver' => env('CURRICULUM_API_DRIVER', 'mock'),
    'url' => env('CURRICULUM_API_URL'),
    'base_path' => env('CURRICULUM_API_BASE_PATH', ''),
    'timeout' => (int) env('CURRICULUM_API_TIMEOUT', 15),
    'verify_ssl' => env('CURRICULUM_API_VERIFY_SSL', true),

    'endpoints' => [
        'curriculums' => env('CURRICULUM_API_CURRICULUMS_ENDPOINT', '/curriculums'),
        'study_plans' => env('CURRICULUM_API_STUDY_PLANS_ENDPOINT', '/study-plans'),
        'curriculum_categories' => env('CURRICULUM_API_CATEGORIES_ENDPOINT', '/curriculum-categories'),
    ],

    'mock' => require __DIR__.'/../resources/mocks/curriculum.php',
];
