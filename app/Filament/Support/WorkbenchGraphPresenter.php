<?php

namespace App\Filament\Support;

use App\Data\Workbench\WorkbenchTreeNode;
use App\Enums\FeatureStatus;
use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\RequestReplyEdgeKind;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\ModelFields\ModelFieldResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use App\Filament\Resources\RequestReplies\RequestReplyResource;
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
use App\Models\RequestReply;
use App\Models\RequestReplyEdge;
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
        'feature' => '功能',
        'request_reply' => '入口',
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
            $record instanceof Feature, $record instanceof RequestReply, $record instanceof Test => $record->title,
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
            $record instanceof RequestReply => implode("\n", array_filter([
                $record->label(),
                "触发：{$record->trigger->getLabel()}",
                $record->module === null ? null : "模块：{$record->module->name}",
            ])),
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
            $record instanceof RequestReply => RequestReplyResource::class,
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
                    WorkbenchTreeNode::folder($key, 'scenarios', '场景', 'heroicon-m-play', $this->scenarioNodes($useCase)),
                    WorkbenchTreeNode::folder($key, 'features', '功能', 'heroicon-m-bolt', $this->featureNodes($useCase->features, $this->entryNumbers($useCase))),
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
     * ① ② ③… by the use case's entry order, so a scenario path reads as ③→④→⑤.
     *
     * @return array<int, string>
     */
    private function entryNumbers(UseCase $useCase): array
    {
        $numbers = [];

        foreach ($useCase->requestReplies->values() as $index => $requestReply) {
            $numbers[$requestReply->id] = $index < 20 ? mb_chr(0x2460 + $index) : '('.($index + 1).')';
        }

        return $numbers;
    }

    /**
     * Each scenario with its path, a failed step marked ✗: ③→④✗→③→④→⑤.
     *
     * @return list<WorkbenchTreeNode>
     */
    private function scenarioNodes(UseCase $useCase): array
    {
        $numbers = $this->entryNumbers($useCase);
        $failureEdges = $useCase->requestReplies
            ->flatMap(fn (RequestReply $requestReply) => $requestReply->outgoingEdges)
            ->filter(fn (RequestReplyEdge $edge): bool => $edge->kind === RequestReplyEdgeKind::OnFailure)
            ->mapWithKeys(fn (RequestReplyEdge $edge): array => ["{$edge->from_request_reply_id}>{$edge->to_request_reply_id}" => true])
            ->all();
        $nodes = [];

        foreach ($useCase->scenarios as $scenario) {
            $ids = $scenario->steps->pluck('request_reply_id')->values()->all();
            $path = [];

            foreach ($ids as $index => $id) {
                $failed = isset($ids[$index + 1], $failureEdges["{$id}>{$ids[$index + 1]}"]);
                $path[] = ($numbers[$id] ?? '?').($failed ? '✗' : '');
            }

            $nodes[] = new WorkbenchTreeNode(
                key: "scenario:{$scenario->id}",
                label: $path === [] ? $scenario->name : "{$scenario->name}  ".implode('→', $path),
                icon: 'heroicon-m-play',
                tone: $this->tone($scenario),
            );
        }

        return $nodes;
    }

    /**
     * @param  iterable<Feature>  $features
     * @param  array<int, string>  $entryNumbers  the use case's ①②③, so entry rows match scenario paths
     * @return list<WorkbenchTreeNode>
     */
    private function featureNodes(iterable $features, array $entryNumbers = []): array
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
                    WorkbenchTreeNode::folder($key, 'entries', '入口', 'heroicon-m-arrow-right-circle', array_values($feature->requestReplies->map(
                        fn (RequestReply $requestReply): WorkbenchTreeNode => $this->leaf($requestReply, ltrim(($entryNumbers[$requestReply->id] ?? '').' '.$requestReply->label()), 'heroicon-m-arrow-right-circle'),
                    )->all())),
                    WorkbenchTreeNode::folder($key, 'tests', '测试', 'heroicon-m-beaker', $this->leaves($feature->tests, 'heroicon-m-beaker')),
                    WorkbenchTreeNode::folder($key, 'commits', 'Commits', 'heroicon-m-code-bracket', $this->leaves($feature->commits, 'heroicon-m-code-bracket')),
                ])),
            );
        }

        return $nodes;
    }

    /**
     * The entry's call tree flattened into numbered steps: depth-first from the roots along
     * calls/on_success; an on_failure child comes right after its parent, one level deeper.
     *
     * @return list<array{node: ImplementationNode, depth: int, failureCondition: string|null}>
     */
    public function callSteps(RequestReply $requestReply): array
    {
        $nodesById = $requestReply->implementationNodes()->with('outgoingEdges')->orderBy('id')->get()->keyBy('id');
        $edges = $nodesById
            ->flatMap(fn (ImplementationNode $node) => $node->outgoingEdges)
            ->filter(fn (ImplementationNodeEdge $edge): bool => in_array($edge->kind, ImplementationNodeEdgeKind::callTree(), true) && $nodesById->has($edge->to_node_id));
        $calledIds = $edges->pluck('to_node_id')->flip();
        $edgesByFrom = $edges->groupBy('from_node_id')->all();
        $steps = [];
        $visited = [];

        foreach ([...$nodesById->reject(fn (ImplementationNode $node): bool => $calledIds->has($node->id))->all(), ...$nodesById->all()] as $root) {
            $this->collectSteps($root, 0, null, $nodesById->all(), $edgesByFrom, $visited, $steps);
        }

        return $steps;
    }

    /**
     * @param  array<int, ImplementationNode>  $nodesById
     * @param  array<int|string, Collection<int, ImplementationNodeEdge>>  $edgesByFrom
     * @param  array<int, true>  $visited  a node is listed once, so a cycle ends
     * @param  list<array{node: ImplementationNode, depth: int, failureCondition: string|null}>  $steps
     */
    private function collectSteps(ImplementationNode $node, int $depth, ?string $failureCondition, array $nodesById, array $edgesByFrom, array &$visited, array &$steps): void
    {
        if (isset($visited[$node->id])) {
            return;
        }

        $visited[$node->id] = true;
        $steps[] = ['node' => $node, 'depth' => $depth, 'failureCondition' => $failureCondition];
        [$failures, $calls] = collect($edgesByFrom[$node->id] ?? [])->partition(fn (ImplementationNodeEdge $edge): bool => $edge->kind === ImplementationNodeEdgeKind::OnFailure);

        foreach ($failures as $edge) {
            $this->collectSteps($nodesById[$edge->to_node_id], $depth + 1, $edge->condition ?? '失败', $nodesById, $edgesByFrom, $visited, $steps);
        }

        foreach ($calls as $edge) {
            $this->collectSteps($nodesById[$edge->to_node_id], $depth, null, $nodesById, $edgesByFrom, $visited, $steps);
        }
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
