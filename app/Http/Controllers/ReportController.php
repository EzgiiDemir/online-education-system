<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\OrganizationUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A single, universal "enrollments" report (learner x course x status x
 * completion x certificate) covers the union of what §23 asks for
 * (completion, attendance-by-status, certificates) without building a full
 * dimension/metric combinator. Saved/scheduled reports are not implemented --
 * this is on-screen filter + CSV export only; noted as a scope reduction,
 * not silently skipped.
 */
class ReportController extends Controller
{
    public function index(Request $r)
    {
        $this->authorizeAdmin();

        $enrollments = $this->filteredQuery($r)->with(['user', 'course.instructor'])->latest()->paginate(25)->withQueryString();

        $courses = Course::orderBy('title')->get(['id', 'title']);
        $departments = \App\Models\User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department');
        $organizationUnits = OrganizationUnit::orderBy('name')->get(['id', 'name']);

        return view('lms.reports', compact('enrollments', 'courses', 'departments', 'organizationUnits'));
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

    private function filteredQuery(Request $r)
    {
        return Enrollment::query()
            ->when($r->filled('course_id'), fn ($q) => $q->where('course_id', $r->query('course_id')))
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->query('status')))
            ->when($r->filled('department'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department', $r->query('department'))))
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

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
}
