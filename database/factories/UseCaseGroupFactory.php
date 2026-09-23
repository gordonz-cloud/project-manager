<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\UseCaseGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UseCaseGroup>
 */
class UseCaseGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
