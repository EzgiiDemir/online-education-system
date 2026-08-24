<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistSpotAvailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * P1 course capacity / waitlist: a run with max_seats set previously had no
 * enforcement at all -- any number of learners could enroll. This is the
 * seat-limit check plus the waitlist it feeds into, and the promotion that
 * happens once an enrollment for a full run is removed.
 */
class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolling_in_a_run_with_open_seats_creates_a_normal_enrollment(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W1', 'status' => 'open', 'max_seats' => 2]);
        $learner = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($learner)->post(route('courses.enroll', $course));

        $response->assertRedirect(route('learning.index'));
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);
        $this->assertDatabaseMissing('waitlist_entries', ['user_id' => $learner->id]);
    }

    public function test_enrolling_in_a_full_run_creates_a_waitlist_entry_instead(): void
    {
        Notification::fake();
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W2', 'status' => 'open', 'max_seats' => 1]);
        $first = User::factory()->create(['role' => 'learner']);
        $second = User::factory()->create(['role' => 'learner']);
        $this->actingAs($first)->post(route('courses.enroll', $course));

        $response = $this->actingAs($second)->post(route('courses.enroll', $course));

        $response->assertRedirect(route('learning.index'));
        $this->assertDatabaseMissing('enrollments', ['user_id' => $second->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $second->id, 'course_run_id' => $run->id]);
    }

    public function test_enrolling_twice_while_already_waitlisted_does_not_duplicate_the_entry(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W3', 'status' => 'open', 'max_seats' => 1]);
        $first = User::factory()->create(['role' => 'learner']);
        $second = User::factory()->create(['role' => 'learner']);
        $this->actingAs($first)->post(route('courses.enroll', $course));

        $this->actingAs($second)->post(route('courses.enroll', $course));
        $this->actingAs($second)->post(route('courses.enroll', $course));

        $this->assertSame(1, WaitlistEntry::where('course_run_id', $run->id)->where('user_id', $second->id)->count());
    }

    public function test_an_already_enrolled_learner_re_posting_enroll_is_not_waitlisted(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W4', 'status' => 'open', 'max_seats' => 1]);
        $learner = User::factory()->create(['role' => 'learner']);
        $this->actingAs($learner)->post(route('courses.enroll', $course));

        $this->actingAs($learner)->post(route('courses.enroll', $course));

        $this->assertSame(1, Enrollment::where('course_id', $course->id)->where('user_id', $learner->id)->count());
        $this->assertDatabaseMissing('waitlist_entries', ['user_id' => $learner->id]);
    }

    public function test_deleting_an_enrollment_promotes_the_longest_waiting_entry(): void
    {
        Notification::fake();
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W5', 'status' => 'open', 'max_seats' => 1]);
        $seated = User::factory()->create(['role' => 'learner']);
        $waiting = User::factory()->create(['role' => 'learner']);
        $seatedEnrollment = Enrollment::create(['user_id' => $seated->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);
        $this->actingAs($waiting)->post(route('courses.enroll', $course));
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $waiting->id]);

        $seatedEnrollment->delete();

        $this->assertDatabaseHas('enrollments', ['user_id' => $waiting->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);
        $this->assertDatabaseMissing('waitlist_entries', ['user_id' => $waiting->id]);
        Notification::assertSentTo($waiting, WaitlistSpotAvailable::class);
    }

    public function test_promotion_is_a_noop_when_the_waitlist_is_empty(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W6', 'status' => 'open', 'max_seats' => 1]);
        $seated = User::factory()->create(['role' => 'learner']);
        $enrollment = Enrollment::create(['user_id' => $seated->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);

        $enrollment->delete();

        $this->assertSame(0, Enrollment::where('course_id', $course->id)->count());
    }

    public function test_a_run_with_no_max_seats_never_waitlists_anyone(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W7', 'status' => 'open']);
        $learners = User::factory()->count(5)->create(['role' => 'learner']);

        foreach ($learners as $learner) {
            $this->actingAs($learner)->post(route('courses.enroll', $course));
        }

        $this->assertSame(5, Enrollment::where('course_id', $course->id)->count());
        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_course_show_page_indicates_when_the_learner_is_on_the_waitlist(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $run = $course->runs()->create(['name' => 'Dönem 1', 'code' => 'RUN-W8', 'status' => 'open', 'max_seats' => 1]);
        $seated = User::factory()->create(['role' => 'learner']);
        Enrollment::create(['user_id' => $seated->id, 'course_id' => $course->id, 'course_run_id' => $run->id]);
        $waiting = User::factory()->create(['role' => 'learner']);
        WaitlistEntry::create(['course_run_id' => $run->id, 'user_id' => $waiting->id]);

        $response = $this->actingAs($waiting)->get(route('courses.show', $course));

        $response->assertOk();
        $response->assertSee('Bekleme listesindesiniz');
    }
}
