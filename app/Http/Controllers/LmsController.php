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
use App\Models\WaitlistEntry;
use App\Notifications\CoursePublished;
use App\Notifications\LiveSessionScheduled;
use App\Services\CompletionEngine;
use App\Services\EnrollmentService;
use App\Services\QuizService;
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
    /**
     * Whitelist for learner/instructor-uploaded files (course materials,
     * class-shared files, assignment submissions). Laravel's `mimes` rule
     * checks the file's actually-detected content type against this list,
     * not just its extension -- a `.pdf` that's really an HTML/script
     * payload fails validation instead of landing on the public disk to be
     * served (and potentially executed in a browser) as whatever a learner
     * or instructor later clicks on. Deliberately excludes svg/html/js and
     * anything executable; recordings have their own stricter webm/mp4-only
     * rule in uploadRecording(), and avatars/posters use Laravel's `image`
     * rule (content-verified, not just extension) instead of this list.
     */
    public const SAFE_UPLOAD_MIMES = 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,mp4,webm,mov,mp3,wav,jpg,jpeg,png,gif,txt,csv';

    public function __construct(private CompletionEngine $completion, private QuizService $quiz, private EnrollmentService $enrollments) {}

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
        $upcomingDeadlines = collect();

        if (Auth::check()) {
            // A stale (recertification-due) current cycle shouldn't hide the
            // catalog's "Enroll now" button -- it's meaningfully the same as
            // not being enrolled at all right now.
            $enrolledCourseIds = Auth::user()->enrollments()->currentCycle()->get()
                ->reject(fn (Enrollment $e) => $e->isStale())
                ->pluck('course_id');

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

            // "2 days left" style urgency (spec §30) -- distinct from
            // $requiredIncomplete's completion-percent cards above: this is
            // about deadline pressure, not progress, and only lists
            // enrollments that actually have a due date at all.
            $upcomingDeadlines = $activeEnrollments
                ->map(fn ($e) => ['enrollment' => $e, 'due' => $e->course->dueDateFor($e)])
                ->filter(fn ($row) => $row['due'] && $row['due']->isFuture())
                ->sortBy(fn ($row) => $row['due'])
                ->take(5)
                ->values();
        }

        // A simple, explainable heuristic (most-enrolled published courses
        // the learner hasn't joined yet) rather than a fabricated ML
        // recommendation this app has no interaction data to actually back.
        $recommended = Auth::check()
            ? Course::where('status', 'published')->whereNotIn('id', $enrolledCourseIds)
                ->withCount('enrollments')->orderByDesc('enrollments_count')->take(3)->get()
            : collect();

        $continueUrl = null;
        if ($continueLearning) {
            $nextItem = $continueLearning->nextIncompleteItem();
            $continueUrl = match ($nextItem['type'] ?? null) {
                'material' => route('materials.view', $nextItem['model']),
                'activity' => route('activities.view', $nextItem['model']),
                default => route('learning.index'),
            };
        }

        return view('lms.home', compact('courses', 'enrolledCourseIds', 'continueLearning', 'continueUrl', 'requiredIncomplete', 'todaySessions', 'upcomingDeadlines', 'recommended'));
    }

    public function courseShow(Course $course)
    {
        $isOwnerOrAdmin = Auth::check() && (Auth::user()->role === 'admin' || Auth::id() === $course->instructor_id);
        abort_unless($course->status === 'published' || $isOwnerOrAdmin, 404);

        $course->load(['instructor', 'sessions.materials', 'sections.activities', 'runs', 'skills']);

        $enrollment = null;
        $stats = null;
        $continueUrl = null;
        $needsRecertification = false;
        if (Auth::check()) {
            $enrollment = Auth::user()->enrollments()->where('course_id', $course->id)->with('progress', 'activityProgress', 'sessionProgress', 'certificate')->currentCycle()->first();
            if ($enrollment) {
                $needsRecertification = $enrollment->isStale();
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
        // No enrollment, or the current one is stale and due for a fresh
        // cycle -- either way, this is "someone who can (re-)enroll", so the
        // prerequisite/waitlist checks below apply the same as a first-timer.
        $canEnroll = Auth::check() && (! $enrollment || $needsRecertification);
        $unmetPrerequisites = $canEnroll ? $course->unmetPrerequisitesFor(Auth::user()) : collect();

        $isWaitlisted = false;
        if ($canEnroll) {
            $openRun = $course->runs()->whereIn('status', ['open', 'running'])->orderBy('starts_at')->first();
            $isWaitlisted = $openRun && WaitlistEntry::where('course_run_id', $openRun->id)->where('user_id', Auth::id())->exists();
        }

        return view('lms.course-show', compact('course', 'enrollment', 'stats', 'continueUrl', 'newerVersion', 'unmetPrerequisites', 'isWaitlisted', 'needsRecertification'));
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
        // Either a learner enrolled in the course or the instructor teaching
        // it -- the instructor calendar links to this same .ics download for
        // their own sessions, and they're never "enrolled" in their own course.
        $enrolled = Auth::user()->enrollments()->where('course_id', $session->course_id)->exists();
        $teaches = Auth::user()->id === $session->course->instructor_id;
        abort_unless($enrolled || $teaches, 403);

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
        // deactivated_at was previously only checked for Filament admin
        // panel access -- the primary login path never consulted it at
        // all, so a deactivated account (manually, or via SCIM
        // deprovisioning) could still sign in and use the whole app.
        if (Auth::user()->isDeactivated()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Bu hesap devre dışı bırakılmış.']);
        }
        $r->session()->regenerate();

        return redirect(Auth::user()->defaultWorkspaceRoute());
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
     * instant every required item is done (CompletionEngine::evaluateEnrollment), so
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

        $query = Auth::user()->enrollments()->with([
            'course.sessions.materials', 'course.sections.activities', 'certificate',
            'progress', 'activityProgress', 'sessionProgress',
        ]);

        $query = match ($tab) {
            'in_progress' => $query->where('status', '!=', 'completed'),
            'completed' => $query->where('status', 'completed'),
            'certificates' => $query->whereHas('certificate'),
            'upcoming' => $query->where('status', '!=', 'completed')->whereHas('course.sessions', fn ($q) => $q->where('starts_at', '>', now())),
            default => $query,
        };

        if ($r->filled('q')) {
            $query->whereHas('course', fn ($q) => $q->where('title', 'like', '%'.$r->query('q').'%'));
        }

        $enrollments = $query->latest()->paginate(12)->withQueryString();

        return view('lms.my-learning', compact('enrollments', 'counts', 'tab'));
    }

    public function enroll(Course $course)
    {
        abort_unless($course->status === 'published', 404);
        $result = $this->enrollUserInCourse($course);

        if ($result instanceof WaitlistEntry) {
            return redirect()->route('learning.index')->with('success', 'Bu eğitimin kontenjanı dolu. Bekleme listesine eklendiniz; bir kontenjan açıldığında bilgilendirileceksiniz.');
        }

        return redirect()->route('learning.index')->with('success', 'Eğitime kaydınız oluşturuldu. Canlı ders bilgileri e-posta adresinize gönderildi.');
    }

    private function enrollUserInCourse(Course $course): Enrollment|WaitlistEntry
    {
        return $this->enrollments->enroll($course, Auth::user());
    }

    public function learningPaths()
    {
        $paths = LearningPath::where('status', 'published')->withCount('courses')->get();

        $progress = [];
        if (Auth::check()) {
            foreach ($paths as $path) {
                $courseIds = $path->courses->pluck('id');
                $completedCount = Auth::user()->enrollments()->currentCycle()->whereIn('course_id', $courseIds)->where('status', 'completed')->count();
                $progress[$path->id] = $courseIds->isEmpty() ? 0 : (int) round($completedCount / $courseIds->count() * 100);
            }
        }

        return view('lms.learning-paths', compact('paths', 'progress'));
    }

    public function learningPathShow(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);
        $path->load('courses.instructor');

        $currentCycles = Auth::check() ? Auth::user()->enrollments()->currentCycle()->get() : collect();
        // A stale (recertification-due) cycle counts as neither "enrolled"
        // nor "completed" here -- same reasoning as home()'s catalog badge.
        $enrolledCourseIds = $currentCycles->reject(fn (Enrollment $e) => $e->isStale())->pluck('course_id');
        $completedCourseIds = $currentCycles->filter(fn (Enrollment $e) => $e->status === 'completed' && ! $e->isStale())->pluck('course_id');

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
        abort_unless(Auth::user()->hasRole('instructor'), 403);

        $courseIds = Auth::user()->courses()->pluck('id');

        $courses = Auth::user()->courses()
            ->withCount([
                'currentEnrollments as enrollments_count',
                'currentEnrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
                'currentEnrollments as new_enrollments_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays(7)),
            ])
            ->with(['sessions.materials', 'sections'])
            ->latest()
            ->get();

        $upcomingSession = CourseSession::whereIn('course_id', $courseIds)
            ->where('starts_at', '>', now())
            ->with('course')
            ->orderBy('starts_at')
            ->first();

        $todaySessions = CourseSession::whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->with('course')
            ->orderBy('starts_at')
            ->get();

        $sessionsThisWeekCount = CourseSession::whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // Past sessions (last 7 days) where nobody has been recorded as attended yet --
        // a concrete, actionable "go check this" list for the instructor.
        $sessionsNeedingAttendanceReview = CourseSession::whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [now()->subDays(7), now()])
            ->whereDoesntHave('progress', fn ($q) => $q->whereNotNull('completed_at'))
            ->with('course')
            ->orderByDesc('starts_at')
            ->get();

        // Each assignment with pending submissions is its own row (not one
        // "N assignments waiting" aggregate) -- the whole point of this list
        // is a link that lands exactly where the work is, not a click that
        // dead-ends on the general course list wondering which course/
        // assignment it meant.
        $pendingSubmissionActivities = Activity::where('type', 'assignment')
            ->whereHas('section', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->whereHas('assignmentSubmissions', fn ($q) => $q->whereNull('graded_at'))
            ->withCount(['assignmentSubmissions as pending_count' => fn ($q) => $q->whereNull('graded_at')])
            ->with('section.course')
            ->get();

        // currentCycle(): a recertified enrollment's old cycle is already
        // completed and must not double-count as "behind" here.
        $studentsBehindCount = Enrollment::currentCycle()->whereIn('course_id', $courseIds)->where('status', '!=', 'completed')
            ->with('course')->get()
            ->filter(fn (Enrollment $e) => $e->course->isOverdueFor($e))
            ->count();

        $tomorrowSessions = CourseSession::whereIn('course_id', $courseIds)
            ->whereBetween('starts_at', [now()->addDay()->startOfDay(), now()->addDay()->endOfDay()])
            ->with('course')
            ->get();

        $coursesMissingContent = $courses->filter(
            fn (Course $c) => $c->sections->isEmpty() && $c->sessions->every(fn (CourseSession $s) => $s->materials->isEmpty())
        );

        // A single prioritised checklist rather than two overlapping lists
        // (spec's "Attention Required" + "My Tasks" would otherwise show the
        // same four numbers under two headings) -- each entry links straight
        // to where the instructor can act on it.
        $pendingTasks = collect();
        if ($studentsBehindCount > 0) {
            $pendingTasks->push(['count' => $studentsBehindCount, 'label_tr' => 'öğrenci geride kaldı', 'label_en' => 'students are behind', 'href' => route('instructor.courses.index')]);
        }
        foreach ($pendingSubmissionActivities as $activity) {
            $pendingTasks->push([
                'count' => $activity->pending_count,
                'label_tr' => $activity->title.' ödevi değerlendirme bekliyor — '.$activity->section->course->title,
                'label_en' => $activity->title.' assignment waiting for grading — '.$activity->section->course->title,
                'href' => route('instructor.activities.submissions', $activity),
            ]);
        }
        foreach ($tomorrowSessions as $session) {
            $pendingTasks->push([
                'count' => 1,
                'label_tr' => $session->title.' yarın planlandı — '.$session->course->title,
                'label_en' => $session->title.' is scheduled for tomorrow — '.$session->course->title,
                'href' => route('instructor.courses.show', $session->course).'#sessions',
            ]);
        }
        foreach ($coursesMissingContent as $course) {
            $pendingTasks->push([
                'count' => 1,
                'label_tr' => $course->title.' eğitiminde henüz içerik yok',
                'label_en' => $course->title.' has no content yet',
                'href' => route('instructor.courses.show', $course).'#curriculum',
            ]);
        }

        $recentActivity = collect()
            ->merge(
                Enrollment::whereIn('course_id', $courseIds)->where('created_at', '>=', now()->subDays(7))
                    ->with('user', 'course')->latest()->limit(5)->get()
                    ->map(fn (Enrollment $e) => ['at' => $e->created_at, 'text_tr' => $e->user->name.', '.$e->course->title.' eğitimine kaydoldu', 'text_en' => $e->user->name.' enrolled in '.$e->course->title])
            )
            ->merge(
                AssignmentSubmission::whereHas('activity.section', fn ($q) => $q->whereIn('course_id', $courseIds))
                    ->where('submitted_at', '>=', now()->subDays(7))
                    ->with('enrollment.user', 'activity')->latest('submitted_at')->limit(5)->get()
                    ->map(fn (AssignmentSubmission $s) => ['at' => $s->submitted_at, 'text_tr' => $s->enrollment->user->name.', '.$s->activity->title.' ödevini teslim etti', 'text_en' => $s->enrollment->user->name.' submitted '.$s->activity->title])
            )
            ->sortByDesc('at')->take(6)->values();

        return view('lms.instructor', compact(
            'courses', 'upcomingSession', 'todaySessions', 'sessionsThisWeekCount',
            'sessionsNeedingAttendanceReview', 'pendingTasks', 'recentActivity'
        ));
    }

    /**
     * A dedicated, purpose-built attendance flow (course search → session
     * picker → roster) -- previously attendance marking only existed
     * buried inside a specific course's Course Workspace Sessions tab, with
     * no standalone entry point an instructor could reach directly for
     * "I need to take attendance right now" without first navigating into
     * that course. Reuses markSessionAttendance(), the same POST endpoint
     * the Sessions tab's own attendance form already calls.
     */
    public function instructorAttendance(Request $r)
    {
        abort_unless(Auth::user()->hasRole('instructor'), 403);

        $coursesQuery = Auth::user()->courses()->withCount('sessions')->orderBy('title');
        if ($r->filled('q')) {
            $coursesQuery->where('title', 'like', '%'.$r->query('q').'%');
        }
        $courses = $coursesQuery->get();

        $selectedCourse = null;
        $sessions = collect();
        $selectedSession = null;
        $roster = collect();

        if ($r->filled('course_id')) {
            $selectedCourse = Auth::user()->courses()->find($r->query('course_id'));
        }
        if ($selectedCourse) {
            $sessions = $selectedCourse->sessions()->orderByDesc('starts_at')->get();
        }
        if ($selectedCourse && $r->filled('session_id')) {
            $selectedSession = $sessions->firstWhere('id', (int) $r->query('session_id'));
        }
        if ($selectedSession) {
            $roster = $selectedCourse->currentEnrollments()->with(['user', 'sessionProgress'])->orderBy('id')->get()
                ->map(fn (Enrollment $e) => ['enrollment' => $e, 'attended' => $e->sessionProgress->firstWhere('course_session_id', $selectedSession->id)]);
        }

        return view('lms.instructor-attendance', compact('courses', 'selectedCourse', 'sessions', 'selectedSession', 'roster'));
    }

    public function instructorCourses()
    {
        abort_unless(Auth::user()->hasRole('instructor'), 403);

        $courses = Auth::user()->courses()
            ->withCount([
                'currentEnrollments as enrollments_count',
                'currentEnrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->with('sessions')
            ->latest()
            ->get();

        return view('lms.instructor-courses', compact('courses'));
    }

    /**
     * The learner-facing calendar() reads Auth::user()->enrollments(), so an
     * instructor visiting it would see their own learning (if any), never
     * their teaching schedule -- this is the instructor's own view over the
     * same month-grid + agenda UI, sourced from the courses they teach.
     */
    public function instructorCalendar(Request $r)
    {
        abort_unless(Auth::user()->hasRole('instructor'), 403);

        $month = \Illuminate\Support\Carbon::parse($r->query('month', now()->toDateString()))->startOfMonth();
        $courses = Auth::user()->courses()->get();
        $courseIds = $courses->pluck('id');

        $events = collect();

        CourseSession::whereIn('course_id', $courseIds)->whereNotNull('starts_at')->with('course')->get()->each(function ($session) use (&$events) {
            $events->push(['date' => $session->starts_at->toDateString(), 'time' => $session->starts_at->format('H:i'), 'type' => 'session', 'title' => $session->title, 'url' => route('instructor.courses.show', $session->course).'#sessions', 'ics' => route('calendar.ics.session', $session)]);
        });

        $courses->whereNotNull('starts_at')->each(function ($course) use (&$events) {
            $events->push(['date' => $course->starts_at->toDateString(), 'time' => null, 'type' => 'start', 'title' => $course->title, 'url' => route('instructor.courses.show', $course), 'ics' => null]);
        });

        $courses->whereNotNull('ends_at')->each(function ($course) use (&$events) {
            $events->push(['date' => $course->ends_at->toDateString(), 'time' => null, 'type' => 'end', 'title' => $course->title, 'url' => route('instructor.courses.show', $course), 'ics' => null]);
        });

        $eventsByDate = $events->groupBy('date');
        $upcoming = $events->filter(fn ($e) => $e['date'] >= now()->toDateString())->sortBy('date')->values();

        return view('lms.instructor-calendar', compact('month', 'eventsByDate', 'upcoming'));
    }

    /**
     * A unified, searchable view over everything the instructor has already
     * uploaded across all of their courses (spec §11's nav/search/filters).
     * Deliberately NOT the full "reusable content" architecture the spec
     * also describes (one piece of content attached to several courses) --
     * that needs Material/Activity to stop being owned by a single
     * session/section, which touches the course builder, versioning, and
     * every quiz/assignment flow. This is the browsing/discovery half on
     * its own, scoped out from that larger, separately-planned change.
     */
    public function contentLibrary(Request $r)
    {
        abort_unless(Auth::user()->hasRole('instructor'), 403);

        $courses = Auth::user()->courses()->with(['sections.activities', 'sessions.materials'])->get();

        $items = collect();
        foreach ($courses as $course) {
            foreach ($course->sections as $section) {
                foreach ($section->activities as $activity) {
                    $type = in_array($activity->type, ['video', 'document', 'presentation', 'quiz', 'assignment'], true) ? $activity->type : 'document';
                    $items->push(['title' => $activity->title, 'type' => $type, 'course' => $course->title, 'course_id' => $course->id, 'language' => $course->language, 'created_at' => $activity->created_at, 'updated_at' => $activity->updated_at, 'preview_url' => $activity->path ? asset('storage/'.$activity->path) : null, 'edit_url' => route('instructor.courses.show', $course).'#curriculum']);
                }
            }
            foreach ($course->sessions as $session) {
                $items->push(['title' => $session->title, 'type' => 'live_session', 'course' => $course->title, 'course_id' => $course->id, 'language' => $course->language, 'created_at' => $session->created_at, 'updated_at' => $session->updated_at, 'preview_url' => null, 'edit_url' => route('instructor.courses.show', $course).'#sessions']);
                foreach ($session->materials as $material) {
                    $items->push(['title' => $material->title, 'type' => 'document', 'course' => $course->title, 'course_id' => $course->id, 'language' => $course->language, 'created_at' => $material->created_at, 'updated_at' => $material->updated_at, 'preview_url' => asset('storage/'.$material->path), 'edit_url' => route('instructor.courses.show', $course).'#sessions']);
                }
            }
        }

        if ($r->filled('course_id')) {
            $items = $items->where('course_id', (int) $r->query('course_id'));
        }
        if ($r->filled('language')) {
            $items = $items->where('language', $r->query('language'));
        }
        if ($r->filled('q')) {
            $needle = mb_strtolower($r->query('q'));
            $items = $items->filter(fn ($i) => str_contains(mb_strtolower($i['title']), $needle));
        }

        // Counts reflect the search/course/language filters but not the
        // type filter itself, so the type tabs show what switching to them
        // would reveal rather than collapsing to the currently active one.
        $typeCounts = $items->countBy('type');

        $activeType = $r->query('type');
        if ($activeType) {
            $items = $items->where('type', $activeType);
        }

        $items = $items->sortByDesc('updated_at')->values();

        return view('lms.content-library', [
            'items' => new \Illuminate\Pagination\LengthAwarePaginator(
                $items->forPage($r->integer('page', 1), 20),
                $items->count(), 20, $r->integer('page', 1),
                ['path' => $r->url(), 'query' => $r->query()]
            ),
            'typeCounts' => $typeCounts,
            'activeType' => $activeType,
            'courses' => $courses,
        ]);
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
        $course->load(['instructor', 'sessions.materials.versions.replacedBy', 'currentEnrollments.user', 'currentEnrollments.sessionProgress.session', 'sections.activities.quizQuestions', 'sections.activities.quizAttempts', 'sections.activities.assignmentSubmissions', 'runs']);
        // Roster/stats read currentEnrollments (one row per distinct learner,
        // their current cycle) rather than enrollments -- otherwise a
        // recertifying learner would show up twice in "Participants" and
        // inflate the enrollment/completed counts.
        $stats = ['enrollments' => $course->currentEnrollments->count(), 'completed' => $course->currentEnrollments->where('status', 'completed')->count(), 'sessions' => $course->sessions->count(), 'attendance_minutes' => (int) (DB::table('session_attendance')->whereIn('course_session_id', $course->sessions->pluck('id'))->sum('duration_seconds') / 60), 'recordings' => $course->sessions->whereNotNull('recording_path')->count()];
        // Separate, dedicated paginated query for the participant list card only.
        // $course->currentEnrollments (the full eager-loaded collection above)
        // must stay intact and unpaginated: the per-run participant counts and
        // the per-session attendance-marking roll below both need every
        // current enrollment, not just one page of them.
        $participants = $course->currentEnrollments()
            ->with(['user', 'sessionProgress.session', 'activityProgress', 'progress'])
            ->withAvg('quizAttempts', 'score')
            ->withCount('assignmentSubmissions')
            ->latest()->paginate(15, ['*'], 'participants_page');

        // Attached as pseudo-attributes rather than a parallel array so the
        // Students tab can keep using $participants exactly like the old
        // Blade did -- completionStats() and the timestamp collections are
        // already loaded in memory, no extra queries per row.
        $participants->getCollection()->transform(function (Enrollment $enrollment) {
            $lastActivityAt = collect([
                $enrollment->activityProgress->max('updated_at'),
                $enrollment->progress->max('updated_at'),
                $enrollment->sessionProgress->max('updated_at'),
            ])->filter()->max();
            $enrollment->setAttribute('last_activity_at', $lastActivityAt);
            $enrollment->setAttribute('completion_percent', $enrollment->completionStats()['percent']);

            return $enrollment;
        });

        $assessableActivities = $course->sections->flatMap->activities->whereIn('type', ['quiz', 'assignment']);

        // A plain, defensible checklist rather than invented percentages --
        // "70% ready" would need a scoring formula nothing else in the app
        // has a basis for, but "has at least one X" is a fact the instructor
        // can act on directly.
        $courseHealth = [
            'content' => $course->sections->contains(fn ($s) => $s->activities->isNotEmpty()) || $course->sessions->contains(fn ($s) => $s->materials->isNotEmpty()),
            'assessment' => $assessableActivities->isNotEmpty(),
            'schedule' => $course->delivery_type !== 'online' || $course->sessions->isNotEmpty(),
        ];

        $analytics = $this->buildCourseAnalytics($course, $assessableActivities);

        return view('lms.course-detail', compact('course', 'stats', 'participants', 'assessableActivities', 'courseHealth', 'analytics'));
    }

    /**
     * "Content performance" (spec §13) is the point of this: not just how
     * students are doing, but which specific video/quiz/document is where
     * they're getting stuck -- a course-wide completion percentage can look
     * fine while one video is quietly losing half the class.
     */
    private function buildCourseAnalytics(Course $course, \Illuminate\Support\Collection $assessableActivities): array
    {
        $enrollmentIds = $course->currentEnrollments->pluck('id');
        $total = $enrollmentIds->count();

        $completionPercents = $course->currentEnrollments->map(fn (Enrollment $e) => $e->completionStats()['percent']);
        $funnel = [
            'started' => $completionPercents->filter(fn ($p) => $p > 0)->count(),
            'halfway' => $completionPercents->filter(fn ($p) => $p >= 50)->count(),
            'completed' => $course->currentEnrollments->where('status', 'completed')->count(),
        ];

        $quizActivities = $assessableActivities->where('type', 'quiz');
        $quizAttempts = $quizActivities->flatMap->quizAttempts;
        $attemptGroups = $quizAttempts->groupBy(fn ($a) => $a->enrollment_id.'-'.$a->activity_id);
        $quizStats = [
            'avg_score' => $quizAttempts->isNotEmpty() ? (int) round($quizAttempts->avg('score')) : null,
            'pass_rate' => $quizAttempts->isNotEmpty() ? (int) round($quizAttempts->where('passed', true)->count() / $quizAttempts->count() * 100) : null,
            'avg_attempts' => $attemptGroups->isNotEmpty() ? round($quizAttempts->count() / $attemptGroups->count(), 1) : null,
        ];

        $assignmentActivities = $assessableActivities->where('type', 'assignment');
        $submissions = $assignmentActivities->flatMap->assignmentSubmissions;
        $gradedSubmissions = $submissions->whereNotNull('score');
        $assignmentStats = [
            'submitted' => $submissions->count(),
            'pending' => $submissions->whereNull('graded_at')->count(),
            'avg_score' => $gradedSubmissions->isNotEmpty() ? (int) round($gradedSubmissions->avg('score')) : null,
        ];

        $sessionAttendanceRates = $total ? $course->sessions->map(function (CourseSession $session) use ($course, $total) {
            $attended = $course->currentEnrollments->flatMap->sessionProgress->where('course_session_id', $session->id)->whereNotNull('completed_at')->count();

            return $attended / $total * 100;
        }) : collect();
        $avgAttendance = $sessionAttendanceRates->isNotEmpty() ? (int) round($sessionAttendanceRates->avg()) : null;

        $materials = $course->sessions->flatMap->materials;
        $materialCompletions = $materials->isEmpty() ? collect() : MaterialProgress::whereIn('material_id', $materials->pluck('id'))
            ->whereIn('enrollment_id', $enrollmentIds)->where('completed', true)
            ->selectRaw('material_id, count(*) as cnt')->groupBy('material_id')->pluck('cnt', 'material_id');
        $nonQuizActivities = $assessableActivities->isEmpty() && $course->sections->flatMap->activities->isEmpty()
            ? collect()
            : $course->sections->flatMap->activities->where('type', '!=', 'quiz');
        $activityCompletions = $nonQuizActivities->isEmpty() ? collect() : ActivityProgress::whereIn('activity_id', $nonQuizActivities->pluck('id'))
            ->whereIn('enrollment_id', $enrollmentIds)->where('completed', true)
            ->selectRaw('activity_id, count(*) as cnt')->groupBy('activity_id')->pluck('cnt', 'activity_id');

        $contentPerformance = collect()
            ->concat($materials->map(fn ($m) => ['title' => $m->title, 'rate' => $total ? (int) round(($materialCompletions[$m->id] ?? 0) / $total * 100) : 0]))
            ->concat($nonQuizActivities->map(fn ($a) => ['title' => $a->title, 'rate' => $total ? (int) round(($activityCompletions[$a->id] ?? 0) / $total * 100) : 0]))
            ->concat($quizActivities->map(function ($quiz) {
                $attempts = $quiz->quizAttempts;

                return $attempts->isEmpty() ? null : ['title' => $quiz->title, 'rate' => (int) round($attempts->avg('score'))];
            })->filter())
            ->sortByDesc('rate')->values();

        return compact('funnel', 'quizStats', 'assignmentStats', 'avgAttendance', 'contentPerformance', 'total');
    }

    /**
     * Instructor-side course settings -- until now, only the admin panel
     * could edit a course after its initial storeCourse() creation, which
     * meant an instructor had to ask an admin to fix a typo in their own
     * course description. CoursePolicy::update() already allows this (admin
     * or the owning instructor), so this just gives that permission a form.
     */
    public function updateCourse(Request $r, Course $course)
    {
        $this->authorize('update', $course);
        $data = $r->validate(['title' => 'required', 'description' => 'nullable', 'delivery_type' => 'required|in:online,offline', 'language' => 'required|in:tr,en', 'status' => 'required|in:draft,published,completed', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'guest_instructor_name' => 'nullable', 'guest_instructor_email' => 'nullable|email', 'guest_instructor_bio' => 'nullable', 'certificate_enabled' => 'nullable|boolean', 'sdg_goals' => 'nullable|array', 'poster' => 'nullable|image|max:5120', 'image' => 'nullable|image|max:5120']);
        $data['certificate_enabled'] = $r->boolean('certificate_enabled');
        $data['sdg_goals'] = $r->input('sdg_goals', []);
        if ($r->hasFile('poster')) {
            $data['poster_path'] = $r->file('poster')->store('course-posters', 'public');
        }
        if ($r->hasFile('image')) {
            $data['image_path'] = $r->file('image')->store('course-images', 'public');
        }
        unset($data['poster'], $data['image']);
        $course->update($data);

        return redirect()->route('instructor.courses.show', $course)->with('success', 'Eğitim ayarları kaydedildi.');
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
        $data = $r->validate(['title' => 'required', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'material' => ['nullable', 'file', 'max:51200', self::SAFE_UPLOAD_MIMES]]);
        $file = $r->file('material');
        unset($data['material']);
        $session = $course->sessions()->create(array_merge($data, ['room_key' => $course->delivery_type === 'online' ? Str::lower(Str::random(24)) : null, 'meeting_status' => $course->delivery_type === 'online' ? 'scheduled' : 'not_applicable']));
        if ($file) {
            Material::create(['course_session_id' => $session->id, 'title' => $file->getClientOriginalName(), 'type' => $file->getMimeType(), 'path' => $file->store('materials', 'public')]);
        }
        if ($course->delivery_type === 'online') {
            foreach ($course->currentEnrollments()->with('user')->get() as $enrollment) {
                $enrollment->user->notify(new LiveSessionScheduled($session));
            }
        }

        return back()->with('success', 'Seans planlandı. Portal içi toplantı odası otomatik oluşturuldu.');
    }

    public function meeting(string $roomKey)
    {
        $session = CourseSession::where('room_key', $roomKey)->firstOrFail();
        abort_unless(Auth::check(), 403);
        $this->authorizeClassroomAccess($session);

        return redirect()->route('sessions.classroom', $session);
    }

    public function startSession(CourseSession $session)
    {
        $enrollment = Auth::user()->currentEnrollmentForOrFail($session->course_id);
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
        $enrollment = Auth::user()->currentEnrollmentForOrFail($session->course_id);
        $attended = SessionAttendance::where('course_session_id', $session->id)->where('user_id', Auth::id())->value('duration_seconds') ?? 0;
        $required = $this->completion->requiredSessionSeconds($session);
        abort_unless($attended >= $required, 422, 'Seansı tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now(), 'completed_at' => now()]);
        $this->completion->evaluateEnrollment($enrollment);

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
        $enrollment = Enrollment::currentFor($data['user_id'], $session->course_id) ?? abort(404);
        SessionProgress::updateOrCreate(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id], ['started_at' => now(), 'last_seen_at' => now(), 'completed_at' => now()]);
        $this->completion->evaluateEnrollment($enrollment);

        return back()->with('success', 'Katılım işaretlendi.');
    }

    public function classroom(CourseSession $session)
    {
        $this->authorizeClassroomAccess($session);
        $session->load('course.instructor', 'materials');
        $messages = ClassMessage::where('course_session_id', $session->id)->with('user')->latest()->limit(50)->get()->reverse();

        return view('lms.classroom', compact('session', 'messages'));
    }

    public function shareClassFile(Request $r, CourseSession $session)
    {
        $this->authorize('update', $session->course);
        $r->validate(['file' => ['required', 'file', 'max:51200', self::SAFE_UPLOAD_MIMES]]);
        $file = $r->file('file');
        Material::create(['course_session_id' => $session->id, 'title' => $file->getClientOriginalName(), 'type' => $file->getMimeType(), 'path' => $file->store('materials', 'public')]);

        return back()->with('success', 'Dosya sınıfta paylaşıldı.');
    }

    public function classMessage(Request $r, CourseSession $session)
    {
        $this->authorizeClassroomAccess($session);
        $r->validate(['body' => 'required|string|max:2000']);
        ClassMessage::create(['course_session_id' => $session->id, 'user_id' => Auth::id(), 'body' => $r->body]);

        return back();
    }

    public function classPresence(CourseSession $session)
    {
        $this->authorizeClassroomAccess($session);
        \DB::table('class_presence')->updateOrInsert(['course_session_id' => $session->id, 'user_id' => Auth::id()], ['last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        if (Auth::user()->enrollments()->where('course_id', $session->course_id)->exists()) {
            $this->completion->accrueSessionAttendance($session, Auth::id());
        }
        $users = \DB::table('class_presence')->join('users', 'users.id', '=', 'class_presence.user_id')->where('course_session_id', $session->id)->where('last_seen_at', '>', now()->subSeconds(30))->select('users.id', 'users.name', 'users.avatar_path')->get();

        return response()->json($users);
    }

    public function sessionAttendanceStatus(CourseSession $session)
    {
        $this->authorizeClassroomAccess($session);
        $attended = SessionAttendance::where('course_session_id', $session->id)->where('user_id', Auth::id())->value('duration_seconds') ?? 0;
        $required = $this->completion->requiredSessionSeconds($session);

        return response()->json(['attended_seconds' => $attended, 'required_seconds' => $required, 'eligible' => $attended >= $required]);
    }

    public function classSignals(Request $r, CourseSession $session)
    {
        $this->authorizeClassroomAccess($session);
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
        $this->authorizeClassroomAccess($session);
        $data = $r->validate(['type' => 'required|string', 'payload' => 'required', 'recipient_id' => 'nullable|integer']);
        \DB::table('class_signals')->insert(['course_session_id' => $session->id, 'sender_id' => Auth::id(), 'recipient_id' => $data['recipient_id'] ?? null, 'type' => $data['type'], 'payload' => json_encode($data['payload']), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * The same "is this your live class" rule classroom() has always used,
     * now shared by every AJAX endpoint that page's JS calls -- those
     * previously had no check of their own at all, so a direct POST/GET to
     * classMessage/classPresence/classSignals/sendSignal/attendance-status
     * with any session id worked for any authenticated user, enrolled or
     * not. The page itself was never the actual boundary.
     */
    private function authorizeClassroomAccess(CourseSession $session): void
    {
        $allowed = (Auth::user()->isInstructor() && Auth::id() === $session->course->instructor_id)
            || Auth::user()->enrollments()->where('course_id', $session->course_id)->exists();
        abort_unless($allowed, 403);
    }

    public function uploadRecording(Request $r, CourseSession $session)
    {
        $this->authorize('update', $session->course);
        $r->validate(['recording' => 'required|file|mimes:webm,mp4|max:512000']);
        $path = $r->file('recording')->store('recordings', 'public');
        $session->update(['recording_path' => $path, 'recording_expires_at' => now()->addDays(CourseSession::RECORDING_RETENTION_DAYS)]);

        return response()->json(['ok' => true, 'path' => asset('storage/'.$path)]);
    }

    public function materialView(Material $material)
    {
        $enrollment = Auth::user()->currentEnrollmentForOrFail($material->session->course_id);
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $isVideo = str_starts_with($material->type, 'video/');
        $required = $isVideo ? $this->completion->requiredWatchSeconds($material->duration_seconds) : null;
        $estimatedMinutes = $isVideo ? null : DocumentEstimator::estimateMinutes('public', $material->path, $material->type);

        return view('lms.material-view', ['material' => $material, 'progress' => $progress, 'required' => $required, 'isVideo' => $isVideo, 'estimatedMinutes' => $estimatedMinutes]);
    }

    public function materialHeartbeat(Material $material)
    {
        $enrollment = Auth::user()->currentEnrollmentForOrFail($material->session->course_id);
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        $this->completion->accrueHeartbeat($progress);
        $required = $this->completion->requiredWatchSeconds($material->duration_seconds);

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
        Auth::user()->currentEnrollmentForOrFail($material->session->course_id);
        if (is_null($material->duration_seconds)) {
            $material->update(['duration_seconds' => $r->validate(['seconds' => 'required|integer|min:1|max:86400'])['seconds']]);
        }

        return response()->json(['ok' => true]);
    }

    public function completeMaterial(Material $material)
    {
        $enrollment = Auth::user()->currentEnrollmentForOrFail($material->session->course_id);
        $progress = MaterialProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'material_id' => $material->id]);
        if (str_starts_with($material->type, 'video/')) {
            $required = $this->completion->requiredWatchSeconds($material->duration_seconds);
            abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        }
        $this->completion->completeMaterialProgress($progress, $enrollment);

        return back()->with('success', 'Materyal tamamlandı olarak işaretlendi.');
    }

    public function activityView(Activity $activity)
    {
        abort_if($activity->type === 'live_session', 422, 'Canlı oturum etkinlikleri katılım kaydından otomatik izlenir.');
        $enrollment = Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);

        if ($activity->type === 'quiz') {
            // setRelation (not load()) so the existing blade template's
            // $activity->quizQuestions keeps working unchanged, but now
            // holds this attempt's drawn subset rather than the full bank
            // when pool_size is set -- see QuizService for why the subset
            // has to be derived deterministically rather than stored.
            $activity->setRelation('quizQuestions', $this->quiz->questionsForAttempt($activity, $enrollment));
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
        $required = $isVideo ? $this->completion->requiredWatchSeconds($activity->duration_seconds) : null;
        $estimatedMinutes = (! $isVideo && $activity->path) ? DocumentEstimator::estimateMinutes('public', $activity->path, $this->activityFileMime($activity)) : null;

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
        $enrollment = Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);

        $alreadyPassed = AssignmentSubmission::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)
            ->whereNotNull('graded_at')->where('score', '>=', $activity->pass_score ?? 0)->exists();
        abort_if($alreadyPassed, 422, 'Bu ödevi zaten geçtiniz.');

        $submission = ['activity_id' => $activity->id, 'enrollment_id' => $enrollment->id, 'submitted_at' => now()];

        if ($activity->submission_type === 'file') {
            $data = $r->validate(['file' => ['required', 'file', 'max:20480', self::SAFE_UPLOAD_MIMES]]);
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
        $this->authorize('view', $course);

        $activity->load('rubricCriteria');
        $submissions = AssignmentSubmission::where('activity_id', $activity->id)->with('enrollment.user', 'grader')->orderByDesc('submitted_at')->get();

        return view('lms.assignment-submissions', compact('activity', 'course', 'submissions'));
    }

    /**
     * With a rubric attached, the score is derived from per-criterion
     * points rather than typed in directly -- but it still lands in the
     * same `score` column every pass/fail check elsewhere already reads,
     * so grading with vs. without a rubric is invisible to the rest of the
     * app. Points are clamped server-side to each criterion's max_points;
     * the client only ever supplies which points it's awarding, never the
     * resulting percentage, matching the app's usual anti-cheat posture.
     */
    public function gradeAssignment(Request $r, AssignmentSubmission $submission)
    {
        $activity = $submission->activity;
        $course = $activity->section->course;
        $this->authorize('view', $course);

        if ($activity->hasRubric()) {
            $data = $r->validate(['rubric_scores' => 'required|array', 'rubric_scores.*' => 'required|integer|min:0', 'feedback' => 'nullable|string|max:4000']);
            $earned = 0;
            $possible = 0;
            $rubricScores = [];
            foreach ($activity->rubricCriteria as $criterion) {
                $points = min((int) ($data['rubric_scores'][$criterion->id] ?? 0), $criterion->max_points);
                $rubricScores[$criterion->id] = $points;
                $earned += $points;
                $possible += $criterion->max_points;
            }
            $score = $possible ? (int) round($earned / $possible * 100) : 0;
            $submission->update(['score' => $score, 'rubric_scores' => $rubricScores, 'feedback' => $data['feedback'] ?? null, 'graded_by' => Auth::id(), 'graded_at' => now()]);
        } else {
            $data = $r->validate(['score' => 'required|integer|min:0|max:100', 'feedback' => 'nullable|string|max:4000']);
            $score = $data['score'];
            $submission->update(['score' => $score, 'feedback' => $data['feedback'] ?? null, 'graded_by' => Auth::id(), 'graded_at' => now()]);
        }

        if ($score >= ($activity->pass_score ?? 0)) {
            $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $submission->enrollment_id, 'activity_id' => $activity->id]);
            $this->completion->completeActivityProgress($progress, $submission->enrollment);
        }

        return back()->with('success', 'Ödev değerlendirildi.');
    }

    public function activityHeartbeat(Activity $activity)
    {
        abort_if(in_array($activity->type, ['live_session', 'quiz', 'assignment'], true), 422, 'Bu etkinlik türü izleme süresiyle takip edilmez.');
        $enrollment = Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        $this->completion->accrueHeartbeat($progress);
        $required = $this->completion->requiredWatchSeconds($activity->duration_seconds);

        return response()->json(['watched_seconds' => $progress->watched_seconds, 'required_seconds' => $required, 'eligible' => $progress->watched_seconds >= $required]);
    }

    /**
     * See setMaterialDuration() -- same first-write-wins auto-detection,
     * for activities of type 'video'.
     */
    public function setActivityDuration(Request $r, Activity $activity)
    {
        Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);
        if (is_null($activity->duration_seconds)) {
            $activity->update(['duration_seconds' => $r->validate(['seconds' => 'required|integer|min:1|max:86400'])['seconds']]);
        }

        return response()->json(['ok' => true]);
    }

    public function completeActivity(Activity $activity)
    {
        abort_if(in_array($activity->type, ['live_session', 'quiz', 'assignment'], true), 422, 'Bu etkinlik türü bu şekilde tamamlanamaz.');
        $enrollment = Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);
        $progress = ActivityProgress::firstOrCreate(['enrollment_id' => $enrollment->id, 'activity_id' => $activity->id]);
        if ($activity->type === 'video') {
            $required = $this->completion->requiredWatchSeconds($activity->duration_seconds);
            abort_unless($progress->watched_seconds >= $required, 422, 'İçeriği tamamlamadan tamamlandı olarak işaretleyemezsiniz.');
        }
        $this->completion->completeActivityProgress($progress, $enrollment);

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
        $enrollment = Auth::user()->currentEnrollmentForOrFail($activity->section->course_id);

        $previousAttempts = QuizAttempt::where('activity_id', $activity->id)->where('enrollment_id', $enrollment->id)->get();
        abort_if($previousAttempts->contains('passed', true), 422, 'Bu sınavı zaten geçtiniz.');
        if ($activity->max_attempts) {
            abort_if($previousAttempts->count() >= $activity->max_attempts, 422, 'İzin verilen deneme hakkınız kalmadı.');
        }
        // Guards against a double-click/double-submit creating two attempt
        // rows (and burning two of a limited attempt budget) for what the
        // learner experienced as one submission -- not a rate limit on
        // genuine retries, just a few-second debounce.
        $lastAttemptAt = $previousAttempts->max('submitted_at');
        abort_if($lastAttemptAt && $lastAttemptAt->diffInSeconds(now()) < 3, 429, 'Lütfen birkaç saniye bekleyip tekrar deneyin.');

        $questions = $this->quiz->questionsForAttempt($activity, $enrollment);
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
            $this->completion->completeActivityProgress($progress, $enrollment);
        }

        return redirect()->route('activities.view', $activity)->with($passed ? 'success' : 'error', $passed
            ? 'Sınavı geçtiniz! Puan: %'.$score
            : 'Sınavı geçemediniz. Puan: %'.$score.' (gerekli: %'.$activity->pass_score.')');
    }

    public function certificate(Certificate $certificate)
    {
        $this->authorize('view', $certificate);
        abort_if($certificate->isRevoked(), 410, 'Bu sertifika iptal edilmiştir ve artık indirilemez.');

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
