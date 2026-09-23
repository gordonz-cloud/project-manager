<?php

namespace Database\Factories;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use App\Models\Feature;
use App\Models\Project;
use App\Models\UseCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    public function forUseCase(UseCase $useCase): static
    {
        return $this->state(fn (): array => [
            'project_id' => $useCase->project_id,
            'use_case_id' => $useCase->id,
            'requirement_id' => $useCase->requirement_id,
        ]);
    }

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
            'status' => fake()->randomElement(FeatureStatus::cases()),
            'triggers' => [fake()->randomElement(FeatureTrigger::cases())->value],
            'layers' => [fake()->randomElement(FeatureLayer::cases())->value],
        ];
    }
}
