<?php

namespace App\Filament\Support;

use App\Data\Workbench\WorkbenchGraphState;
use App\Enums\DataModelStatus;
use App\Enums\ImplementationNodeState;
use App\Enums\NodeRunStatus;
use App\Enums\ScenarioStatus;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Filament\Resources\Commits\CommitResource;
use App\Filament\Resources\DataModels\DataModelResource;
use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\FlowSteps\FlowStepResource;
use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use App\Filament\Resources\Scenarios\ScenarioResource;
use App\Filament\Resources\Tests\TestResource;
use App\Filament\Resources\UseCases\UseCaseResource;
use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use App\Support\StateMachineCatalog;
use App\Support\TraceEdge;
use App\Support\TraceGraph;
use App\Support\TraceNode;

class WorkbenchGraphPresenter
{
    public function __construct(
        private StateMachineCatalog $stateMachineCatalog,
    ) {}

    public function resourceUrl(TraceNode $node): ?string
    {
        return match ($node->type) {
            'Module Spec' => ModuleSpecResource::getUrl('edit', ['record' => $node->id]),
            'Use Case' => UseCaseResource::getUrl('edit', ['record' => $node->id]),
            'Scenario' => ScenarioResource::getUrl('edit', ['record' => $node->id]),
            'Data Model' => DataModelResource::getUrl('edit', ['record' => $node->id]),
            'Feature' => FeatureResource::getUrl('view', ['record' => $node->id]),
            'Implementation Node' => ImplementationNodeResource::getUrl('view', ['record' => $node->id]),
            'Workflow Run' => WorkflowRunResource::getUrl('view', ['record' => $node->id]),
            'Flow Step' => FlowStepResource::getUrl('edit', ['record' => $node->id]),
            'Test' => TestResource::getUrl('edit', ['record' => $node->id]),
            'Commit' => CommitResource::getUrl('index'),
            default => null,
        };
    }

    /**
     * @return list<array{key: string, label: string, english: string, tone: string, description: string}>
     */
    public function layerDefinitions(): array
    {
        return [
            ['key' => 'scope', 'label' => '范围', 'english' => 'Scope', 'tone' => 'slate', 'description' => '模块与边界'],
            ['key' => 'specs', 'label' => '模块规格', 'english' => 'Module Specs', 'tone' => 'amber', 'description' => '模块当前完整事实'],
            ['key' => 'behavior', 'label' => '行为', 'english' => 'Behavior', 'tone' => 'sky', 'description' => '用例、场景与规则'],
            ['key' => 'solution', 'label' => '方案', 'english' => 'Solution', 'tone' => 'violet', 'description' => '决策与数据模型'],
            ['key' => 'delivery', 'label' => '交付', 'english' => 'Delivery', 'tone' => 'emerald', 'description' => '功能与实现节点'],
            ['key' => 'execution', 'label' => '执行', 'english' => 'Execution', 'tone' => 'orange', 'description' => 'Run、Node 与事件'],
            ['key' => 'evidence', 'label' => '证据', 'english' => 'Evidence', 'tone' => 'rose', 'description' => '测试、数据流与提交'],
        ];
    }

    public function statusLabel(TraceNode $node): string
    {
        $label = match ($node->type) {
            'Module Spec' => ucfirst($node->status),
            'Workflow Run' => WorkflowRunStatus::tryFrom($node->status)?->getLabel(),
            'Node Run' => NodeRunStatus::tryFrom($node->status)?->getLabel(),
            'Scenario' => ScenarioStatus::tryFrom($node->status)?->getLabel(),
            'Implementation Node' => ImplementationNodeState::tryFrom($node->status)?->getLabel(),
            'Use Case' => UseCaseStatus::tryFrom($node->status)?->getLabel(),
            'Data Model' => DataModelStatus::tryFrom($node->status)?->getLabel(),
            default => null,
        };

        return $label ?? $node->status;
    }

    public function edgeTone(TraceEdge $edge): string
    {
        if (str_starts_with($edge->kind, 'plan_') || $edge->kind === 'rework') {
            return 'back';
        }

        return match ($edge->kind) {
            'contains' => 'contains',
            'verified_by' => 'verified_by',
            'evidenced_by' => 'evidenced_by',
            'unassigned' => 'related',
            default => 'related',
        };
    }

    public function edgeLabel(TraceEdge $edge): string
    {
        return match ($edge->kind) {
            'contains' => '包含 contains',
            'verified_by' => '测试验证 verified_by',
            'evidenced_by' => '实现证据 evidenced_by',
            'unassigned' => '待归入 Use Case unassigned',
            default => str_starts_with($edge->kind, 'plan_') ? '回边 '.$edge->label : $edge->label,
        };
    }

    public function relationTable(TraceEdge $edge): string
    {
        return match ($edge->kind) {
            'scopes' => 'module_requirement',
            'contains' => match (true) {
                str_starts_with($edge->to, 'use_case:') => 'module_use_cases',
                str_starts_with($edge->to, 'scenario:') => 'scenarios.use_case_id',
                str_starts_with($edge->to, 'model_field:') => 'model_fields.data_model_id',
                str_starts_with($edge->to, 'node_run:') => 'node_runs.workflow_run_id',
                default => 'parent_id',
            },
            'implements' => 'scenario_implementation_nodes',
            'verified_by' => 'tests.scenario_id / feature_test',
            'evidenced_by' => 'flow_steps / commits',
            'uses' => 'data_model_feature',
            'delivers' => 'features.requirement_id',
            'implemented_by' => 'features.use_case_id',
            'unassigned' => 'features.use_case_id is null',
            'declared_by' => 'module_specs.module_id',
            'decomposes' => 'implementation_nodes.feature_id',
            'executed_as' => 'workflow_runs.use_case_id',
            'instantiated_as' => 'node_runs.implementation_node_id',
            'emits' => 'run_events.workflow_run_id',
            'parent' => 'implementation_nodes.parent_id',
            default => str_starts_with($edge->kind, 'plan_') ? 'implementation_node_edges' : $edge->kind,
        };
    }

    public function edgeKey(TraceEdge $edge): string
    {
        return "{$edge->from}|{$edge->to}|{$edge->kind}";
    }

    /**
     * @return array{section: string, group: string}|null
     */
    public function treeContextForNode(TraceNode $node): ?array
    {
        foreach ($this->treeSectionDefinitions() as $section) {
            foreach ($section['groups'] as $group) {
                if (in_array($node->type, $group['types'], true)) {
                    return [
                        'section' => $section['key'],
                        'group' => $group['key'],
                    ];
                }
            }
        }

        return null;
    }

    public function useCaseIdForNode(TraceNode $node, TraceGraph $graph): ?int
    {
        if ($node->type === 'Use Case') {
            return $node->id;
        }

        $edgeKind = match ($node->type) {
            'Scenario' => 'contains',
            'Feature' => 'implemented_by',
            default => null,
        };

        if ($edgeKind === null) {
            return null;
        }

        foreach ($graph->edges() as $edge) {
            if ($edge->kind !== $edgeKind || ! str_starts_with($edge->from, 'use_case:') || $edge->to !== $node->key) {
                continue;
            }

            return (int) str_replace('use_case:', '', $edge->from);
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string, tone: string, expanded: bool, count: int, groups: list<array{key: string, label: string, relation: string, tone: string, expanded: bool, show_all: bool, count: int, items: list<TraceNode>}>}>
     */
    public function treeSections(TraceGraph $graph, WorkbenchGraphState $state): array
    {
        $nodes = collect($graph->nodes());

        return array_values(collect($this->treeSectionDefinitions())
            ->map(function (array $section) use ($nodes, $graph, $state): array {
                $groups = collect($section['groups'])
                    ->map(function (array $group) use ($nodes, $graph, $state): array {
                        $items = match ($group['key']) {
                            'use_cases' => $nodes
                                ->filter(fn (TraceNode $node): bool => $node->type === 'Use Case')
                                ->sortBy('title')
                                ->values()
                                ->all(),
                            'unassigned_features' => $this->treeUnassignedFeatures($graph),
                            default => $nodes
                                ->filter(fn (TraceNode $node): bool => in_array($node->type, $group['types'], true))
                                ->sortBy(fn (TraceNode $node): array => [$node->type, $node->title])
                                ->values()
                                ->all(),
                        };

                        return [
                            'key' => $group['key'],
                            'label' => $group['label'],
                            'relation' => $group['relation'],
                            'tone' => $group['tone'],
                            'expanded' => $state->expandedTreeGroups[$group['key']] ?? false,
                            'show_all' => $state->expandedTreeGroupsAll[$group['key']] ?? false,
                            'count' => count($items),
                            'items' => array_values($items),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'key' => $section['key'],
                    'label' => $section['label'],
                    'tone' => $section['tone'],
                    'expanded' => $state->expandedTreeSections[$section['key']] ?? false,
                    'count' => $nodes->where('layer', $section['key'])->count(),
                    'groups' => array_values($groups),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @return list<TraceNode>
     */
    public function treeUnassignedFeatures(TraceGraph $graph): array
    {
        $nodes = collect($graph->nodes())->keyBy('key');

        return array_values(collect($graph->edges())
            ->filter(fn (TraceEdge $edge): bool => $edge->kind === 'unassigned'
                && str_starts_with($edge->to, 'feature:'))
            ->map(fn (TraceEdge $edge): ?TraceNode => $nodes->get($edge->to))
            ->filter()
            ->sortBy('title')
            ->all());
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeFeaturesByUseCase(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'use_case:',
            toType: 'Feature',
            kind: 'implemented_by',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeScenariosByUseCase(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'use_case:',
            toType: 'Scenario',
            kind: 'contains',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeImplementationNodesByFeature(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'feature:',
            toType: 'Implementation Node',
            kind: 'decomposes',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeTestsByFeature(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'feature:',
            toType: 'Test',
            kind: 'verified_by',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeCommitsByFeature(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'feature:',
            toType: 'Commit',
            kind: 'evidenced_by',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeFlowStepsByFeature(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'feature:',
            toType: 'Flow Step',
            kind: 'evidenced_by',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeModelFieldsByDataModel(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'data_model:',
            toType: 'Model Field',
            kind: 'contains',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeNodeRunsByWorkflowRun(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'workflow_run:',
            toType: 'Node Run',
            kind: 'contains',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeRunEventsByNodeRun(TraceGraph $graph): array
    {
        return $this->treeNodesByParentEdge(
            graph: $graph,
            fromPrefix: 'node_run:',
            toType: 'Run Event',
            kind: 'emits',
        );
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    public function treeRunEventsByWorkflowRun(TraceGraph $graph): array
    {
        $nodes = collect($graph->nodes())
            ->filter(fn (TraceNode $node): bool => $node->type === 'Run Event')
            ->keyBy('key');
        $nodeEventKeys = collect($this->treeRunEventsByNodeRun($graph))
            ->flatten(1)
            ->pluck('key')
            ->flip();
        $grouped = [];

        foreach ($graph->edges() as $edge) {
            if ($edge->kind !== 'emits' || ! str_starts_with($edge->from, 'workflow_run:') || ! str_starts_with($edge->to, 'run_event:')) {
                continue;
            }

            if ($nodeEventKeys->has($edge->to)) {
                continue;
            }

            $node = $nodes->get($edge->to);

            if ($node === null) {
                continue;
            }

            $grouped[(int) str_replace('workflow_run:', '', $edge->from)][] = $node;
        }

        return $grouped;
    }

    /**
     * @return array{key: string, label: string, field: string, current: string, current_label: string, states: list<array{value: string, label: string, current: bool}>, transitions: list<array{from: string, to: string, label: string, tone: string, current: bool}>, allowed: list<array{from: string, to: string, label: string, tone: string, current: bool}>}|null
     */
    public function selectedStateMachine(?TraceNode $selectedNode): ?array
    {
        if ($selectedNode === null) {
            return null;
        }

        $definition = $this->stateMachineCatalog->forNodeType($selectedNode->type);

        if ($definition === null) {
            return null;
        }

        $current = $selectedNode->status;
        $states = array_values(collect($definition['states'])
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
                'current' => $value === $current,
            ])
            ->all());
        $transitions = array_map(
            fn (array $transition): array => [
                ...$transition,
                'current' => $transition['from'] === $current || $transition['to'] === $current,
            ],
            $definition['transitions'],
        );

        return [
            ...$definition,
            'current' => $current,
            'current_label' => $definition['states'][$current] ?? $current,
            'states' => $states,
            'transitions' => $transitions,
            'allowed' => array_values(array_filter(
                $transitions,
                fn (array $transition): bool => $transition['from'] === $current,
            )),
        ];
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @return array<string, string>
     */
    public function blockedReasons(array $nodes): array
    {
        $reasons = [];

        foreach ($nodes as $key => $node) {
            if ($node->blocking) {
                $reasons[$key] = '阻塞决策';

                continue;
            }

            if (in_array($node->status, ['blocked', 'failed'], true)) {
                $reasons[$key] = $node->type === 'Node Run' ? '节点执行阻塞' : '执行失败';

                continue;
            }

            if ($node->type === 'Workflow Run' && in_array($node->status, ['waiting', 'paused'], true)) {
                $reasons[$key] = 'Run 等待恢复';
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @param  list<TraceEdge>  $edges
     * @return array<string, string>
     */
    public function gapReasons(array $nodes, array $edges): array
    {
        $outgoingKinds = [];
        $outgoingTargets = [];
        $incomingKinds = [];

        foreach ($edges as $edge) {
            $outgoingKinds[$edge->from][$edge->kind] = true;
            $outgoingTargets[$edge->from][$edge->to] = true;
            $incomingKinds[$edge->to][$edge->kind] = true;
        }

        $hasTarget = function (string $nodeKey, string $targetPrefix) use ($outgoingTargets): bool {
            foreach (array_keys($outgoingTargets[$nodeKey] ?? []) as $targetKey) {
                if (str_starts_with($targetKey, $targetPrefix)) {
                    return true;
                }
            }

            return false;
        };

        $reasons = [];

        foreach ($nodes as $key => $node) {
            $reason = match ($node->type) {
                'Module Spec' => $hasTarget($key, 'use_case:') ? null : 'Module Spec 缺 UseCase',
                'Use Case' => $hasTarget($key, 'scenario:') ? null : '用例缺场景',
                'Scenario' => isset($outgoingKinds[$key]['verified_by']) ? null : '场景缺测试',
                'Feature' => ! ($incomingKinds[$key]['implemented_by'] ?? false)
                    ? '功能未归入 Use Case'
                    : ($hasTarget($key, 'implementation_node:') ? null : '功能缺实现节点'),
                'Implementation Node' => $node->evidenceCount === 0 ? '实现节点缺证据' : null,
                'Workflow Run' => in_array($node->status, ['waiting', 'paused', 'failed'], true) ? '执行链未收口' : null,
                'Node Run' => in_array($node->status, ['blocked', 'failed'], true) ? '节点执行未收口' : null,
                'Test' => $node->status !== '有效' || in_array($node->subtitle, ['失败', '阻塞'], true) ? '测试未就绪' : null,
                default => null,
            };

            if ($reason !== null) {
                $reasons[$key] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @return array<string, true>
     */
    public function currentNodeKeys(array $nodes): array
    {
        $currentNode = collect($nodes)->first(
            fn (TraceNode $node): bool => $node->type === 'Node Run'
                && in_array($node->status, ['talking', 'running'], true),
        );

        return $currentNode === null ? [] : [$currentNode->key => true];
    }

    /**
     * @param  list<TraceEdge>  $edges
     * @return array<string, int>
     */
    public function relationshipCounts(array $edges): array
    {
        $counts = [];

        foreach ($edges as $edge) {
            $counts[$edge->from] = ($counts[$edge->from] ?? 0) + 1;
            $counts[$edge->to] = ($counts[$edge->to] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @param  list<TraceEdge>  $edges
     * @return array<string, true>
     */
    public function focusKeySet(
        array $nodes,
        array $edges,
        WorkbenchGraphState $state,
    ): array {
        if (! $state->focusMode || $state->selectedKey === null || ! isset($nodes[$state->selectedKey])) {
            return array_fill_keys(array_keys($nodes), true);
        }

        $adjacency = [];

        foreach ($edges as $edge) {
            $adjacency[$edge->from][$edge->to] = true;
            $adjacency[$edge->to][$edge->from] = true;
        }

        $visited = [$state->selectedKey => true];
        $frontier = [$state->selectedKey];

        for ($depth = 0; $depth < $state->focusDepth; $depth++) {
            $nextFrontier = [];

            foreach ($frontier as $nodeKey) {
                foreach (array_keys($adjacency[$nodeKey] ?? []) as $relatedKey) {
                    if (isset($visited[$relatedKey])) {
                        continue;
                    }

                    $visited[$relatedKey] = true;
                    $nextFrontier[] = $relatedKey;
                }
            }

            if ($nextFrontier === []) {
                break;
            }

            $frontier = $nextFrontier;
        }

        return $visited;
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @param  list<TraceEdge>  $edges
     * @return list<TraceNode>
     */
    public function visibleNodes(
        array $nodes,
        array $edges,
        WorkbenchGraphState $state,
    ): array {
        $blockedReasons = $this->blockedReasons($nodes);
        $gapReasons = $this->gapReasons($nodes, $edges);
        $focusKeySet = $this->focusKeySet($nodes, $edges, $state);

        return array_values(array_filter(
            $nodes,
            function (TraceNode $node, string $key) use ($state, $blockedReasons, $gapReasons, $focusKeySet): bool {
                if ($state->layerFilter !== 'all' && $node->layer !== $state->layerFilter) {
                    return false;
                }

                if ($state->blockingOnly && ! isset($blockedReasons[$key])) {
                    return false;
                }

                if ($state->gapsOnly && ! isset($gapReasons[$key])) {
                    return false;
                }

                return ! $state->focusMode || isset($focusKeySet[$key]);
            },
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @param  list<TraceNode>  $visibleNodes
     * @return array<string, list<TraceNode>>
     */
    public function visibleNodesByLayer(array $visibleNodes): array
    {
        $nodesByLayer = array_fill_keys(array_column($this->layerDefinitions(), 'key'), []);

        foreach ($visibleNodes as $node) {
            $nodesByLayer[$node->layer][] = $node;
        }

        return $nodesByLayer;
    }

    /**
     * @param  list<TraceNode>  $visibleNodes
     * @param  list<TraceEdge>  $edges
     * @return list<TraceEdge>
     */
    public function visibleEdges(array $visibleNodes, array $edges): array
    {
        $visibleKeys = array_fill_keys(array_map(
            fn (TraceNode $node): string => $node->key,
            $visibleNodes,
        ), true);

        return array_values(array_filter(
            $edges,
            fn (TraceEdge $edge): bool => isset($visibleKeys[$edge->from], $visibleKeys[$edge->to]),
        ));
    }

    /**
     * @param  list<TraceEdge>  $visibleEdges
     * @return list<array{key: string, from: string, to: string, kind: string, tone: string}>
     */
    public function edgeVisuals(array $visibleEdges): array
    {
        return array_map(
            fn (TraceEdge $edge): array => [
                'key' => $this->edgeKey($edge),
                'from' => $edge->from,
                'to' => $edge->to,
                'kind' => $edge->kind,
                'tone' => $this->edgeTone($edge),
            ],
            $visibleEdges,
        );
    }

    /**
     * @param  array<string, TraceNode>  $nodes
     * @return list<TraceNode>
     */
    public function searchMatches(array $nodes, string $search): array
    {
        $needle = mb_strtolower(trim($search));

        if ($needle === '') {
            return [];
        }

        return array_values(array_filter(
            $nodes,
            fn (TraceNode $node): bool => str_contains(
                mb_strtolower(implode(' ', [
                    $node->key,
                    $node->type,
                    $node->title,
                    $node->status,
                    $node->subtitle ?? '',
                ])),
                $needle,
            ),
        ));
    }

    /**
     * @param  list<TraceNode>  $searchMatches
     * @return array<string, true>
     */
    public function searchMatchedKeys(array $searchMatches): array
    {
        return array_fill_keys(array_map(
            fn (TraceNode $node): string => $node->key,
            $searchMatches,
        ), true);
    }

    /**
     * @return list<array{key: string, label: string, tone: string, groups: list<array{key: string, label: string, relation: string, tone: string, types: list<string>}>}>
     */
    private function treeSectionDefinitions(): array
    {
        return [
            [
                'key' => 'foundation',
                'label' => '地基',
                'tone' => 'slate',
                'groups' => [
                    ['key' => 'data_models', 'label' => 'Data Models', 'relation' => 'data_model_feature', 'tone' => 'violet', 'types' => ['Data Model']],
                ],
            ],
            [
                'key' => 'behavior',
                'label' => '行为',
                'tone' => 'sky',
                'groups' => [
                    ['key' => 'use_cases', 'label' => 'Use Cases', 'relation' => 'module_use_cases', 'tone' => 'sky', 'types' => ['Use Case']],
                    ['key' => 'unassigned_features', 'label' => 'Unassigned Features', 'relation' => 'features.use_case_id is null', 'tone' => 'amber', 'types' => ['Feature']],
                ],
            ],
            [
                'key' => 'execution',
                'label' => '执行',
                'tone' => 'orange',
                'groups' => [
                    ['key' => 'workflow_runs', 'label' => 'Workflow Runs', 'relation' => 'workflow_runs.use_case_id', 'tone' => 'orange', 'types' => ['Workflow Run']],
                ],
            ],
            [
                'key' => 'evidence',
                'label' => '证据',
                'tone' => 'rose',
                'groups' => [
                    ['key' => 'commits', 'label' => 'Commits', 'relation' => 'commits.feature_id', 'tone' => 'rose', 'types' => ['Commit']],
                ],
            ],
        ];
    }

    /**
     * @return array<int, list<TraceNode>>
     */
    private function treeNodesByParentEdge(
        TraceGraph $graph,
        string $fromPrefix,
        string $toType,
        string $kind,
    ): array {
        $nodes = collect($graph->nodes())
            ->filter(fn (TraceNode $node): bool => $node->type === $toType)
            ->keyBy('key');
        $grouped = [];

        foreach ($graph->edges() as $edge) {
            if ($edge->kind !== $kind || ! str_starts_with($edge->from, $fromPrefix)) {
                continue;
            }

            $node = $nodes->get($edge->to);

            if ($node === null) {
                continue;
            }

            $grouped[(int) str_replace($fromPrefix, '', $edge->from)][] = $node;
        }

        return $grouped;
    }
}
