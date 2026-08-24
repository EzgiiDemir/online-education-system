<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagerController extends Controller
{
    public function dashboard()
    {
        abort_unless(Auth::user()->isManager(), 403);

        $team = Auth::user()->directReports()
            ->withCount([
                'enrollments',
                'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->get();

        // Compliance visibility follows the whole reporting chain, not just
        // direct reports -- a senior manager with sub-managers under them
        // needs to see overdue/certificate posture across their entire
        // organization, the same as someone with only ICs reporting to them.
        $directIds = $team->pluck('id');
        $allReports = Auth::user()->allReports();
        $teamIds = $allReports->pluck('id');

        $incompleteEnrollments = Enrollment::whereIn('user_id', $teamIds)
            ->where('status', '!=', 'completed')
            ->whereNull('exempted_at')
            ->with(['user', 'course'])
            ->latest()
            ->get()
            // Overdue rows surface first -- the whole point of this list is
            // for a manager to know who needs a nudge, and burying that
            // under enrollments that are still on track defeats it.
            ->sortByDesc(fn (Enrollment $enrollment) => $enrollment->course->isOverdueFor($enrollment));

        $overdueCount = $incompleteEnrollments->filter(fn (Enrollment $enrollment) => $enrollment->course->isOverdueFor($enrollment))->count();

        // "At risk" (spec §15: status buckets, not raw scores) -- not yet
        // overdue, but due inside the next 7 days.
        $dueThisWeekCount = $incompleteEnrollments->filter(function (Enrollment $enrollment) {
            $due = $enrollment->course->dueDateFor($enrollment);

            return $due && ! $enrollment->course->isOverdueFor($enrollment) && $due->isBefore(now()->addDays(7));
        })->count();

        $certificatesCount = Certificate::whereHas('enrollment', fn ($q) => $q->whereIn('user_id', $teamIds))->count();

        // Distinct from $overdueCount: a stale (recertification-due) cycle is
        // still status === 'completed', so isOverdueFor() never flags it --
        // without this, a manager has no way to see who is coasting on an
        // expired certificate.
        $recertificationDue = Enrollment::currentCycle()
            ->whereIn('user_id', $teamIds)
            ->where('status', 'completed')
            ->with(['user', 'course', 'certificate'])
            ->get()
            ->filter(fn (Enrollment $enrollment) => $enrollment->isStale());

        // "Team compliance": completion rate among the team's mandatory
        // (required/compliance category) training -- the same definition
        // Admin's org-wide ComplianceDashboard uses, just scoped to this
        // manager's reporting chain instead of the whole company.
        $mandatoryEnrollments = Enrollment::currentCycle()
            ->whereIn('user_id', $teamIds)
            ->whereHas('course', fn ($q) => $q->whereIn('category', ['required', 'compliance']))
            ->get();
        $complianceRate = $mandatoryEnrollments->isNotEmpty()
            ? (int) round($mandatoryEnrollments->where('status', 'completed')->count() / $mandatoryEnrollments->count() * 100)
            : null;

        return view('lms.manager-dashboard', compact(
            'team', 'directIds', 'allReports', 'incompleteEnrollments', 'overdueCount',
            'dueThisWeekCount', 'certificatesCount', 'recertificationDue', 'complianceRate'
        ));
    }

    /**
     * A manager-scoped slice of the same export ReportController gives
     * admins -- their own reporting chain only, never routed through the
     * admin-only ReportController (whose authorizeAdmin() deliberately
     * stays admin-only; this is a separate, narrower capability).
     */
    public function exportTeam(): StreamedResponse
    {
        abort_unless(Auth::user()->isManager(), 403);

        $teamIds = Auth::user()->allReports()->pluck('id');
        $rows = Enrollment::currentCycle()->whereIn('user_id', $teamIds)
            ->with(['user', 'course.instructor', 'certificate'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Öğrenci', 'E-posta', 'Eğitim', 'Eğitmen', 'Durum', 'Tamamlanma %', 'Kayıt tarihi', 'Tamamlanma tarihi', 'Sertifika no']);
            foreach ($rows as $enrollment) {
                fputcsv($out, [
                    $enrollment->user->name,
                    $enrollment->user->email,
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
        }, 'ekip-raporu-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
