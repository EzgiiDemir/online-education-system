<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'instructor_id' => User::factory()->state(['role' => 'instructor']),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'delivery_type' => fake()->randomElement(['online', 'offline']),
            'status' => 'published',
            'certificate_enabled' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
