<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The app uses three session guards (web / instructor / admin) so two
 * different people can stay signed in in one browser. The same person
 * still needs every panel they own: an instructor who signed in on
 * /login (web) must be able to open /instructor, and an admin who
 * signed in on the portal must be able to open /admin. This fills any
 * empty workspace guard for the already-authenticated user and never
 * overwrites a different person already sitting on that guard.
 */
class SyncPanelGuards
{
    /**
     * The workspace session guards, in the order they are filled. Shared
     * with App\Http\Middleware\Authenticate (and its Filament twin), which
     * has to ask the same question -- "is anybody signed in here at all?"
     * -- to tell a guest apart from someone in the wrong workspace.
     */
    public const GUARDS = ['web', 'instructor', 'admin'];

    /**
     * Sign the same person out of every workspace guard they occupy.
     * A different person on another guard is left alone.
     */
    public static function logoutSameUserFromAllGuards(?Authenticatable $user): void
    {
        if (! $user) {
            return;
        }

        $id = $user->getAuthIdentifier();
        foreach (self::GUARDS as $guard) {
            $occupied = Auth::guard($guard)->user();
            if ($occupied && $occupied->getAuthIdentifier() === $id) {
                Auth::guard($guard)->logout();
            }
        }
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('post') && ($request->routeIs('logout', 'instructor.logout') || $request->is('admin/logout'))) {
            return $next($request);
        }

        $users = [];
        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();
            if ($user instanceof User) {
                $users[$guard] = $user;
            }
        }

        foreach ($users as $user) {
            $needed = ['web'];
            if ($user->hasRole('admin')) {
                $needed[] = 'admin';
            }
            if ($user->isInstructor()) {
                $needed[] = 'instructor';
            }
            foreach (array_unique($needed) as $target) {
                if (Auth::guard($target)->check()) {
                    continue;
                }
                Auth::guard($target)->login($user);
            }
        }

        return $next($request);
    }
}
