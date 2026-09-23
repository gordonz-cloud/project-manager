<?php

namespace App\Services\Workbench;

use App\Models\Commit;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ImplementationNode;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\NodeRun;
use App\Models\Project;
use App\Models\Scenario;
use App\Models\Test;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\UseCaseSpec;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads what the workbench tree shows. Loads the whole project in one go.
 * ponytail: eager loads every use case at once; load branches on expand if a project grows past a few hundred use cases.
 */
class WorkbenchGraphService
{
    /** @var array<string, class-string<Model>> */
    public const RECORD_TYPES = [
        'use_case_group' => UseCaseGroup::class,
        'use_case' => UseCase::class,
        'use_case_spec' => UseCaseSpec::class,
        'module' => Module::class,
        'module_spec' => ModuleSpec::class,
        'data_model' => DataModel::class,
        'model_field' => ModelField::class,
        'scenario' => Scenario::class,
        'feature' => Feature::class,
        'implementation_node' => ImplementationNode::class,
        'test' => Test::class,
        'flow_step' => FlowStep::class,
        'commit' => Commit::class,
        'workflow_run' => WorkflowRun::class,
        'node_run' => NodeRun::class,
    ];

    private const FEATURE_RELATIONS = ['module', 'implementationNodes', 'tests', 'flowSteps', 'commits'];

    /**
     * @return Collection<int, UseCaseGroup>
     */
    public function useCaseGroups(Project $project): Collection
    {
        return UseCaseGroup::query()
            ->where('project_id', $project->id)
            ->with([
                'useCases' => fn ($useCases) => $useCases->orderBy('goal')->orderBy('id'),
                ...$this->useCaseTreeRelations('useCases.'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, UseCase>
     */
    public function ungroupedUseCases(Project $project): Collection
    {
        return UseCase::query()
            ->where('project_id', $project->id)
            ->whereNull('use_case_group_id')
            ->with($this->useCaseTreeRelations())
            ->orderBy('goal')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Feature>
     */
    public function featuresWithoutUseCase(Project $project): Collection
    {
        return Feature::query()
            ->where('project_id', $project->id)
            ->whereNull('use_case_id')
            ->with(self::FEATURE_RELATIONS)
            ->orderBy('number')
            ->get();
    }

    /**
     * Data models a module's features use; a model has no module of its own.
     *
     * @return array<int, Collection<int, DataModel>>
     */
    public function dataModelsByModule(Project $project): array
    {
        $features = Feature::query()
            ->where('project_id', $project->id)
            ->whereNotNull('module_id')
            ->with('dataModels.modelFields')
            ->get();
        $dataModelsByModule = [];

        foreach ($features as $feature) {
            foreach ($feature->dataModels as $dataModel) {
                $dataModelsByModule[(int) $feature->module_id][$dataModel->id] = $dataModel;
            }
        }

        return array_map(
            fn (array $dataModels): Collection => (new Collection(array_values($dataModels)))->sortBy('name')->values(),
            $dataModelsByModule,
        );
    }

    /**
     * The record a tree key points at, only when it belongs to this project.
     */
    public function record(Project $project, string $key): ?Model
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);
        $modelClass = self::RECORD_TYPES[$type] ?? null;

        if ($modelClass === null || ! ctype_digit((string) $id)) {
            return null;
        }

        $query = $modelClass::query()->whereKey((int) $id);

        if ($modelClass === NodeRun::class) {
            $query->whereHas('workflowRun', fn (Builder $run) => $run->where('project_id', $project->id));
        } else {
            $query->where('project_id', $project->id);
        }

        return $query->first();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function useCaseTreeRelations(string $prefix = ''): array
    {
        return [
            "{$prefix}spec",
            "{$prefix}modules.spec",
            "{$prefix}scenarios",
            ...array_map(fn (string $relation): string => "{$prefix}features.{$relation}", self::FEATURE_RELATIONS),
            "{$prefix}workflowRuns" => fn ($runs) => $runs->latest('id'),
            "{$prefix}workflowRuns.nodeRuns.implementationNode",
        ];
    }
}
