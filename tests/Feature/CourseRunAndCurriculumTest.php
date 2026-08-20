<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRunAndCurriculumTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_assigns_the_open_course_run(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Mart 2026 Dönemi', 'code' => 'RUN-2026-03', 'status' => 'open', 'starts_at' => now()]);
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->firstOrFail();
        $this->assertSame($run->id, $enrollment->course_run_id);
    }

    public function test_enrolling_without_any_run_leaves_course_run_null(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->post(route('courses.enroll', $course));

        $enrollment = Enrollment::where('user_id', $learner->id)->where('course_id', $course->id)->firstOrFail();
        $this->assertNull($enrollment->course_run_id);
    }

    public function test_instructor_course_detail_renders_sections_and_activities(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = $course->sections()->create(['title' => 'Giriş', 'sort_order' => 0]);
        $section->activities()->create(['title' => 'Tanıtım videosu', 'type' => 'video', 'sort_order' => 0, 'is_required' => true]);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Giriş');
        $response->assertSee('Tanıtım videosu');
    }
}
