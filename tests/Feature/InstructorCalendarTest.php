<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 94 (spec §6): the learner-facing calendar() reads
 * Auth::user()->enrollments(), so it's useless to an instructor -- this is
 * their own teaching schedule instead, sourced from the courses they teach.
 */
class InstructorCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_from_the_instructors_own_course_appears_on_its_date(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $sessionDate = now()->addDays(5);
        $course->sessions()->create(['title' => 'Öğretim Takvimi Seansı', 'starts_at' => $sessionDate]);

        $response = $this->actingAs($instructor)->get(route('instructor.calendar', ['month' => $sessionDate->format('Y-m')]));

        $response->assertOk();
        $response->assertSee('Öğretim Takvimi Seansı');
    }

    public function test_a_session_from_a_course_taught_by_someone_else_does_not_appear(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $otherInstructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $otherInstructor->id, 'delivery_type' => 'online']);
        $sessionDate = now()->addDays(5);
        $course->sessions()->create(['title' => 'Başkasının Seansı', 'starts_at' => $sessionDate]);

        $response = $this->actingAs($instructor)->get(route('instructor.calendar', ['month' => $sessionDate->format('Y-m')]));

        $response->assertOk();
        $response->assertDontSee('Başkasının Seansı');
    }

    public function test_course_start_and_end_dates_appear(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $startsAt = now()->addDays(3);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Yeni Dönem Eğitimi', 'starts_at' => $startsAt, 'ends_at' => now()->addDays(60)]);

        $response = $this->actingAs($instructor)->get(route('instructor.calendar', ['month' => $startsAt->format('Y-m')]));

        $response->assertOk();
        $response->assertSee('Yeni Dönem Eğitimi');
    }

    public function test_a_learner_cannot_access_the_instructor_calendar(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('instructor.calendar'))->assertForbidden();
    }

    public function test_the_instructor_can_download_the_ics_file_for_their_own_session_without_being_enrolled(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'delivery_type' => 'online']);
        $session = $course->sessions()->create(['title' => 'Seans 1', 'starts_at' => now()->addDay()]);

        $response = $this->actingAs($instructor)->get(route('calendar.ics.session', $session));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
    }

    public function test_the_calendar_link_appears_in_the_instructor_navigation(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('instructor.calendar'), false);
    }
}
