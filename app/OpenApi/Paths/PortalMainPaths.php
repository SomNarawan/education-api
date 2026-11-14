<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/portal-main-student/check-user/{nontriId}',
    operationId: 'portalMainCheckUser',
    summary: 'Check whether a Nontri account belongs to a student',
    tags: ['Portal Main'],
    responseSchema: 'PortalMainCheckUser',
    errorSchema: 'PortalMainError',
    errors: [401],
    security: [new OA\Security\Requirement(scheme: 'portalApiKey')],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/portal-main-student/get-user-data-by-nontri/{nontriId}',
    operationId: 'portalMainGetUser',
    summary: 'Get student data by Nontri account',
    tags: ['Portal Main'],
    responseSchema: 'PortalMainStudent',
    errorSchema: 'PortalMainError',
    errors: [401, 404],
    security: [new OA\Security\Requirement(scheme: 'portalApiKey')],
)]
#[ApiOperation(
    method: OA\HttpMethod::Post,
    path: '/api/portal-main-student/get-user-data-list-by-nontri',
    operationId: 'portalMainGetUsers',
    summary: 'Get student data for up to 100 Nontri accounts',
    tags: ['Portal Main'],
    requestSchema: 'BulkNontriRequest',
    responseSchema: 'PortalMainStudentList',
    errorSchema: 'PortalMainError',
    errors: [401, 422],
    security: [new OA\Security\Requirement(scheme: 'portalApiKey')],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/portal-main-student/search-nontri-by-any',
    operationId: 'portalMainSearchByAny',
    summary: 'Search student Nontri IDs by one keyword',
    tags: ['Portal Main'],
    parameters: [new OA\Parameter\Query(name: 'search', required: true, schema: new OA\Schema(type: OA\Type::String, minLength: 1, maxLength: 255))],
    responseSchema: 'NontriSearchResult',
    errorSchema: 'PortalMainError',
    errors: [401, 422],
    security: [new OA\Security\Requirement(scheme: 'portalApiKey')],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/portal-main-student/search-nontri',
    operationId: 'portalMainSearch',
    summary: 'Search student Nontri IDs by account, name, or agency',
    tags: ['Portal Main'],
    parameters: [
        new OA\Parameter\Query(name: 'nontriId', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
        new OA\Parameter\Query(name: 'fullName', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        new OA\Parameter\Query(name: 'agency', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
    ],
    responseSchema: 'NontriSearchResult',
    errorSchema: 'PortalMainError',
    errors: [401, 422],
    security: [new OA\Security\Requirement(scheme: 'portalApiKey')],
    description: 'At least one of nontriId, fullName, or agency is required.',
)]
final class PortalMainPaths {}
