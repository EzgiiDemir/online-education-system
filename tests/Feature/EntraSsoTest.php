<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EntraSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function enableEntra(array $overrides = []): PortalSetting
    {
        return PortalSetting::create(array_merge([
            'id' => 1,
            'accepted_domains' => ['company.com'],
            'entra_enabled' => true,
            'entra_tenant_id' => 'test-tenant',
            'entra_client_id' => 'test-client',
            'entra_client_secret' => 'test-secret',
        ], $overrides));
    }

    public function test_redirect_is_unavailable_when_entra_disabled(): void
    {
        PortalSetting::create(['id' => 1, 'entra_enabled' => false]);

        $this->get('/auth/entra/redirect')->assertNotFound();
    }

    public function test_redirect_sends_user_to_microsoft_authorize_endpoint(): void
    {
        $this->enableEntra();

        $response = $this->get('/auth/entra/redirect');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://login.microsoftonline.com/test-tenant/oauth2/v2.0/authorize?', $location);
        $this->assertStringContainsString('client_id=test-client', $location);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $this->enableEntra();

        $response = $this->withSession(['entra_state' => 'expected'])
            ->get('/auth/entra/callback?state=tampered&code=abc');

        $response->assertStatus(419);
        $this->assertGuest();
    }

    public function test_callback_creates_and_logs_in_user_on_success(): void
    {
        $this->enableEntra();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/*' => Http::response(['mail' => 'yeni.kullanici@company.com', 'displayName' => 'Yeni Kullanıcı'], 200),
        ]);

        $response = $this->withSession(['entra_state' => 'abc'])
            ->get('/auth/entra/callback?state=abc&code=xyz');

        $response->assertRedirect(route('lms.home'));
        $this->assertAuthenticated();
        $user = User::where('email', 'yeni.kullanici@company.com')->firstOrFail();
        $this->assertSame('learner', $user->role);
    }

    public function test_callback_rejects_email_outside_accepted_domain(): void
    {
        $this->enableEntra();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/*' => Http::response(['mail' => 'someone@outsider.com', 'displayName' => 'Someone'], 200),
        ]);

        $response = $this->withSession(['entra_state' => 'abc'])
            ->get('/auth/entra/callback?state=abc&code=xyz');

        $response->assertStatus(422);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone@outsider.com']);
    }
}
