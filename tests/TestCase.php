<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Keep the three workspace guards in sync for the same person, matching
     * SyncPanelGuards in HTTP. Tests historically called actingAs($user)
     * without a guard name; instructor/admin routes now use dedicated
     * guards so the same user must be set on each workspace they own.
     */
    public function actingAs(AuthenticatableContract $user, $guard = null)
    {
        $guard ??= config('auth.defaults.guard');
        parent::actingAs($user, $guard);

        if (! $user instanceof User) {
            return $this;
        }

        parent::actingAs($user, 'web');
        if ($user->isInstructor()) {
            parent::actingAs($user, 'instructor');
        }
        if ($user->hasRole('admin')) {
            parent::actingAs($user, 'admin');
        }

        return parent::actingAs($user, $guard);
    }
}
