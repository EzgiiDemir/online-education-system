<?php

use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ScimUserController;
use Illuminate\Support\Facades\Route;

/**
 * Stateless, bearer-token-authenticated API -- no session, no CSRF, not
 * meant to be browsed. See ScimAuthenticate for the auth mechanism and
 * ScimUserController's docblock for what's in/out of scope.
 */
Route::prefix('scim/v2')->middleware(['scim.auth', 'throttle:scim'])->name('scim.')->group(function () {
    Route::get('/Users', [ScimUserController::class, 'index'])->name('users.index');
    Route::post('/Users', [ScimUserController::class, 'store'])->name('users.store');
    Route::get('/Users/{id}', [ScimUserController::class, 'show'])->name('users.show');
    Route::put('/Users/{id}', [ScimUserController::class, 'update'])->name('users.update');
    Route::patch('/Users/{id}', [ScimUserController::class, 'patch'])->name('users.patch');
    Route::delete('/Users/{id}', [ScimUserController::class, 'destroy'])->name('users.destroy');
});

/**
 * General read-only reporting API -- see CourseController's docblock for
 * scope. Auth is ApiAuthenticate/ApiToken, a separate credential from the
 * SCIM tokens above.
 */
Route::prefix('v1')->middleware(['api.auth', 'throttle:api'])->name('api.v1.')->group(function () {
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
});
