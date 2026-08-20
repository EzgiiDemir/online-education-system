<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Certificate;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\PortalSetting;
use App\Models\SessionProgress;
use App\Models\User;
use App\Notifications\CoursePublished;
use App\Notifications\LiveSessionScheduled;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LmsController extends Controller
{
    public function loginForm()
    {
        return view('lms.login', ['settings' => PortalSetting::current()]);
    }

    public function home()
    {
        $courses = Schema::hasTable('courses') ? Course::where('status', 'published')->with('instructor')->latest()->get() : collect();

        return view('lms.home', compact('courses'));
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $settings = PortalSetting::current();
        $domain = strtolower(substr(strrchr($data['email'], '@'), 1));
        abort_unless(in_array($domain, array_map('strtolower', $settings->accepted_domains ?: []), true), 422, 'Bu e-posta domaini kabul edilmiyor.');
        abort_unless($settings->local_login_enabled, 403, 'Yerel giriş devre dışı.');
        if (! Auth::attempt($data)) {
            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.']);
        }
        $r->session()->regenerate();
        if (Auth::user()->role === 'admin') {
            return redirect('/admin');
        }

        return redirect()->route(Auth::user()->isInstructor() ? 'instructor.dashboard' : 'lms.home');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();

        return redirect('/');
    }

    public function registerForm()
    {
        abort_unless(PortalSetting::current()->self_registration_enabled, 404);

        return view('lms.register');
    }

    public function register(Request $r)
    {
        $settings = PortalSetting::current();
        abort_unless($settings->self_registration_enabled, 404);
        $data = $r->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8|confirmed']);
        $domain = strtolower(substr(strrchr($data['email'], '@'), 1));
        abort_unless(in_array($domain, array_map('strtolower', $settings->accepted_domains ?: []), true), 422, 'Bu e-posta domaini kabul edilmiyor.');
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => 'learner']);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('lms.home')->with('success', 'Hesabınız oluşturuldu, hoş geldiniz!');
    }

    public function myLearning()
    {
        $enrollments = Auth::user()->enrollments()->with(['course.sessions.materials', 'course.sections.activities', 'progress', 'activityProgress', 'certificate'])->latest()->get();

        return view('lms.my-learning', compact('enrollments'));
    }

    public function enroll(Course $course)
    {
        abort_unless($course->status === 'published', 404);
        $openRun = $course->runs()->whereIn('status', ['open', 'running'])->orderBy('starts_at')->first();
        $enrollment = Auth::user()->enrollments()->firstOrCreate(['course_id' => $course->id], ['course_run_id' => $openRun?->id]);
        if ($enrollment->wasRecentlyCreated) {
            foreach ($course->sessions()->where('meeting_status', '!=', 'not_applicable')->get() as $session) {
                Auth::user()->notify(new LiveSessionScheduled($session));
            }
        }

        return redirect()->route('learning.index')->with('success', 'Eğitime kaydınız oluşturuldu. Canlı ders bilgileri e-posta adresinize gönderildi.');
    }

    public function instructor()
    {
        if (Auth::user()->role === 'admin') {
            return redirect('/admin');
        }
        abort_unless(Auth::user()->role === 'instructor', 403);
        $courses = Auth::user()->courses()->with(['sessions', 'enrollments'])->latest()->get();

        return view('lms.instructor', compact('courses'));
    }

    public function instructorProfile()
    {
        abort_unless(Auth::user()->isInstructor(), 403);

        return view('lms.instructor-profile', ['user' => Auth::user()]);
    }

    public function updateInstructorProfile(Request $r)
    {
        abort_unless(Auth::user()->isInstructor(), 403);
        $data = $r->validate(['name' => 'required|string|max:255', 'department' => 'nullable|string|max:255', 'job_title' => 'nullable|string|max:255', 'bio' => 'nullable|string', 'avatar' => 'nullable|image|max:5120']);
        if ($r->hasFile('avatar')) {
            $data['avatar_path'] = $r->file('avatar')->store('avatars', 'public');
        }
        unset($data['avatar']);
        Auth::user()->update($data);

        return back()->with('success', 'Profil bilgileriniz güncellendi.');
    }

    public function courseDetail(Course $course)
    {
        $this->authorize('view', $course);
        $course->load(['instructor', 'sessions.materials', 'enrollments.user', 'enrollments.sessionProgress.session', 'sections.activities', 'runs']);
        $stats = ['enrollments' => $course->enrollments->count(), 'completed' => $course->enrollments->where('status', 'completed')->count(), 'sessions' => $course->sessions->count(), 'attendance_minutes' => (int) DB::table('session_attendance')->whereIn('course_session_id', $course->sessions->pluck('id'))->sum('duration_seconds') / 60, 'recordings' => $course->sessions->whereNotNull('recording_path')->count()];

        return view('lms.course-detail', compact('course', 'stats'));
    }

    public function storeCourse(Request $r)
    {
        $this->authorize('create', Course::class);
        $data = $r->validate(['title' => 'required', 'description' => 'nullable', 'delivery_type' => 'required|in:online,offline', 'language' => 'required|in:tr,en', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'guest_instructor_name' => 'nullable', 'guest_instructor_email' => 'nullable|email', 'guest_instructor_bio' => 'nullable', 'certificate_enabled' => 'nullable|boolean', 'sdg_goals' => 'nullable|array', 'poster' => 'nullable|image|max:5120', 'image' => 'nullable|image|max:5120']);
        $data['instructor_id'] = Auth::id();
        $data['status'] = 'published';
        $data['certificate_enabled'] = $r->boolean('certificate_enabled');
        $data['sdg_goals'] = $r->input('sdg_goals', []);
        if ($r->hasFile('poster')) {
            $data['poster_path'] = $r->file('poster')->store('course-posters', 'public');
        }
        if ($r->hasFile('image')) {
            $data['image_path'] = $r->file('image')->store('course-images', 'public');
        }
        unset($data['poster'], $data['image']);
        $course = Course::create($data);
        foreach (User::where('role', 'learner')->get() as $user) {
            $user->notify(new CoursePublished($course->title));
        }

        return redirect()->route('instructor.courses.show', $course)->with('success', 'Eğitim planlandı ve yayınlandı.');
    }

    public function addSession(Request $r, Course $course)
    {
        $this->authorize('update', $course);
        $data = $r->validate(['title' => 'required', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'material' => 'nullable|file|max:51200']);
        $file = $r->file('material');
        unset($data['material']);
        $session = $course->sessions()->create(array_merge($data, ['room_key' => $course->delivery_type === 'online' ? Str::lower(Str::random(24)) : null, 'meeting_status' => $course->delivery_type === 'online' ? 'scheduled' : 'not_applicable']));
        if ($file) {
            Material::create(['course_session_id' => $session->id, 'title' => $file->getClientOriginalName(), 'type' => $file->getMimeType(), 'path' => $file->store('materials', 'public')]);
        }
        if ($course->delivery_type === 'online') {
            foreach ($course->enrollments()->with('user')->get() as $enrollment) {
                $enrollment->user->notify(new LiveSessionScheduled($session));
            }
        }

        return back()->with('success', 'Seans planlandı. Portal içi toplantı odası otomatik oluşturuldu.');
    }

    public function meeting(string $roomKey)
    {
        $session = CourseSession::where('room_key', $roomKey)->firstOrFail();
        abort_unless(Auth::check(), 403);
        $allowed = Auth::user()->isInstructor() && Auth::id() === $session->course->instructor_id || Auth::user()->enrollments()->where('course_id', $session->course_id)->exists();
        abort_unless($allowed, 403);

        return redirect()->route('sessions.classroom', $session);
    }

    public function startSession(CourseSession $session)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $session->course_id)->firstOrFail();
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now()]);

        return back()->with('success', 'Seans başlangıcı kaydedildi.');
    }

    public function completeSession(CourseSession $session)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $session->course_id)->firstOrFail();
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now(), 'completed_at' => now()]);

        return back()->with('success', 'Seans tamamlandı.');
    }

    public function classroom(CourseSession $session)
    {
        $allowed = Auth::user()->isInstructor() && Auth::id() === $session->course->instructor_id || Auth::user()->enrollments()->where('course_id', $session->course_id)->exists();
        abort_unless($allowed, 403);
        $session->load('course.instructor', 'materials');
        $messages = ClassMessage::where('course_session_id', $session->id)->with('user')->latest()->limit(50)->get()->reverse();

        return view('lms.classroom', compact('session', 'messages'));
    }

    public function shareClassFile(Request $r, CourseSession $session)
    {
        $this->authorize('update', $session->course);
        $r->validate(['file' => 'required|file|max:51200']);
        $file = $r->file('file');
        Material::create(['course_session_id' => $session->id, 'title' => $file->getClientOriginalName(), 'type' => $file->getMimeType(), 'path' => $file->store('materials', 'public')]);

        return back()->with('success', 'Dosya sınıfta paylaşıldı.');
    }

    public function classMessage(Request $r, CourseSession $session)
    {
        $r->validate(['body' => 'required|string|max:2000']);
        ClassMessage::create(['course_session_id' => $session->id, 'user_id' => Auth::id(), 'body' => $r->body]);

        return back();
    }

    public function classPresence(CourseSession $session)
    {
        \DB::table('class_presence')->updateOrInsert(['course_session_id' => $session->id, 'user_id' => Auth::id()], ['last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        $users = \DB::table('class_presence')->join('users', 'users.id', '=', 'class_presence.user_id')->where('course_session_id', $session->id)->where('last_seen_at', '>', now()->subSeconds(30))->select('users.id', 'users.name', 'users.avatar_path')->get();

        return response()->json($users);
    }

    public function classSignals(Request $r, CourseSession $session)
    {
        $signals = \DB::table('class_signals')->where('course_session_id', $session->id)->where('consumed', false)->where(function ($q) {
            $q->whereNull('recipient_id')->orWhere('recipient_id', Auth::id());
        })->where('sender_id', '!=', Auth::id())->orderBy('id')->get();
        if ($signals->isNotEmpty()) {
            \DB::table('class_signals')->whereIn('id', $signals->pluck('id'))->update(['consumed' => true]);
        }

        return response()->json($signals);
    }

    public function sendSignal(Request $r, CourseSession $session)
    {
        $data = $r->validate(['type' => 'required|string', 'payload' => 'required', 'recipient_id' => 'nullable|integer']);
        \DB::table('class_signals')->insert(['course_session_id' => $session->id, 'sender_id' => Auth::id(), 'recipient_id' => $data['recipient_id'] ?? null, 'type' => $data['type'], 'payload' => json_encode($data['payload']), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function uploadRecording(Request $r, CourseSession $session)
    {
        $this->authorize('update', $session->course);
        $r->validate(['recording' => 'required|file|mimes:webm,mp4|max:512000']);
        $path = $r->file('recording')->store('recordings', 'public');
        $session->update(['recording_path' => $path]);

        return response()->json(['ok' => true, 'path' => asset('storage/'.$path)]);
    }

    public function materialView(Material $material)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $required = $this->requiredWatchSeconds($material->duration_seconds);

        return view('lms.material-view', ['material' => $material, 'progress' => $progress, 'required' => $required]);
    }

    public function materialHeartbeat(Material $material)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $this->accrueHeartbeat($progress);
        $required = $this->requiredWatchSeconds($material->duration_seconds);

        return response()->json(['watched_seconds' => $progress->watched_seconds, 'required_seconds' => $required, 'eligible' => $progress->watched_seconds >= $required]);
    }

    public function completeMaterial(Material $material)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $required = $this->requiredWatchSeconds($material->duration_seconds);
        abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Materyal tamamlandı olarak işaretlendi.');
    }

    public function activityView(Activity $activity)
    {
        abort_if($activity->type === 'live_session', 422, 'Canlı oturum etkinlikleri katılım kaydından otomatik izlenir.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        $required = $this->requiredWatchSeconds($activity->duration_seconds);

        return view('lms.activity-view', ['activity' => $activity, 'progress' => $progress, 'required' => $required]);
    }

    public function activityHeartbeat(Activity $activity)
    {
        abort_if($activity->type === 'live_session', 422, 'Canlı oturum etkinlikleri katılım kaydından otomatik izlenir.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        $this->accrueHeartbeat($progress);
        $required = $this->requiredWatchSeconds($activity->duration_seconds);

        return response()->json(['watched_seconds' => $progress->watched_seconds, 'required_seconds' => $required, 'eligible' => $progress->watched_seconds >= $required]);
    }

    public function completeActivity(Activity $activity)
    {
        abort_if($activity->type === 'live_session', 422, 'Canlı oturum etkinlikleri katılım kaydından otomatik izlenir.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        $required = $this->requiredWatchSeconds($activity->duration_seconds);
        abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Aktivite tamamlandı olarak işaretlendi.');
    }

    /**
     * İstemciden gelen süre beyanına asla güvenilmez: her kabul edilen heartbeat
     * çağrısı sabit, küçük bir artış ekler ve art arda çağrılar en az 4 saniye
     * aralıkla sınırlandırılır. Böylece "tamamlandı" durumu yalnızca sekme
     * gerçekten açık/odaklıyken ge­çen gerçek zamanla kazanılabilir.
     */
    private function accrueHeartbeat(MaterialProgress|ActivityProgress $progress): void
    {
        if ($progress->completed) {
            return;
        }
        if ($progress->last_heartbeat_at && $progress->last_heartbeat_at->diffInSeconds(now()) < 4) {
            return;
        }
        $progress->increment('watched_seconds', 5);
        $progress->update(['last_heartbeat_at' => now()]);
    }

    private function requiredWatchSeconds(?int $durationSeconds): int
    {
        return $durationSeconds ? max(10, (int) ceil($durationSeconds * 0.9)) : 20;
    }

    private function checkAndCompleteEnrollment(Enrollment $enrollment): void
    {
        $totalMaterials = Material::whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))->count();
        $completedMaterials = $enrollment->progress()->where('completed', true)->count();
        $totalActivities = Activity::whereHas('section', fn ($q) => $q->where('course_id', $enrollment->course_id))->where('is_required', true)->count();
        $completedActivities = $enrollment->activityProgress()->whereHas('activity', fn ($q) => $q->where('is_required', true))->where('completed', true)->count();
        $total = $totalMaterials + $totalActivities;
        if ($total && ($completedMaterials + $completedActivities) >= $total) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            Certificate::firstOrCreate(['enrollment_id' => $enrollment->id], ['certificate_number' => 'CERT-'.strtoupper(Str::random(10)), 'issued_at' => now()]);
        }
    }

    public function certificate(Certificate $certificate)
    {
        $this->authorize('view', $certificate);

        return Pdf::loadView('lms.certificate', ['certificate' => $certificate, 'enrollment' => $certificate->enrollment->load('user', 'course')])->download($certificate->certificate_number.'.pdf');
    }
}
