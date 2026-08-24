<?php

use App\Http\Middleware\AddRequestIdToLogs;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\ScimAuthenticate;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [AddRequestIdToLogs::class]);
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(prepend: [AddRequestIdToLogs::class]);
        $middleware->alias(['scim.auth' => ScimAuthenticate::class, 'api.auth' => ApiAuthenticate::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:send-training-reminders')->dailyAt('07:00');
        $schedule->command('app:purge-expired-recordings')->dailyAt('03:00');
        // A frequent, trivial heartbeat so the admin System Health page can
        // show "is the scheduler process actually running right now" --
        // the daily reminder job alone would show a stale, misleading
        // timestamp for the other 23+ hours of the day.
        $schedule->call(fn () => \Illuminate\Support\Facades\Cache::forever('scheduler_heartbeat_at', now()))->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
