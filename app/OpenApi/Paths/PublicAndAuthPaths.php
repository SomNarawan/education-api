<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[OA\Operation\Get(
    path: '/api/health',
    operationId: 'getHealth',
    summary: 'Check service health',
    description: 'Returns 200 for healthy or degraded status and 503 when a critical dependency is down.',
    tags: ['Health'],
    responses: [
        new OA\Response(response: 200, description: 'Service is healthy or degraded'),
        new OA\Response(response: 503, description: 'A critical dependency is unavailable'),
    ],
    security: [],
)]
#[ApiOperation(
    method: OA\HttpMethod::Post,
    path: '/api/auth/portal-login',
    operationId: 'portalLogin',
    summary: 'Exchange Portal tokens for an Education API JWT',
    tags: ['Authentication'],
    requestSchema: 'PortalLoginRequest',
    responseSchema: 'PortalLoginResponse',
    errors: [401, 403, 422, 429, 503],
    security: [],
)]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/auth/logout', operationId: 'logout', summary: 'Revoke the current Portal session', tags: ['Authentication'])]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/me',
    operationId: 'getCurrentUser',
    summary: 'Get claims for the authenticated user',
    tags: ['Authentication'],
    responseSchema: 'CurrentUserResponse',
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/mock-login/search',
    operationId: 'searchMockTeachers',
    summary: 'Search local mock teacher accounts',
    tags: ['Mock Login'],
    parameters: [new OA\Parameter\Query(name: 'q', schema: new OA\Schema(type: OA\Type::String, maxLength: 255))],
    responseSchema: 'FreeformArray',
    errors: [404],
    security: [],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/mock-login/students/search',
    operationId: 'searchMockStudents',
    summary: 'Search students available for local mock login',
    tags: ['Mock Login'],
    parameters: [new OA\Parameter\Query(name: 'q', schema: new OA\Schema(type: OA\Type::String, maxLength: 255))],
    responseSchema: 'FreeformArray',
    errors: [404],
    security: [],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/mock-login/system-teacher/{nontriId}',
    operationId: 'issueMockTeacherToken',
    summary: 'Issue a local teacher token and redirect to the frontend',
    tags: ['Mock Login'],
    parameters: [new OA\Parameter\Query(name: 'admin', schema: new OA\Schema(type: OA\Type::Boolean, default: false))],
    errors: [404],
    responses: [
        new OA\Response(response: 302, description: 'Redirect to the frontend callback with a token in the URL'),
        new OA\Response(response: 404, description: 'Mock login is disabled or the account was not found'),
    ],
    security: [],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/mock-login/student/{studentCode}',
    operationId: 'issueMockStudentToken',
    summary: 'Issue a local student token and redirect to the frontend',
    tags: ['Mock Login'],
    errors: [404],
    responses: [
        new OA\Response(response: 302, description: 'Redirect to the frontend callback with a token in the URL'),
        new OA\Response(response: 404, description: 'Student mock login is disabled or the student was not found'),
    ],
    security: [],
)]
final class PublicAndAuthPaths {}
