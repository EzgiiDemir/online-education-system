<?php

namespace App\Http\Middleware;

use App\Models\PortalSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $settings = Schema::hasTable('portal_settings') ? PortalSetting::current() : null;
        $allowed = $settings?->available_locales ?: ['tr', 'en'];
        $locale = session('locale', $request->cookie('locale', $settings?->default_locale ?: 'tr'));
        if (! in_array($locale, $allowed, true)) {
            $locale = 'tr';
        }
        session(['locale' => $locale]);
        app()->setLocale($locale);

        return $next($request);
    }
}
