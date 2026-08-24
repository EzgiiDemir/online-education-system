<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemHealth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P0 observability: a stuck queue worker or a dead scheduler previously had
 * zero visible symptom anywhere in the admin panel -- see Faz 63's Procfile
 * for the underlying deployment gap this page exists to make visible.
 */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/system-health');

        $response->assertOk();
    }

    public function test_a_non_admin_cannot_view_it(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $this->actingAs($learner)->get('/admin/system-health')->assertForbidden();
    }

    public function test_pending_and_failed_job_counts_are_accurate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'created_at' => now()->timestamp, 'available_at' => now()->timestamp]);
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Notifications\\TestJob']), 'exception' => 'boom', 'failed_at' => now()]);

        $metrics = Livewire::actingAs($admin)->test(SystemHealth::class)->instance()->getMetrics();

        $this->assertSame(1, $metrics['queue_pending']);
        $this->assertSame(1, $metrics['queue_failed']);
    }

    public function test_deleting_a_failed_job_removes_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

        Livewire::actingAs($admin)->test(SystemHealth::class)->call('deleteFailedJob', $uuid);

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
    }

    /**
     * Faz 100 (spec §18: "Scheduled Jobs"): each named job in bootstrap/
     * app.php's ->withSchedule() writes its own last-run cache key via
     * ->after(), so this can show real per-job status instead of only the
     * single generic scheduler-is-alive heartbeat.
     */
    public function test_a_scheduled_job_with_no_recorded_run_is_flagged_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $jobs = Livewire::actingAs($admin)->test(SystemHealth::class)->instance()->getScheduledJobs();

        $reminderJob = collect($jobs)->firstWhere('command', 'app:send-training-reminders');
        $this->assertNotNull($reminderJob);
        $this->assertTrue($reminderJob['overdue']);
        $this->assertNull($reminderJob['last_run']);
    }

    public function test_a_recently_run_scheduled_job_is_not_flagged_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        \Illuminate\Support\Facades\Cache::forever('scheduled_job_last_run:app:send-training-reminders', now());

        $jobs = Livewire::actingAs($admin)->test(SystemHealth::class)->instance()->getScheduledJobs();

        $reminderJob = collect($jobs)->firstWhere('command', 'app:send-training-reminders');
        $this->assertFalse($reminderJob['overdue']);
    }

    public function test_responses_carry_a_correlation_id_header(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Request-Id');
    }
}
