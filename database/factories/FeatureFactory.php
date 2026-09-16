<?php

namespace Database\Factories;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use App\Models\Feature;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
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
            'status' => fake()->randomElement(FeatureStatus::cases()),
            'triggers' => [fake()->randomElement(FeatureTrigger::cases())->value],
            'layer' => fake()->randomElement(FeatureLayer::cases()),
            'version' => null,
        ];
    }
}
