<?php

namespace App\Services\Workbench;

use App\Data\Workbench\WorkbenchGraphState;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\RunEvent;
use App\Services\TraceGraphProjection;
use App\Support\TraceGraph;
use App\Support\TraceNode;
use Illuminate\Support\Collection;

class WorkbenchGraphService
{
    public function __construct(
        private TraceGraphProjection $traceGraphProjection,
    ) {}

    public function module(Project $project, int $moduleId): ?Module
    {
        return Module::query()
            ->where('project_id', $project->id)
            ->whereKey($moduleId)
            ->first();
    }

    public function moduleSpec(Project $project, int $moduleSpecId): ?ModuleSpec
    {
        return ModuleSpec::query()
            ->where('project_id', $project->id)
            ->whereKey($moduleSpecId)
            ->first();
    }

    /**
     * @return Collection<int, ModuleSpec>
     */
    public function moduleSpecOptions(Project $project): Collection
    {
        $moduleOrder = Module::inBuildOrder($project)->pluck('id')->flip();

        return ModuleSpec::query()
            ->where('project_id', $project->id)
            ->with('module')
            ->get()
            ->sortBy(fn (ModuleSpec $spec): array => [
                (int) ($moduleOrder[$spec->module_id] ?? PHP_INT_MAX),
                $spec->id,
            ])
            ->values();
    }

    /**
     * @return Collection<int, Module>
     */
    public function moduleOptions(Project $project): Collection
    {
        return Module::query()
            ->where('project_id', $project->id)
            ->withCount('requirements')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, ModuleSpec>  $moduleSpecOptions
     * @return Collection<int, ModuleSpec>
     */
    public function scopeSpecs(
        WorkbenchGraphState $state,
        Collection $moduleSpecOptions,
    ): Collection {
        if ($state->scopeType === 'project') {
            $active = $moduleSpecOptions
                ->filter(fn (ModuleSpec $spec): bool => $spec->status !== 'stale');

            if ($state->moduleSpecId !== null && ! $active->contains('id', $state->moduleSpecId)) {
                $selected = $moduleSpecOptions->firstWhere('id', $state->moduleSpecId);

                if ($selected !== null) {
                    $active = $active->prepend($selected);
                }
            }

            return $active->take(8)->values();
        }

        if ($state->scopeType === 'module' && $state->moduleId !== null) {
            return $moduleSpecOptions
                ->where('module_id', $state->moduleId)
                ->values();
        }

        if ($state->moduleSpecId === null) {
            return collect();
        }

        return $moduleSpecOptions
            ->where('id', $state->moduleSpecId)
            ->values();
    }

    /**
     * @return list<array{id: int, key: string, label: string, selected: bool, expanded: bool, show_all: bool, spec_count: int, specs: list<array{id: int, key: string, label: string, title: string, status: string, status_label: string, selected: bool}>}>
     */
    public function treeModules(Project $project, WorkbenchGraphState $state): array
    {
        $specsByModule = ModuleSpec::query()
            ->where('project_id', $project->id)
            ->with('module')
            ->get()
            ->groupBy('module_id');

        return array_values(Module::inBuildOrder($project)
            ->map(function (Module $module) use ($specsByModule, $state): array {
                $specs = $specsByModule->get($module->id, collect());

                return [
                    'id' => $module->id,
                    'key' => "module:{$module->id}",
                    'label' => $module->name,
                    'selected' => $state->moduleId === $module->id
                        && $state->selectedKey === "module:{$module->id}",
                    'expanded' => $state->expandedTreeModules[$module->id] ?? false,
                    'show_all' => $state->expandedTreeModulesAll[$module->id] ?? false,
                    'spec_count' => $specs->count(),
                    'specs' => array_values($specs
                        ->map(function (ModuleSpec $spec) use ($state): array {
                            $title = $spec->summary ?: mb_strimwidth(
                                (string) $spec->content,
                                0,
                                100,
                                '...',
                            );

                            return [
                                'id' => $spec->id,
                                'key' => "module_spec:{$spec->id}",
                                'label' => 'Spec v'.$spec->version,
                                'title' => $title,
                                'status' => $spec->status,
                                'status_label' => ucfirst($spec->status),
                                'selected' => $state->moduleSpecId === $spec->id,
                            ];
                        })
                        ->values()
                        ->all()),
                ];
            })
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, ModuleSpec>  $scopeSpecs
     */
    public function graph(Project $project, Collection $scopeSpecs): TraceGraph
    {
        $graph = new TraceGraph;

        foreach ($scopeSpecs as $spec) {
            $specGraph = $this->traceGraphProjection->forModuleSpec($project, $spec);

            foreach ($specGraph->nodes() as $node) {
                $graph->addNode($node);
            }

            foreach ($specGraph->edges() as $edge) {
                $graph->addEdge($edge);
            }
        }

        return $graph;
    }

    /**
     * @param  Collection<int, ModuleSpec>  $moduleSpecOptions
     */
    public function graphForSpec(
        Project $project,
        Collection $moduleSpecOptions,
        ?int $moduleSpecId,
    ): ?TraceGraph {
        $spec = $moduleSpecOptions->firstWhere('id', $moduleSpecId);

        if ($spec === null) {
            return null;
        }

        return $this->traceGraphProjection->forModuleSpec($project, $spec);
    }

    /**
     * @return list<array{label: string, time: string, tone: string}>
     */
    public function stateHistory(Project $project, ?TraceNode $selectedNode): array
    {
        if ($selectedNode === null) {
            return [];
        }

        if (! in_array($selectedNode->type, ['Workflow Run', 'Node Run', 'Implementation Node'], true)) {
            return [];
        }

        $query = RunEvent::query()
            ->whereHas('workflowRun', fn ($query) => $query->where('project_id', $project->id))
            ->with(['nodeRun.implementationNode'])
            ->latest();

        if ($selectedNode->type === 'Workflow Run') {
            $query->where('workflow_run_id', $selectedNode->id);
        } elseif ($selectedNode->type === 'Node Run') {
            $query->where('node_run_id', $selectedNode->id);
        } else {
            $query->whereHas(
                'nodeRun',
                fn ($query) => $query->where('implementation_node_id', $selectedNode->id),
            );
        }

        return array_values($query
            ->limit(20)
            ->get()
            ->map(fn (RunEvent $event): array => [
                'label' => $event->event_type->getLabel(),
                'time' => $event->created_at->format('Y-m-d H:i'),
                'tone' => match ($event->event_type->value) {
                    'blocked', 'gap_detected' => 'danger',
                    'back_edge_taken', 'attempt_finished' => 'warning',
                    'completed', 'approved', 'edge_taken' => 'success',
                    default => 'info',
                },
            ])
            ->values()
            ->all());
    }
}
