<?php

namespace Database\Factories;

use App\Enums\ImplementationNodeEdgeKind;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImplementationNodeEdge>
 */
class ImplementationNodeEdgeFactory extends Factory
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
            'from_node_id' => ImplementationNode::factory(),
            'to_node_id' => ImplementationNode::factory(),
            'kind' => ImplementationNodeEdgeKind::Forward,
            'condition' => null,
        ];
    }
}
