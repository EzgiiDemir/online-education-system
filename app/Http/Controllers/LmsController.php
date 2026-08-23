<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ClassMessage;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\PortalSetting;
use App\Models\QuizAttempt;
use App\Models\SessionAttendance;
use App\Models\SessionProgress;
use App\Models\User;
use App\Notifications\CoursePublished;
use App\Notifications\LiveSessionScheduled;
use App\Support\DocumentEstimator;
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
        $courses = Schema::hasTable('courses')
            ? Course::where('status', 'published')->with('instructor', 'sessions')->latest()->paginate(12)
            : new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 12);
        $enrolledCourseIds = collect();
        $continueLearning = null;
        $requiredIncomplete = collect();
        $todaySessions = collect();

        if (Auth::check()) {
            $enrolledCourseIds = Auth::user()->enrollments()->pluck('course_id');

            $activeEnrollments = Auth::user()->enrollments()
                ->with('course')
                ->where('status', '!=', 'completed')
                ->withMax('progress', 'updated_at')
                ->withMax('activityProgress', 'updated_at')
                ->get();

            $continueLearning = $activeEnrollments->sortByDesc(fn ($e) => max(
                $e->progress_max_updated_at ?? $e->created_at,
                $e->activity_progress_max_updated_at ?? $e->created_at,
                $e->created_at,
            ))->first();

            $requiredIncomplete = $activeEnrollments
                ->map(fn ($e) => ['enrollment' => $e, 'stats' => $e->completionStats()])
                ->filter(fn ($row) => $row['stats']['total'] > 0 && $row['stats']['completed'] < $row['stats']['total'])
                ->values();

            if ($enrolledCourseIds->isNotEmpty()) {
                $todaySessions = CourseSession::whereIn('course_id', $enrolledCourseIds)
                    ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
                    ->with('course')
                    ->orderBy('starts_at')
                    ->get();
            }
        }

        $continueUrl = null;
        if ($continueLearning) {
            $nextItem = $continueLearning->nextIncompleteItem();
            $continueUrl = match ($nextItem['type'] ?? null) {
                'material' => route('materials.view', $nextItem['model']),
                'activity' => route('activities.view', $nextItem['model']),
                default => route('learning.index'),
            };
        }

        return view('lms.home', compact('courses', 'enrolledCourseIds', 'continueLearning', 'continueUrl', 'requiredIncomplete', 'todaySessions'));
    }

    public function courseShow(Course $course)
    {
        $isOwnerOrAdmin = Auth::check() && (Auth::user()->role === 'admin' || Auth::id() === $course->instructor_id);
        abort_unless($course->status === 'published' || $isOwnerOrAdmin, 404);

        $course->load(['instructor', 'sessions.materials', 'sections.activities', 'runs', 'skills']);

        $enrollment = null;
        $stats = null;
        $continueUrl = null;
        if (Auth::check()) {
            $enrollment = Auth::user()->enrollments()->where('course_id', $course->id)->with('progress', 'activityProgress', 'certificate')->first();
            if ($enrollment) {
                $stats = $enrollment->completionStats();
                $nextItem = $enrollment->nextIncompleteItem();
                $continueUrl = match ($nextItem['type'] ?? null) {
                    'material' => route('materials.view', $nextItem['model']),
                    'activity' => route('activities.view', $nextItem['model']),
                    default => null,
                };
            }
        }

        $newerVersion = $course->versions()->where('status', 'published')->where('version', '>', $course->version)->first();

        return view('lms.course-show', compact('course', 'enrollment', 'stats', 'continueUrl', 'newerVersion'));
    }

    public function mySkills()
    {
        $skills = Auth::user()->earnedSkills();

        return view('lms.my-skills', compact('skills'));
    }

    /**
     * All calendar-worthy dates for the learner are derived from data that
     * already exists elsewhere -- live session times, due dates (course.due_days
     * + enrollment date), certificate expiry -- there is no separate "events"
     * table. Week view is intentionally not built; month + agenda cover the
     * same underlying event set without a second rendering path to maintain.
     */
    public function calendar(Request $r)
    {
        $month = \Illuminate\Support\Carbon::parse($r->query('month', now()->toDateString()))->startOfMonth();

        $enrollments = Auth::user()->enrollments()->with('course', 'certificate')->get();
        $courseIds = $enrollments->pluck('course_id');

        $events = collect();

        CourseSession::whereIn('course_id', $courseIds)->whereNotNull('starts_at')->with('course')->get()->each(function ($session) use (&$events) {
            $events->push(['date' => $session->starts_at->toDateString(), 'time' => $session->starts_at->format('H:i'), 'type' => 'session', 'title' => $session->title, 'course' => $session->course->title, 'url' => route('courses.show', $session->course), 'ics' => route('calendar.ics.session', $session)]);
        });

        $enrollments->where('status', '!=', 'completed')->each(function ($enrollment) use (&$events) {
            $due = $enrollment->course->dueDateFor($enrollment);
            if ($due) {
                $events->push(['date' => $due->toDateString(), 'time' => null, 'type' => 'due', 'title' => $enrollment->course->title, 'course' => null, 'url' => route('courses.show', $enrollment->course), 'ics' => null]);
            }
        });

        $enrollments->whereNotNull('certificate')->each(function ($enrollment) use (&$events) {
            $expiry = $enrollment->certificate->expiresAt();
            if ($expiry) {
                $events->push(['date' => $expiry->toDateString(), 'time' => null, 'type' => 'expiry', 'title' => $enrollment->course->title, 'course' => null, 'url' => route('courses.show', $enrollment->course), 'ics' => null]);
            }
        });

        $eventsByDate = $events->groupBy('date');
        $upcoming = $events->filter(fn ($e) => $e['date'] >= now()->toDateString())->sortBy('date')->values();

        return view('lms.calendar', compact('month', 'eventsByDate', 'upcoming'));
    }

    public function calendarSessionIcs(CourseSession $session)
    {
        $enrolled = Auth::user()->enrollments()->where('course_id', $session->course_id)->exists();
        abort_unless($enrolled, 403);

        $session->load('course');
        $start = $session->starts_at?->utc()->format('Ymd\\THis\\Z') ?? now()->utc()->format('Ymd\\THis\\Z');
        $end = $session->ends_at?->utc()->format('Ymd\\THis\\Z') ?? now()->addHour()->utc()->format('Ymd\\THis\\Z');
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Arucad Campus LMS//Calendar//EN\r\nBEGIN:VEVENT\r\nUID:session-{$session->id}@campus-lms\r\nDTSTAMP:".now()->utc()->format('Ymd\\THis\\Z')."\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:".addslashes($session->course->title.' - '.$session->title)."\r\nURL:{$session->meeting_url}\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        return response($ics, 200, ['Content-Type' => 'text/calendar', 'Content-Disposition' => 'attachment; filename="'.$session->id.'.ics"']);
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
        if (Auth::user()->role === 'manager') {
            return redirect()->route('manager.dashboard');
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

    /**
     * "Required" is deliberately not a separate tab here: in this app's
     * completion model an enrollment's status flips to 'completed' the
     * instant every required item is done (checkAndCompleteEnrollment), so
     * "status != completed" already IS "still has required work outstanding".
     * A second tab computing the same thing from completionStats() would
     * just show the same rows under a different label.
     */
    public function myLearning(Request $r)
    {
        $tab = $r->query('tab', 'all');
        $allowedTabs = ['all', 'in_progress', 'upcoming', 'completed', 'certificates'];
        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'all';
        }

        $counts = Auth::user()->enrollments()
            ->selectRaw("count(*) as total_count")
            ->selectRaw("sum(case when status != 'completed' then 1 else 0 end) as in_progress_count")
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed_count")
            ->first();

        $counts = [
            'all' => (int) $counts->total_count,
            'in_progress' => (int) $counts->in_progress_count,
            'upcoming' => Auth::user()->enrollments()->where('status', '!=', 'completed')->whereHas('course.sessions', fn ($q) => $q->where('starts_at', '>', now()))->count(),
            'completed' => (int) $counts->completed_count,
            'certificates' => Auth::user()->enrollments()->whereHas('certificate')->count(),
        ];

        $query = Auth::user()->enrollments()->with(['course.sessions.materials', 'course.sections.activities', 'progress', 'activityProgress', 'sessionProgress', 'certificate']);

        $query = match ($tab) {
            'in_progress' => $query->where('status', '!=', 'completed'),
            'completed' => $query->where('status', 'completed'),
            'certificates' => $query->whereHas('certificate'),
            'upcoming' => $query->where('status', '!=', 'completed')->whereHas('course.sessions', fn ($q) => $q->where('starts_at', '>', now())),
            default => $query,
        };

        $enrollments = $query->latest()->paginate(10)->withQueryString();

        return view('lms.my-learning', compact('enrollments', 'counts', 'tab'));
    }

    public function enroll(Course $course)
    {
        abort_unless($course->status === 'published', 404);
        $this->enrollUserInCourse($course);

        return redirect()->route('learning.index')->with('success', 'Eğitime kaydınız oluşturuldu. Canlı ders bilgileri e-posta adresinize gönderildi.');
    }

    private function enrollUserInCourse(Course $course): Enrollment
    {
        $openRun = $course->runs()->whereIn('status', ['open', 'running'])->orderBy('starts_at')->first();
        $enrollment = Auth::user()->enrollments()->firstOrCreate(['course_id' => $course->id], ['course_run_id' => $openRun?->id]);
        if ($enrollment->wasRecentlyCreated) {
            foreach ($course->sessions()->where('meeting_status', '!=', 'not_applicable')->get() as $session) {
                Auth::user()->notify(new LiveSessionScheduled($session));
            }
        }

        return $enrollment;
    }

    public function learningPaths()
    {
        $paths = LearningPath::where('status', 'published')->withCount('courses')->get();

        $progress = [];
        if (Auth::check()) {
            foreach ($paths as $path) {
                $courseIds = $path->courses->pluck('id');
                $completedCount = Auth::user()->enrollments()->whereIn('course_id', $courseIds)->where('status', 'completed')->count();
                $progress[$path->id] = $courseIds->isEmpty() ? 0 : (int) round($completedCount / $courseIds->count() * 100);
            }
        }

        return view('lms.learning-paths', compact('paths', 'progress'));
    }

    public function learningPathShow(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);
        $path->load('courses.instructor');

        $enrolledCourseIds = Auth::check() ? Auth::user()->enrollments()->pluck('course_id') : collect();
        $completedCourseIds = Auth::check() ? Auth::user()->enrollments()->where('status', 'completed')->pluck('course_id') : collect();

        return view('lms.learning-path-show', compact('path', 'enrolledCourseIds', 'completedCourseIds'));
    }

    public function enrollInPath(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);
        foreach ($path->courses()->where('status', 'published')->get() as $course) {
            $this->enrollUserInCourse($course);
        }

        return redirect()->route('learning-paths.show', $path)->with('success', 'Öğrenim yolundaki tüm eğitimlere kaydınız oluşturuldu.');
    }

    public function instructor()
    {
        if (Auth::user()->role === 'admin') {
            return redirect('/admin');
        }
        abort_unless(Auth::user()->role === 'instructor', 403);

        $courseIds = Auth::user()->courses()->pluck('id');

        $courses = Auth::user()->courses()
            ->withCount([
                'enrollments',
                'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
                'enrollments as new_enrollments_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays(7)),
            ])
            ->with('sessions')
            ->latest()
            ->get();

        $upcomingSession = CourseSession::whereIn('course_id', $courseIds)
            ->where('starts_at', '>', now())
            ->with('course')
            ->orderBy('starts_at')
            ->first();

        $incompleteLearnersCount = Enrollment::whereIn('course_id', $courseIds)->where('status', '!=', 'completed')->count();
        $newEnrollmentsCount = Enrollment::whereIn('course_id', $courseIds)->where('created_at', '>=', now()->subDays(7))->count();

        // Past sessions (last 7 days) where nobody has been recorded as attended yet --
        // a concrete, actionable "go check this" list for the instructor.
        $sessionsNeedingAttendanceReview = CourseSession::whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [now()->subDays(7), now()])
            ->whereDoesntHave('progress', fn ($q) => $q->whereNotNull('completed_at'))
            ->with('course')
            ->orderByDesc('starts_at')
            ->get();

        return view('lms.instructor', compact(
            'courses', 'upcomingSession', 'incompleteLearnersCount', 'newEnrollmentsCount', 'sessionsNeedingAttendanceReview'
        ));
    }

    public function instructorCourses()
    {
        abort_unless(Auth::user()->role === 'instructor', 403);

        $courses = Auth::user()->courses()
            ->withCount([
                'enrollments',
                'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->with('sessions')
            ->latest()
            ->get();

        return view('lms.instructor-courses', compact('courses'));
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
        // Separate, dedicated paginated query for the participant list card only.
        // $course->enrollments (the full eager-loaded collection above) must stay
        // intact and unpaginated: the per-run participant counts and the
        // per-session attendance-marking roll below both need every enrollment,
        // not just one page of them.
        $participants = $course->enrollments()->with(['user', 'sessionProgress.session'])->latest()->paginate(15, ['*'], 'participants_page');

        return view('lms.course-detail', compact('course', 'stats', 'participants'));
    }

    /**
     * "Kim eriştiği, ne yaptığı, istatistikler" audit view for a course:
     * merges the mutation trail (AuditLog rows -- who created/edited/deleted
     * the course/section/activity/material rows) with the access trail
     * (material_progress/activity_progress -- who actually opened/watched
     * each piece of content, learner-side). Two different questions, both
     * asked in the same view: "who touched the content" vs "who consumed it".
     */
    public function courseLog(Course $course)
    {
        $this->authorize('view', $course);
        $course->load(['sections.activities', 'sessions.materials']);

        $activityIds = $course->sections->flatMap->activities->pluck('id');
        $materialIds = $course->sessions->flatMap->materials->pluck('id');

        $accessLog = ActivityProgress::with(['activity', 'enrollment.user'])
            ->whereIn('activity_id', $activityIds)
            ->get()
            ->map(fn ($p) => ['learner' => $p->enrollment->user, 'content_title' => $p->activity->title, 'content_type' => 'Etkinlik', 'watched_seconds' => $p->watched_seconds, 'completed' => $p->completed, 'completed_at' => $p->completed_at, 'last_seen_at' => $p->last_heartbeat_at ?? $p->updated_at, 'first_seen_at' => $p->created_at, 'enrollment_user_id' => $p->enrollment->user_id])
            ->toBase()
            ->concat(
                MaterialProgress::with(['material', 'enrollment.user'])
                    ->whereIn('material_id', $materialIds)
                    ->get()
                    ->map(fn ($p) => ['learner' => $p->enrollment->user, 'content_title' => $p->material->title, 'content_type' => 'Materyal', 'watched_seconds' => $p->watched_seconds, 'completed' => $p->completed, 'completed_at' => $p->completed_at, 'last_seen_at' => $p->last_heartbeat_at ?? $p->updated_at, 'first_seen_at' => $p->created_at, 'enrollment_user_id' => $p->enrollment->user_id])
            )
            ->sortByDesc('last_seen_at')
            ->values();

        $auditTrail = AuditLog::with('actor')
            ->where(fn ($q) => $q->where('entity_type', 'Course')->where('entity_id', $course->id))
            ->orWhere(fn ($q) => $q->where('entity_type', 'CourseSection')->whereIn('entity_id', $course->sections->pluck('id')))
            ->orWhere(fn ($q) => $q->where('entity_type', 'Activity')->whereIn('entity_id', $activityIds))
            ->orWhere(fn ($q) => $q->where('entity_type', 'Material')->whereIn('entity_id', $materialIds))
            ->latest()
            ->limit(100)
            ->get();

        $totalContentItems = $activityIds->count() + $materialIds->count();
        $stats = [
            'total_content_items' => $totalContentItems,
            'total_views' => $accessLog->count(),
            'completed_views' => $accessLog->where('completed', true)->count(),
            'completion_rate' => $accessLog->count() ? (int) round($accessLog->where('completed', true)->count() / $accessLog->count() * 100) : 0,
            'avg_watched_minutes' => $accessLog->count() ? round($accessLog->avg('watched_seconds') / 60, 1) : 0,
            'unique_learners' => $accessLog->pluck('enrollment_user_id')->unique()->count(),
        ];

        return view('lms.course-log', compact('course', 'accessLog', 'auditTrail', 'stats'));
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
            $user->notify(new CoursePublished($course));
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

    /**
     * Öğrenci kendi katılımını yalnızca online seanslarda, gerçek oda
     * yoklaması (class_presence -> session_attendance) yeterli süreye
     * ulaştığında tamamlayabilir. Yüz yüze (offline) seanslarda fiziksel
     * katılım sistem tarafından doğrulanamaz; bu yüzden self-servis
     * tamamlama tamamen kapalıdır -- yoklamayı yalnızca dersi bizzat
     * yürüten eğitmen işaretleyebilir (bkz. markSessionAttendance).
     */
    public function completeSession(CourseSession $session)
    {
        abort_if($session->meeting_status === 'not_applicable', 422, 'Yüz yüze seanslarda katılım yalnızca eğitmen tarafından işaretlenebilir.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $session->course_id)->firstOrFail();
        $attended = SessionAttendance::where('course_session_id', $session->id)->where('user_id', Auth::id())->value('duration_seconds') ?? 0;
        $required = $this->requiredSessionSeconds($session);
        abort_unless($attended >= $required, 422, 'Seansı tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now(), 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Seans tamamlandı.');
    }

    /**
     * Eğitmenin, dersi bizzat yürüten kişi olarak, bir katılımcının
     * seansa katıldığını elle işaretlemesi. Yüz yüze seanslarda tek
     * güvenilir kaynak budur; online seanslarda da bağlantı sorunu
     * yaşayan bir katılımcı için telafi amacıyla kullanılabilir.
     */
    public function markSessionAttendance(Request $r, CourseSession $session)
    {
        $this->authorize('update', $session->course);
        $data = $r->validate(['user_id' => 'required|exists:users,id']);
        $enrollment = Enrollment::where('course_id', $session->course_id)->where('user_id', $data['user_id'])->firstOrFail();
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now(), 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Katılım işaretlendi.');
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
        if (Auth::user()->enrollments()->where('course_id', $session->course_id)->exists()) {
            $this->accrueSessionAttendance($session);
        }
        $users = \DB::table('class_presence')->join('users', 'users.id', '=', 'class_presence.user_id')->where('course_session_id', $session->id)->where('last_seen_at', '>', now()->subSeconds(30))->select('users.id', 'users.name', 'users.avatar_path')->get();

        return response()->json($users);
    }

    public function sessionAttendanceStatus(CourseSession $session)
    {
        $attended = SessionAttendance::where('course_session_id', $session->id)->where('user_id', Auth::id())->value('duration_seconds') ?? 0;
        $required = $this->requiredSessionSeconds($session);

        return response()->json(['attended_seconds' => $attended, 'required_seconds' => $required, 'eligible' => $attended >= $required]);
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
        $isVideo = str_starts_with($material->type, 'video/');
        $required = $isVideo ? $this->requiredWatchSeconds($material->duration_seconds) : null;
        $estimatedMinutes = $isVideo ? null : DocumentEstimator::estimateMinutes(storage_path('app/public/'.$material->path), $material->type);

        return view('lms.material-view', ['material' => $material, 'progress' => $progress, 'required' => $required, 'isVideo' => $isVideo, 'estimatedMinutes' => $estimatedMinutes]);
    }

    public function materialHeartbeat(Material $material)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $this->accrueHeartbeat($progress);
        $required = $this->requiredWatchSeconds($material->duration_seconds);

        return response()->json(['watched_seconds' => $progress->watched_seconds, 'required_seconds' => $required, 'eligible' => $progress->watched_seconds >= $required]);
    }

    /**
     * The real duration of a video, read client-side from the file itself
     * once its metadata loads, replacing the old model where an instructor
     * had no way to enter it and every video fell back to a flat 20s
     * requirement. First-write-wins: once set, later calls can't shrink it
     * (the client-reported value is never trusted for anything that already
     * has a value, same anti-cheat posture as the heartbeat mechanism).
     */
    public function setMaterialDuration(Request $r, Material $material)
    {
        Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        if (is_null($material->duration_seconds)) {
            $material->update(['duration_seconds' => $r->validate(['seconds' => 'required|integer|min:1|max:86400'])['seconds']]);
        }

        return response()->json(['ok' => true]);
    }

    public function completeMaterial(Material $material)
    {
        $enrollment = Auth::user()->enrollments()->where('course_id', $material->session->course_id)->firstOrFail();
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        if (str_starts_with($material->type, 'video/')) {
            $required = $this->requiredWatchSeconds($material->duration_seconds);
            abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        }
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Materyal tamamlandı olarak işaretlendi.');
    }

    public function activityView(Activity $activity)
    {
        abort_if($activity->type === 'live_session', 422, 'Canlı oturum etkinlikleri katılım kaydından otomatik izlenir.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);

        if ($activity->type === 'quiz') {
            $activity->load('quizQuestions.options');
            $attempts = QuizAttempt::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)->orderByDesc('submitted_at')->get();
            $hasPassed = $attempts->contains('passed', true);
            $attemptsRemaining = $activity->max_attempts ? max(0, $activity->max_attempts - $attempts->count()) : null;
            $canAttempt = ! $hasPassed && ($attemptsRemaining === null || $attemptsRemaining > 0);

            return view('lms.quiz-view', compact('activity', 'progress', 'attempts', 'hasPassed', 'attemptsRemaining', 'canAttempt'));
        }

        if ($activity->type === 'assignment') {
            $submissions = AssignmentSubmission::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)->orderByDesc('submitted_at')->get();
            $hasPassed = $submissions->contains(fn ($s) => $s->isGraded() && $s->score >= ($activity->pass_score ?? 0));
            $hasPendingSubmission = $submissions->contains(fn ($s) => ! $s->isGraded());

            return view('lms.assignment-view', compact('activity', 'progress', 'submissions', 'hasPassed', 'hasPendingSubmission'));
        }

        $isVideo = $activity->type === 'video';
        $required = $isVideo ? $this->requiredWatchSeconds($activity->duration_seconds) : null;
        $estimatedMinutes = (! $isVideo && $activity->path) ? DocumentEstimator::estimateMinutes(storage_path('app/public/'.$activity->path), $this->activityFileMime($activity)) : null;

        return view('lms.activity-view', ['activity' => $activity, 'progress' => $progress, 'required' => $required, 'isVideo' => $isVideo, 'estimatedMinutes' => $estimatedMinutes]);
    }

    /**
     * Activities store a coarse type ('document', 'presentation', ...) rather
     * than a MIME type, so map it to the MIME DocumentEstimator understands.
     */
    private function activityFileMime(Activity $activity): ?string
    {
        return match ($activity->type) {
            'document' => 'application/pdf',
            'presentation' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => null,
        };
    }

    /**
     * Learner submission -- content only, no self-grading. Score/feedback
     * only ever get written by an instructor via gradeAssignment().
     */
    public function submitAssignment(Request $r, Activity $activity)
    {
        abort_unless($activity->type === 'assignment', 404);
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();

        $alreadyPassed = AssignmentSubmission::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)
            ->whereNotNull('graded_at')->where('score', '>=', $activity->pass_score ?? 0)->exists();
        abort_if($alreadyPassed, 422, 'Bu ödevi zaten geçtiniz.');

        $submission = ['activity_id' => $activity->id, 'enrollment_id' => $enrollment->id, 'submitted_at' => now()];

        if ($activity->submission_type === 'file') {
            $data = $r->validate(['file' => 'required|file|max:20480']);
            $submission['file_path'] = $data['file']->store('assignment-submissions', 'public');
        } elseif ($activity->submission_type === 'url') {
            $data = $r->validate(['content' => 'required|url']);
            $submission['content'] = $data['content'];
        } else {
            $data = $r->validate(['content' => 'required|string|max:10000']);
            $submission['content'] = $data['content'];
        }

        AssignmentSubmission::create($submission);

        return redirect()->route('activities.view', $activity)->with('success', 'Ödeviniz teslim edildi, eğitmeniniz değerlendirecek.');
    }

    /**
     * Instructor-only grading queue for one activity's submissions, scoped
     * to courses the grader actually owns (or an admin).
     */
    public function assignmentSubmissions(Activity $activity)
    {
        abort_unless($activity->type === 'assignment', 404);
        $course = $activity->section->course;
        abort_unless(Auth::user()->role === 'admin' || Auth::id() === $course->instructor_id, 403);

        $submissions = AssignmentSubmission::where('activity_id', $activity->id)->with('enrollment.user', 'grader')->orderByDesc('submitted_at')->get();

        return view('lms.assignment-submissions', compact('activity', 'course', 'submissions'));
    }

    public function gradeAssignment(Request $r, AssignmentSubmission $submission)
    {
        $activity = $submission->activity;
        $course = $activity->section->course;
        abort_unless(Auth::user()->role === 'admin' || Auth::id() === $course->instructor_id, 403);

        $data = $r->validate(['score' => 'required|integer|min:0|max:100', 'feedback' => 'nullable|string|max:4000']);
        $submission->update(['score' => $data['score'], 'feedback' => $data['feedback'] ?? null, 'graded_by' => Auth::id(), 'graded_at' => now()]);

        if ($data['score'] >= ($activity->pass_score ?? 0)) {
            $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $submission->enrollment_id, 'activity_id' => $activity->id]);
            $progress->update(['completed' => true, 'completed_at' => now()]);
            $this->checkAndCompleteEnrollment($submission->enrollment);
        }

        return back()->with('success', 'Ödev değerlendirildi.');
    }

    public function activityHeartbeat(Activity $activity)
    {
        abort_if(in_array($activity->type, ['live_session', 'quiz', 'assignment'], true), 422, 'Bu etkinlik türü izleme süresiyle takip edilmez.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        $this->accrueHeartbeat($progress);
        $required = $this->requiredWatchSeconds($activity->duration_seconds);

        return response()->json(['watched_seconds' => $progress->watched_seconds, 'required_seconds' => $required, 'eligible' => $progress->watched_seconds >= $required]);
    }

    /**
     * See setMaterialDuration() -- same first-write-wins auto-detection,
     * for activities of type 'video'.
     */
    public function setActivityDuration(Request $r, Activity $activity)
    {
        Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        if (is_null($activity->duration_seconds)) {
            $activity->update(['duration_seconds' => $r->validate(['seconds' => 'required|integer|min:1|max:86400'])['seconds']]);
        }

        return response()->json(['ok' => true]);
    }

    public function completeActivity(Activity $activity)
    {
        abort_if(in_array($activity->type, ['live_session', 'quiz', 'assignment'], true), 422, 'Bu etkinlik türü bu şekilde tamamlanamaz.');
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        if ($activity->type === 'video') {
            $required = $this->requiredWatchSeconds($activity->duration_seconds);
            abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        }
        $progress->update(['completed' => true, 'completed_at' => now()]);
        $this->checkAndCompleteEnrollment($enrollment);

        return back()->with('success', 'Aktivite tamamlandı olarak işaretlendi.');
    }

    /**
     * Grades entirely server-side from the stored correct-option flags --
     * the client only ever sends which option IDs it selected per question,
     * never a score. This mirrors the watch-time rule elsewhere in this
     * controller: nothing the client claims about its own success is trusted.
     */
    public function submitQuizAttempt(Request $r, Activity $activity)
    {
        abort_unless($activity->type === 'quiz', 404);
        $enrollment = Auth::user()->enrollments()->where('course_id', $activity->section->course_id)->firstOrFail();

        $previousAttempts = QuizAttempt::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)->get();
        abort_if($previousAttempts->contains('passed', true), 422, 'Bu sınavı zaten geçtiniz.');
        if ($activity->max_attempts) {
            abort_if($previousAttempts->count() >= $activity->max_attempts, 422, 'İzin verilen deneme hakkınız kalmadı.');
        }

        $questions = $activity->quizQuestions()->with('options')->get();
        abort_if($questions->isEmpty(), 422, 'Bu sınavda henüz soru yok.');

        $data = $r->validate(['answers' => 'required|array']);
        $submittedAnswers = collect($data['answers'])->map(fn ($ids) => array_map('intval', (array) $ids));

        $correctCount = 0;
        foreach ($questions as $question) {
            $correctOptionIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values();
            $selectedOptionIds = collect($submittedAnswers->get($question->id, []))->sort()->values();
            if ($correctOptionIds->all() === $selectedOptionIds->all()) {
                $correctCount++;
            }
        }

        $score = (int) round($correctCount / $questions->count() * 100);
        $passed = $score >= ($activity->pass_score ?? 0);

        $attempt = QuizAttempt::create([
            'activity_id' => $activity->id,
            'enrollment_id' => $enrollment->id,
            'score' => $score,
            'passed' => $passed,
            'answers' => $submittedAnswers->toArray(),
            'submitted_at' => now(),
        ]);

        if ($passed) {
            $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
            $progress->update(['completed' => true, 'completed_at' => now()]);
            $this->checkAndCompleteEnrollment($enrollment);
        }

        return redirect()->route('activities.view', $activity)->with($passed ? 'success' : 'error', $passed
            ? 'Sınavı geçtiniz! Puan: %'.$score
            : 'Sınavı geçemediniz. Puan: %'.$score.' (gerekli: %'.$activity->pass_score.')');
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

    /**
     * class_presence her ping'te güncellenir (kamera/mikrofon açıp "Katıl"
     * dedikten sonra 5sn'de bir); burada da materyal heartbeat'iyle aynı
     * mantıkla, istemciden gelen hiçbir süreye güvenmeden, ardışık kabul
     * edilen çağrılar arasında en az 4sn olacak şekilde sabit +5sn birikir.
     */
    private function accrueSessionAttendance(CourseSession $session): void
    {
        $attendance = SessionAttendance::firstOrCreate(
            ['course_session_id' => $session->id, 'user_id' => Auth::id()],
            ['joined_at' => now(), 'duration_seconds' => 0]
        );
        if ($attendance->left_at && $attendance->left_at->diffInSeconds(now()) < 4) {
            return;
        }
        $attendance->increment('duration_seconds', 5);
        $attendance->update(['left_at' => now()]);
    }

    private function requiredSessionSeconds(CourseSession $session): int
    {
        if ($session->starts_at && $session->ends_at) {
            return max(60, (int) ceil($session->starts_at->diffInSeconds($session->ends_at) * 0.9));
        }

        return 300;
    }

    private function checkAndCompleteEnrollment(Enrollment $enrollment): void
    {
        $stats = $enrollment->completionStats();
        if ($stats['total'] && $stats['completed'] >= $stats['total']) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            Certificate::firstOrCreate(['enrollment_id' => $enrollment->id], ['certificate_number' => 'CERT-'.strtoupper(Str::random(10)), 'issued_at' => now()]);
        }
    }

    public function certificate(Certificate $certificate)
    {
        $this->authorize('view', $certificate);

        return Pdf::loadView('lms.certificate', ['certificate' => $certificate, 'enrollment' => $certificate->enrollment->load('user', 'course')])->download($certificate->certificate_number.'.pdf');
    }

    /**
     * Public, unauthenticated: anyone holding a certificate number (e.g. a
     * hiring manager checking a candidate's claim) can confirm it's real
     * without needing an account or access to the downloadable PDF itself.
     * Deliberately shows only holder name / course / dates -- not a login,
     * not the PDF, not any other enrollment data.
     */
    public function verifyCertificate(string $certificateNumber)
    {
        $certificate = Certificate::where('certificate_number', $certificateNumber)
            ->with('enrollment.user', 'enrollment.course')
            ->first();

        return view('lms.certificate-verify', compact('certificate', 'certificateNumber'));
    }
}
