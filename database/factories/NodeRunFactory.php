<?php

namespace Database\Factories;

use App\Enums\NodeRunMode;
use App\Enums\NodeRunStatus;
use App\Models\ImplementationNode;
use App\Models\NodeRun;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NodeRun>
 */
class NodeRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_run_id' => WorkflowRun::factory(),
            'implementation_node_id' => ImplementationNode::factory(),
            'mode' => NodeRunMode::Oneshot,
            'status' => NodeRunStatus::Pending,
            'contract_snapshot' => ['contract' => fake()->sentence()],
            'payload' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
