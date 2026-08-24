<?php

namespace App\Filament\Pages;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Org-wide compliance posture for "required"/"compliance" category
 * courses: how many people are overdue, exempt, or holding an
 * expiring/expired certificate, plus a per-course completion breakdown.
 * Every number here is derived from Course::isOverdueFor()/Enrollment::
 * isExempt()/Certificate::isExpired() -- the same methods the manager
 * dashboard, reminder scheduler, and account page already use -- rather
 * than a second copy of that logic that could drift from the original.
 */
class ComplianceDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Katılımcılar';

    protected static ?string $navigationLabel = 'Uyumluluk paneli';

    protected static ?string $title = 'Uyumluluk paneli';

    protected string $view = 'filament.pages.compliance-dashboard';

    private function mandatoryEnrollments(): Collection
    {
        return Enrollment::whereHas('course', fn ($q) => $q->whereIn('category', ['required', 'compliance']))
            ->with('course')
            ->get();
    }

    public function getMetrics(): array
    {
        $enrollments = $this->mandatoryEnrollments();
        $total = $enrollments->count();
        $completed = $enrollments->where('status', 'completed')->count();
        $exempt = $enrollments->filter(fn (Enrollment $e) => $e->isExempt())->count();
        $overdue = $enrollments->filter(fn (Enrollment $e) => $e->course->isOverdueFor($e))->count();

        $certificates = Certificate::whereHas('enrollment.course', fn ($q) => $q->whereIn('category', ['required', 'compliance']))
            ->with('enrollment.course')
            ->get()
            ->filter(fn (Certificate $c) => ! $c->isRevoked());
        $expired = $certificates->filter(fn (Certificate $c) => $c->isExpired())->count();
        $expiringSoon = $certificates->filter(fn (Certificate $c) => ! $c->isExpired() && $c->expiresAt() && $c->expiresAt()->diffInDays(now()) <= 30)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'exempt' => $exempt,
            'overdue' => $overdue,
            'expired_certificates' => $expired,
            'expiring_soon_certificates' => $expiringSoon,
            'completion_rate' => $total ? (int) round($completed / $total * 100) : null,
        ];
    }

    public function getCourseBreakdown(): Collection
    {
        return Course::whereIn('category', ['required', 'compliance'])
            ->with('enrollments')
            ->orderBy('title')
            ->get()
            ->map(function (Course $course) {
                $enrollments = $course->enrollments;
                $total = $enrollments->count();
                $completed = $enrollments->where('status', 'completed')->count();

                return [
                    'course' => $course,
                    'total' => $total,
                    'completed' => $completed,
                    'overdue' => $enrollments->filter(fn (Enrollment $e) => $course->isOverdueFor($e))->count(),
                    'exempt' => $enrollments->filter(fn (Enrollment $e) => $e->isExempt())->count(),
                    'completion_rate' => $total ? (int) round($completed / $total * 100) : null,
                ];
            });
    }
}
