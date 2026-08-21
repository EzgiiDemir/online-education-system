<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Compliance training classification (§26) and Skills (§25). Both are
 * derived from existing enrollment/certificate data plus a few additive
 * columns -- no due-date tracking table, no separate "earned skill" table.
 */
class ComplianceAndSkillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_show_page_flags_an_overdue_enrollment(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'category' => 'compliance', 'due_days' => 5]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('gecikmiş');
    }

    public function test_course_show_page_shows_upcoming_due_date_when_not_yet_overdue(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['status' => 'published', 'due_days' => 30]);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'created_at' => now()]);

        $response = $this->actingAs($learner)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('tamamlanmalı');
        $response->assertDontSee('gecikmiş');
    }

    public function test_a_course_completed_after_its_due_date_is_no_longer_flagged_overdue(): void
    {
        $course = Course::factory()->create(['due_days' => 5]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'created_at' => now()->subDays(10)]);

        $this->assertFalse($course->isOverdueFor($enrollment));
    }

    public function test_certificate_expiry_is_computed_from_the_courses_recertification_period(): void
    {
        $course = Course::factory()->create(['recertification_period_days' => 365]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-EXP1', 'issued_at' => now()->subDays(400)]);

        $this->assertTrue($certificate->isExpired());
    }

    public function test_certificate_without_a_recertification_period_never_expires(): void
    {
        $course = Course::factory()->create(['recertification_period_days' => null]);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-EXP2', 'issued_at' => now()->subYears(5)]);

        $this->assertFalse($certificate->isExpired());
    }

    public function test_completing_a_course_grants_its_skills_to_the_learner(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $skill = Skill::create(['name' => 'Etkili İletişim']);
        $course->skills()->attach($skill);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $earned = $learner->earnedSkills();

        $this->assertCount(1, $earned);
        $this->assertSame('Etkili İletişim', $earned->first()->name);
    }

    public function test_an_incomplete_enrollment_does_not_grant_its_courses_skills(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $skill = Skill::create(['name' => 'Proje Yönetimi']);
        $course->skills()->attach($skill);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $this->assertCount(0, $learner->earnedSkills());
    }

    public function test_my_skills_page_lists_earned_skills_with_their_source_course(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create(['title' => 'Liderlik 101']);
        $skill = Skill::create(['name' => 'Liderlik']);
        $course->skills()->attach($skill);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('skills.index'));

        $response->assertOk();
        $response->assertSee('Liderlik');
        $response->assertSee('Liderlik 101');
    }
}
