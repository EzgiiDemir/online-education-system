<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    protected static string|BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedHeart;

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Sistem sağlığı';

    protected static ?string $title = 'Sistem sağlığı';

    protected string $view = 'filament.pages.system-health';

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

        $schedulerLastRun = Cache::get('scheduler_heartbeat_at');
        $publicDiskPath = storage_path('app/public');
        $diskFree = is_dir($publicDiskPath) ? @disk_free_space($publicDiskPath) : null;
        $diskTotal = is_dir($publicDiskPath) ? @disk_total_space($publicDiskPath) : null;

        $logPath = storage_path('logs/laravel.log');
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

    public function getFailedJobs()
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)->get();
    }

    public function retryFailedJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        Notification::make()->title('İş yeniden kuyruğa alındı')->success()->send();
    }

    public function deleteFailedJob(string $uuid): void
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        Notification::make()->title('Başarısız iş kaydı silindi')->success()->send();
    }

    public function retryAllFailedJobs(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        Notification::make()->title('Tüm başarısız işler yeniden kuyruğa alındı')->success()->send();
    }
}
