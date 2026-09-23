<?php

namespace Database\Factories;

use App\Enums\RunEventType;
use App\Models\RunEvent;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunEvent>
 */
class RunEventFactory extends Factory
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
            'node_run_id' => null,
            'event_type' => RunEventType::Entered,
            'payload' => null,
            'created_at' => now(),
        ];
    }
}
