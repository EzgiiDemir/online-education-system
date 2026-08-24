<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_shows_enrollment_progress(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'title' => 'Demo Kurs']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee('Demo Kurs');
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password']);

        $response = $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'wrong-password']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $learner->id]);
    }

    /**
     * "Delete my account" anonymizes rather than hard-deletes: enrollments,
     * certificates and other completion evidence cascade from users.id, so
     * a real delete would silently destroy exactly the compliance records
     * this app exists to keep. See CertificateLifecycleTest for the same
     * "invalidate, don't erase" principle applied to certificates.
     */
    public function test_learner_can_delete_their_own_account(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password', 'email' => 'ayrilan@company.com']);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertRedirect(route('lms.home'));
        $this->assertGuest();
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id]);
        $learner->refresh();
        $this->assertNotNull($learner->deactivated_at);
        $this->assertSame('Silinmiş Kullanıcı', $learner->name);
        $this->assertNotSame('ayrilan@company.com', $learner->email);
        $this->assertNull($learner->avatar_path);
        $this->assertNull($learner->department);
    }

    public function test_the_deactivated_account_can_no_longer_log_in(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password', 'email' => 'ayrilan@company.com']);
        \App\Models\PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'local_login_enabled' => true]);
        $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response = $this->post('/login', ['email' => 'ayrilan@company.com', 'password' => 'correct-password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_new_signup_can_reuse_the_email_freed_by_account_deletion(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password', 'email' => 'ayrilan@company.com']);
        $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'correct-password']);

        $newUser = User::factory()->create(['email' => 'ayrilan@company.com']);

        $this->assertDatabaseHas('users', ['id' => $newUser->id, 'email' => 'ayrilan@company.com', 'deactivated_at' => null]);
    }

    public function test_certificates_survive_account_deletion_as_compliance_evidence(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password']);
        $course = Course::factory()->create(['status' => 'published']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed']);
        $certificate = \App\Models\Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-KEEPME1', 'issued_at' => now()]);

        $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'correct-password']);

        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'certificate_number' => 'CERT-KEEPME1']);
    }

    public function test_last_admin_cannot_delete_their_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'correct-password']);

        $response = $this->actingAs($admin)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_their_account_when_another_admin_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'correct-password']);
        User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertRedirect(route('lms.home'));
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertTrue($admin->fresh()->isDeactivated());
    }

    public function test_instructor_with_active_courses_cannot_self_delete(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'password' => 'correct-password']);
        Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $instructor->id]);
    }
}
