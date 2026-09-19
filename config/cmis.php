<?php

return [
    'url' => env('CMIS_URL'),
    'base_path' => env('CMIS_SERVICE_BASE_PATH', '/api'),
    'timeout' => (int) env('CMIS_SERVICE_TIMEOUT', 15),
    'verify_ssl' => env('CMIS_SERVICE_VERIFY_SSL', true),

    'endpoints' => [
        'curriculums' => '/curriculums',
        'curriculum_plans' => '/curriculums-plans',
        'curriculum_categories' => '/curriculum-categories',
        'curriculum_personnel' => '/curriculum-personnel',
        'teachers' => '/teachers',
    ],
];
