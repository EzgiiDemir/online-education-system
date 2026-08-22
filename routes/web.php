<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EntraSsoController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LmsController::class, 'home'])->name('lms.home');
Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['tr', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('language.switch');
Route::get('/login', [LmsController::class, 'loginForm'])->name('login');
Route::post('/login', [LmsController::class, 'login']);
Route::get('/register', [LmsController::class, 'registerForm'])->name('register');
Route::post('/register', [LmsController::class, 'register']);
Route::get('/auth/entra/redirect', [EntraSsoController::class, 'redirect'])->name('entra.redirect');
Route::get('/auth/entra/callback', [EntraSsoController::class, 'callback'])->name('entra.callback');
Route::get('/meet/{roomKey}', [LmsController::class, 'meeting'])->name('meet.join');
Route::get('/courses/{course}', [LmsController::class, 'courseShow'])->name('courses.show');
Route::get('/learning-paths', [LmsController::class, 'learningPaths'])->name('learning-paths.index');
Route::get('/learning-paths/{path}', [LmsController::class, 'learningPathShow'])->name('learning-paths.show');
Route::get('/verify/{certificateNumber}', [LmsController::class, 'verifyCertificate'])->name('certificates.verify');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/search/quick', [SearchController::class, 'quick'])->name('search.quick');
Route::middleware('auth')->get('/my-learning', [LmsController::class, 'myLearning'])->name('learning.index');
Route::middleware('auth')->post('/sessions/{session}/files', [LmsController::class, 'shareClassFile'])->name('sessions.files.share');
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LmsController::class, 'logout'])->name('logout');
    Route::post('/courses/{course}/enroll', [LmsController::class, 'enroll'])->name('courses.enroll');
    Route::post('/learning-paths/{path}/enroll', [LmsController::class, 'enrollInPath'])->name('learning-paths.enroll');
    Route::get('/instructor', [LmsController::class, 'instructor'])->name('instructor.dashboard');
    Route::get('/manager', [ManagerController::class, 'dashboard'])->name('manager.dashboard');
    Route::post('/instructor/courses', [LmsController::class, 'storeCourse'])->name('instructor.courses.store');
    Route::get('/instructor/courses/{course}', [LmsController::class, 'courseDetail'])->name('instructor.courses.show');
    Route::post('/instructor/courses/{course}/sessions', [LmsController::class, 'addSession'])->name('instructor.sessions.store');
    Route::post('/sessions/{session}/start', [LmsController::class, 'startSession'])->name('sessions.start');
    Route::post('/sessions/{session}/complete', [LmsController::class, 'completeSession'])->name('sessions.complete');
    Route::get('/sessions/{session}/attendance-status', [LmsController::class, 'sessionAttendanceStatus'])->name('sessions.attendance-status');
    Route::post('/instructor/sessions/{session}/attendance', [LmsController::class, 'markSessionAttendance'])->name('instructor.sessions.attendance');
    Route::get('/sessions/{session}/classroom', [LmsController::class, 'classroom'])->name('sessions.classroom');
    Route::post('/sessions/{session}/messages', [LmsController::class, 'classMessage'])->name('sessions.messages');
    Route::match(['get', 'post'], '/sessions/{session}/presence', [LmsController::class, 'classPresence'])->name('sessions.presence');
    Route::get('/sessions/{session}/signals', [LmsController::class, 'classSignals'])->name('sessions.signals');
    Route::post('/sessions/{session}/signals', [LmsController::class, 'sendSignal'])->name('sessions.signals.send');
    Route::post('/sessions/{session}/recording', [LmsController::class, 'uploadRecording'])->name('sessions.recording');
    Route::get('/materials/{material}', [LmsController::class, 'materialView'])->name('materials.view');
    Route::post('/materials/{material}/heartbeat', [LmsController::class, 'materialHeartbeat'])->name('materials.heartbeat');
    Route::post('/materials/{material}/complete', [LmsController::class, 'completeMaterial'])->name('materials.complete');
    Route::get('/activities/{activity}', [LmsController::class, 'activityView'])->name('activities.view');
    Route::post('/activities/{activity}/heartbeat', [LmsController::class, 'activityHeartbeat'])->name('activities.heartbeat');
    Route::post('/activities/{activity}/complete', [LmsController::class, 'completeActivity'])->name('activities.complete');
    Route::post('/activities/{activity}/quiz-attempts', [LmsController::class, 'submitQuizAttempt'])->name('activities.quiz-attempts.store');
    Route::get('/certificates/{certificate}', [LmsController::class, 'certificate'])->name('certificates.download');
    Route::get('/skills', [LmsController::class, 'mySkills'])->name('skills.index');
    Route::get('/calendar', [LmsController::class, 'calendar'])->name('calendar.index');
    Route::get('/calendar/sessions/{session}.ics', [LmsController::class, 'calendarSessionIcs'])->name('calendar.ics.session');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::post('/account/delete', [AccountController::class, 'destroy'])->name('account.destroy');
});
Route::middleware('auth')->group(function () {
    Route::get('/instructor/profile', [LmsController::class, 'instructorProfile'])->name('instructor.profile');
    Route::post('/instructor/profile', [LmsController::class, 'updateInstructorProfile'])->name('instructor.profile.update');
});
