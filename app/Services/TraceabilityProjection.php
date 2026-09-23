<?php

namespace App\Services;

use App\Models\Commit;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RunEvent;
use App\Models\Scenario;
use App\Models\Test;
use App\Models\UseCase;
use App\Models\WorkflowRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TraceabilityProjection
{
    /**
     * @return array<string, array{label: string, count: int, detail: string}>
     */
    public function projectStats(Project $project): array
    {
        return [
            'scope' => [
                'label' => 'Scope',
                'count' => $project->modules()->count(),
                'detail' => 'Modules',
            ],
            'requirements' => [
                'label' => 'Requirements',
                'count' => $project->requirements()->count(),
                'detail' => 'Requirements',
            ],
            'behavior' => [
                'label' => 'Behavior',
                'count' => UseCase::query()->where('project_id', $project->id)->count()
                    + Scenario::query()->where('project_id', $project->id)->count(),
                'detail' => 'UseCases + Scenarios',
            ],
            'solution' => [
                'label' => 'Solution',
                'count' => DataModel::query()->where('project_id', $project->id)->count()
                    + DB::table('model_fields')->where('project_id', $project->id)->count(),
                'detail' => 'Data Models + Fields',
            ],
            'delivery' => [
                'label' => 'Delivery',
                'count' => Feature::query()->where('project_id', $project->id)->count()
                    + ImplementationNode::query()->where('project_id', $project->id)->count()
                    + DB::table('implementation_node_edges')->where('project_id', $project->id)->count(),
                'detail' => 'Features + Nodes + Edges',
            ],
            'execution' => [
                'label' => 'Execution',
                'count' => WorkflowRun::query()->where('project_id', $project->id)->count()
                    + DB::table('node_runs')->whereIn('workflow_run_id', function ($query) use ($project): void {
                        $query->select('id')->from('workflow_runs')->where('project_id', $project->id);
                    })->count()
                    + DB::table('run_events')->whereIn('workflow_run_id', function ($query) use ($project): void {
                        $query->select('id')->from('workflow_runs')->where('project_id', $project->id);
                    })->count(),
                'detail' => 'Runs + Node Runs + Events',
            ],
            'evidence' => [
                'label' => 'Evidence',
                'count' => Test::query()->where('project_id', $project->id)->count()
                    + Commit::query()->where('project_id', $project->id)->count(),
                'detail' => 'Tests + Commits',
            ],
        ];
    }

    /**
     * @return list<array{label: string, count: int, tone: string}>
     */
    public function issues(Project $project): array
    {
        return [
            [
                'label' => 'Scenarios without valid tests',
                'count' => Scenario::query()
                    ->where('project_id', $project->id)
                    ->whereDoesntHave('tests', fn ($query) => $query->where('status', '有效'))
                    ->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Leaf nodes without evidence',
                'count' => ImplementationNode::query()
                    ->where('project_id', $project->id)
                    ->whereDoesntHave('children')
                    ->whereDoesntHave('commits')
                    ->whereDoesntHave('scenarios.tests', fn ($query) => $query->where('status', '有效'))
                    ->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'Blocked / waiting execution',
                'count' => WorkflowRun::query()
                    ->where('project_id', $project->id)
                    ->whereIn('status', ['waiting', 'paused', 'failed'])
                    ->count()
                    + DB::table('node_runs')
                        ->whereIn('workflow_run_id', function ($query) use ($project): void {
                            $query->select('id')->from('workflow_runs')->where('project_id', $project->id);
                        })
                        ->whereIn('status', ['blocked', 'failed'])
                        ->count(),
                'tone' => 'danger',
            ],
        ];
    }

    /**
     * @return Collection<int, RunEvent>
     */
    public function recentEvents(Project $project, Requirement $requirement, int $limit = 20): Collection
    {
        return RunEvent::query()
            ->whereHas('workflowRun', fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('requirement_id', $requirement->id))
            ->with(['workflowRun.feature', 'nodeRun.implementationNode'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
