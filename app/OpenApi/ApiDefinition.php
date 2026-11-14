<?php

namespace App\OpenApi;

use OpenApi\Spec as OA;

#[OA\OpenApi(
    version: '3.1.0',
    security: [new OA\Security\Requirement(scheme: 'bearerAuth')],
)]
#[OA\Info(
    title: 'Education API',
    version: '1.0.0',
    description: 'API contract for the Education system and external integrations.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Education API server')]
#[OA\Security\Scheme\Http(
    component: 'bearerAuth',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Security\Scheme\ApiKey(
    component: 'portalApiKey',
    name: 'X-API-KEY',
    in: OA\SchemeIn::Header,
)]
#[OA\Tag(name: 'Health', description: 'Service health and dependency checks')]
final class ApiDefinition {}
