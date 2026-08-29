<?php

namespace App\Enums;

enum ListOfValueType: string
{
    case Titles = 'titles';
    case AdmissionChannels = 'admission-channels';
    case Relationships = 'relationships';
    case StudentStatuses = 'student-statuses';
    case NoteTypes = 'note-types';
    case ImportTypes = 'import-types';
    case HighSchools = 'high-schools';
    case Provinces = 'provinces';
    case Districts = 'districts';
    case Subdistricts = 'subdistricts';
    case SystemTeachers = 'system-teachers';
    case SystemDepartments = 'system-departments';
    case SystemFaculties = 'system-faculties';
}
