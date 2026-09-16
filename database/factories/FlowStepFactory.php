<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowStep>
 */
class FlowStepFactory extends Factory
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
            'feature_id' => Feature::factory(),
            'path' => fake()->word(),
            'order' => fake()->numberBetween(1, 10),
            'step' => fake()->sentence(3),
        ];
    }
}
