<?php

namespace Database\Factories;

use App\Enums\WorkflowRunStatus;
use App\Models\UseCase;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    public function forUseCase(UseCase $useCase): static
    {
        return $this->state(fn (): array => [
            'use_case_id' => $useCase->id,
            'project_id' => $useCase->project_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'use_case_id' => UseCase::factory(),
            'project_id' => fn (array $attributes): int => UseCase::withoutGlobalScopes()
                ->whereKey($attributes['use_case_id'])->firstOrFail()
                ->project_id,
            'requirement_id' => null,
            'feature_id' => null,
            'graph_version' => 'v1',
            'focus_node_run_id' => null,
            'status' => WorkflowRunStatus::Pending,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
