<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseSession;
use App\Models\DemoSeedRecord;
use App\Models\Enrollment;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Removes everything DemoFakeDataSeeder created, and only that -- every row
 * it makes is recorded in demo_seed_records first (see DemoSeedTracker), so
 * this never has to guess "is this real data or showcase data" by naming
 * convention. Deletion order is children-first as a defensive measure; most
 * of these tables already cascade from courses/users via FK, but a few
 * (courses.instructor_id) are RESTRICT, so courses must go before the
 * instructor users that own them.
 */
#[Signature('demo:clear {--force : Skip the confirmation prompt}')]
#[Description('Remove all fake showcase data created by DemoFakeDataSeeder')]
class DemoClear extends Command
{
    private const DELETE_ORDER = [
        DatabaseNotification::class,
        AssignmentSubmission::class,
        Certificate::class,
        Enrollment::class,
        QuizQuestion::class,
        Activity::class,
        CourseSection::class,
        CourseSession::class,
        Course::class,
        User::class,
    ];

    public function handle(): int
    {
        $total = DemoSeedRecord::count();

        if ($total === 0) {
            $this->info('Kaldırılacak gösterim (demo) verisi yok.');

            return self::SUCCESS;
        }

        $this->info("{$total} kayıt izleniyor.");

        if (! $this->option('force') && ! $this->confirm('Tüm gösterim verisi silinsin mi? Bu işlem geri alınamaz.')) {
            $this->warn('Vazgeçildi.');

            return self::SUCCESS;
        }

        foreach (self::DELETE_ORDER as $modelType) {
            $ids = DemoSeedRecord::where('model_type', $modelType)->pluck('model_id');
            if ($ids->isEmpty()) {
                continue;
            }
            $deleted = $modelType::whereIn((new $modelType)->getKeyName(), $ids)->delete();
            $this->line("{$modelType}: {$deleted} kayıt silindi (izlenen: {$ids->count()}).");
        }

        DemoSeedRecord::truncate();
        $this->info('Gösterim verisi temizlendi.');

        return self::SUCCESS;
    }
}
