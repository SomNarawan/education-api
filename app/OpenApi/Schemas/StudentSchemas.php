<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'StudentWriteFields',
    type: OA\Type::Object,
    properties: [
        new OA\Property(property: 'student_code', schema: new OA\Schema(type: OA\Type::String, pattern: '^\\d+$', maxLength: 10, example: '6612345678')),
        new OA\Property(property: 'student_id_card', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 13)),
        new OA\Property(property: 'title_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'first_name_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 50, example: 'สมชาย')),
        new OA\Property(property: 'last_name_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 50, example: 'ตัวอย่าง')),
        new OA\Property(property: 'first_name_en', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50, example: 'Somchai')),
        new OA\Property(property: 'last_name_en', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50, example: 'Example')),
        new OA\Property(property: 'phone', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 10, example: '0812345678')),
        new OA\Property(property: 'email', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], format: OA\Format::Email, maxLength: 50, example: 'student@example.test')),
        new OA\Property(property: 'teacher_id', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50)),
        new OA\Property(property: 'teacher_full_name', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 255)),
        new OA\Property(property: 'student_status_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'admission_channel_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'high_school_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], minimum: 1)),
        new OA\Property(property: 'curriculum_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'curriculum_code', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        new OA\Property(property: 'study_plan_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'study_plan_name_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        new OA\Property(property: 'is_international', schema: new OA\Schema(type: OA\Type::Boolean)),
        new OA\Property(property: 'system_department_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'entry_year', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1901, maximum: 2155, example: 2026)),
        new OA\Property(property: 'guardian_title_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], minimum: 1)),
        new OA\Property(property: 'guardian_first_name_th', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50)),
        new OA\Property(property: 'guardian_last_name_th', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50)),
        new OA\Property(property: 'guardian_relationship_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], minimum: 1)),
        new OA\Property(property: 'guardian_phone', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 10)),
    ],
)]
#[OA\Schema(
    component: 'StudentCreateRequest',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/StudentWriteFields'),
        new OA\Schema(required: [
            'student_code', 'title_id', 'first_name_th', 'last_name_th', 'student_status_id',
            'admission_channel_id', 'curriculum_id', 'curriculum_code', 'study_plan_id',
            'study_plan_name_th', 'is_international', 'system_department_id', 'entry_year',
        ]),
    ],
)]
#[OA\Schema(component: 'StudentUpdateRequest', ref: '#/components/schemas/StudentWriteFields')]
#[OA\Schema(
    component: 'UpdateStudentAdvisorRequest',
    type: OA\Type::Object,
    required: ['study_plan_id', 'teacher_id', 'teacher_full_name', 'assign_student_ids', 'remove_student_ids'],
    properties: [
        new OA\Property(property: 'study_plan_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'teacher_id', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
        new OA\Property(property: 'teacher_full_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
        new OA\Property(property: 'assign_student_ids', schema: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::Integer, minimum: 1))),
        new OA\Property(property: 'remove_student_ids', schema: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::Integer, minimum: 1))),
    ],
)]
#[OA\Schema(
    component: 'ResetStudentGradesRequest',
    type: OA\Type::Object,
    required: ['scope'],
    properties: [
        new OA\Property(property: 'scope', schema: new OA\Schema(type: OA\Type::String, enum: ['all', 'semester'])),
        new OA\Property(property: 'study_year', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], minimum: 1)),
        new OA\Property(property: 'semester', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 50)),
    ],
)]
final class StudentSchemas {}
