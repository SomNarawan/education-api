<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/advisor-summary/history', operationId: 'getAdvisorSummaryHistory', summary: 'Get the advisor dashboard history: GPA by study year and plan status counts per term', tags: ['Students'], parameters: [new OA\Parameter\Query(name: 'teacher_id', schema: new OA\Schema(type: OA\Type::String, maxLength: 50))], errors: [401, 403, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/students/advisor-summary/history/{academicYear}/{semester}', operationId: 'getAdvisorSummaryTermStudents', summary: 'List the students of one term of the advisor dashboard history', tags: ['Students'], parameters: [new OA\Parameter\Query(name: 'teacher_id', schema: new OA\Schema(type: OA\Type::String, maxLength: 50))], errors: [401, 403, 422])]
final class AdvisorSummaryPaths {}
