<?php

namespace Database\Factories;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use App\Models\Project;
use App\Models\Scenario;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scenario>
 */
class ScenarioFactory extends Factory
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
            'use_case_id' => UseCase::factory(),
            'name' => fake()->sentence(3),
            'type' => ScenarioType::Happy,
            'given' => fake()->sentence(),
            'when' => fake()->sentence(),
            'then' => fake()->sentence(),
            'coverage_dimension' => fake()->word(),
            'equivalence_class' => fake()->word(),
            'boundary' => null,
            'priority' => ScenarioPriority::Normal,
            'status' => ScenarioStatus::Draft,
        ];
    }
}
