<?php

$defaults = require base_path('vendor/darkaonline/l5-swagger/config/l5-swagger.php');

return array_replace_recursive($defaults, [
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Education API — Swagger UI',
            ],
            'routes' => [
                'api' => 'api/documentation',
            ],
            'paths' => [
                'annotations' => [
                    base_path('app/OpenApi'),
                ],
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
            ],
        ],
    ],
    'defaults' => [
        'scanOptions' => [
            'mode' => 'spec',
            'open_api_spec_version' => '3.1.0',
            'default_processors_configuration' => [
                'cleanup.enabled' => false,
            ],
        ],
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', true),
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', 'alpha'),
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', '/'),
        ],
        'ui' => [
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', false),
            ],
        ],
    ],
]);
