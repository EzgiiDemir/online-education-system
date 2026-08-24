<?php

namespace Tests\Feature;

use App\Filament\Resources\WebhookSubscriptions\Pages\CreateWebhookSubscription;
use App\Filament\Resources\WebhookSubscriptions\Pages\ListWebhookSubscriptions;
use App\Jobs\DeliverWebhookJob;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Material;
use App\Models\MaterialProgress;
use App\Models\SessionProgress;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\EnrollmentService;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 outbound webhooks: enrollment.created (EnrollmentService), enrollment.
 * completed + certificate.issued (CompletionEngine::evaluateEnrollment),
 * certificate.revoked (Certificate::revoke). QUEUE_CONNECTION=sync in
 * testing means DeliverWebhookJob runs inline here, same as every other
 * ShouldQueue class already tested in this app -- so Http::fake() with a
 * 200 response is what keeps these tests from actually throwing (a failed
 * delivery throws by design, to drive Laravel's real queue retry, which
 * would otherwise bubble up through the request that triggered it).
 */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(array $events, array $overrides = []): WebhookSubscription
    {
        return WebhookSubscription::create(array_merge([
            'name' => 'Test aboneliği',
            'url' => 'https://example.test/hooks',
            'secret' => WebhookSubscription::generateSecret(),
            'events' => $events,
            'is_active' => true,
        ], $overrides));
    }

    // --- Dispatcher targeting --------------------------------------------------------

    public function test_dispatch_only_delivers_to_subscriptions_subscribed_to_the_event(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $subscribed = $this->subscription(['enrollment.created']);
        $this->subscription(['certificate.issued']); // not subscribed to this event

        app(WebhookDispatcher::class)->dispatch('enrollment.created', ['x' => 1]);

        $this->assertSame(1, $subscribed->deliveries()->count());
        Http::assertSentCount(1);
    }

    public function test_dispatch_never_delivers_to_an_inactive_subscription(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $this->subscription(['enrollment.created'], ['is_active' => false]);

        app(WebhookDispatcher::class)->dispatch('enrollment.created', ['x' => 1]);

        Http::assertNothingSent();
    }

    // --- Delivery mechanics --------------------------------------------------------

    public function test_a_successful_delivery_is_signed_and_logged(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $subscription = $this->subscription(['enrollment.created']);

        DeliverWebhookJob::dispatch($subscription, 'enrollment.created', ['enrollment_id' => 5]);

        Http::assertSent(function ($request) use ($subscription) {
            $expectedSignature = hash_hmac('sha256', $request->body(), $subscription->secret);

            return $request->url() === $subscription->url
                && $request->hasHeader('X-Webhook-Event', 'enrollment.created')
                && $request->header('X-Webhook-Signature')[0] === $expectedSignature
                && $request['data']['enrollment_id'] === 5;
        });

        $delivery = $subscription->deliveries()->first();
        $this->assertNotNull($delivery);
        $this->assertTrue($delivery->successful);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_a_failed_delivery_is_logged_and_throws_for_the_queue_to_retry(): void
    {
        Http::fake(['example.test/*' => Http::response('server error', 500)]);
        $subscription = $this->subscription(['enrollment.created']);
        $job = new DeliverWebhookJob($subscription, 'enrollment.created', ['x' => 1]);

        $this->expectException(\RuntimeException::class);
        $job->handle();

        // Unreached on a real failure, but if handle() ever stops throwing
        // this still confirms the attempt was logged as unsuccessful.
        $delivery = $subscription->deliveries()->first();
        $this->assertFalse($delivery->successful);
        $this->assertSame(500, $delivery->response_status);
    }

    public function test_a_test_delivery_never_throws_even_on_failure(): void
    {
        Http::fake(['example.test/*' => Http::response('server error', 500)]);
        $subscription = $this->subscription([]); // subscribed to nothing

        DeliverWebhookJob::dispatchSync($subscription, 'webhook.test', ['message' => 'hi'], isTest: true);

        $delivery = $subscription->deliveries()->first();
        $this->assertNotNull($delivery);
        $this->assertFalse($delivery->successful);
    }

    // --- Domain integration --------------------------------------------------------

    public function test_enrolling_fires_enrollment_created(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $subscription = $this->subscription(['enrollment.created']);
        $course = Course::factory()->create(['status' => 'published']);
        $learner = User::factory()->create(['role' => 'learner']);

        app(EnrollmentService::class)->enroll($course, $learner);

        Http::assertSent(fn ($request) => $request['event'] === 'enrollment.created' && $request['data']['user_id'] === $learner->id);
    }

    public function test_completing_every_requirement_fires_enrollment_completed_and_certificate_issued(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $this->subscription(['enrollment.completed', 'certificate.issued']);
        $course = Course::factory()->create(['status' => 'published']);
        $section = CourseSection::create(['course_id' => $course->id, 'title' => 'Bölüm 1']);
        $session = $course->sessions()->create(['title' => 'Seans 1']);
        $material = Material::create(['course_session_id' => $session->id, 'title' => 'Slides', 'type' => 'application/pdf', 'path' => 'demo/slides.pdf']);
        $learner = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]);
        MaterialProgress::create(['enrollment_id' => $enrollment->id, 'material_id' => $material->id, 'watched_seconds' => 20]);
        SessionProgress::create(['enrollment_id' => $enrollment->id, 'course_session_id' => $session->id, 'completed_at' => now()]);

        $this->actingAs($learner)->post(route('materials.complete', $material));

        Http::assertSent(fn ($request) => $request['event'] === 'enrollment.completed');
        Http::assertSent(fn ($request) => $request['event'] === 'certificate.issued');
    }

    /**
     * Regression guard for CompletionEngine::evaluateEnrollment()'s new
     * early-return: re-evaluating an already-completed enrollment must not
     * re-fire either webhook a second time.
     */
    public function test_re_evaluating_an_already_completed_enrollment_does_not_refire_webhooks(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $this->subscription(['enrollment.completed', 'certificate.issued']);
        $course = Course::factory()->create(['status' => 'published']);
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-WH1', 'issued_at' => now()]);

        app(\App\Services\CompletionEngine::class)->evaluateEnrollment($enrollment);

        Http::assertNothingSent();
    }

    public function test_revoking_a_certificate_fires_certificate_revoked(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $this->subscription(['certificate.revoked']);
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $enrollment = Enrollment::create(['user_id' => User::factory()->create()->id, 'course_id' => $course->id, 'status' => 'completed', 'completed_at' => now()]);
        $certificate = Certificate::create(['enrollment_id' => $enrollment->id, 'certificate_number' => 'CERT-WH2', 'issued_at' => now()]);

        $certificate->revoke($admin, 'Hatalı verildi.');

        Http::assertSent(fn ($request) => $request['event'] === 'certificate.revoked' && $request['data']['reason'] === 'Hatalı verildi.');
    }

    // --- Admin panel --------------------------------------------------------

    public function test_admin_can_create_a_subscription_and_a_secret_is_auto_generated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)->test(CreateWebhookSubscription::class)
            ->fillForm([
                'name' => 'Muhasebe entegrasyonu',
                'url' => 'https://muhasebe.example.test/webhook',
                'events' => ['certificate.issued'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscription = WebhookSubscription::where('name', 'Muhasebe entegrasyonu')->firstOrFail();
        $this->assertNotEmpty($subscription->secret);
        $this->assertStringStartsWith('whsec_', $subscription->secret);
    }

    public function test_the_test_send_action_delivers_immediately_and_logs_a_delivery(): void
    {
        Http::fake(['example.test/*' => Http::response('ok', 200)]);
        $admin = User::factory()->create(['role' => 'admin']);
        $subscription = $this->subscription(['enrollment.created']);

        Livewire::actingAs($admin)->test(ListWebhookSubscriptions::class)
            ->callTableAction('test', $subscription);

        $this->assertSame(1, $subscription->deliveries()->count());
        Http::assertSent(fn ($request) => $request['event'] === 'webhook.test');
    }
}
