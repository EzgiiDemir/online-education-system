<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 Report Builder (§23), scoped to a single universal enrollments report
 * (learner x course x status x completion x certificate) with on-screen
 * filters and CSV export. Saved/scheduled reports are not implemented --
 * a deliberate scope reduction, not a silent gap.
 */
class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_view_the_report(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $instructor = User::factory()->create(['role' => 'instructor']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($learner)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($instructor)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.index'))->assertForbidden();
    }

    public function test_admin_sees_enrollments_in_the_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['name' => 'Rapor Testi Kullanıcı']);
        $course = Course::factory()->create(['title' => 'Rapor Testi Eğitimi']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Rapor Testi Kullanıcı');
        $response->assertSee('Rapor Testi Eğitimi');
    }

    public function test_status_filter_narrows_the_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $completedLearner = User::factory()->create(['name' => 'Tamamlayan Kullanıcı']);
        $enrolledLearner = User::factory()->create(['name' => 'Kayıtlı Kullanıcı']);
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $completedLearner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Enrollment::create(['user_id' => $enrolledLearner->id, 'course_id' => $course->id, 'status' => 'enrolled']);

        $response = $this->actingAs($admin)->get(route('reports.index', ['status' => 'completed']));

        $response->assertOk();
        $response->assertSee('Tamamlayan Kullanıcı');
        $response->assertDontSee('Kayıtlı Kullanıcı');
    }

    public function test_course_filter_narrows_the_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $courseA = Course::factory()->create(['title' => 'A Eğitimi']);
        $courseB = Course::factory()->create(['title' => 'B Eğitimi']);
        $learnerA = User::factory()->create(['name' => 'A Öğrencisi']);
        $learnerB = User::factory()->create(['name' => 'B Öğrencisi']);
        Enrollment::create(['user_id' => $learnerA->id, 'course_id' => $courseA->id]);
        Enrollment::create(['user_id' => $learnerB->id, 'course_id' => $courseB->id]);

        $response = $this->actingAs($admin)->get(route('reports.index', ['course_id' => $courseA->id]));

        $response->assertOk();
        $response->assertSee('A Öğrencisi');
        $response->assertDontSee('B Öğrencisi');
    }

    public function test_non_admin_cannot_export_the_csv(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get(route('reports.export'))->assertForbidden();
    }

    public function test_csv_export_contains_the_expected_header_and_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['name' => 'CSV Testi Kullanıcı', 'email' => 'csvtest@example.com']);
        $course = Course::factory()->create(['title' => 'CSV Testi Eğitimi']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($admin)->get(route('reports.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('CSV Testi Kullanıcı', $content);
        $this->assertStringContainsString('CSV Testi Eğitimi', $content);
        $this->assertStringContainsString('csvtest@example.com', $content);
    }

    public function test_csv_export_respects_the_same_filters_as_the_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $courseA = Course::factory()->create(['title' => 'Dahil Eğitim']);
        $courseB = Course::factory()->create(['title' => 'Hariç Eğitim']);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $courseA->id]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $courseB->id]);

        $response = $this->actingAs($admin)->get(route('reports.export', ['course_id' => $courseA->id]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Dahil Eğitim', $content);
        $this->assertStringNotContainsString('Hariç Eğitim', $content);
    }
}
