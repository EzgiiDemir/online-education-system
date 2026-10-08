<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

/**
 * The app runs three session guards -- web / instructor / admin, see
 * config/auth.php -- so two different people can stay signed in in one
 * browser. That makes "the guard this route wants is empty" ambiguous,
 * and the two cases need opposite answers:
 *
 *   - nobody is signed in at all        -> send them to the right login
 *     page (Faz 117's redirectGuestsTo)
 *   - somebody IS signed in, but this
 *     workspace is not theirs           -> 403, exactly as before Faz 117
 *
 * Without the split, every role check on an /instructor or /admin URL
 * degraded into a 302 back to a login form the visitor was already past:
 * learners were shown an instructor login page instead of being refused,
 * and real authorization failures were hidden behind a redirect.
 */
class Authenticate extends Middleware
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
