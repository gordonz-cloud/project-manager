<?php

namespace App\Filament\Support;

use App\Data\Workbench\WorkbenchTreeNode;
use App\Enums\FeatureStatus;
use App\Enums\ImplementationNodeEdgeKind;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use App\Filament\Resources\ModelFields\ModelFieldResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use App\Filament\Resources\Scenarios\ScenarioResource;
use App\Filament\Resources\Tests\TestResource;
use App\Filament\Resources\UseCaseGroups\UseCaseGroupResource;
use App\Filament\Resources\UseCases\UseCaseResource;
use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use App\Models\Commit;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
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
use App\Services\Workbench\WorkbenchGraphService;
use BackedEnum;
use Filament\Resources\Resource as FilamentResource;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class WorkbenchGraphPresenter
{
    private const TYPE_LABELS = [
        'use_case_group' => 'Use Case Group',
        'use_case' => 'Use Case',
        'use_case_spec' => 'Use Case Spec',
        'module' => 'Module',
        'module_spec' => 'Module Spec',
        'data_model' => 'Data Model',
        'model_field' => 'Model Field',
        'scenario' => 'Scenario',
        'feature' => '入口',
        'implementation_node' => '调用节点',
        'test' => 'Test',
        'commit' => 'Commit',
        'workflow_run' => 'Workflow Run',
        'node_run' => 'Node Run',
    ];

    /** @var array<int, Collection<int, DataModel>> */
    private array $dataModelsByModule = [];

    public function __construct(
        private WorkbenchGraphService $workbenchGraphService,
    ) {}

    /**
     * @return list<WorkbenchTreeNode>
     */
    public function tree(Project $project): array
    {
        $this->dataModelsByModule = $this->workbenchGraphService->dataModelsByModule($project);

        $groups = $this->workbenchGraphService->useCaseGroups($project)
            ->map(fn (UseCaseGroup $group): WorkbenchTreeNode => new WorkbenchTreeNode(
                key: "use_case_group:{$group->id}",
                label: $group->name,
                icon: 'heroicon-m-folder',
                badge: (string) $group->useCases->count(),
                children: $this->useCaseNodes($group->useCases),
            ))
            ->values()
            ->all();

        return array_values(array_filter([
            ...$groups,
            WorkbenchTreeNode::folder('root', 'ungrouped', '未分组 Use Case', 'heroicon-m-folder', $this->useCaseNodes($this->workbenchGraphService->ungroupedUseCases($project))),
            WorkbenchTreeNode::folder('root', 'unassigned', '未归入 Use Case', 'heroicon-m-inbox', $this->featureNodes($this->workbenchGraphService->featuresWithoutUseCase($project))),
        ]));
    }

    public function typeLabel(Model $record): string
    {
        return self::TYPE_LABELS[$this->type($record)];
    }

    public function statusLabel(Model $record): ?string
    {
        $status = $this->status($record);

        if ($status instanceof HasLabel) {
            $label = $status->getLabel();

            return is_string($label) ? $label : null;
        }

        return $status instanceof BackedEnum ? (string) $status->value : $status;
    }

    public function title(Model $record): string
    {
        return match (true) {
            $record instanceof UseCaseGroup, $record instanceof Module, $record instanceof DataModel, $record instanceof ModelField, $record instanceof Scenario => $record->name,
            $record instanceof UseCase => $record->goal,
            $record instanceof UseCaseSpec => $record->useCase->goal,
            $record instanceof ModuleSpec => $record->module->name,
            $record instanceof Feature, $record instanceof ImplementationNode, $record instanceof Test => $record->title,
            $record instanceof Commit => $record->subject,
            $record instanceof WorkflowRun => "Run #{$record->id}",
            $record instanceof NodeRun => $record->implementationNode->title,
            default => (string) $record->getKey(),
        };
    }

    /**
     * The one block of text the detail panel shows.
     *
     * @return array{text: string|null, markdown: bool}
     */
    public function mainText(Model $record): array
    {
        $markdown = match (true) {
            $record instanceof UseCase => $record->spec?->content,
            $record instanceof UseCaseSpec, $record instanceof ModuleSpec => $record->content,
            $record instanceof Module => $record->spec?->content,
            default => null,
        };

        if ($markdown !== null) {
            return ['text' => $markdown, 'markdown' => true];
        }

        $text = match (true) {
            $record instanceof UseCase => $record->success_outcome,
            $record instanceof DataModel => $record->description ?? $record->business_purpose,
            $record instanceof ModelField => trim(($record->type ?? '').' — '.($record->description ?? ''), ' —'),
            $record instanceof Scenario => "Given {$record->given}\nWhen {$record->when}\nThen {$record->then}",
            $record instanceof Feature => $record->entry,
            $record instanceof ImplementationNode => $this->nodeText($record),
            $record instanceof Test => $record->location,
            $record instanceof Commit => $record->body ?? $record->hash,
            $record instanceof WorkflowRun => $record->feature?->title,
            $record instanceof NodeRun => json_encode($record->contract_snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: null,
            default => null,
        };

        return ['text' => filled($text) ? $text : null, 'markdown' => false];
    }

    /**
     * Where the edit slide-over saves: a spec is edited inside its use case form.
     *
     * @return array{record: Model, resource: class-string<FilamentResource>}|null
     */
    public function editTarget(Model $record): ?array
    {
        $resource = match (true) {
            $record instanceof UseCaseGroup => UseCaseGroupResource::class,
            $record instanceof UseCase, $record instanceof UseCaseSpec => UseCaseResource::class,
            $record instanceof Module => ModuleResource::class,
            $record instanceof ModuleSpec => ModuleSpecResource::class,
            $record instanceof DataModel => DataModelResource::class,
            $record instanceof ModelField => ModelFieldResource::class,
            $record instanceof Scenario => ScenarioResource::class,
            $record instanceof Feature => FeatureResource::class,
            $record instanceof ImplementationNode => ImplementationNodeResource::class,
            $record instanceof Test => TestResource::class,
            $record instanceof Commit => CommitResource::class,
            $record instanceof WorkflowRun => WorkflowRunResource::class,
            default => null,
        };

        if ($resource === null) {
            return null;
        }

        return [
            'record' => $record instanceof UseCaseSpec ? $record->useCase : $record,
            'resource' => $resource,
        ];
    }

    /**
     * @param  iterable<UseCase>  $useCases
     * @return list<WorkbenchTreeNode>
     */
    private function useCaseNodes(iterable $useCases): array
    {
        $nodes = [];

        foreach ($useCases as $useCase) {
            $key = "use_case:{$useCase->id}";
            $doneFeatures = $useCase->features->where('status', FeatureStatus::Done)->count();

            $nodes[] = new WorkbenchTreeNode(
                key: $key,
                label: $useCase->goal,
                icon: 'heroicon-m-rectangle-stack',
                tone: $this->tone($useCase),
                badge: "{$doneFeatures}/{$useCase->features->count()}",
                children: array_values(array_filter([
                    $useCase->spec === null ? null : $this->leaf($useCase->spec, 'Use Case Spec', 'heroicon-m-document-text'),
                    WorkbenchTreeNode::folder($key, 'modules', '模块', 'heroicon-m-cube', $this->moduleNodes($useCase->participatingModules()->load('spec'))),
                    WorkbenchTreeNode::folder($key, 'features', '入口', 'heroicon-m-bolt', $this->featureNodes($useCase->features, $useCase->scenarios->countBy('end_node_id')->all())),
                    WorkbenchTreeNode::folder($key, 'scenarios', '场景', 'heroicon-m-play', $this->leaves($useCase->scenarios, 'heroicon-m-play')),
                    WorkbenchTreeNode::folder($key, 'runs', '执行记录', 'heroicon-m-arrow-path', $this->workflowRunNodes($useCase->workflowRuns)),
                ])),
            );
        }

        return $nodes;
    }

    /**
     * @param  iterable<Module>  $modules
     * @return list<WorkbenchTreeNode>
     */
    private function moduleNodes(iterable $modules): array
    {
        $nodes = [];

        foreach ($modules as $module) {
            $key = "module:{$module->id}";
            $dataModels = $this->dataModelsByModule[$module->id] ?? new Collection;

            $nodes[] = new WorkbenchTreeNode(
                key: $key,
                label: $module->name,
                icon: 'heroicon-m-cube',
                children: array_values(array_filter([
                    $module->spec === null ? null : $this->leaf($module->spec, 'Module Spec', 'heroicon-m-document-text'),
                    WorkbenchTreeNode::folder($key, 'data_models', '数据模型', 'heroicon-m-circle-stack', array_values($dataModels->map(
                        fn (DataModel $model): WorkbenchTreeNode => new WorkbenchTreeNode(
                            key: "data_model:{$model->id}",
                            label: $model->name,
                            icon: 'heroicon-m-circle-stack',
                            tone: $this->tone($model),
                            children: $this->leaves($model->modelFields, 'heroicon-m-bars-3'),
                        ),
                    )->all())),
                ])),
            );
        }

        return $nodes;
    }

    /**
     * @param  iterable<Feature>  $features
     * @param  array<int|string, int>  $scenarioCountsByEndNode
     * @return list<WorkbenchTreeNode>
     */
    private function featureNodes(iterable $features, array $scenarioCountsByEndNode = []): array
    {
        $nodes = [];

        foreach ($features as $feature) {
            $key = "feature:{$feature->id}";

            $nodes[] = new WorkbenchTreeNode(
                key: $key,
                label: $feature->title,
                icon: 'heroicon-m-bolt',
                tone: $this->tone($feature),
                badge: $feature->module?->name,
                children: array_values(array_filter([
                    ...$this->callTree($feature, $scenarioCountsByEndNode),
                    WorkbenchTreeNode::folder($key, 'tests', '测试', 'heroicon-m-beaker', $this->leaves($feature->tests, 'heroicon-m-beaker')),
                    WorkbenchTreeNode::folder($key, 'commits', 'Commits', 'heroicon-m-code-bracket', $this->leaves($feature->commits, 'heroicon-m-code-bracket')),
                ])),
            );
        }

        return $nodes;
    }

    /**
     * The entry's call tree: roots are nodes no call-tree edge points at.
     *
     * @param  array<int|string, int>  $scenarioCountsByEndNode
     * @return list<WorkbenchTreeNode>
     */
    private function callTree(Feature $feature, array $scenarioCountsByEndNode): array
    {
        $nodesById = $feature->implementationNodes->keyBy('id');
        $treeEdges = $feature->implementationNodes
            ->flatMap(fn (ImplementationNode $node) => $node->outgoingEdges)
            ->filter(fn (ImplementationNodeEdge $edge): bool => in_array($edge->kind, ImplementationNodeEdgeKind::callTree(), true) && $nodesById->has($edge->to_node_id));
        $childEdgesByNode = $treeEdges->groupBy('from_node_id');
        $calledIds = $treeEdges->pluck('to_node_id')->flip();
        $branches = [];

        foreach ($nodesById as $node) {
            if (! $calledIds->has($node->id)) {
                $branches[] = $this->nodeBranch($node, false, $nodesById->all(), $childEdgesByNode->all(), $scenarioCountsByEndNode, []);
            }
        }

        return $branches;
    }

    /**
     * @param  array<int, ImplementationNode>  $nodesById
     * @param  array<int|string, \Illuminate\Support\Collection<int, ImplementationNodeEdge>>  $childEdgesByNode
     * @param  array<int|string, int>  $scenarioCountsByEndNode
     * @param  array<int, true>  $ancestorIds  guards against a cycle in hand-drawn edges
     */
    private function nodeBranch(ImplementationNode $node, bool $isFailureBranch, array $nodesById, array $childEdgesByNode, array $scenarioCountsByEndNode, array $ancestorIds): WorkbenchTreeNode
    {
        $ancestorIds[$node->id] = true;
        $children = [];

        foreach ($childEdgesByNode[$node->id] ?? [] as $edge) {
            if (! isset($ancestorIds[$edge->to_node_id])) {
                $children[] = $this->nodeBranch($nodesById[$edge->to_node_id], $edge->kind === ImplementationNodeEdgeKind::OnFailure, $nodesById, $childEdgesByNode, $scenarioCountsByEndNode, $ancestorIds);
            }
        }

        $scenarioCount = $scenarioCountsByEndNode[$node->id] ?? 0;

        return new WorkbenchTreeNode(
            key: "implementation_node:{$node->id}",
            label: $node->title,
            icon: $isFailureBranch ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-turn-down-right',
            tone: $this->tone($node),
            badge: $scenarioCount > 0 ? "{$scenarioCount} 场景" : null,
            children: $children,
            isFailureBranch: $isFailureBranch,
            hasNoScenario: $children === [] && $scenarioCount === 0,
        );
    }

    private function nodeText(ImplementationNode $node): string
    {
        $lines = array_filter([
            'file' => filled($node->file) ? $node->file.(filled($node->function) ? "::{$node->function}" : '') : null,
            'input' => filled($node->input) ? "输入：{$node->input}" : null,
            'change' => filled($node->change) ? "变化：{$node->change}" : null,
            'output' => filled($node->output) ? "输出：{$node->output}" : null,
        ]);

        return $lines === [] ? $node->contract : implode("\n", $lines);
    }

    /**
     * @param  iterable<WorkflowRun>  $runs
     * @return list<WorkbenchTreeNode>
     */
    private function workflowRunNodes(iterable $runs): array
    {
        $nodes = [];

        foreach ($runs as $run) {
            $nodes[] = new WorkbenchTreeNode(
                key: "workflow_run:{$run->id}",
                label: "Run #{$run->id}",
                icon: 'heroicon-m-arrow-path',
                tone: $this->tone($run),
                badge: $this->statusLabel($run),
                children: $this->leaves($run->nodeRuns, 'heroicon-m-play-circle'),
            );
        }

        return $nodes;
    }

    /**
     * @param  iterable<Model>  $records
     * @return list<WorkbenchTreeNode>
     */
    private function leaves(iterable $records, string $icon): array
    {
        $nodes = [];

        foreach ($records as $record) {
            $nodes[] = $this->leaf($record, $this->title($record), $icon);
        }

        return $nodes;
    }

    private function leaf(Model $record, string $label, string $icon): WorkbenchTreeNode
    {
        return new WorkbenchTreeNode(
            key: $this->type($record).':'.$record->getKey(),
            label: $label,
            icon: $icon,
            tone: $this->tone($record),
        );
    }

    private function type(Model $record): string
    {
        return (string) array_search($record::class, WorkbenchGraphService::RECORD_TYPES, true);
    }

    private function status(Model $record): BackedEnum|string|null
    {
        return match (true) {
            $record instanceof ImplementationNode => $record->state,
            $record instanceof Module => $record->spec?->status,
            $record instanceof Commit => substr($record->hash, 0, 8),
            $record instanceof UseCaseGroup => null,
            default => $record->getAttribute('status'),
        };
    }

    private function tone(Model $record): ?string
    {
        $status = $this->status($record);

        if ($status instanceof HasColor) {
            $color = $status->getColor();

            return is_string($color) ? $color : null;
        }

        return match ($status instanceof BackedEnum ? $status->value : $status) {
            'verified', 'implemented', 'accepted', 'done', 'active' => 'success',
            'ready', 'running', 'talking' => 'info',
            'waiting', 'paused', 'proposed', 'stale' => 'warning',
            'failed', 'blocked', 'void', 'obsolete' => 'danger',
            null => null,
            default => 'gray',
        };
    }
}
