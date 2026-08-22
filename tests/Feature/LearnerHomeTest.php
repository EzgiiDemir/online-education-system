<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Learner Home: personalized greeting, "Continue Learning", today's live
 * sessions, and required-but-incomplete training. Reuses Enrollment::completionStats()
 * (the same engine that gates certificate issuance) rather than a new calculation,
 * so these widgets can never disagree with the real completion state.
 */
class LearnerHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_marketing_hero_without_a_personalized_greeting(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Öğren, geliş,');
        $response->assertDontSee('home-greeting', false);
    }

    public function test_learner_with_nothing_pending_sees_all_caught_up_message(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertSee('Her şey güncel, harika iş çıkardınız.');
    }

    public function test_required_incomplete_training_is_listed_and_matches_completion_stats(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        Material::create(['course_session_id' => $session->id, 'title' => 'Slaytlar', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertSee('Zorunlu eğitimler');
        $response->assertSee($course->title);
        $response->assertSee('0 / '.$enrollment->completionStats()['total'].' tamamlandı');
    }

    public function test_todays_live_session_is_listed_with_a_join_link(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Bugünkü Ders', 'starts_at' => now()->startOfDay()->addHours(12), 'room_key' => 'today-room']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertSee('Bugünün canlı dersleri');
        $response->assertSee('Bugünkü Ders');
        $response->assertSee(route('sessions.classroom', $session));
    }

    public function test_a_live_session_scheduled_for_another_day_is_not_listed_as_todays(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $course->sessions()->create(['title' => 'Gelecek Hafta Dersi', 'starts_at' => now()->addWeek(), 'room_key' => 'next-week-room']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertDontSee('Bugünün canlı dersleri');
    }

    public function test_continue_learning_links_to_the_next_incomplete_material(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'İlk materyal', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get('/');

        $response->assertOk();
        $response->assertSee('Öğrenmeye devam et');
        $response->assertSee(route('materials.view', $material));
    }
}
