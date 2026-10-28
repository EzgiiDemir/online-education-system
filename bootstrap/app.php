<?php

use App\Http\Middleware\AddRequestIdToLogs;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\ScimAuthenticate;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SyncPanelGuards;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [AddRequestIdToLogs::class]);
        $middleware->web(append: [SetLocale::class, SyncPanelGuards::class]);
        $middleware->api(prepend: [AddRequestIdToLogs::class]);
        // Behind a load balancer or reverse proxy that terminates TLS, the
        // app only learns the request was HTTPS from X-Forwarded-Proto. Without
        // trusting the proxy, every generated URL comes out as http:// --
        // including the links in notification email and the certificate QR
        // code, which would then point somewhere insecure. TRUSTED_PROXIES
        // takes a comma-separated list, or "*" when the app is reachable only
        // through the proxy; empty (the default) trusts none, which is correct
        // when the web server faces users directly.
        $proxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
            );
        }
        // SyncPanelGuards has to run BEFORE the auth middleware, not merely
        // earlier in the web group: Laravel hoists anything implementing
        // AuthenticatesRequests to its slot in the priority list, which sits
        // above every unprioritised appended middleware. Left unpinned,
        // auth:instructor ran first, found an empty guard and redirected, so
        // the pipeline short-circuited and SyncPanelGuards never got to fill
        // the guard at all -- an instructor who signed in on /login was
        // bounced off every /instructor URL until some other page happened to
        // run the backfill for them. Pinning it before the contract (not the
        // concrete class) keeps it ahead of our Authenticate subclass too.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SyncPanelGuards::class);
        $middleware->alias([
            'scim.auth' => ScimAuthenticate::class,
            'api.auth' => ApiAuthenticate::class,
            // Restores 403 (rather than a login redirect) for someone who is
            // signed in but does not own the workspace -- see Authenticate.
            'auth' => Authenticate::class,
        ]);
        // Instructor routes authenticate against a separate 'instructor'
        // guard (see config/auth.php) so an instructor login doesn't
        // displace a learner/admin session sharing the same browser --
        // an unauthenticated hit there needs its own login page too.
        $middleware->redirectGuestsTo(fn ($request) => $request->is('instructor*')
            ? route('instructor.login')
            : route('login'));
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Each ->after() records its own last-run timestamp (keyed by
        // command name) so the admin System Health page's "Scheduled Jobs"
        // table can show real per-job status instead of only the generic
        // process-is-alive heartbeat below.
        $schedule->command('app:send-training-reminders')->dailyAt('07:00')
            ->after(fn () => Cache::forever('scheduled_job_last_run:app:send-training-reminders', now()->toIso8601String()));
        $schedule->command('app:purge-expired-recordings')->dailyAt('03:00')
            ->after(fn () => Cache::forever('scheduled_job_last_run:app:purge-expired-recordings', now()->toIso8601String()));
        // Nightly database + uploads backup (deploy/backup.sh also drops local
        // copies older than BACKUP_KEEP_DAYS). Recorded only on success, so a
        // failing backup shows up as overdue on the System Health page instead
        // of silently looking fine. Linux servers only -- it is a bash script.
        $schedule->exec('bash '.escapeshellarg(base_path('deploy/backup.sh')))->dailyAt('02:30')
            ->environments(['production', 'staging'])->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup.log'))
            ->onSuccess(fn () => Cache::forever('scheduled_job_last_run:backup', now()->toIso8601String()));
        // Housekeeping so these tables do not grow forever.
        $schedule->command('queue:prune-failed --hours=720')->dailyAt('04:00')
            ->onSuccess(fn () => Cache::forever('scheduled_job_last_run:queue:prune-failed', now()->toIso8601String()));
        $schedule->command('auth:clear-resets')->dailyAt('04:10')
            ->onSuccess(fn () => Cache::forever('scheduled_job_last_run:auth:clear-resets', now()->toIso8601String()));
        // A frequent, trivial heartbeat so the admin System Health page can
        // show "is the scheduler process actually running right now" --
        // the daily reminder job alone would show a stale, misleading
        // timestamp for the other 23+ hours of the day.
        $schedule->call(fn () => Cache::forever('scheduler_heartbeat_at', now()->toIso8601String()))->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
