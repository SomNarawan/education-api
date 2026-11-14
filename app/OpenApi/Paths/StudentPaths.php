<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/students',
    operationId: 'listStudents',
    summary: 'List students using optional filters',
    tags: ['Students'],
    parameters: [
        new OA\Parameter\Query(name: 'teacher_id', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
        new OA\Parameter\Query(name: 'department_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'faculty_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'student_status_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'search_text', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        new OA\Parameter\Query(name: 'search_note_type_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'search_note', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
    ],
    errors: [401, 422],
)]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/students', operationId: 'createStudent', summary: 'Create a student', tags: ['Students'], requestSchema: 'StudentCreateRequest', success: 201, errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/studying', operationId: 'listStudyingStudents', summary: 'List studying students for an advisor and study plan', tags: ['Students'], parameters: [
    new OA\Parameter\Query(name: 'teacher_id', required: true, schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
    new OA\Parameter\Query(name: 'study_plan_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/studying/without-advisor', operationId: 'listStudentsWithoutAdvisor', summary: 'List studying students without an advisor', tags: ['Students'], parameters: [new OA\Parameter\Query(name: 'study_plan_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/advisor-summary', operationId: 'getAdvisorSummary', summary: 'Get advisor dashboard summary', tags: ['Students'], parameters: [new OA\Parameter\Query(name: 'teacher_id', schema: new OA\Schema(type: OA\Type::String, maxLength: 50))], errors: [401, 403, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/students/advisor', operationId: 'updateStudentAdvisor', summary: 'Assign and remove students for an advisor', tags: ['Students'], requestSchema: 'UpdateStudentAdvisorRequest', errors: [401, 422])]
#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/students/import/template',
    operationId: 'downloadStudentImportTemplate',
    summary: 'Download the student-import spreadsheet template',
    tags: ['Student Imports'],
    parameters: [new OA\Parameter\Query(name: 'curriculum_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))],
    responses: [
        new OA\Response(response: 200, description: 'XLSX template', content: new OA\MediaType(mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', schema: new OA\Schema(type: OA\Type::String, format: 'binary'))),
        new OA\Response(response: 401, description: 'Missing or invalid credentials', content: new OA\MediaType\Json(ref: '#/components/schemas/ApiError')),
        new OA\Response(response: 422, description: 'Validation failed', content: new OA\MediaType\Json(ref: '#/components/schemas/ValidationError')),
    ],
)]
#[ApiOperation(
    method: OA\HttpMethod::Post,
    path: '/api/students/import',
    operationId: 'importStudents',
    summary: 'Import students from an XLSX spreadsheet',
    tags: ['Student Imports'],
    requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(type: OA\Type::Object, required: ['file', 'system_department_id', 'curriculum_id', 'curriculum_code', 'is_international', 'study_plan_id', 'study_plan_name_th'], properties: [
            new OA\Property(property: 'file', schema: new OA\Schema(type: OA\Type::String, format: 'binary')),
            new OA\Property(property: 'system_department_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
            new OA\Property(property: 'curriculum_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
            new OA\Property(property: 'curriculum_code', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
            new OA\Property(property: 'is_international', schema: new OA\Schema(type: OA\Type::Boolean)),
            new OA\Property(property: 'study_plan_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
            new OA\Property(property: 'study_plan_name_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        ]),
    )),
    errors: [401, 422, 500],
)]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{id}', operationId: 'getStudent', summary: 'Get student details', tags: ['Students'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/students/{id}', operationId: 'updateStudent{method}', summary: 'Update a student', tags: ['Students'], requestSchema: 'StudentUpdateRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Delete, path: '/api/students/{id}', operationId: 'deleteStudent', summary: 'Delete a student', tags: ['Students'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{id}/academic-overview', operationId: 'getStudentAcademicOverview', summary: 'Get the student academic overview JSON', tags: ['Student Academic Data'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{id}/unattempted-courses', operationId: 'getStudentUnattemptedCourses', summary: 'Get curriculum courses not yet attempted', tags: ['Student Academic Data'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{studentCode}/enrollments', operationId: 'getStudentEnrollments', summary: 'Get enrollment data by student code', tags: ['Student Academic Data'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{studentCode}/enrollment-statuses', operationId: 'getStudentEnrollmentStatuses', summary: 'Get passed, failed, and excess enrollment data', tags: ['Student Academic Data'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/{studentCode}/performance-summary', operationId: 'getStudentPerformanceSummary', summary: 'Get student performance charts and summaries', tags: ['Student Academic Data'], errors: [401, 404])]
#[ApiOperation(method: OA\HttpMethod::Delete, path: '/api/students/{studentCode}/grades', operationId: 'resetStudentGrades', summary: 'Reset all or one semester of student grades', tags: ['Student Academic Data'], requestSchema: 'ResetStudentGradesRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/notes', operationId: 'listStudentNotes', summary: 'List notes for a student', tags: ['Student Notes'], parameters: [new OA\Parameter\Query(name: 'student_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/notes', operationId: 'createStudentNote', summary: 'Create a student note', tags: ['Student Notes'], requestSchema: 'NoteWriteRequest', success: 201, errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Delete, path: '/api/notes/{id}', operationId: 'deleteStudentNote', summary: 'Delete a student note', tags: ['Student Notes'], errors: [401, 403, 404])]
final class StudentPaths {}
