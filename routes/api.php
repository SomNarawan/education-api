<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\TitleController;
use App\Http\Controllers\Api\AffiliationController;
use App\Http\Controllers\Api\AdmissionChannelController;
use App\Http\Controllers\Api\CampusController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\CoursePrerequisiteController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\CurriculumCategoryController;
use App\Http\Controllers\Api\CurriculumCourseController;
use App\Http\Controllers\Api\CurriculumGroupController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\HighSchoolController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\StudyPlanTrackController;
use App\Http\Controllers\Api\StudyTermController;
use App\Http\Controllers\Api\StudyTermCourseController;
use App\Http\Controllers\Api\SubdistrictController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\RelationshipController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/titles', [TitleController::class, 'index']);
Route::get('/affiliations', [AffiliationController::class, 'index']);
Route::get('/admission-channels', [AdmissionChannelController::class, 'index']);
Route::get('/campuses', [CampusController::class, 'index']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/course-prerequisites', [CoursePrerequisiteController::class, 'index']);
Route::get('/curriculums', [CurriculumController::class, 'index']);
Route::get('/curriculum-categories', [CurriculumCategoryController::class, 'index']);
Route::get('/curriculum-courses', [CurriculumCourseController::class, 'index']);
Route::get('/curriculum-groups', [CurriculumGroupController::class, 'index']);
Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/districts', [DistrictController::class, 'index']);
Route::get('/faculties', [FacultyController::class, 'index']);
Route::get('/high-schools', [HighSchoolController::class, 'index']);
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/student-statuses', [StudentStatusController::class, 'index']);
Route::get('/study-plan-tracks', [StudyPlanTrackController::class, 'index']);
Route::get('/study-terms', [StudyTermController::class, 'index']);
Route::get('/study-term-courses', [StudyTermCourseController::class, 'index']);
Route::get('/subdistricts', [SubdistrictController::class, 'index']);
Route::get('/relationships', [RelationshipController::class, 'index']);

Route::prefix('students')->group(function () {
    Route::get('/', [StudentController::class, 'index']);
    Route::get('/detail', [StudentController::class, 'detail']);
    Route::get('/{id}', [StudentController::class, 'show']);
});

Route::get('/teachers', [TeacherController::class, 'index']);
Route::post('/teachers/sync', [TeacherController::class, 'sync']);

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::delete('/notes/{id}', [NoteController::class, 'destroy']);