<?php

namespace App\Filament\Pages;

use App\Data\Workbench\WorkbenchGraphState;
use App\Enums\NavigationGroup;
use App\Filament\Support\WorkbenchGraphPresenter;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Services\Workbench\WorkbenchGraphService;
use App\Support\TraceEdge;
use App\Support\TraceGraph;
use App\Support\TraceNode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use LogicException;

/**
 * @property-read Project $project
 * @property-read Collection<int, ModuleSpec> $moduleSpecOptions
 * @property-read Collection<int, Module> $moduleOptions
 * @property-read Collection<int, ModuleSpec> $scopeSpecs
 * @property-read TraceGraph $graph
 * @property-read array<string, TraceNode> $nodes
 * @property-read list<TraceEdge> $edges
 * @property-read TraceNode|null $selectedNode
 * @property-read list<TraceEdge> $incomingEdges
 * @property-read list<TraceEdge> $outgoingEdges
 * @property-read array<string, string> $blockedReasons
 * @property-read array<string, string> $gapReasons
 * @property-read array<string, true> $currentNodeKeys
 * @property-read array<string, int> $relationshipCounts
 * @property-read list<TraceNode> $visibleNodes
 * @property-read array<string, list<TraceNode>> $visibleNodesByLayer
 * @property-read list<TraceEdge> $visibleEdges
 * @property-read list<array{key: string, from: string, to: string, kind: string, tone: string}> $edgeVisuals
 * @property-read list<TraceNode> $searchMatches
 * @property-read array<string, true> $searchMatchedKeys
 * @property-read list<array{id: int, key: string, label: string, selected: bool, expanded: bool, show_all: bool, spec_count: int, specs: list<array{id: int, key: string, label: string, title: string, status: string, status_label: string, selected: bool}>}> $treeModules
 * @property-read list<array{key: string, label: string, tone: string, expanded: bool, count: int, groups: list<array{key: string, label: string, relation: string, tone: string, expanded: bool, show_all: bool, count: int, items: list<TraceNode>}>}> $treeSections
 * @property-read list<array{key: string, label: string, relation: string, tone: string, expanded: bool, show_all: bool, count: int, items: list<TraceNode>}> $treeGroups
 * @property-read array<int, list<TraceNode>> $treeScenariosByUseCase
 * @property-read array<int, list<TraceNode>> $treeFeaturesByUseCase
 * @property-read list<TraceNode> $treeUnassignedFeatures
 * @property-read array<int, list<TraceNode>> $treeImplementationNodesByFeature
 * @property-read array<int, list<TraceNode>> $treeTestsByFeature
 * @property-read array<int, list<TraceNode>> $treeCommitsByFeature
 * @property-read array<int, list<TraceNode>> $treeFlowStepsByFeature
 * @property-read array<int, list<TraceNode>> $treeModelFieldsByDataModel
 * @property-read array<int, list<TraceNode>> $treeNodeRunsByWorkflowRun
 * @property-read array<int, list<TraceNode>> $treeRunEventsByNodeRun
 * @property-read array<int, list<TraceNode>> $treeRunEventsByWorkflowRun
 * @property-read array{key: string, label: string, field: string, current: string, current_label: string, states: list<array{value: string, label: string, current: bool}>, transitions: list<array{from: string, to: string, label: string, tone: string, current: bool}>, allowed: list<array{from: string, to: string, label: string, tone: string, current: bool}>}|null $selectedStateMachine
 * @property-read list<array{label: string, time: string, tone: string}> $stateHistory
 */
class WorkbenchGraph extends Page
{
    protected string $view = 'filament.pages.workbench-graph';

    protected static ?string $slug = 'workbench';

    protected static ?string $navigationLabel = '关系工作台';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    public string $scopeType = 'spec';

    public ?int $moduleSpecId = null;

    public ?int $moduleId = null;

    public ?string $selectedKey = null;

    public string $search = '';

    public string $layerFilter = 'all';

    public bool $blockingOnly = false;

    public bool $gapsOnly = false;

    public bool $focusMode = true;

    public int $focusDepth = 2;

    public string $inspectorTab = 'overview';

    /** @var array<string, bool> */
    public array $expandedTreeSections = ['behavior' => true];

    /** @var array<int, bool> */
    public array $expandedTreeModules = [];

    /** @var array<int, bool> */
    public array $expandedTreeModulesAll = [];

    /** @var array<string, bool> */
    public array $expandedTreeGroups = [];

    /** @var array<string, bool> */
    public array $expandedTreeGroupsAll = [];

    /** @var array<int, bool> */
    public array $expandedTreeUseCases = [];

    /** @var array<int, bool> */
    public array $expandedTreeFeatures = [];

    /** @var array<int, bool> */
    public array $expandedTreeDataModels = [];

    /** @var array<int, bool> */
    public array $expandedTreeWorkflowRuns = [];

    /** @var array<int, bool> */
    public array $expandedTreeNodeRuns = [];

    private ?WorkbenchGraphService $workbenchGraphService = null;

    private ?WorkbenchGraphPresenter $workbenchGraphPresenter = null;

    public function boot(
        WorkbenchGraphService $workbenchGraphService,
        WorkbenchGraphPresenter $workbenchGraphPresenter,
    ): void {
        $this->workbenchGraphService = $workbenchGraphService;
        $this->workbenchGraphPresenter = $workbenchGraphPresenter;
    }

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public function getTitle(): string
    {
        return '关系工作台';
    }

    public function mount(): void
    {
        $spec = $this->moduleSpecOptions->first(
            fn (ModuleSpec $spec): bool => $spec->status === 'active',
        ) ?? $this->moduleSpecOptions->first();

        $this->scopeType = 'project';
        $this->moduleSpecId = $spec?->id;
        $this->selectedKey = $spec === null ? null : "module_spec:{$spec->id}";
        $this->focusMode = false;

        if ($spec !== null) {
            $this->moduleId = $spec->module_id;
            $this->expandedTreeModules[$this->moduleId] = true;
        }
    }

    public function selectProjectScope(): void
    {
        $this->scopeType = 'project';
        $this->moduleId = null;
        $this->focusMode = false;
    }

    public function startModuleScope(): void
    {
        $this->scopeType = 'module';
        $this->moduleSpecId = null;
        $this->moduleId = null;
        $this->selectedKey = null;
        $this->focusMode = false;
    }

    public function startSpecScope(): void
    {
        $spec = $this->moduleSpecOptions->first(
            fn (ModuleSpec $spec): bool => $spec->id === $this->moduleSpecId,
        ) ?? $this->moduleSpecOptions->first();

        $this->scopeType = 'spec';
        $this->moduleId = null;
        $this->moduleSpecId = $spec?->id;
        $this->selectedKey = $spec === null ? null : "module_spec:{$spec->id}";
        $this->focusMode = $spec !== null;
    }

    public function selectModuleScope(int $moduleId): void
    {
        $module = $this->workbenchGraphService()->module($this->project, $moduleId);

        abort_if($module === null, 404);

        $this->scopeType = 'module';
        $this->moduleId = $module->id;
        $this->moduleSpecId = null;
        $this->selectedKey = "module:{$module->id}";
        $this->focusMode = true;
        $this->expandedTreeModules[$module->id] = true;
    }

    public function selectSpec(int $moduleSpecId): void
    {
        $spec = $this->workbenchGraphService()->moduleSpec($this->project, $moduleSpecId);

        abort_if($spec === null, 404);

        $this->scopeType = 'spec';
        $this->moduleSpecId = $spec->id;
        $this->moduleId = $spec->module_id;
        $this->selectedKey = "module_spec:{$spec->id}";
        $this->focusMode = true;
        $this->expandedTreeModules[$this->moduleId] = true;
    }

    public function updatedModuleSpecId(): void
    {
        if ($this->moduleSpecId === null) {
            return;
        }

        $spec = $this->workbenchGraphService()->moduleSpec($this->project, $this->moduleSpecId);

        if ($spec === null) {
            $this->moduleSpecId = null;
            $this->selectedKey = null;

            return;
        }

        $this->scopeType = 'spec';
        $this->moduleId = $spec->module_id;
        $this->selectedKey = "module_spec:{$spec->id}";
        $this->focusMode = true;
    }

    public function updatedModuleId(): void
    {
        if ($this->moduleId === null) {
            return;
        }

        $module = $this->workbenchGraphService()->module($this->project, $this->moduleId);

        if ($module === null) {
            $this->moduleId = null;
            $this->selectedKey = null;

            return;
        }

        $this->scopeType = 'module';
        $this->moduleSpecId = null;
        $this->selectedKey = "module:{$module->id}";
        $this->focusMode = true;
    }

    public function selectNode(string $key): void
    {
        abort_unless(isset($this->nodes[$key]), 404);

        $this->selectedKey = $key;
        $this->focusMode = true;
        $this->inspectorTab = 'overview';
        $this->expandTreePathForNode($key);
    }

    public function setInspectorTab(string $tab): void
    {
        $this->inspectorTab = in_array($tab, ['overview', 'relations', 'state', 'history'], true)
            ? $tab
            : 'overview';
    }

    public function toggleTreeSection(string $sectionKey): void
    {
        $this->expandedTreeSections[$sectionKey] = ! ($this->expandedTreeSections[$sectionKey] ?? false);
    }

    public function toggleTreeModule(int $moduleId): void
    {
        $this->expandedTreeModules[$moduleId] = ! ($this->expandedTreeModules[$moduleId] ?? false);
    }

    public function expandTreeModule(int $moduleId): void
    {
        $this->expandedTreeModules[$moduleId] = true;
        $this->expandedTreeModulesAll[$moduleId] = true;
    }

    public function toggleTreeGroup(string $groupKey): void
    {
        $this->expandedTreeGroups[$groupKey] = ! ($this->expandedTreeGroups[$groupKey] ?? false);
    }

    public function expandTreeGroup(string $groupKey): void
    {
        $this->expandedTreeGroups[$groupKey] = true;
        $this->expandedTreeGroupsAll[$groupKey] = true;
    }

    public function toggleTreeUseCase(int $useCaseId): void
    {
        $this->expandedTreeUseCases[$useCaseId] = ! ($this->expandedTreeUseCases[$useCaseId] ?? false);
    }

    public function toggleTreeFeature(int $featureId): void
    {
        $this->expandedTreeFeatures[$featureId] = ! ($this->expandedTreeFeatures[$featureId] ?? false);
    }

    public function toggleTreeDataModel(int $dataModelId): void
    {
        $this->expandedTreeDataModels[$dataModelId] = ! ($this->expandedTreeDataModels[$dataModelId] ?? false);
    }

    public function toggleTreeWorkflowRun(int $workflowRunId): void
    {
        $this->expandedTreeWorkflowRuns[$workflowRunId] = ! ($this->expandedTreeWorkflowRuns[$workflowRunId] ?? false);
    }

    public function toggleTreeNodeRun(int $nodeRunId): void
    {
        $this->expandedTreeNodeRuns[$nodeRunId] = ! ($this->expandedTreeNodeRuns[$nodeRunId] ?? false);
    }

    public function setLayerFilter(string $layer): void
    {
        $allowedLayers = ['all', ...array_column($this->layerDefinitions(), 'key')];

        $this->layerFilter = in_array($layer, $allowedLayers, true) ? $layer : 'all';
    }

    public function setFocusDepth(int $depth): void
    {
        $this->focusDepth = in_array($depth, [1, 2], true) ? $depth : 2;
    }

    public function resetView(): void
    {
        $this->search = '';
        $this->layerFilter = 'all';
        $this->blockingOnly = false;
        $this->gapsOnly = false;
        $this->focusDepth = 2;
        $this->focusMode = $this->selectedKey !== null;
    }

    public function nodeForKey(string $key): ?TraceNode
    {
        return $this->nodes[$key] ?? null;
    }

    public function resourceUrl(TraceNode $node): ?string
    {
        return $this->workbenchGraphPresenter()->resourceUrl($node);
    }

    /**
     * @return list<array{key: string, label: string, english: string, tone: string, description: string}>
     */
    public function layerDefinitions(): array
    {
        return $this->workbenchGraphPresenter()->layerDefinitions();
    }

    public function statusLabel(TraceNode $node): string
    {
        return $this->workbenchGraphPresenter()->statusLabel($node);
    }

    public function edgeTone(TraceEdge $edge): string
    {
        return $this->workbenchGraphPresenter()->edgeTone($edge);
    }

    public function edgeLabel(TraceEdge $edge): string
    {
        return $this->workbenchGraphPresenter()->edgeLabel($edge);
    }

    public function relationTable(TraceEdge $edge): string
    {
        return $this->workbenchGraphPresenter()->relationTable($edge);
    }

    public function edgeKey(TraceEdge $edge): string
    {
        return $this->workbenchGraphPresenter()->edgeKey($edge);
    }

    private function expandTreePathForNode(string $key): void
    {
        $node = $this->nodes[$key] ?? null;

        if ($node === null) {
            return;
        }

        $useCaseId = match ($node->type) {
            'Use Case' => $node->id,
            'Scenario', 'Feature' => $this->workbenchGraphPresenter()->useCaseIdForNode($node, $this->graph),
            default => null,
        };

        if ($useCaseId !== null) {
            $this->expandedTreeSections['behavior'] = true;
            $this->expandedTreeGroups['use_cases'] = true;
            $this->expandedTreeUseCases[$useCaseId] = true;

            return;
        }

        $context = $this->workbenchGraphPresenter()->treeContextForNode($node);

        if ($context === null) {
            return;
        }

        $this->expandedTreeSections[$context['section']] = true;
        $this->expandedTreeGroups[$context['group']] = true;
    }

    private function selectedSpecGraph(): ?TraceGraph
    {
        return $this->workbenchGraphService()->graphForSpec(
            project: $this->project,
            moduleSpecOptions: $this->moduleSpecOptions,
            moduleSpecId: $this->moduleSpecId,
        );
    }

    #[Computed]
    public function project(): Project
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Project, 404);

        return $tenant;
    }

    /**
     * @return Collection<int, ModuleSpec>
     */
    #[Computed]
    public function moduleSpecOptions(): Collection
    {
        return $this->workbenchGraphService()->moduleSpecOptions($this->project);
    }

    /**
     * @return Collection<int, Module>
     */
    #[Computed]
    public function moduleOptions(): Collection
    {
        return $this->workbenchGraphService()->moduleOptions($this->project);
    }

    /**
     * @return Collection<int, ModuleSpec>
     */
    #[Computed]
    public function scopeSpecs(): Collection
    {
        return $this->workbenchGraphService()->scopeSpecs(
            state: $this->state(),
            moduleSpecOptions: $this->moduleSpecOptions,
        );
    }

    /**
     * @return list<array{id: int, key: string, label: string, selected: bool, expanded: bool, show_all: bool, spec_count: int, specs: list<array{id: int, key: string, label: string, title: string, status: string, status_label: string, selected: bool}>}>
     */
    #[Computed]
    public function treeModules(): array
    {
        return $this->workbenchGraphService()->treeModules($this->project, $this->state());
    }

    /**
     * @return list<array{key: string, label: string, tone: string, expanded: bool, count: int, groups: list<array{key: string, label: string, relation: string, tone: string, expanded: bool, show_all: bool, count: int, items: list<TraceNode>}>}>
     */
    #[Computed]
    public function treeSections(): array
    {
        $graph = $this->selectedSpecGraph();

        if ($graph === null) {
            return [];
        }

        return $this->workbenchGraphPresenter()->treeSections($graph, $this->state());
    }

    /**
     * @return list<array{key: string, label: string, relation: string, tone: string, expanded: bool, show_all: bool, count: int, items: list<TraceNode>}>
     */
    #[Computed]
    public function treeGroups(): array
    {
        return array_values(collect($this->treeSections)
            ->flatMap(fn (array $section): array => $section['groups'])
            ->values()
            ->all());
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeFeaturesByUseCase(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeFeaturesByUseCase($graph);
    }

    /**
     * @return list<TraceNode>
     */
    #[Computed]
    public function treeUnassignedFeatures(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeUnassignedFeatures($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeScenariosByUseCase(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeScenariosByUseCase($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeImplementationNodesByFeature(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeImplementationNodesByFeature($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeTestsByFeature(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeTestsByFeature($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeCommitsByFeature(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeCommitsByFeature($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeFlowStepsByFeature(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeFlowStepsByFeature($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeModelFieldsByDataModel(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeModelFieldsByDataModel($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeNodeRunsByWorkflowRun(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeNodeRunsByWorkflowRun($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeRunEventsByNodeRun(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeRunEventsByNodeRun($graph);
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    #[Computed]
    public function treeRunEventsByWorkflowRun(): array
    {
        $graph = $this->selectedSpecGraph();

        return $graph === null ? [] : $this->workbenchGraphPresenter()->treeRunEventsByWorkflowRun($graph);
    }

    /**
     * @return array{key: string, label: string, field: string, current: string, current_label: string, states: list<array{value: string, label: string, current: bool}>, transitions: list<array{from: string, to: string, label: string, tone: string, current: bool}>, allowed: list<array{from: string, to: string, label: string, tone: string, current: bool}>}|null
     */
    #[Computed]
    public function selectedStateMachine(): ?array
    {
        return $this->workbenchGraphPresenter()->selectedStateMachine($this->selectedNode);
    }

    /**
     * @return list<array{label: string, time: string, tone: string}>
     */
    #[Computed]
    public function stateHistory(): array
    {
        return $this->workbenchGraphService()->stateHistory($this->project, $this->selectedNode);
    }

    #[Computed]
    public function graph(): TraceGraph
    {
        return $this->workbenchGraphService()->graph($this->project, $this->scopeSpecs);
    }

    /**
     * @return array<string, TraceNode>
     */
    #[Computed]
    public function nodes(): array
    {
        return $this->graph->nodes();
    }

    /**
     * @return list<TraceEdge>
     */
    #[Computed]
    public function edges(): array
    {
        return $this->graph->edges();
    }

    #[Computed]
    public function selectedNode(): ?TraceNode
    {
        if ($this->selectedKey === null) {
            return null;
        }

        return $this->nodes[$this->selectedKey] ?? null;
    }

    /**
     * @return list<TraceEdge>
     */
    #[Computed]
    public function incomingEdges(): array
    {
        if ($this->selectedKey === null) {
            return [];
        }

        return $this->graph->incoming($this->selectedKey);
    }

    /**
     * @return list<TraceEdge>
     */
    #[Computed]
    public function outgoingEdges(): array
    {
        if ($this->selectedKey === null) {
            return [];
        }

        return $this->graph->outgoing($this->selectedKey);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function blockedReasons(): array
    {
        return $this->workbenchGraphPresenter()->blockedReasons($this->nodes);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function gapReasons(): array
    {
        return $this->workbenchGraphPresenter()->gapReasons($this->nodes, $this->edges);
    }

    /**
     * @return array<string, true>
     */
    #[Computed]
    public function currentNodeKeys(): array
    {
        return $this->workbenchGraphPresenter()->currentNodeKeys($this->nodes);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function relationshipCounts(): array
    {
        return $this->workbenchGraphPresenter()->relationshipCounts($this->edges);
    }

    /**
     * @return array<string, true>
     */
    #[Computed]
    public function focusKeySet(): array
    {
        return $this->workbenchGraphPresenter()->focusKeySet($this->nodes, $this->edges, $this->state());
    }

    /**
     * @return list<TraceNode>
     */
    #[Computed]
    public function visibleNodes(): array
    {
        return $this->workbenchGraphPresenter()->visibleNodes($this->nodes, $this->edges, $this->state());
    }

    /**
     * @return array<string, list<TraceNode>>
     */
    #[Computed]
    public function visibleNodesByLayer(): array
    {
        return $this->workbenchGraphPresenter()->visibleNodesByLayer($this->visibleNodes);
    }

    /**
     * @return list<TraceEdge>
     */
    #[Computed]
    public function visibleEdges(): array
    {
        return $this->workbenchGraphPresenter()->visibleEdges($this->visibleNodes, $this->edges);
    }

    /**
     * @return list<array{key: string, from: string, to: string, kind: string, tone: string}>
     */
    #[Computed]
    public function edgeVisuals(): array
    {
        return $this->workbenchGraphPresenter()->edgeVisuals($this->visibleEdges);
    }

    /**
     * @return list<TraceNode>
     */
    #[Computed]
    public function searchMatches(): array
    {
        return $this->workbenchGraphPresenter()->searchMatches($this->nodes, $this->search);
    }

    /**
     * @return array<string, true>
     */
    #[Computed]
    public function searchMatchedKeys(): array
    {
        return $this->workbenchGraphPresenter()->searchMatchedKeys($this->searchMatches);
    }

    private function state(): WorkbenchGraphState
    {
        return new WorkbenchGraphState(
            scopeType: $this->scopeType,
            moduleSpecId: $this->moduleSpecId,
            moduleId: $this->moduleId,
            selectedKey: $this->selectedKey,
            search: $this->search,
            layerFilter: $this->layerFilter,
            blockingOnly: $this->blockingOnly,
            gapsOnly: $this->gapsOnly,
            focusMode: $this->focusMode,
            focusDepth: $this->focusDepth,
            expandedTreeSections: $this->expandedTreeSections,
            expandedTreeModules: $this->expandedTreeModules,
            expandedTreeModulesAll: $this->expandedTreeModulesAll,
            expandedTreeGroups: $this->expandedTreeGroups,
            expandedTreeGroupsAll: $this->expandedTreeGroupsAll,
        );
    }

    private function workbenchGraphService(): WorkbenchGraphService
    {
        return $this->workbenchGraphService
            ?? throw new LogicException('Workbench graph service has not been booted.');
    }

    private function workbenchGraphPresenter(): WorkbenchGraphPresenter
    {
        return $this->workbenchGraphPresenter
            ?? throw new LogicException('Workbench graph presenter has not been booted.');
    }
}
