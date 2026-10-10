<?php

namespace Database\Factories;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Models\Project;
use App\Models\Test;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'status' => TestStatus::Valid,
            'last_result' => fake()->randomElement(TestLastResult::cases()),
        ];
    }
}
