<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the reported "GET /materials/1, /materials/2 -> ParseError"
 * incident. The blade source itself was never actually broken; the failure came
 * from a stale compiled view cache. This test exercises the full real-world path
 * (route -> controller -> auth -> enrollment -> file-type rendering) for both
 * material types the user hit in production data: a PDF and a PPTX.
 */
class MaterialViewRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_material_view_renders_with_an_embedded_viewer(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1 - Temel Güvenlik']);
        $material = Material::create([
            'course_session_id' => $session->id,
            'title' => 'İSG Başlangıç Sunumu.pdf',
            'type' => 'application/pdf',
            'path' => 'demo/isg-baslangic-sunumu.pdf',
        ]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('materials.view', $material));

        $response->assertOk();
        $response->assertSee('İSG Başlangıç Sunumu.pdf');
        $response->assertSee('<iframe', false);
    }

    public function test_pptx_material_view_falls_back_to_a_download_link(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Canlı Ders - İletişim Temelleri']);
        $material = Material::create([
            'course_session_id' => $session->id,
            'title' => 'Atölye Slaytları.pptx',
            'type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'path' => 'demo/iletisim-atolyesi.pptx',
        ]);
        $learner = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);

        $response = $this->actingAs($learner)->get(route('materials.view', $material));

        $response->assertOk();
        $response->assertSee('Atölye Slaytları.pptx');
        $response->assertSee('Dosyayı indir / aç');
    }

    public function test_learner_not_enrolled_in_the_course_cannot_view_its_material(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Gizli.pdf', 'type' => 'application/pdf', 'path' => 'demo/gizli.pdf']);
        $outsider = User::factory()->create(['role' => 'learner']);

        $this->actingAs($outsider)->get(route('materials.view', $material))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login_when_viewing_a_material(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Doc.pdf', 'type' => 'application/pdf', 'path' => 'demo/doc.pdf']);

        $this->get(route('materials.view', $material))->assertRedirect(route('login'));
    }
}
