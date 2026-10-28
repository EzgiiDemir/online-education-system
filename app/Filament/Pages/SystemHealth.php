<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesNavigation;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A single screen answering "is the stuff nothing else on this dashboard
 * shows actually working right now": is the queue worker running (jobs
 * pending vs. failed), is the scheduler alive, can we reach the database,
 * how full is the upload disk, what's today's error/audit volume. None of
 * this existed before -- a stuck queue worker (see Faz 63's Procfile) or a
 * dead scheduler previously had no visible symptom in the admin panel at
 * all, just silently-undelivered email and a reminder job that never fired.
 */
class SystemHealth extends Page
{
    use TranslatesNavigation;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Sistem sağlığı';

    protected static ?string $title = 'Sistem sağlığı';

    protected string $view = 'filament.pages.system-health';

    public function getTitle(): string|Htmlable
    {
        return __('Sistem sağlığı');
    }

    public function getMetrics(): array
    {
        $dbOk = true;
        $dbError = null;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        $schedulerLastRun = self::cachedTime('scheduler_heartbeat_at');
        $publicDiskPath = storage_path('app/public');
        $diskFree = is_dir($publicDiskPath) ? @disk_free_space($publicDiskPath) : null;
        $diskTotal = is_dir($publicDiskPath) ? @disk_total_space($publicDiskPath) : null;

        // Production logs to daily files (LOG_STACK=daily); laravel.log is
        // only written by the "single" channel, which made this always 0.
        $logPath = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (! is_file($logPath)) {
            $logPath = storage_path('logs/laravel.log');
        }
        $errorCountToday = 0;
        if (is_file($logPath)) {
            $today = now()->format('Y-m-d');
            $handle = fopen($logPath, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if (str_contains($line, $today) && str_contains($line, '.ERROR:')) {
                        $errorCountToday++;
                    }
                }
                fclose($handle);
            }
        }

        return [
            'db_ok' => $dbOk,
            'db_error' => $dbError,
            'db_driver' => config('database.connections.'.config('database.default').'.driver'),
            'queue_connection' => config('queue.default'),
            'queue_pending' => DB::table('jobs')->count(),
            'queue_failed' => DB::table('failed_jobs')->count(),
            'scheduler_last_run' => $schedulerLastRun,
            'scheduler_healthy' => $schedulerLastRun && now()->diffInMinutes($schedulerLastRun) < 5,
            'mail_mailer' => config('mail.default'),
            'disk_free_gb' => $diskFree ? round($diskFree / 1073741824, 1) : null,
            'disk_total_gb' => $diskTotal ? round($diskTotal / 1073741824, 1) : null,
            'error_count_today' => $errorCountToday,
            'audit_events_today' => DB::table('audit_logs')->whereDate('created_at', now())->count(),
        ];
    }

    /**
     * Mirrors bootstrap/app.php's ->withSchedule() definitions -- each job's
     * ->after() hook writes its own "scheduled_job_last_run:{command}" cache
     * key, so this can show real per-job status rather than only the single
     * generic scheduler heartbeat above.
     */
    public function getScheduledJobs(): array
    {
        return collect([
            ['command' => 'app:send-training-reminders', 'label' => __('Eğitim hatırlatmaları'), 'frequency' => __('Her gün 07:00'), 'overdue_after_hours' => 26],
            ['command' => 'app:purge-expired-recordings', 'label' => __('Süresi dolan kayıtları temizleme'), 'frequency' => __('Her gün 03:00'), 'overdue_after_hours' => 26],
            ['command' => 'backup', 'label' => __('Veritabanı ve dosya yedeği'), 'frequency' => __('Her gün 02:30'), 'overdue_after_hours' => 26],
            ['command' => 'queue:prune-failed', 'label' => __('Eski başarısız işleri temizleme'), 'frequency' => __('Her gün 04:00'), 'overdue_after_hours' => 26],
            ['command' => 'auth:clear-resets', 'label' => __('Süresi dolan şifre sıfırlama bağlantılarını temizleme'), 'frequency' => __('Her gün 04:10'), 'overdue_after_hours' => 26],
        ])->map(function (array $job) {
            $lastRun = self::cachedTime('scheduled_job_last_run:'.$job['command']);
            $job['last_run'] = $lastRun;
            $job['overdue'] = ! $lastRun || now()->diffInHours($lastRun) > $job['overdue_after_hours'];

            return $job;
        })->all();
    }

    /**
     * The scheduler stores these timestamps as ISO-8601 strings. They used
     * to be Carbon objects, but config/cache.php sets serializable_classes
     * to false, so on a real (database) cache store every object came back
     * as __PHP_Incomplete_Class and this page threw a 500 as soon as the
     * scheduler had run once. Anything that is not a string -- such a stale
     * object from before the fix -- reads as "never ran" instead of crashing.
     */
    private static function cachedTime(string $key): ?Carbon
    {
        $value = Cache::get($key);

        try {
            return is_string($value) ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getFailedJobs()
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)->get();
    }

    public function retryFailedJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        Notification::make()->title(__('İş yeniden kuyruğa alındı'))->success()->send();
    }

    public function deleteFailedJob(string $uuid): void
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        Notification::make()->title(__('Başarısız iş kaydı silindi'))->success()->send();
    }

    public function retryAllFailedJobs(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Notification::make()->title(__('Tüm başarısız işler yeniden kuyruğa alındı'))->success()->send();
    }
}
