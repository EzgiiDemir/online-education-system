<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_unavailable_when_disabled(): void
    {
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'self_registration_enabled' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'A', 'email' => 'a@company.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'a@company.com']);
    }

    public function test_registration_rejects_email_outside_accepted_domain(): void
    {
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'self_registration_enabled' => true]);

        $response = $this->post('/register', ['name' => 'A', 'email' => 'a@outsider.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'a@outsider.com']);
    }

    public function test_registration_creates_learner_and_logs_in(): void
    {
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'self_registration_enabled' => true]);

        $response = $this->post('/register', ['name' => 'Yeni Kullanıcı', 'email' => 'yeni@company.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);

        $response->assertRedirect(route('lms.home'));
        $this->assertAuthenticated();
        $user = User::where('email', 'yeni@company.com')->firstOrFail();
        $this->assertSame('learner', $user->role);
    }
}
