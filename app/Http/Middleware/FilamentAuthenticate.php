<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as Middleware;

/**
 * Filament's own Authenticate resolves the panel guard (admin) and, for a
 * user who is signed in but may not enter, aborts 403 via canAccessPanel().
 * It only falls back to a login redirect when the panel guard is empty --
 * which, since the admin panel moved onto its own guard, is also what a
 * signed-in learner or manager hits. This restores the 403 for them while
 * leaving the redirect in place for genuine guests.
 *
 * @see Authenticate for the portal-side counterpart.
 */
class FilamentAuthenticate extends Middleware
{
    protected function unauthenticated($request, array $guards)
    {
        foreach (SyncPanelGuards::GUARDS as $guard) {
            if ($this->auth->guard($guard)->check()) {
                abort(403);
            }
        }

        parent::unauthenticated($request, $guards);
    }
}
