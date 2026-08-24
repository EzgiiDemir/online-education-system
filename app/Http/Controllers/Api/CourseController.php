<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

/**
 * Read-only /api/v1/* endpoints for external reporting/BI integrations --
 * a small, deliberately narrow starting surface (courses + enrollments),
 * not an exhaustive resource catalog. Auth is ApiAuthenticate (a separate
 * token type from SCIM's, see ApiToken's docblock).
 */
class CourseController extends Controller
{
    public function index(Request $r)
    {
        $courses = Course::query()
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->filled('category'), fn ($q) => $q->where('category', $r->query('category')))
            ->with('instructor')
            ->orderBy('title')
            ->paginate(min(100, max(1, (int) $r->query('per_page', 25))))
            ->withQueryString();

        return response()->json([
            'data' => $courses->getCollection()->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'status' => $course->status,
                'category' => $course->category,
                'delivery_type' => $course->delivery_type,
                'instructor' => $course->instructor?->name,
                'certificate_enabled' => (bool) $course->certificate_enabled,
                'created_at' => $course->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }
}
