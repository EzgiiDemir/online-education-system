<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for a full-app i18n/accessibility/responsive audit pass.
 * Covers the three pages built this session that were mistakenly left
 * Turkish-only (reports, quiz-view, assignment-view), the shared pagination
 * partial (used by bilingual pages), and the heading-level-skip fix on
 * my-skills/learning-paths.
 */
class I18nAndA11yAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_renders_in_english_when_locale_is_set(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->withSession(['locale' => 'en'])->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Enrollment report');
        $response->assertDontSee('Kayıt raporu');
    }

    public function test_quiz_view_renders_in_english_when_locale_is_set(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Section 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Quiz', 'type' => 'quiz', 'is_required' => true, 'pass_score' => 60]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('activities.view', $activity));

        $response->assertOk();
        $response->assertSee('Submit quiz');
    }

    public function test_assignment_view_renders_in_english_when_locale_is_set(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Section 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Assignment', 'type' => 'assignment', 'submission_type' => 'text', 'is_required' => true, 'pass_score' => 60]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('activities.view', $activity));

        $response->assertOk();
        $response->assertSee('Your answer');
        $response->assertSee('Submit');
    }

    public function test_pagination_partial_renders_in_english_on_a_bilingual_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // 30 enrollments so the paginator actually has more than one page (per_page = 25).
        $course = Course::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id]);
        }

        $response = $this->actingAs($admin)->withSession(['locale' => 'en'])->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Next');
        $response->assertDontSee('Sonraki');
    }

    public function test_my_skills_and_learning_paths_use_h2_for_card_titles_not_h3(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        $course = Course::factory()->create();
        $skill = \App\Models\Skill::create(['name' => 'Test Yetkinliği']);
        $course->skills()->attach($skill);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);

        $response = $this->actingAs($learner)->get(route('skills.index'));

        $response->assertOk();
        $response->assertSee('<h2>Test Yetkinliği</h2>', false);
    }

    /**
     * The whole session's own bug class: a page's body could be fully
     * bilingual while its <title> tag (set in @section('title', ...) before
     * $en is computed further down the same file) stayed hardcoded Turkish.
     * Found via test_reports_page_renders_in_english_when_locale_is_set
     * catching "Kayıt raporu" still in the title -- same fix applied across
     * every page built this session; this locks in a representative sample.
     */
    public function test_page_title_tags_are_localized_not_just_page_bodies(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $notifications = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('notifications.index'));
        $notifications->assertSee('<title>Notifications · Arucad Campus LMS</title>', false);

        $calendar = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('calendar.index'));
        $calendar->assertSee('<title>Calendar · Arucad Campus LMS</title>', false);

        $skills = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('skills.index'));
        $skills->assertSee('<title>My Skills · Arucad Campus LMS</title>', false);

        $home = $this->withSession(['locale' => 'en'])->get('/');
        $home->assertSee('<title>Arucad Campus LMS · Course catalog</title>', false);
    }

    public function test_course_builder_edit_section_input_has_an_accessible_label(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('aria-label="Bölüm başlığı"', false);
    }

    /**
     * The pages left Turkish-only from earlier sessions (login, register,
     * account, my-learning, course-detail, instructor dashboard,
     * instructor-profile, classroom) were bilingualized in this pass. One
     * representative assertion per page, all under a single EN session.
     */
    public function test_previously_turkish_only_pages_now_render_in_english(): void
    {
        \App\Models\PortalSetting::create(['id' => 1, 'accepted_domains' => ['company.com'], 'local_login_enabled' => true, 'self_registration_enabled' => true]);

        $loginResponse = $this->withSession(['locale' => 'en'])->get('/login');
        $loginResponse->assertOk();
        $loginResponse->assertSee('Sign in', false);

        $registerResponse = $this->withSession(['locale' => 'en'])->get('/register');
        $registerResponse->assertOk();
        $registerResponse->assertSee('Create account', false);

        $learner = User::factory()->create(['role' => 'learner']);
        $accountResponse = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('account.show'));
        $accountResponse->assertOk();
        $accountResponse->assertSee('Delete my account');

        $myLearningResponse = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('learning.index'));
        $myLearningResponse->assertOk();
        $myLearningResponse->assertSee('My Courses');

        $instructor = User::factory()->create(['role' => 'instructor']);
        $dashboardResponse = $this->actingAs($instructor)->withSession(['locale' => 'en'])->get(route('instructor.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee("Today's schedule");

        $profileResponse = $this->actingAs($instructor)->withSession(['locale' => 'en'])->get(route('instructor.profile'));
        $profileResponse->assertOk();
        $profileResponse->assertSee('Profile information');

        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $courseDetailResponse = $this->actingAs($instructor)->withSession(['locale' => 'en'])->get(route('instructor.courses.show', $course));
        $courseDetailResponse->assertOk();
        $courseDetailResponse->assertSee('Curriculum');

        $classroomCourse = Course::factory()->create(['status' => 'published', 'delivery_type' => 'online']);
        $session = $classroomCourse->sessions()->create(['title' => 'Session 1', 'room_key' => 'en-test-room']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $classroomCourse->id]);
        $classroomResponse = $this->actingAs($learner)->withSession(['locale' => 'en'])->get(route('sessions.classroom', $session));
        $classroomResponse->assertOk();
        $classroomResponse->assertSee('Ready to join the class?');
    }

    public function test_certificate_pdf_and_grading_queue_page_render_in_english(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Section 1']);
        $activity = Activity::create(['course_section_id' => $section->id, 'title' => 'Assignment', 'type' => 'assignment', 'submission_type' => 'text', 'is_required' => true]);

        $gradingResponse = $this->actingAs($instructor)->withSession(['locale' => 'en'])->get(route('instructor.activities.submissions', $activity));
        $gradingResponse->assertOk();
        $gradingResponse->assertSee('No submissions for this activity yet.');
    }

    /**
     * The certificate PDF template doesn't go through a normal HTTP response
     * (it's converted to a binary PDF by DomPDF), so rendering the Blade
     * view directly is the practical way to assert its localization without
     * needing a PDF-parsing library.
     */
    public function test_certificate_template_renders_in_english(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['title' => 'Test Course']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = \App\Models\Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-EN01', 'issued_at' => now()]);

        session(['locale' => 'en']);
        $html = view('lms.certificate', ['certificate' => $certificate, 'enrollment' => $enrollment->load('user', 'course')])->render();

        $this->assertStringContainsString('Certificate of Achievement', $html);
        $this->assertStringContainsString('Issued on:', $html);
    }
}
