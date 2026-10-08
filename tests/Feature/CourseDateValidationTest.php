<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A course or session whose end date precedes its start date used to save
 * happily: the catalogue then showed "01.12.2026 - 01.01.2026", the trainer
 * calendar placed it wrongly, and date-range reporting silently skipped it.
 */
class CourseDateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function instructor(): User
    {
        return User::factory()->create(['role' => 'instructor']);
    }

    public function test_a_course_cannot_end_before_it_starts(): void
    {
        $instructor = $this->instructor();

        $response = $this->actingAs($instructor)->post(route('instructor.courses.store'), [
            'title' => 'Ters Tarihli Eğitim',
            'delivery_type' => 'online',
            'language' => 'tr',
            'status' => 'published',
            'starts_at' => '2026-12-01T10:00',
            'ends_at' => '2026-01-01T10:00',
        ]);

        $response->assertSessionHasErrors('ends_at');
        $this->assertDatabaseMissing('courses', ['title' => 'Ters Tarihli Eğitim']);
    }

    public function test_a_course_with_a_valid_range_still_saves(): void
    {
        $instructor = $this->instructor();

        $this->actingAs($instructor)->post(route('instructor.courses.store'), [
            'title' => 'Düzgün Tarihli Eğitim',
            'delivery_type' => 'online',
            'language' => 'tr',
            'status' => 'published',
            'starts_at' => '2026-01-01T10:00',
            'ends_at' => '2026-12-01T10:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('courses', ['title' => 'Düzgün Tarihli Eğitim']);
    }

    public function test_a_session_cannot_end_before_it_starts(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Ters Saatli Seans',
            'starts_at' => '2026-11-05T14:00',
            'ends_at' => '2026-11-05T09:00',
        ]);

        $response->assertSessionHasErrors('ends_at');
        $this->assertDatabaseMissing('course_sessions', ['title' => 'Ters Saatli Seans']);
    }

    public function test_a_session_with_a_valid_range_still_saves(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);

        $this->actingAs($instructor)->post(route('instructor.sessions.store', $course), [
            'title' => 'Düzgün Saatli Seans',
            'starts_at' => '2026-11-05T09:00',
            'ends_at' => '2026-11-05T14:00',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('course_sessions', ['title' => 'Düzgün Saatli Seans']);
        $this->assertSame(1, CourseSession::where('course_id', $course->id)->count());
    }

    public function test_updating_a_course_cannot_reverse_its_dates(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'starts_at' => '2026-01-01 10:00',
            'ends_at' => '2026-12-01 10:00',
        ]);

        $this->actingAs($instructor)->post(route('instructor.courses.update', $course), [
            'title' => $course->title,
            'delivery_type' => $course->delivery_type,
            'language' => 'tr',
            'starts_at' => '2026-12-01T10:00',
            'ends_at' => '2026-01-01T10:00',
        ])->assertSessionHasErrors('ends_at');

        $this->assertSame('2026-12-01', $course->fresh()->ends_at->format('Y-m-d'));
    }
}
