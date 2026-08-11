<?php

use App\Http\Controllers\MockLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('mock-login')->group(function (): void {
    Route::get('/', [MockLoginController::class, 'picker']);
    Route::get('/search', [MockLoginController::class, 'search']);
    Route::get('/admin', [MockLoginController::class, 'issueAdmin']);
    Route::get('/teacher/{nontriId}', [MockLoginController::class, 'issueTeacher']);
});
