<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P1 recording lifecycle: uploaded live-class recordings previously had no
 * retention policy and sat on disk forever. This is the expiry stamp set at
 * upload time (CourseSession::RECORDING_RETENTION_DAYS) and the scheduled
 * command that actually purges past-expiry files.
 */
class RecordingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_recording_stamps_an_expiry_date(): void
    {
        Storage::fake('public');
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $instructor->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);

        $response = $this->actingAs($instructor)->post(route('sessions.recording', $session), [
            'recording' => UploadedFile::fake()->create('class.webm', 1000, 'video/webm'),
        ]);

        $response->assertOk();
        $session->refresh();
        $this->assertNotNull($session->recording_path);
        $this->assertNotNull($session->recording_expires_at);
        $this->assertTrue($session->recording_expires_at->isSameDay(now()->addDays(CourseSession::RECORDING_RETENTION_DAYS)));
    }

    public function test_a_non_owning_instructor_cannot_upload_a_recording(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'instructor']);
        $other = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $session = $course->sessions()->create(['title' => 'Seans 1']);

        $this->actingAs($other)->post(route('sessions.recording', $session), [
            'recording' => UploadedFile::fake()->create('class.webm', 1000, 'video/webm'),
        ])->assertForbidden();
    }

    public function test_purge_command_deletes_an_expired_recording_file_and_clears_the_columns(): void
    {
        Storage::fake('public');
        $path = 'recordings/old.webm';
        Storage::disk('public')->put($path, 'fake video bytes');
        $course = Course::factory()->create();
        $session = $course->sessions()->create([
            'title' => 'Eski seans',
            'recording_path' => $path,
            'recording_expires_at' => now()->subDay(),
        ]);

        $this->artisan('app:purge-expired-recordings');

        Storage::disk('public')->assertMissing($path);
        $session->refresh();
        $this->assertNull($session->recording_path);
        $this->assertNull($session->recording_expires_at);
    }

    public function test_purge_command_leaves_a_recording_that_has_not_expired_yet(): void
    {
        Storage::fake('public');
        $path = 'recordings/fresh.webm';
        Storage::disk('public')->put($path, 'fake video bytes');
        $course = Course::factory()->create();
        $session = $course->sessions()->create([
            'title' => 'Yeni seans',
            'recording_path' => $path,
            'recording_expires_at' => now()->addDays(30),
        ]);

        $this->artisan('app:purge-expired-recordings');

        Storage::disk('public')->assertExists($path);
        $session->refresh();
        $this->assertSame($path, $session->recording_path);
        $this->assertNotNull($session->recording_expires_at);
    }

    public function test_purge_command_ignores_sessions_with_no_recording(): void
    {
        $course = Course::factory()->create();
        $session = $course->sessions()->create(['title' => 'Kayıtsız seans']);

        $this->artisan('app:purge-expired-recordings')->assertExitCode(0);

        $session->refresh();
        $this->assertNull($session->recording_path);
    }
}
