<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_cannot_view_another_instructors_course(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $response = $this->actingAs($other)->get(route('instructor.courses.show', $course));

        $response->assertForbidden();
    }

    public function test_owning_instructor_can_view_their_course(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('instructor.courses.show', $course));

        $response->assertOk();
    }

    public function test_admin_can_view_any_instructors_course(): void
    {
        $owner = User::factory()->create(['role' => 'instructor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);

        $response = $this->actingAs($admin)->get(route('instructor.courses.show', $course));

        $response->assertOk();
    }

    public function test_certificate_download_is_restricted_to_its_owner(): void
    {
        $owner = User::factory()->create(['role' => 'learner']);
        $intruder = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => $owner->id, 'course_id' => $course->id, 'status' => 'completed']);
        $certificate = Certificate::create([
            'enrollment_id' => $enrollment->id,
            'certificate_number' => 'CERT-TEST123',
            'issued_at' => now(),
        ]);

        $response = $this->actingAs($intruder)->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }
}
