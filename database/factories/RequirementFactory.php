<?php

namespace Database\Factories;

use App\Enums\RequirementStatus;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
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
            'title' => fake()->sentence(4),
            'acceptance' => fake()->paragraph(),
            'status' => fake()->randomElement(RequirementStatus::cases()),
        ];
    }
}
