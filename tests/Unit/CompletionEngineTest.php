<?php

namespace Tests\Unit;

use App\Models\CourseSession;
use App\Services\CompletionEngine;
use Tests\TestCase;

/**
 * Locks in the exact completion thresholds now that they live in one place
 * (CompletionEngine) instead of being duplicated across LmsController's
 * material/activity/session completion methods. Uses Laravel's TestCase
 * (not a bare PHPUnit one) because constructing a CourseSession with
 * datetime-cast attributes needs the app container for date resolution,
 * even though nothing here actually touches the database.
 */
class CompletionEngineTest extends TestCase
{
    private CompletionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CompletionEngine;
    }

    public function test_required_watch_seconds_is_ninety_percent_of_a_known_duration(): void
    {
        $this->assertSame(90, $this->engine->requiredWatchSeconds(100));
    }

    public function test_required_watch_seconds_has_a_ten_second_floor(): void
    {
        $this->assertSame(10, $this->engine->requiredWatchSeconds(5));
    }

    public function test_required_watch_seconds_falls_back_to_twenty_when_duration_is_unknown(): void
    {
        $this->assertSame(20, $this->engine->requiredWatchSeconds(null));
    }

    public function test_required_session_seconds_is_ninety_percent_of_the_scheduled_window(): void
    {
        $session = new CourseSession(['starts_at' => now(), 'ends_at' => now()->addMinutes(10)]);

        $this->assertSame(540, $this->engine->requiredSessionSeconds($session));
    }

    public function test_required_session_seconds_has_a_sixty_second_floor(): void
    {
        $session = new CourseSession(['starts_at' => now(), 'ends_at' => now()->addSeconds(10)]);

        $this->assertSame(60, $this->engine->requiredSessionSeconds($session));
    }

    public function test_required_session_seconds_falls_back_to_five_minutes_without_a_schedule(): void
    {
        $session = new CourseSession;

        $this->assertSame(300, $this->engine->requiredSessionSeconds($session));
    }
}
