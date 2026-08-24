<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;

/**
 * Same filter set as ReportController's CSV export (course/status/
 * department/organization_unit rollup/date range), as JSON instead of a
 * download -- for a reporting tool that wants to pull this on a schedule
 * rather than a person clicking "download CSV".
 */
class EnrollmentController extends Controller
{
    public function index(Request $r)
    {
        $query = Enrollment::query()
            ->when($r->filled('course_id'), fn ($q) => $q->where('course_id', $r->query('course_id')))
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->filled('department'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department', $r->query('department'))))
            ->when($r->filled('organization_unit_id'), function ($q) use ($r) {
                $unit = OrganizationUnit::find($r->query('organization_unit_id'));
                if ($unit) {
                    $q->whereHas('user', fn ($u) => $u->whereIn('organization_unit_id', $unit->descendantIds()));
                }
            })
            ->when($r->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $r->query('from')))
            ->when($r->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $r->query('to')));

        $enrollments = $query->with(['user', 'course', 'certificate'])
            ->latest()
            ->paginate(min(100, max(1, (int) $r->query('per_page', 25))))
            ->withQueryString();

        return response()->json([
            'data' => $enrollments->getCollection()->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->id,
                'user' => ['id' => $enrollment->user_id, 'name' => $enrollment->user?->name, 'email' => $enrollment->user?->email],
                'course' => ['id' => $enrollment->course_id, 'title' => $enrollment->course?->title],
                'status' => $enrollment->status,
                'completion_percent' => $enrollment->completionStats()['percent'],
                'created_at' => $enrollment->created_at?->toIso8601String(),
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
                'certificate_number' => $enrollment->certificate?->certificate_number,
            ])->all(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }
}
