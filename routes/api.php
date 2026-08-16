<?php

use App\Http\Controllers\Api\AdmissionChannelController;
use App\Http\Controllers\Api\CurriculumDivisionController;
use App\Http\Controllers\Api\CurriculumPlanController;
use App\Http\Controllers\Api\DistrictController;
use App\Http\Controllers\Api\HighSchoolController;
use App\Http\Controllers\Api\ImportTypeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteTypeController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\RelationshipController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentImportController;
use App\Http\Controllers\Api\StudentJsonDataController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\SubdistrictController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\SystemDepartmentController;
use App\Http\Controllers\Api\SystemFacultyController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TitleController;
use Illuminate\Support\Facades\Route;

Route::get('/me', [MeController::class, 'show']);

Route::prefix('titles')->group(function (): void {
    Route::get('/', [TitleController::class, 'index']);
    Route::post('/', [TitleController::class, 'store']);
    Route::get('/{id}', [TitleController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [TitleController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [TitleController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [TitleController::class, 'destroy'])->whereNumber('id');
});
Route::prefix('admission-channels')->group(function (): void {
    Route::get('/', [AdmissionChannelController::class, 'index']);
    Route::post('/', [AdmissionChannelController::class, 'store']);
    Route::get('/{id}', [AdmissionChannelController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [AdmissionChannelController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [AdmissionChannelController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [AdmissionChannelController::class, 'destroy'])->whereNumber('id');
});
Route::get('/provinces', [ProvinceController::class, 'index']);
Route::get('/districts', [DistrictController::class, 'index']);
Route::get('/subdistricts', [SubdistrictController::class, 'index']);
Route::prefix('high-schools')->group(function (): void {
    Route::get('/', [HighSchoolController::class, 'index']);
    Route::post('/', [HighSchoolController::class, 'store']);
    Route::get('/{id}', [HighSchoolController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [HighSchoolController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [HighSchoolController::class, 'updateStatus'])->whereNumber('id');
});
Route::prefix('relationships')->group(function (): void {
    Route::get('/', [RelationshipController::class, 'index']);
    Route::post('/', [RelationshipController::class, 'store']);
    Route::get('/{id}', [RelationshipController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [RelationshipController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [RelationshipController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [RelationshipController::class, 'destroy'])->whereNumber('id');
});
Route::prefix('student-statuses')->group(function (): void {
    Route::get('/', [StudentStatusController::class, 'index']);
    Route::post('/', [StudentStatusController::class, 'store']);
    Route::get('/{id}', [StudentStatusController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [StudentStatusController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [StudentStatusController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [StudentStatusController::class, 'destroy'])->whereNumber('id');
});
Route::prefix('note-types')->group(function (): void {
    Route::get('/', [NoteTypeController::class, 'index']);
    Route::post('/', [NoteTypeController::class, 'store']);
    Route::get('/{id}', [NoteTypeController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [NoteTypeController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [NoteTypeController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [NoteTypeController::class, 'destroy'])->whereNumber('id');
});
Route::prefix('import-types')->group(function (): void {
    Route::get('/', [ImportTypeController::class, 'index']);
    Route::post('/', [ImportTypeController::class, 'store']);
    Route::get('/{id}', [ImportTypeController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], '/{id}', [ImportTypeController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}/status', [ImportTypeController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/{id}', [ImportTypeController::class, 'destroy'])->whereNumber('id');
});
Route::get('/study-plans', [CurriculumPlanController::class, 'index']);
Route::get('/curriculum-divisions', [CurriculumDivisionController::class, 'index']);

Route::prefix('students')->group(function (): void {
    Route::get('/', [StudentController::class, 'index']);
    Route::get('/studying', [StudentController::class, 'studying']);
    Route::get('/studying/without-advisor', [StudentController::class, 'studyingWithoutAdvisor']);
    Route::patch('/advisor', [StudentController::class, 'updateAdvisor']);
    Route::post('/import', StudentImportController::class);

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
