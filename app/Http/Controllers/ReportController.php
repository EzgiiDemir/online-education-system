<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report center (spec §26): a "Training" detail report (learner x course x
 * status x completion x certificate) plus three aggregate views --
 * Completion, Attendance, Instructor. Deliberately NOT built as separate
 * "Certificate Report" / "Compliance Report" tabs: those already exist as
 * their own, richer screens (Filament's CertificateResource, the admin
 * ComplianceDashboard page) and duplicating them here would just be a
 * second, drifting copy of the same data. Excel/PDF export and scheduled
 * reports (spec's "Schedule Report / Recipients / Frequency") are also not
 * implemented -- CSV + on-screen filters only, same scope line the original
 * Training report already drew.
 */
class ReportController extends Controller
{
    public function index(Request $r)
    {
        $this->authorizeAdmin();

        $enrollments = $this->filteredQuery($r)->with(['user', 'course.instructor'])->latest()->paginate(25)->withQueryString();

        return view('lms.reports', array_merge($this->filterOptions(), compact('enrollments')));
    }

    public function export(Request $r): StreamedResponse
    {
        $this->authorizeAdmin();

        $rows = $this->filteredQuery($r)->with(['user', 'course.instructor', 'certificate'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Öğrenci', 'E-posta', 'Departman', 'Eğitim', 'Eğitmen', 'Durum', 'Tamamlanma %', 'Kayıt tarihi', 'Tamamlanma tarihi', 'Sertifika no']);
            foreach ($rows as $enrollment) {
                fputcsv($out, [
                    $enrollment->user->name,
                    $enrollment->user->email,
                    $enrollment->user->department,
                    $enrollment->course->title,
                    $enrollment->course->instructor->name,
                    $enrollment->status,
                    $enrollment->completionStats()['percent'],
                    $enrollment->created_at->format('Y-m-d'),
                    $enrollment->completed_at?->format('Y-m-d'),
                    $enrollment->certificate?->certificate_number,
                ]);
            }
            fclose($out);
        }, 'kayit-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Per-course completion rate -- how each course as a whole is doing,
     * as opposed to Training's per-enrollment rows.
     */
    public function completion(Request $r)
    {
        $this->authorizeAdmin();

        $courses = $this->courseCompletionRows();

        return view('lms.reports-completion', array_merge($this->filterOptions(), compact('courses')));
    }

    public function exportCompletion(): StreamedResponse
    {
        $this->authorizeAdmin();
        $rows = $this->courseCompletionRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Eğitim', 'Eğitmen', 'Kayıt', 'Tamamlayan', 'Tamamlanma %']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['course']->title, $row['course']->instructor->name, $row['total'], $row['completed'], $row['rate']]);
            }
            fclose($out);
        }, 'tamamlanma-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Per-course average session attendance -- only meaningful for online
     * courses, since offline attendance is only ever manually marked.
     */
    public function attendance(Request $r)
    {
        $this->authorizeAdmin();

        $courses = $this->courseAttendanceRows();

        return view('lms.reports-attendance', array_merge($this->filterOptions(), compact('courses')));
    }

    public function exportAttendance(): StreamedResponse
    {
        $this->authorizeAdmin();
        $rows = $this->courseAttendanceRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Eğitim', 'Seans sayısı', 'Ortalama katılım %']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['course']->title, $row['session_count'], $row['avg_rate']]);
            }
            fclose($out);
        }, 'katilim-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Per-instructor rollup -- courses taught, total students, average
     * completion rate across all of their courses.
     */
    public function instructors(Request $r)
    {
        $this->authorizeAdmin();

        $instructors = $this->instructorRows();

        return view('lms.reports-instructors', array_merge($this->filterOptions(), compact('instructors')));
    }

    public function exportInstructors(): StreamedResponse
    {
        $this->authorizeAdmin();
        $rows = $this->instructorRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Eğitmen', 'E-posta', 'Eğitim sayısı', 'Toplam öğrenci', 'Ortalama tamamlanma %']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['instructor']->name, $row['instructor']->email, $row['course_count'], $row['student_count'], $row['avg_rate']]);
            }
            fclose($out);
        }, 'egitmen-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function courseCompletionRows()
    {
        return Course::with('instructor')
            ->withCount([
                'currentEnrollments as total',
                'currentEnrollments as completed' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderBy('title')->get()
            ->map(fn (Course $course) => [
                'course' => $course,
                'total' => $course->total,
                'completed' => $course->completed,
                'rate' => $course->total ? (int) round($course->completed / $course->total * 100) : 0,
            ]);
    }

    private function courseAttendanceRows()
    {
        return Course::where('delivery_type', 'online')->with('sessions.progress')->orderBy('title')->get()
            ->map(function (Course $course) {
                $enrolledCount = $course->currentEnrollments()->count();
                $rates = $enrolledCount ? $course->sessions->map(
                    fn (CourseSession $session) => $session->progress->whereNotNull('completed_at')->count() / $enrolledCount * 100
                ) : collect();

                return [
                    'course' => $course,
                    'session_count' => $course->sessions->count(),
                    'avg_rate' => $rates->isNotEmpty() ? (int) round($rates->avg()) : null,
                ];
            })
            ->filter(fn ($row) => $row['session_count'] > 0)
            ->values();
    }

    private function instructorRows()
    {
        return User::where('role', 'instructor')->orderBy('name')->get()
            ->map(function (User $instructor) {
                $courses = $this->courseCompletionRows()->filter(fn ($row) => $row['course']->instructor_id === $instructor->id);

                return [
                    'instructor' => $instructor,
                    'course_count' => $courses->count(),
                    'student_count' => $courses->sum('total'),
                    'avg_rate' => $courses->isNotEmpty() ? (int) round($courses->avg('rate')) : null,
                ];
            })
            ->filter(fn ($row) => $row['course_count'] > 0)
            ->values();
    }

    private function filteredQuery(Request $r)
    {
        return Enrollment::query()
            ->when($r->filled('course_id'), fn ($q) => $q->where('course_id', $r->query('course_id')))
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->filled('department'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department', $r->query('department'))))
            ->when($r->filled('instructor_id'), fn ($q) => $q->whereHas('course', fn ($c) => $c->where('instructor_id', $r->query('instructor_id'))))
            ->when($r->filled('manager_id'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('manager_id', $r->query('manager_id'))))
            // Includes every sub-unit beneath the chosen one -- filtering by
            // a division should roll up everyone in the teams under it, not
            // just people assigned to that exact row.
            ->when($r->filled('organization_unit_id'), function ($q) use ($r) {
                $unit = OrganizationUnit::find($r->query('organization_unit_id'));
                if ($unit) {
                    $q->whereHas('user', fn ($u) => $u->whereIn('organization_unit_id', $unit->descendantIds()));
                }
            })
            ->when($r->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $r->query('from')))
            ->when($r->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $r->query('to')));
    }

    private function filterOptions(): array
    {
        return [
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'departments' => User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'organizationUnits' => OrganizationUnit::orderBy('name')->get(['id', 'name']),
            'instructorList' => User::where('role', 'instructor')->orderBy('name')->get(['id', 'name']),
            'managerList' => User::where('role', 'manager')->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
}
