<?php

use App\Http\Controllers\Api\AdmissionChannelController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\CurriculumDivisionController;
use App\Http\Controllers\Api\CurriculumDivisionSubjectController;
use App\Http\Controllers\Api\CurriculumPlanController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\HighSchoolController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteTypeController;
use App\Http\Controllers\Api\PlanEntryController;
use App\Http\Controllers\Api\PlanTermController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentJsonDataController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\SubdistrictController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\SubjectPrerequisiteController;
use App\Http\Controllers\Api\SystemDepartmentController;
use App\Http\Controllers\Api\SystemFacultyController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TitleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/me', [MeController::class, 'show']);

Route::get('/titles', [TitleController::class, 'index']);
Route::get('/affiliations', [SystemFacultyController::class, 'index']);
Route::get('/admission-channels', [AdmissionChannelController::class, 'index']);
Route::get('/campuses', [CampusController::class, 'index']);
Route::get('/courses', [SubjectController::class, 'index']);
Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/course-prerequisites', [SubjectPrerequisiteController::class, 'index']);
Route::get('/subject-prerequisites', [SubjectPrerequisiteController::class, 'index']);
Route::get('/curriculums', [CurriculumController::class, 'index']);
Route::get('/curriculum-division-categories', [CurriculumDivisionController::class, 'categories']);
Route::get('/curriculum-divisions', [CurriculumDivisionController::class, 'index']);
Route::get('/curriculum-courses', [CurriculumDivisionSubjectController::class, 'index']);
Route::get('/curriculum-division-subjects', [CurriculumDivisionSubjectController::class, 'index']);
Route::get('/curriculum-groups', [CurriculumDivisionController::class, 'groups']);
Route::get('/districts', [DistrictController::class, 'index']);
Route::get('/faculties', [FacultyController::class, 'index']);
Route::get('/high-schools', [HighSchoolController::class, 'index']);
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/student-statuses', [StudentStatusController::class, 'index']);
Route::get('/study-plan-tracks', [CurriculumPlanController::class, 'index']);
Route::get('/curriculum-plans', [CurriculumPlanController::class, 'index']);
Route::get('/study-terms', [PlanTermController::class, 'index']);
Route::get('/plan-terms', [PlanTermController::class, 'index']);
Route::get('/study-term-courses', [PlanEntryController::class, 'index']);
Route::get('/plan-entries', [PlanEntryController::class, 'index']);
Route::get('/subdistricts', [SubdistrictController::class, 'index']);
Route::get('/relationships', [RelationshipController::class, 'index']);

Route::prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index']);
    Route::get('/json-data/enrollment/{studentCode}', [StudentJsonDataController::class, 'enrollment'])
        ->whereNumber('studentCode');
    Route::get('/json-data/enrollment-statuses/{studentCode}', [StudentJsonDataController::class, 'enrollmentStatuses'])
        ->whereNumber('studentCode');
    Route::get('/json-data/graphs/{studentCode}', [StudentJsonDataController::class, 'graphs'])
        ->whereNumber('studentCode');
    Route::get('/{id}', [StudentController::class, 'show']);
    Route::post('/', [StudentController::class, 'store']);
    Route::match(['put', 'patch'], '/{id}', [StudentController::class, 'update']);
    Route::delete('/{id}', [StudentController::class, 'destroy']);
});

Route::get('/teachers', [TeacherController::class, 'index']);
Route::get('/teachers/sync', [TeacherController::class, 'sync']);

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::delete('/notes/{id}', [NoteController::class, 'destroy']);

Route::get('/note-types', [NoteTypeController::class, 'index']);

Route::get('/system-departments', [SystemDepartmentController::class, 'index']);
Route::get('/system-departments/sync', [SystemDepartmentController::class, 'sync']);

Route::get('/system-faculties', [SystemFacultyController::class, 'index']);
Route::get('/system-faculties/sync', [SystemFacultyController::class, 'sync']);
