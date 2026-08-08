<?php

use App\Http\Controllers\Api\AdmissionChannelController;
use App\Http\Controllers\Api\CurriculumDivisionController;
use App\Http\Controllers\Api\CurriculumPlanController;
use App\Http\Controllers\Api\HighSchoolController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteTypeController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentJsonDataController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\SystemDepartmentController;
use App\Http\Controllers\Api\SystemFacultyController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TitleController;
use Illuminate\Support\Facades\Route;

Route::get('/me', [MeController::class, 'show']);

Route::get('/titles', [TitleController::class, 'index']);
Route::get('/admission-channels', [AdmissionChannelController::class, 'index']);
Route::get('/high-schools', [HighSchoolController::class, 'index']);
Route::get('/relationships', [RelationshipController::class, 'index']);
Route::get('/student-statuses', [StudentStatusController::class, 'index']);
Route::get('/note-types', [NoteTypeController::class, 'index']);
Route::get('/study-plans', [CurriculumPlanController::class, 'index']);
Route::get('/curriculum-divisions', [CurriculumDivisionController::class, 'index']);

Route::prefix('students')->group(function (): void {
    Route::get('/', [StudentController::class, 'index']);
    Route::get('/studying', [StudentController::class, 'studying']);
    Route::get('/studying/without-advisor', [StudentController::class, 'studyingWithoutAdvisor']);
    Route::patch('/advisor', [StudentController::class, 'updateAdvisor']);

    Route::get('/{studentCode}/enrollments', [StudentJsonDataController::class, 'enrollments'])
        ->whereNumber('studentCode');
    Route::get('/{studentCode}/enrollment-statuses', [StudentJsonDataController::class, 'enrollmentStatuses'])
        ->whereNumber('studentCode');
    Route::get('/{studentCode}/performance-summary', [StudentJsonDataController::class, 'performanceSummary'])
        ->whereNumber('studentCode');

    Route::post('/', [StudentController::class, 'store']);
    Route::get('/{id}', [StudentController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [StudentController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [StudentController::class, 'destroy'])->whereNumber('id');
});

Route::get('/teachers', [TeacherController::class, 'index']);
Route::post('/teachers/sync', [TeacherController::class, 'sync']);

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::delete('/notes/{id}', [NoteController::class, 'destroy'])->whereNumber('id');

Route::get('/syncs', [SyncController::class, 'index']);

Route::get('/system-departments', [SystemDepartmentController::class, 'index']);
Route::post('/system-departments/sync', [SystemDepartmentController::class, 'sync']);

Route::get('/system-faculties', [SystemFacultyController::class, 'index']);
Route::post('/system-faculties/sync', [SystemFacultyController::class, 'sync']);
