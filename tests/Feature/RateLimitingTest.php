<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 rate limiting: login/register/certificate-verify/search/heartbeat/
 * quiz-attempts/submissions previously had no floor at all -- see
 * AppServiceProvider::boot() for the named limiters. One test per limiter
 * is enough to prove the middleware is actually wired up (the exact
 * threshold values are a product tuning choice, not something worth
 * pinning down to the exact number everywhere).
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_login_attempts_are_throttled(): void
    {
        PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'local_login_enabled' => true]);
        User::factory()->create(['email' => 'user@company.com', 'password' => 'secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'user@company.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'user@company.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_repeated_certificate_verification_lookups_are_throttled(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('certificates.verify', 'CERT-DOES-NOT-EXIST'))->assertOk();
        }

        $this->get(route('certificates.verify', 'CERT-DOES-NOT-EXIST'))->assertStatus(429);
    }

    public function test_repeated_quiz_attempts_are_throttled(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $quiz = Activity::create(['course_section_id' => $section->id, 'title' => 'Sınav', 'type' => 'quiz', 'is_required' => true]);
        $question = $quiz->quizQuestions()->create(['question' => 'Soru', 'type' => 'single_choice', 'sort_order' => 1]);
        $question->options()->create(['label' => 'Doğru', 'is_correct' => true, 'sort_order' => 1]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $quiz), ['answers' => []]);
        }

        $this->actingAs($learner)->post(route('activities.quiz-attempts.store', $quiz), ['answers' => []])
            ->assertStatus(429);
    }
}
