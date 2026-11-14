<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/imports', operationId: 'listImports', summary: 'List import jobs by type', tags: ['Imports'], parameters: [new OA\Parameter\Query(name: 'type', required: true, schema: new OA\Schema(type: OA\Type::String, maxLength: 50))], errors: [401, 422])]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/imports/{id}/result',
    operationId: 'downloadImportResult',
    summary: 'Download the result file for an import job',
    tags: ['Imports'],
    responses: [
        new OA\Response(response: 200, description: 'Import result file', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: OA\Type::String, format: 'binary'))),
        new OA\Response(response: 401, description: 'Missing or invalid credentials', content: new OA\MediaType\Json(ref: '#/components/schemas/ApiError')),
        new OA\Response(response: 404, description: 'Import job or result file not found', content: new OA\MediaType\Json(ref: '#/components/schemas/ApiError')),
    ],
)]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/grades/import/template',
    operationId: 'downloadGradeImportTemplate',
    summary: 'Download the grade-import spreadsheet template',
    tags: ['Grade Imports'],
    responses: [
        new OA\Response(response: 200, description: 'XLSX template', content: new OA\MediaType(mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', schema: new OA\Schema(type: OA\Type::String, format: 'binary'))),
        new OA\Response(response: 401, description: 'Missing or invalid credentials', content: new OA\MediaType\Json(ref: '#/components/schemas/ApiError')),
    ],
)]
#[ApiOperation(
    method: OA\HttpMethod::Post,
    path: '/api/grades/import',
    operationId: 'importGrades',
    summary: 'Import grades from an XLSX spreadsheet',
    tags: ['Grade Imports'],
    requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
        type: OA\Type::Object,
        required: ['file', 'curriculum_id', 'curriculum_name'],
        properties: [
            new OA\Property(property: 'file', schema: new OA\Schema(type: OA\Type::String, format: 'binary')),
            new OA\Property(property: 'curriculum_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
            new OA\Property(property: 'curriculum_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        ],
    ))),
    errors: [401, 422, 500],
)]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/grade-calculations', operationId: 'listGradeCalculations', summary: 'List saved grade calculations', tags: ['Grade Calculations'], parameters: [
    new OA\Parameter\Query(name: 'student_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
    new OA\Parameter\Query(name: 'page', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1, default: 1)),
    new OA\Parameter\Query(name: 'per_page', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1, maximum: 100)),
], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/grade-calculations/latest', operationId: 'getLatestGradeCalculation', summary: 'Get the latest grade calculation', tags: ['Grade Calculations'], parameters: [new OA\Parameter\Query(name: 'student_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))], errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/grade-calculations', operationId: 'createGradeCalculation', summary: 'Calculate and save grades', tags: ['Grade Calculations'], requestSchema: 'GradeCalculationRequest', success: 201, errors: [401, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/syncs', operationId: 'listSyncs', summary: 'List synchronization history', tags: ['Synchronization'], parameters: [
    new OA\Parameter\Query(name: 'sync_type', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
    new OA\Parameter\Query(name: 'status', schema: new OA\Schema(type: OA\Type::String)),
], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/student-academic-period-sync/preview', operationId: 'previewAcademicPeriodSync', summary: 'Preview student academic-period changes', tags: ['Synchronization'], parameters: [new OA\Parameter\Query(name: 'reference_date', schema: new OA\Schema(type: OA\Type::String, format: OA\Format::Date))], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/student-academic-period-sync/history', operationId: 'listAcademicPeriodSyncHistory', summary: 'List student academic-period sync history', tags: ['Synchronization'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/student-academic-period-sync', operationId: 'syncStudentAcademicPeriods', summary: 'Synchronize student academic periods', tags: ['Synchronization'], requestSchema: 'AcademicPeriodSyncRequest', errors: [401, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/academic-calendars', operationId: 'listAcademicCalendars', summary: 'Get academic-calendar terms', tags: ['Academic Calendars'], parameters: [new OA\Parameter\Query(name: 'academicYear', schema: new OA\Schema(type: OA\Type::Integer, minimum: 2000, maximum: 2100))], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/academic-calendars/sync', operationId: 'syncAcademicCalendars', summary: 'Synchronize academic calendars for selected years', tags: ['Academic Calendars'], requestSchema: 'AcademicCalendarSyncRequest', errors: [401, 422, 503])]
final class OperationsPaths {}
