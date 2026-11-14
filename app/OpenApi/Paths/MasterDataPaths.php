<?php

namespace App\OpenApi\Paths;

use App\OpenApi\ApiOperation;
use OpenApi\Spec as OA;

#[ApiOperation(
    method: OA\HttpMethod::Get,
    path: '/api/list-of-values/{type}',
    operationId: 'listOfValues',
    summary: 'Get selectable values by type',
    tags: ['Lookup Values'],
    parameters: [
        new OA\Parameter\Query(name: 'province_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'district_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'curriculum_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
        new OA\Parameter\Query(name: 'include_ids', schema: new OA\Schema(type: OA\Type::Array, items: new OA\Schema(type: OA\Type::Integer, minimum: 1))),
    ],
    errors: [401, 422],
)]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/titles', operationId: 'listTitles', summary: 'List titles', tags: ['Titles'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/titles', operationId: 'createTitle', summary: 'Create a title', tags: ['Titles'], requestSchema: 'TitleWriteRequest', success: 201, errors: [401, 422], description: 'Required fields: title_abbr_th, title_abbr_en, title_name_th, title_name_en (maximum 50 characters each).')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/titles/{id}', operationId: 'getTitle', summary: 'Get a title', tags: ['Titles'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/titles/{id}', operationId: 'updateTitle{method}', summary: 'Update a title', tags: ['Titles'], requestSchema: 'TitleWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/titles/{id}/status', operationId: 'updateTitleStatus', summary: 'Update title status', tags: ['Titles'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/admission-channels', operationId: 'listAdmissionChannels', summary: 'List admission channels', tags: ['Admission Channels'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/admission-channels', operationId: 'createAdmissionChannel', summary: 'Create an admission channel', tags: ['Admission Channels'], requestSchema: 'AdmissionChannelWriteRequest', success: 201, errors: [401, 422], description: 'Required field: channel_name.')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/admission-channels/{id}', operationId: 'getAdmissionChannel', summary: 'Get an admission channel', tags: ['Admission Channels'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/admission-channels/{id}', operationId: 'updateAdmissionChannel{method}', summary: 'Update an admission channel', tags: ['Admission Channels'], requestSchema: 'AdmissionChannelWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/admission-channels/{id}/status', operationId: 'updateAdmissionChannelStatus', summary: 'Update admission-channel status', tags: ['Admission Channels'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/high-schools', operationId: 'listHighSchools', summary: 'List high schools', tags: ['High Schools'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/high-schools', operationId: 'createHighSchool', summary: 'Create a high school', tags: ['High Schools'], requestSchema: 'HighSchoolWriteRequest', success: 201, errors: [401, 422], description: 'Required fields: school_name, subdistrict_id, latitude (-90..90), and longitude (-180..180).')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/high-schools/{id}', operationId: 'getHighSchool', summary: 'Get a high school', tags: ['High Schools'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/high-schools/{id}', operationId: 'updateHighSchool{method}', summary: 'Update a high school', tags: ['High Schools'], requestSchema: 'HighSchoolWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/high-schools/{id}/status', operationId: 'updateHighSchoolStatus', summary: 'Update high-school status', tags: ['High Schools'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/relationships', operationId: 'listRelationships', summary: 'List guardian relationships', tags: ['Relationships'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/relationships', operationId: 'createRelationship', summary: 'Create a guardian relationship', tags: ['Relationships'], requestSchema: 'RelationshipWriteRequest', success: 201, errors: [401, 422], description: 'Required field: relationship_name.')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/relationships/{id}', operationId: 'getRelationship', summary: 'Get a guardian relationship', tags: ['Relationships'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/relationships/{id}', operationId: 'updateRelationship{method}', summary: 'Update a guardian relationship', tags: ['Relationships'], requestSchema: 'RelationshipWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/relationships/{id}/status', operationId: 'updateRelationshipStatus', summary: 'Update relationship status', tags: ['Relationships'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/student-statuses', operationId: 'listStudentStatuses', summary: 'List student statuses', tags: ['Student Statuses'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/student-statuses', operationId: 'createStudentStatus', summary: 'Create a student status', tags: ['Student Statuses'], requestSchema: 'StudentStatusWriteRequest', success: 201, errors: [401, 422], description: 'Required field: status_name.')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/student-statuses/{id}', operationId: 'getStudentStatus', summary: 'Get a student status', tags: ['Student Statuses'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/student-statuses/{id}', operationId: 'updateStudentStatus{method}', summary: 'Update a student status', tags: ['Student Statuses'], requestSchema: 'StudentStatusWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/student-statuses/{id}/status', operationId: 'updateStudentStatusState', summary: 'Update student-status state', tags: ['Student Statuses'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/note-types', operationId: 'listNoteTypes', summary: 'List note types', tags: ['Note Types'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/note-types', operationId: 'createNoteType', summary: 'Create a note type', tags: ['Note Types'], requestSchema: 'NoteTypeWriteRequest', success: 201, errors: [401, 422], description: 'Required field: note.')]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/note-types/{id}', operationId: 'getNoteType', summary: 'Get a note type', tags: ['Note Types'], errors: [401, 404])]
#[ApiOperation(method: null, methods: [OA\HttpMethod::Put, OA\HttpMethod::Patch], path: '/api/note-types/{id}', operationId: 'updateNoteType{method}', summary: 'Update a note type', tags: ['Note Types'], requestSchema: 'NoteTypeWriteRequest', errors: [401, 404, 422])]
#[ApiOperation(method: OA\HttpMethod::Patch, path: '/api/note-types/{id}/status', operationId: 'updateNoteTypeStatus', summary: 'Update note-type status', tags: ['Note Types'], requestSchema: 'StatusUpdateRequest', errors: [401, 404, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/curriculum-categories', operationId: 'listCurriculumCategories', summary: 'List curriculum categories for a study plan', tags: ['Curriculum'], parameters: [new OA\Parameter\Query(name: 'study_plan_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))], errors: [401, 422])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/curriculum-plan-courses', operationId: 'listCurriculumPlanCourses', summary: 'List courses for a curriculum study plan', tags: ['Curriculum'], parameters: [new OA\Parameter\Query(name: 'study_plan_id', required: true, schema: new OA\Schema(type: OA\Type::Integer, minimum: 1))], errors: [401, 422])]

#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/system-departments', operationId: 'listSystemDepartments', summary: 'List active system departments', tags: ['Organization'])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/system-departments/all', operationId: 'listAllSystemDepartments', summary: 'List all system departments', tags: ['Organization'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/system-departments/sync', operationId: 'syncSystemDepartments', summary: 'Synchronize system departments', tags: ['Organization'], errors: [401, 422, 503])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/system-faculties', operationId: 'listSystemFaculties', summary: 'List active system faculties', tags: ['Organization'])]
#[ApiOperation(method: OA\HttpMethod::Get, path: '/api/system-faculties/all', operationId: 'listAllSystemFaculties', summary: 'List all system faculties', tags: ['Organization'])]
#[ApiOperation(method: OA\HttpMethod::Post, path: '/api/system-faculties/sync', operationId: 'syncSystemFaculties', summary: 'Synchronize system faculties', tags: ['Organization'], errors: [401, 422, 503])]
final class MasterDataPaths {}
