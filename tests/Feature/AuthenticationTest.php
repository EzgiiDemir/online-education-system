<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PortalSetting::create([
            'id' => 1,
            'portal_name' => 'Arucad Sürekli Öğrenme Merkezi',
            'accepted_domains' => ['company.com'],
            'local_login_enabled' => true,
        ]);
    }

    public function test_login_rejects_email_outside_accepted_domain(): void
    {
        User::factory()->create(['email' => 'user@company.com', 'password' => 'secret123']);

        $response = $this->post('/login', ['email' => 'user@outsider.com', 'password' => 'secret123']);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_login_rejects_when_local_login_disabled(): void
    {
        PortalSetting::current()->update(['local_login_enabled' => false]);
        User::factory()->create(['email' => 'user@company.com', 'password' => 'secret123']);

        $response = $this->post('/login', ['email' => 'user@company.com', 'password' => 'secret123']);

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'user@company.com', 'password' => 'secret123']);

        $response = $this->post('/login', ['email' => 'user@company.com', 'password' => 'wrong-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_learner_login_redirects_to_home(): void
    {
        User::factory()->create(['email' => 'user@company.com', 'password' => 'secret123', 'role' => 'learner']);

        $response = $this->post('/login', ['email' => 'user@company.com', 'password' => 'secret123']);

        $response->assertRedirect(route('lms.home'));
        $this->assertAuthenticated();
    }

    public function test_instructor_login_redirects_to_instructor_dashboard(): void
    {
        User::factory()->create(['email' => 'trainer@company.com', 'password' => 'secret123', 'role' => 'instructor']);

        $response = $this->post('/login', ['email' => 'trainer@company.com', 'password' => 'secret123']);

        $response->assertRedirect(route('instructor.dashboard'));
    }

    public function test_admin_login_redirects_to_admin_panel(): void
    {
        User::factory()->create(['email' => 'admin@company.com', 'password' => 'secret123', 'role' => 'admin']);

        $response = $this->post('/login', ['email' => 'admin@company.com', 'password' => 'secret123']);

        $response->assertRedirect('/admin');
    }
}
