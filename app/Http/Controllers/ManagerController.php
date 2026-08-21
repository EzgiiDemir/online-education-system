<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;

class ManagerController extends Controller
{
    public function dashboard()
    {
        abort_unless(Auth::user()->role === 'manager', 403);

        $team = Auth::user()->directReports()
            ->withCount([
                'enrollments',
                'enrollments as completed_enrollments_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->get();

        $teamIds = $team->pluck('id');

        $incompleteEnrollments = Enrollment::whereIn('user_id', $teamIds)
            ->where('status', '!=', 'completed')
            ->with(['user', 'course'])
            ->latest()
            ->get();

        $certificatesCount = Certificate::whereHas('enrollment', fn ($q) => $q->whereIn('user_id', $teamIds))->count();

        return view('lms.manager-dashboard', compact('team', 'incompleteEnrollments', 'certificatesCount'));
    }
}
