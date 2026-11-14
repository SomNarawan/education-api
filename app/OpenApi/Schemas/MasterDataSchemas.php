<?php

namespace App\OpenApi\Schemas;

use OpenApi\Spec as OA;

#[OA\Schema(component: 'TitleWriteRequest', type: OA\Type::Object, required: ['title_abbr_th', 'title_abbr_en', 'title_name_th', 'title_name_en'], properties: [
    new OA\Property(property: 'title_abbr_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
    new OA\Property(property: 'title_abbr_en', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
    new OA\Property(property: 'title_name_th', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
    new OA\Property(property: 'title_name_en', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
])]
#[OA\Schema(component: 'AdmissionChannelWriteRequest', type: OA\Type::Object, required: ['channel_name'], properties: [
    new OA\Property(property: 'channel_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 100)),
])]
#[OA\Schema(component: 'RelationshipWriteRequest', type: OA\Type::Object, required: ['relationship_name'], properties: [
    new OA\Property(property: 'relationship_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
])]
#[OA\Schema(component: 'StudentStatusWriteRequest', type: OA\Type::Object, required: ['status_name'], properties: [
    new OA\Property(property: 'status_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 50)),
])]
#[OA\Schema(component: 'NoteTypeWriteRequest', type: OA\Type::Object, required: ['note'], properties: [
    new OA\Property(property: 'note', schema: new OA\Schema(type: OA\Type::String, maxLength: 255)),
])]
#[OA\Schema(component: 'HighSchoolWriteRequest', type: OA\Type::Object, required: ['school_name', 'subdistrict_id', 'latitude', 'longitude'], properties: [
    new OA\Property(property: 'school_name', schema: new OA\Schema(type: OA\Type::String, maxLength: 150)),
    new OA\Property(property: 'subdistrict_id', schema: new OA\Schema(type: OA\Type::Integer, minimum: 1)),
    new OA\Property(property: 'latitude', schema: new OA\Schema(type: OA\Type::Number, minimum: -90, maximum: 90)),
    new OA\Property(property: 'longitude', schema: new OA\Schema(type: OA\Type::Number, minimum: -180, maximum: 180)),
])]
final class MasterDataSchemas {}
