<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;

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

        return view('lms.manager-dashboard', compact('team', 'directIds', 'allReports', 'incompleteEnrollments', 'overdueCount', 'certificatesCount', 'recertificationDue'));
    }
}
