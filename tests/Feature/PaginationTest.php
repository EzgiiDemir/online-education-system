<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the large-list pages actually paginate with real paginate()
 * calls instead of loading every row into one page (Model::all() /
 * unbounded ->get()). Each test creates more rows than one page holds
 * and asserts the first page is capped and a working next-page link exists.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_catalog_is_paginated(): void
    {
        Course::factory()->count(14)->create(['status' => 'published']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sayfa 1 / 2');
        $this->assertSame(12, substr_count($response->getContent(), '<article class="course-card surface"'));
    }

    /**
     * Regression guard for N+1s on the catalog: an authenticated learner's
     * enrollment check ("Enrolled" badge vs. "Enroll now" button) used to run
     * one query per course card. The query count must stay flat as the number
     * of courses on the page grows, not scale linearly with it.
     */
    public function test_course_catalog_query_count_does_not_scale_with_course_count(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        \App\Models\PortalSetting::current(); // one-time row creation, done up front so it doesn't skew the query counts below
        \Illuminate\Support\Facades\DB::enableQueryLog();

        Course::factory()->count(3)->create(['status' => 'published']);
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->actingAs($learner)->get('/')->assertOk();
        $queriesFor3 = count(\Illuminate\Support\Facades\DB::getQueryLog());

        Course::factory()->count(6)->create(['status' => 'published']);
        // Re-fetch a fresh model instead of reusing $learner: a real second HTTP
        // request never carries over Eloquent's in-memory relation cache (e.g.
        // unreadNotifications) the way reusing the same PHP object across two
        // actingAs() calls in-process would, which would hide a real query. Do
        // this before flushing the log so the lookup itself isn't measured.
        $freshLearner = User::find($learner->id);
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->actingAs($freshLearner)->get('/')->assertOk();
        $queriesFor9 = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $this->assertSame($queriesFor3, $queriesFor9, 'Query count scaled with course count: N+1 regressed.');
    }

    public function test_my_learning_is_paginated(): void
    {
        $learner = User::factory()->create(['role' => 'learner']);
        Course::factory()->count(12)->create(['status' => 'published'])
            ->each(fn ($course) => Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]));

        $response = $this->actingAs($learner)->get(route('learning.index'));

        $response->assertOk();
        $response->assertSee('Sayfa 1 / 2');
    }

    public function test_course_participant_list_is_paginated(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        $course = Course::factory()->create(['status' => 'published', 'instructor_id' => $instructor->id]);
        User::factory()->count(17)->create(['role' => 'learner'])
            ->each(fn ($learner) => Enrollment::create(['user_id' => $learner->id, 'course_id' => $course->id]));

        $response = $this->actingAs($instructor)->get(route('instructor.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Katılımcılar (17)');
        $response->assertSee('Sayfa 1 / 2');

        $page2 = $this->actingAs($instructor)->get(route('instructor.courses.show', $course).'?participants_page=2');
        $page2->assertOk();
        $page2->assertSee('Sayfa 2 / 2');
    }
}
