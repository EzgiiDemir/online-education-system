<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Production bootstrap: db:seed is forbidden there (demo accounts with the
 * password "password"), so ecenter:create-admin is the only way the first
 * administrator gets in.
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_whose_domain_is_accepted(): void
    {
        $this->artisan('ecenter:create-admin', ['email' => 'IT@Arucad.edu.tr', '--name' => 'IT Yönetici'])
            ->expectsQuestion('Password (min. 12 characters)', 'correct-horse-battery')
            ->expectsQuestion('Repeat password', 'correct-horse-battery')
            ->assertSuccessful();

        $admin = User::where('email', 'it@arucad.edu.tr')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('IT Yönetici', $admin->name);
        $this->assertTrue(Hash::check('correct-horse-battery', $admin->password));
        $this->assertContains('arucad.edu.tr', PortalSetting::current()->accepted_domains);
        $this->assertSame(0, User::where('email', 'admin@company.com')->count());
    }

    public function test_it_promotes_an_existing_user_and_keeps_their_name(): void
    {
        $user = User::factory()->create(['email' => 'ayse@arucad.edu.tr', 'name' => 'Ayşe', 'role' => 'learner']);

        $this->artisan('ecenter:create-admin', ['email' => 'ayse@arucad.edu.tr'])
            ->expectsQuestion('Password (min. 12 characters)', 'another-long-secret')
            ->expectsQuestion('Repeat password', 'another-long-secret')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('admin', $user->role);
        $this->assertSame('Ayşe', $user->name);
    }

    public function test_it_rejects_short_or_mismatched_passwords(): void
    {
        $this->artisan('ecenter:create-admin', ['email' => 'it@arucad.edu.tr'])
            ->expectsQuestion('Password (min. 12 characters)', 'short')
            ->assertFailed();

        $this->artisan('ecenter:create-admin', ['email' => 'it@arucad.edu.tr'])
            ->expectsQuestion('Password (min. 12 characters)', 'correct-horse-battery')
            ->expectsQuestion('Repeat password', 'different-horse-battery')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
