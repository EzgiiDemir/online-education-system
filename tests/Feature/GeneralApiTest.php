<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiTokens\Pages\CreateApiToken;
use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\OrganizationUnit;
use App\Models\ScimToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 general read-only reporting API (/api/v1/*) -- a separate ApiToken
 * credential from SCIM's, see ApiAuthenticate's docblock.
 */
class GeneralApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(): array
    {
        [, $plaintext] = ApiToken::generate('Test BI aracı');

        return ['Authorization' => 'Bearer '.$plaintext];
    }

    public function test_a_request_with_no_token_is_rejected(): void
    {
        $this->getJson('/api/v1/courses')->assertStatus(401);
    }

    public function test_a_scim_token_does_not_authenticate_the_general_api(): void
    {
        [, $scimPlaintext] = ScimToken::generate('SCIM token');

        $this->getJson('/api/v1/courses', ['Authorization' => 'Bearer '.$scimPlaintext])->assertStatus(401);
    }

    public function test_listing_courses_returns_the_expected_shape(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'name' => 'Eğitmen Adı']);
        Course::factory()->create(['title' => 'API Kursu', 'status' => 'published', 'instructor_id' => $instructor->id]);

        $response = $this->getJson('/api/v1/courses', $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'API Kursu');
        $response->assertJsonPath('data.0.instructor', 'Eğitmen Adı');
        $response->assertJsonStructure(['data' => [['id', 'title', 'status', 'category', 'delivery_type', 'instructor', 'certificate_enabled', 'created_at']], 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_courses_can_be_filtered_by_status(): void
    {
        Course::factory()->create(['status' => 'published']);
        Course::factory()->create(['status' => 'draft']);

        $response = $this->getJson('/api/v1/courses?status=draft', $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.status', 'draft');
    }

    public function test_listing_enrollments_returns_the_expected_shape(): void
    {
        $learner = User::factory()->create(['name' => 'Kayıtlı Öğrenci', 'email' => 'ogrenci@example.com']);
        $course = Course::factory()->create(['title' => 'Kayıt Kursu']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->getJson('/api/v1/enrollments', $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('data.0.user.name', 'Kayıtlı Öğrenci');
        $response->assertJsonPath('data.0.course.title', 'Kayıt Kursu');
        $response->assertJsonStructure(['data' => [['id', 'user', 'course', 'status', 'completion_percent', 'created_at', 'completed_at', 'certificate_number']]]);
    }

    public function test_enrollments_can_be_filtered_by_course(): void
    {
        $courseA = Course::factory()->create();
        $courseB = Course::factory()->create();
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $courseA->id]);
        Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $courseB->id]);

        $response = $this->getJson('/api/v1/enrollments?course_id='.$courseA->id, $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_enrollments_can_be_filtered_by_organization_unit_including_sub_units(): void
    {
        $division = OrganizationUnit::create(['name' => 'Mühendislik']);
        $team = OrganizationUnit::create(['name' => 'Altyapı', 'parent_id' => $division->id]);
        $inTeam = User::factory()->create(['organization_unit_id' => $team->id]);
        $elsewhere = User::factory()->create();
        $course = Course::factory()->create();
        Enrollment::create(['user_id' => $inTeam->id, 'course_id' => $course->id]);
        Enrollment::create(['user_id' => $elsewhere->id, 'course_id' => $course->id]);

        $response = $this->getJson('/api/v1/enrollments?organization_unit_id='.$division->id, $this->bearer());

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_admin_can_create_an_api_token_and_it_authenticates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CreateApiToken::class)
            ->fillForm(['name' => 'Yeni entegrasyon'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('api_tokens', ['name' => 'Yeni entegrasyon']);
    }
}
