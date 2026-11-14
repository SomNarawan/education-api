<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'GradeCalculationRequest',
    type: OA\Type::Object,
    required: ['study_year', 'semester', 'courses'],
    properties: [
        new OA\Property(property: 'study_year', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1, maximum: 20)),
        new OA\Property(property: 'semester', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
        new OA\Property(property: 'semester_order', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], minimum: 1, maximum: 3)),
        new OA\Property(property: 'courses', schema: new OA\Schema(type: OA\Type::Array, minItems: 1, maxItems: 100, items: new OA\Schema(
            type: OA\Type::Object,
            required: ['code', 'name', 'credits', 'source'],
            properties: [
                new OA\Property(property: 'code', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
                new OA\Property(property: 'name', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
                new OA\Property(property: 'credits', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1, maximum: 99)),
                new OA\Property(property: 'source', schema: new OA\Schema(type: OA\Type::String)),
                new OA\Property(property: 'course_group', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 255)),
                new OA\Property(property: 'course_group_gpa', schema: new OA\Schema(type: [OA\Type::Number, OA\Type::Null], minimum: 0, maximum: 4)),
                new OA\Property(property: 'grade', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null])),
            ],
        ))),
    ],
)]
#[OA\Schema(
    component: 'AcademicCalendarSyncRequest',
    type: OA\Type::Object,
    required: ['academic_years'],
    properties: [
        new OA\Property(property: 'academic_years', schema: new OA\Schema(type: OA\Type::Array, minItems: 1, maxItems: 10, uniqueItems: true, items: new OA\Schema(type: OA\Type::Integer, minimum: 2000, maximum: 2100))),
    ],
)]
#[OA\Schema(
    component: 'AcademicPeriodSyncRequest',
    type: OA\Type::Object,
    properties: [
        new OA\Property(property: 'reference_date', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], format: OA\Format::Date, example: '2026-11-12')),
    ],
)]
final class OperationsSchemas {}
