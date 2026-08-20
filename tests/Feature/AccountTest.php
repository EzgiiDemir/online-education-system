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

    public function test_learner_can_delete_their_own_account(): void
    {
        $learner = User::factory()->create(['role' => 'learner', 'password' => 'correct-password']);
        $course = Course::factory()->create(['status' => 'published']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->post(route('account.destroy'), ['password' => 'correct-password']);

        $response->assertRedirect(route('lms.home'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $learner->id]);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $learner->id]);
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
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
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
