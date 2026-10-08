<?php

namespace App\Filament\Widgets;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrollment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LmsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // currentCycle(): a recertified enrollment (2+ rows for the same
        // user+course) must count once here, or the completion rate would
        // permanently drift upward every time anyone recertifies.
        $totalEnrollments = Enrollment::currentCycle()->count();
        $completedEnrollments = Enrollment::currentCycle()->where('status', 'completed')->count();
        $completionRate = $totalEnrollments ? round($completedEnrollments / $totalEnrollments * 100) : 0;
        $upcomingSessions = CourseSession::where('starts_at', '>=', now())->count();

        return [
            Stat::make(__('Yayındaki eğitimler'), Course::where('status', 'published')->count())
                ->description(Course::count().' '.__('eğitimin toplamı'))
                ->color('primary'),
            Stat::make(__('Aktif kayıtlar'), $totalEnrollments)
                ->description(User::where('role', 'learner')->count().' '.__('öğrenci'))
                ->color('info'),
            Stat::make(__('Tamamlanma oranı'), $completionRate.'%')
                ->description($completedEnrollments.' '.__('kayıt tamamlandı'))
                ->color($completionRate >= 50 ? 'success' : 'warning'),
            Stat::make(__('Verilen sertifikalar'), Certificate::count())
                ->description(__('Toplam üretilen sertifika sayısı'))
                ->color('success'),
            Stat::make(__('Yaklaşan canlı seanslar'), $upcomingSessions)
                ->description(__('Bugünden itibaren planlanan'))
                ->color('warning'),
            Stat::make(__('Eğitmenler'), User::where('role', 'instructor')->count())
                ->description(Course::whereNotNull('instructor_id')->distinct('instructor_id')->count('instructor_id').' '.__('aktif eğitmen kurs veriyor'))
                ->color('gray'),
        ];
    }
}
