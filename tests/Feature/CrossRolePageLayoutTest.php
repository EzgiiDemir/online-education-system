<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 103 (item 5 of the UI/UX feedback): Account and Notifications are
 * linked from every workspace's own sidebar, but both pages unconditionally
 * extended the learner layout -- an instructor or manager clicking their
 * own sidebar's "Account" link landed inside the learner's sidebar/shell
 * instead of their own, a jarring context switch. Both pages now pick their
 * parent layout by the visitor's role.
 */
class CrossRolePageLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_instructor_sees_their_own_sidebar_on_the_account_page(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee(route('instructor.dashboard'), false);
        $response->assertDontSee(route('learning.index'), false);
    }

    public function test_a_manager_sees_their_own_sidebar_on_the_account_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee(route('manager.dashboard'), false);
        $response->assertDontSee(route('learning.index'), false);
    }

    public function test_a_learner_still_sees_the_learner_sidebar_on_the_account_page(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($learner)->get(route('account.show'));

        $response->assertOk();
        $response->assertSee(route('learning.index'), false);
    }

    public function test_an_instructor_sees_their_own_sidebar_on_the_notifications_page(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);

        $response = $this->actingAs($instructor)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee(route('instructor.dashboard'), false);
        $response->assertDontSee(route('learning.index'), false);
    }

    public function test_a_manager_sees_their_own_sidebar_on_the_notifications_page(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee(route('manager.dashboard'), false);
    }
}
