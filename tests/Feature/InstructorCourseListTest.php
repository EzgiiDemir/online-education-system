<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trainer's course grid used to render every course they had ever run,
 * unpaginated and unsearchable, while the participant list beside it and the
 * learner's My Learning both already filtered. At a realistic number of
 * courses that grid is unusable.
 */
class InstructorCourseListTest extends TestCase
{
    use RefreshDatabase;

    private function instructorWithCourses(int $count): User
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        for ($i = 1; $i <= $count; $i++) {
            Course::factory()->create([
                'instructor_id' => $instructor->id,
                'title' => sprintf('Kurs %02d', $i),
            ]);
        }

        return $instructor;
    }

    public function test_the_course_grid_is_paginated(): void
    {
        $instructor = $this->instructorWithCourses(18);

        $page1 = $this->actingAs($instructor)->get(route('instructor.courses.index'));
        $page1->assertOk();
        $page1->assertSee('page=2', false);

        $page2 = $this->actingAs($instructor)->get(route('instructor.courses.index', ['page' => 2]));
        $page2->assertOk();

        // 18 courses over a page size of 12 means page 2 is not empty and the
        // two pages between them show every course exactly once.
        $this->assertStringNotContainsString('Henüz eğitim planlamadınız', $page2->getContent());
    }

    public function test_searching_narrows_the_grid(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Yangın Güvenliği']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Gıda Hijyeni']);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.index', ['q' => 'yangın']));

        $response->assertOk();
        $response->assertSee('Yangın Güvenliği');
        $response->assertDontSee('Gıda Hijyeni');
    }

    public function test_search_is_case_insensitive_on_postgres(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'title' => 'Bilgi Güvenliği']);

        foreach (['BILGI', 'bilgi', 'BiLgI'] as $term) {
            $this->actingAs($instructor)
                ->get(route('instructor.courses.index', ['q' => $term]))
                ->assertSee('Bilgi Güvenliği');
        }
    }

    public function test_a_search_with_no_matches_explains_itself(): void
    {
        $instructor = $this->instructorWithCourses(2);

        $response = $this->actingAs($instructor)->get(route('instructor.courses.index', ['q' => 'zzzzzz']));

        $response->assertOk();
        $response->assertSee('Aramanızla eşleşen eğitim yok.');
    }

    public function test_a_trainer_only_ever_sees_their_own_courses(): void
    {
        $mine = $this->instructorWithCourses(2);
        $other = User::factory()->create(['role' => 'instructor']);
        Course::factory()->create(['instructor_id' => $other->id, 'title' => 'Başkasının Kursu']);

        $this->actingAs($mine)
            ->get(route('instructor.courses.index'))
            ->assertDontSee('Başkasının Kursu');
    }
}
