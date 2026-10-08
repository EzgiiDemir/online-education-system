<?php

namespace Tests\Feature;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_navbar_falls_back_to_the_bundled_logo(): void
    {
        PortalSetting::current();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(asset(PortalSetting::DEFAULT_LOGO), false);
    }

    public function test_guest_navbar_uses_the_uploaded_logo_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/custom-mark.png', 'png');
        PortalSetting::current()->update(['logo_path' => 'branding/custom-mark.png']);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('branding/custom-mark.png'), false)
            ->assertDontSee(asset(PortalSetting::DEFAULT_LOGO), false);
    }

    public function test_authenticated_sidebar_uses_the_uploaded_logo_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/custom-mark.png', 'png');
        PortalSetting::current()->update(['logo_path' => 'branding/custom-mark.png']);

        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)
            ->get(route('lms.home'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('branding/custom-mark.png'), false);
    }

    public function test_admin_panel_login_uses_the_uploaded_logo_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/custom-mark.png', 'png');
        PortalSetting::current()->update(['logo_path' => 'branding/custom-mark.png']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('branding/custom-mark.png'), false);
    }
}
