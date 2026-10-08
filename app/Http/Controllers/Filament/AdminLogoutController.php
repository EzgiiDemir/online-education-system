<?php

namespace App\Http\Controllers\Filament;

use App\Http\Middleware\SyncPanelGuards;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;

/**
 * Overrides Filament's own LogoutController, which calls
 * session()->invalidate() -- that flushes the WHOLE shared session,
 * silently logging out the 'web' and 'instructor' guards too. Same-user
 * workspace guards are cleared together so SyncPanelGuards cannot sign
 * them straight back in; a different person on another guard is left.
 */
class AdminLogoutController
{
    public function __invoke(): LogoutResponse
    {
        SyncPanelGuards::logoutSameUserFromAllGuards(Filament::auth()->user());
        session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
