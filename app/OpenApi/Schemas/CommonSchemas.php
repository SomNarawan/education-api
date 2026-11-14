<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(
    component: 'ApiSuccess',
    type: OA\Type::Object,
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', schema: new OA\Schema(type: OA\Type::Boolean, const: true)),
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String, example: 'OK')),
        new OA\Property(property: 'data', schema: new OA\Schema(description: 'Endpoint-specific response data')),
    ],
)]
#[OA\Schema(
    component: 'ApiError',
    type: OA\Type::Object,
    required: ['success', 'message', 'errors'],
    properties: [
        new OA\Property(property: 'success', schema: new OA\Schema(type: OA\Type::Boolean, const: false)),
        new OA\Property(property: 'code', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'PORTAL_SESSION_ENDED')),
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String, example: 'Request could not be completed')),
        new OA\Property(property: 'errors', schema: new OA\Schema(
            type: [OA\Type::Object, OA\Type::Array, OA\Type::Null],
            items: new OA\Schema,
        )),
    ],
)]
#[OA\Schema(
    component: 'ValidationError',
    type: OA\Type::Object,
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'success', schema: new OA\Schema(type: OA\Type::Boolean, example: false)),
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String, example: 'The given data was invalid.')),
        new OA\Property(property: 'errors', schema: new OA\Schema(
            type: OA\Type::Object,
            additionalProperties: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::String)),
            example: ['field' => ['The field is required.']],
        )),
    ],
)]
#[OA\Schema(
    component: 'PortalLoginRequest',
    type: OA\Type::Object,
    required: ['exchangeToken', 'intranetToken', 'role'],
    properties: [
        new OA\Property(property: 'exchangeToken', schema: new OA\Schema(type: OA\Type::String, maxLength: 8192, example: 'portal-exchange-token')),
        new OA\Property(property: 'intranetToken', schema: new OA\Schema(type: OA\Type::String, maxLength: 8192, example: 'portal-intranet-token')),
        new OA\Property(property: 'role', schema: new OA\Schema(type: OA\Type::String, enum: ['admin', 'teacher'], example: 'teacher')),
    ],
)]
#[OA\Schema(
    component: 'PortalLoginResponse',
    type: OA\Type::Object,
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', schema: new OA\Schema(type: OA\Type::Boolean, const: true)),
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String)),
        new OA\Property(property: 'data', schema: new OA\Schema(type: OA\Type::Object, required: ['token', 'user'], properties: [
            new OA\Property(property: 'token', schema: new OA\Schema(type: OA\Type::String, description: 'Bearer JWT')),
            new OA\Property(property: 'user', schema: new OA\Schema(ref: '#/components/schemas/CurrentUser')),
        ])),
    ],
)]
#[OA\Schema(
    component: 'CurrentUser',
    type: OA\Type::Object,
    properties: [
        new OA\Property(property: 'nontri_id', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'example.user')),
        new OA\Property(property: 'name', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'Example User')),
        new OA\Property(property: 'role', schema: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::String), example: ['teacher'])),
        new OA\Property(property: 'current_role', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'teacher')),
        new OA\Property(property: 'department_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null])),
        new OA\Property(property: 'faculty_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null])),
        new OA\Property(property: 'study_plan_id', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null])),
        new OA\Property(property: 'iat', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], format: OA\Format::Int64)),
        new OA\Property(property: 'exp', schema: new OA\Schema(type: [OA\Type::Integer, OA\Type::Null], format: OA\Format::Int64)),
    ],
)]
#[OA\Schema(
    component: 'CurrentUserResponse',
    type: OA\Type::Object,
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', schema: new OA\Schema(type: OA\Type::Boolean, const: true)),
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String)),
        new OA\Property(property: 'data', schema: new OA\Schema(ref: '#/components/schemas/CurrentUser')),
    ],
)]
#[OA\Schema(
    component: 'PortalMainCheckUser',
    type: OA\Type::Object,
    required: ['nontriId', 'exists'],
    properties: [
        new OA\Property(property: 'nontriId', schema: new OA\Schema(type: OA\Type::String, example: 'example.user')),
        new OA\Property(property: 'exists', schema: new OA\Schema(type: OA\Type::Boolean)),
    ],
)]
#[OA\Schema(
    component: 'PortalMainError',
    type: OA\Type::Object,
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', schema: new OA\Schema(type: OA\Type::String, example: 'Invalid or missing API key')),
    ],
)]
#[OA\Schema(
    component: 'PortalMainStudent',
    type: OA\Type::Object,
    required: ['nontriId', 'name', 'surname', 'kuMail', 'agency'],
    properties: [
        new OA\Property(property: 'nontriId', schema: new OA\Schema(type: OA\Type::String, example: 'example.user')),
        new OA\Property(property: 'name', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'Somchai')),
        new OA\Property(property: 'surname', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'Example')),
        new OA\Property(property: 'kuMail', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], format: OA\Format::Email, example: 'example.user@ku.th')),
        new OA\Property(property: 'agency', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], example: 'Department of Example Engineering')),
    ],
)]
#[OA\Schema(
    component: 'PortalMainStudentList',
    type: OA\Type::Array,
    items: new OA\Schema(ref: '#/components/schemas/PortalMainStudent'),
)]
#[OA\Schema(
    component: 'NontriSearchResult',
    type: OA\Type::Object,
    required: ['nontriIds'],
    properties: [
        new OA\Property(property: 'nontriIds', schema: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::String), example: ['example.user'])),
    ],
)]
#[OA\Schema(
    component: 'MasterDataWriteRequest',
    type: OA\Type::Object,
    description: 'Fields depend on the master-data type. See each operation description.',
    additionalProperties: true,
)]
#[OA\Schema(
    component: 'StatusUpdateRequest',
    type: OA\Type::Object,
    required: ['status'],
    properties: [
        new OA\Property(property: 'status', schema: new OA\Schema(type: OA\Type::String, enum: ['active', 'inactive'], example: 'active')),
    ],
)]
#[OA\Schema(
    component: 'NoteWriteRequest',
    type: OA\Type::Object,
    required: ['student_id', 'note_type_id'],
    properties: [
        new OA\Property(property: 'student_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'note_type_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Property(property: 'remark', schema: new OA\Schema(type: [OA\Type::String, OA\Type::Null], maxLength: 255)),
    ],
)]
#[OA\Schema(
    component: 'BulkNontriRequest',
    type: OA\Type::Object,
    required: ['nontriIds'],
    properties: [
        new OA\Property(property: 'nontriIds', schema: new OA\Schema(type: OA\Type::Array, minItems: 1, maxItems: 100, items: new OA\Schema(type: OA\Type::String, maxLength: 50), example: ['user.one', 'user.two'])),
    ],
)]
#[OA\Schema(component: 'FreeformObject', type: OA\Type::Object, additionalProperties: true)]
#[OA\Schema(component: 'FreeformArray', type: OA\Type::Array, items: new OA\Schema(type: OA\Type::Object, additionalProperties: true))]
final class CommonSchemas {}
