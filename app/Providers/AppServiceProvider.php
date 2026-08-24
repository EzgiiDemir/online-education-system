<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * None of these routes had any rate limiting before -- login/register
     * were open to unlimited credential-stuffing/spam-signup attempts, the
     * public certificate-verify page could be scraped for every certificate
     * number, and heartbeat/quiz/submission endpoints had no floor beyond
     * what the completion logic itself throttles. Limits are deliberately
     * generous for legitimate use (a heartbeat every 5s is ~12/min; the
     * limit here is 40) and keyed by user id when authenticated so one
     * learner hammering the API can't degrade service for anyone else.
     */
    public function boot(): void
    {
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email'))));

        RateLimiter::for('register', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('certificate-verify', fn ($request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('search', fn ($request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('heartbeat', fn ($request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('quiz-attempts', fn ($request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('submissions', fn ($request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
