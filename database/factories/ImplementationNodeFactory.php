<?php

namespace Database\Factories;

use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImplementationNode>
 */
class ImplementationNodeFactory extends Factory
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
            'parent_id' => null,
            'kind' => ImplementationNodeKind::Code,
            'title' => fake()->sentence(3),
            'contract' => fake()->sentence(),
            'state' => ImplementationNodeState::Proposed,
            'evidence_required' => fake()->sentence(),
        ];
    }
}
