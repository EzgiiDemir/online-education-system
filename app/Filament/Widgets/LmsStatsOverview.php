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
        $totalEnrollments = Enrollment::count();
        $completedEnrollments = Enrollment::where('status', 'completed')->count();
        $completionRate = $totalEnrollments ? round($completedEnrollments / $totalEnrollments * 100) : 0;
        $upcomingSessions = CourseSession::where('starts_at', '>=', now())->count();

        return [
            Stat::make('Yayındaki eğitimler', Course::where('status', 'published')->count())
                ->description(Course::count().' eğitimin toplamı')
                ->color('primary'),
            Stat::make('Aktif kayıtlar', $totalEnrollments)
                ->description(User::where('role', 'learner')->count().' öğrenci')
                ->color('info'),
            Stat::make('Tamamlanma oranı', $completionRate.'%')
                ->description($completedEnrollments.' kayıt tamamlandı')
                ->color($completionRate >= 50 ? 'success' : 'warning'),
            Stat::make('Verilen sertifikalar', Certificate::count())
                ->description('Toplam üretilen sertifika sayısı')
                ->color('success'),
            Stat::make('Yaklaşan canlı seanslar', $upcomingSessions)
                ->description('Bugünden itibaren planlanan')
                ->color('warning'),
            Stat::make('Eğitmenler', User::where('role', 'instructor')->count())
                ->description(Course::whereNotNull('instructor_id')->distinct('instructor_id')->count('instructor_id').' aktif eğitmen kurs veriyor')
                ->color('gray'),
        ];
    }
}
