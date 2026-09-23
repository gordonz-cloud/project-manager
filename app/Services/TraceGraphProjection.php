<?php

namespace App\Services;

use App\Models\Commit;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\ModelField;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\RunEvent;
use App\Models\Scenario;
use App\Models\Test;
use App\Models\UseCase;
use App\Models\WorkflowRun;
use App\Support\TraceEdge;
use App\Support\TraceGraph;
use App\Support\TraceNode;
use Illuminate\Database\Eloquent\Model;

class TraceGraphProjection
{
    public function forModuleSpec(Project $project, ModuleSpec $spec): TraceGraph
    {
        abort_unless($spec->project_id === $project->id, 404);

        $spec->loadMissing([
            'module',
            'useCases.modules',
            'useCases.scenarios.tests',
            'useCases.scenarios.implementationNodes',
            'useCases.features.dataModels.modelFields',
            'useCases.features.useCase',
            'useCases.features.flowSteps',
            'useCases.features.commits',
            'useCases.features.tests',
            'useCases.features.implementationNodes.flowSteps',
            'useCases.features.implementationNodes.commits',
        ]);

        $legacyFeatures = Feature::query()
            ->where('project_id', $project->id)
            ->whereNull('use_case_id')
            ->whereHas('requirement.modules', fn ($query) => $query->whereKey($spec->module_id))
            ->with([
                'dataModels.modelFields',
                'flowSteps',
                'commits',
                'tests',
                'implementationNodes.flowSteps',
                'implementationNodes.commits',
            ])
            ->get();

        $features = $spec->useCases
            ->flatMap(fn (UseCase $useCase) => $useCase->features)
            ->merge($legacyFeatures)
            ->unique('id')
            ->values();
        $featureIds = $features->pluck('id')->all();
        $nodeIds = ImplementationNode::query()
            ->where('project_id', $project->id)
            ->whereIn('feature_id', $featureIds)
            ->pluck('id')
            ->all();
        $implementationEdges = ImplementationNodeEdge::query()
            ->where('project_id', $project->id)
            ->whereIn('from_node_id', $nodeIds)
            ->whereIn('to_node_id', $nodeIds)
            ->get();
        $runs = WorkflowRun::query()
            ->where('project_id', $project->id)
            ->whereIn('use_case_id', $spec->useCases->modelKeys())
            ->with([
                'feature',
                'events',
                'nodeRuns.implementationNode',
                'nodeRuns.events' => fn ($query) => $query->latest()->limit(20),
            ])
            ->latest()
            ->limit(20)
            ->get();

        $graph = new TraceGraph;
        $module = $spec->module;

        $graph->addNode(new TraceNode(
            key: "module:{$module->id}",
            layer: 'scope',
            type: 'Module',
            id: $module->id,
            title: $module->name,
            status: 'scope',
        ));
        $graph->addNode($this->moduleSpecNode($spec));
        $graph->addEdge(new TraceEdge(
            from: "module:{$module->id}",
            to: "module_spec:{$spec->id}",
            kind: 'declared_by',
            label: 'declared by',
        ));

        foreach ($spec->useCases as $useCase) {
            $graph->addNode($this->useCaseNode($useCase));
            $graph->addEdge(new TraceEdge(
                from: "module_spec:{$spec->id}",
                to: "use_case:{$useCase->id}",
                kind: 'contains',
                label: 'contains',
            ));

            foreach ($useCase->scenarios as $scenario) {
                $graph->addNode($this->scenarioNode($scenario));
                $graph->addEdge(new TraceEdge(
                    from: "use_case:{$useCase->id}",
                    to: "scenario:{$scenario->id}",
                    kind: 'contains',
                    label: 'contains',
                ));

                foreach ($scenario->tests as $test) {
                    $graph->addNode($this->testNode($test));
                    $graph->addEdge(new TraceEdge(
                        from: "scenario:{$scenario->id}",
                        to: "test:{$test->id}",
                        kind: 'verified_by',
                        label: 'verified by',
                    ));
                }

                foreach ($scenario->implementationNodes as $node) {
                    $graph->addNode($this->implementationNode($node));
                    $graph->addEdge(new TraceEdge(
                        from: "implementation_node:{$node->id}",
                        to: "scenario:{$scenario->id}",
                        kind: 'implements',
                        label: 'implements',
                    ));
                }
            }
        }

        foreach ($features as $feature) {
            $graph->addNode($this->featureNode($feature));

            if ($feature->use_case_id === null) {
                $graph->addEdge(new TraceEdge(
                    from: "module_spec:{$spec->id}",
                    to: "feature:{$feature->id}",
                    kind: 'unassigned',
                    label: 'unassigned',
                ));
            } else {
                $graph->addEdge(new TraceEdge(
                    from: "use_case:{$feature->use_case_id}",
                    to: "feature:{$feature->id}",
                    kind: 'implemented_by',
                    label: 'implemented by',
                ));
            }

            foreach ($feature->dataModels as $model) {
                $graph->addNode($this->dataModelNode($model));
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$feature->id}",
                    to: "data_model:{$model->id}",
                    kind: 'uses',
                    label: 'uses',
                ));

                foreach ($model->modelFields as $field) {
                    $graph->addNode($this->modelFieldNode($field));
                    $graph->addEdge(new TraceEdge(
                        from: "data_model:{$model->id}",
                        to: "model_field:{$field->id}",
                        kind: 'contains',
                        label: 'field',
                    ));
                }
            }

            foreach ($feature->implementationNodes as $node) {
                $graph->addNode($this->implementationNode($node));
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$feature->id}",
                    to: "implementation_node:{$node->id}",
                    kind: 'decomposes',
                    label: 'node',
                ));

                if ($node->parent_id !== null) {
                    $graph->addEdge(new TraceEdge(
                        from: "implementation_node:{$node->parent_id}",
                        to: "implementation_node:{$node->id}",
                        kind: 'parent',
                        label: 'child',
                    ));
                }

                foreach ($node->flowSteps as $step) {
                    $graph->addNode($this->flowStepNode($step));
                    $graph->addEdge(new TraceEdge(
                        from: "implementation_node:{$node->id}",
                        to: "flow_step:{$step->id}",
                        kind: 'evidenced_by',
                        label: 'flow',
                    ));
                }

                foreach ($node->commits as $commit) {
                    $graph->addNode($this->commitNode($commit));
                    $graph->addEdge(new TraceEdge(
                        from: "implementation_node:{$node->id}",
                        to: "commit:{$commit->id}",
                        kind: 'evidenced_by',
                        label: 'commit',
                    ));
                }
            }

            foreach ($feature->flowSteps as $step) {
                $graph->addNode($this->flowStepNode($step));
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$feature->id}",
                    to: "flow_step:{$step->id}",
                    kind: 'evidenced_by',
                    label: 'flow',
                ));
            }

            foreach ($feature->commits as $commit) {
                $graph->addNode($this->commitNode($commit));
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$feature->id}",
                    to: "commit:{$commit->id}",
                    kind: 'evidenced_by',
                    label: 'commit',
                ));
            }

            foreach ($feature->tests as $test) {
                $graph->addNode($this->testNode($test));
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$feature->id}",
                    to: "test:{$test->id}",
                    kind: 'verified_by',
                    label: 'verified by',
                ));
            }
        }

        foreach ($implementationEdges as $edge) {
            $graph->addEdge(new TraceEdge(
                from: "implementation_node:{$edge->from_node_id}",
                to: "implementation_node:{$edge->to_node_id}",
                kind: "plan_{$this->rawValue($edge, 'kind')}",
                label: $this->rawValue($edge, 'kind'),
                meta: ['condition' => $edge->condition],
            ));
        }

        foreach ($runs as $run) {
            $graph->addNode($this->workflowRunNode($run));
            $graph->addEdge(new TraceEdge(
                from: "module_spec:{$spec->id}",
                to: "workflow_run:{$run->id}",
                kind: 'executed_as',
                label: 'run',
            ));

            if ($run->feature_id !== null) {
                $graph->addEdge(new TraceEdge(
                    from: "feature:{$run->feature_id}",
                    to: "workflow_run:{$run->id}",
                    kind: 'executed_as',
                    label: 'run',
                ));
            }

            foreach ($run->events as $event) {
                $graph->addNode($this->runEventNode($event));
                $graph->addEdge(new TraceEdge(
                    from: "workflow_run:{$run->id}",
                    to: "run_event:{$event->id}",
                    kind: 'emits',
                    label: 'event',
                ));
            }

            foreach ($run->nodeRuns as $nodeRun) {
                $nodeRunNode = new TraceNode(
                    key: "node_run:{$nodeRun->id}",
                    layer: 'execution',
                    type: 'Node Run',
                    id: $nodeRun->id,
                    title: $nodeRun->implementationNode->title,
                    status: $this->rawValue($nodeRun, 'status'),
                    subtitle: $this->rawValue($nodeRun, 'mode'),
                    meta: ['Contract snapshot' => $nodeRun->contract_snapshot],
                );
                $graph->addNode($nodeRunNode);
                $graph->addEdge(new TraceEdge(
                    from: "workflow_run:{$run->id}",
                    to: $nodeRunNode->key,
                    kind: 'contains',
                    label: 'node run',
                ));
                $graph->addEdge(new TraceEdge(
                    from: "implementation_node:{$nodeRun->implementation_node_id}",
                    to: $nodeRunNode->key,
                    kind: 'instantiated_as',
                    label: 'run node',
                ));

                foreach ($nodeRun->events as $event) {
                    $graph->addNode($this->runEventNode($event));
                    $graph->addEdge(new TraceEdge(
                        from: $nodeRunNode->key,
                        to: "run_event:{$event->id}",
                        kind: 'emits',
                        label: 'event',
                    ));
                }
            }
        }

        return $graph;
    }

    private function moduleSpecNode(ModuleSpec $spec): TraceNode
    {
        return new TraceNode(
            key: "module_spec:{$spec->id}",
            layer: 'specs',
            type: 'Module Spec',
            id: $spec->id,
            title: $spec->module->name.' spec',
            status: $spec->status,
            subtitle: "v{$spec->version}",
            meta: [
                'Module' => $spec->module->name,
                'Summary' => $spec->summary,
                'Content' => $spec->content,
            ],
        );
    }

    private function useCaseNode(UseCase $useCase): TraceNode
    {
        return new TraceNode(
            key: "use_case:{$useCase->id}",
            layer: 'behavior',
            type: 'Use Case',
            id: $useCase->id,
            title: $useCase->goal,
            status: $this->rawValue($useCase, 'status'),
            subtitle: $useCase->actor,
            meta: [
                'Modules' => $useCase->modules->pluck('name')->implode(', '),
                'Trigger' => $useCase->trigger,
                'Precondition' => $useCase->precondition,
                'Success' => $useCase->success_outcome,
                'Failure' => $useCase->failure_outcome,
            ],
        );
    }

    private function scenarioNode(Scenario $scenario): TraceNode
    {
        return new TraceNode(
            key: "scenario:{$scenario->id}",
            layer: 'behavior',
            type: 'Scenario',
            id: $scenario->id,
            title: $scenario->name,
            status: $this->rawValue($scenario, 'status'),
            subtitle: $this->rawValue($scenario, 'type'),
            meta: [
                'Given' => $scenario->given,
                'When' => $scenario->when,
                'Then' => $scenario->then,
                'Coverage' => $scenario->coverage_dimension,
                'Class' => $scenario->equivalence_class,
                'Boundary' => $scenario->boundary,
            ],
        );
    }

    private function featureNode(Feature $feature): TraceNode
    {
        return new TraceNode(
            key: "feature:{$feature->id}",
            layer: 'delivery',
            type: 'Feature',
            id: $feature->id,
            title: $feature->title,
            status: $this->rawValue($feature, 'status'),
            subtitle: "#{$feature->number}",
            meta: [
                'Use case' => $feature->useCase?->goal,
                'Entry' => $feature->entry,
                'Layers' => implode(', ', $feature->layers ?? []),
                'Triggers' => implode(', ', $feature->triggers ?? []),
            ],
        );
    }

    private function dataModelNode(DataModel $model): TraceNode
    {
        return new TraceNode(
            key: "data_model:{$model->id}",
            layer: 'solution',
            type: 'Data Model',
            id: $model->id,
            title: $model->name,
            status: $this->rawValue($model, 'status'),
            subtitle: $model->table_name,
        );
    }

    private function modelFieldNode(ModelField $field): TraceNode
    {
        return new TraceNode(
            key: "model_field:{$field->id}",
            layer: 'solution',
            type: 'Model Field',
            id: $field->id,
            title: $field->name,
            status: $this->rawValue($field, 'status'),
            subtitle: $field->type,
        );
    }

    private function implementationNode(ImplementationNode $node): TraceNode
    {
        return new TraceNode(
            key: "implementation_node:{$node->id}",
            layer: 'delivery',
            type: 'Implementation Node',
            id: $node->id,
            title: $node->title,
            status: $this->rawValue($node, 'state'),
            subtitle: $this->rawValue($node, 'kind'),
            evidenceCount: $node->flowSteps->count() + $node->commits->count(),
            meta: [
                'Contract' => $node->contract,
                'Evidence required' => $node->evidence_required,
            ],
        );
    }

    private function workflowRunNode(WorkflowRun $run): TraceNode
    {
        return new TraceNode(
            key: "workflow_run:{$run->id}",
            layer: 'execution',
            type: 'Workflow Run',
            id: $run->id,
            title: "Run #{$run->id}",
            status: $this->rawValue($run, 'status'),
            subtitle: $run->feature?->title,
            meta: [
                'Graph version' => $run->graph_version,
                'Started' => $run->started_at?->toDateTimeString(),
                'Finished' => $run->finished_at?->toDateTimeString(),
            ],
        );
    }

    private function runEventNode(RunEvent $event): TraceNode
    {
        return new TraceNode(
            key: "run_event:{$event->id}",
            layer: 'execution',
            type: 'Run Event',
            id: $event->id,
            title: $this->rawValue($event, 'event_type'),
            status: $this->rawValue($event, 'event_type'),
            subtitle: $event->created_at->toDateTimeString(),
            meta: ['Payload' => $event->payload],
        );
    }

    private function testNode(Test $test): TraceNode
    {
        return new TraceNode(
            key: "test:{$test->id}",
            layer: 'evidence',
            type: 'Test',
            id: $test->id,
            title: $test->title,
            status: $this->rawValue($test, 'status'),
            subtitle: $this->rawValue($test, 'last_result'),
            meta: ['Location' => $test->location],
        );
    }

    private function flowStepNode(FlowStep $step): TraceNode
    {
        return new TraceNode(
            key: "flow_step:{$step->id}",
            layer: 'evidence',
            type: 'Flow Step',
            id: $step->id,
            title: $step->step,
            status: "step {$step->order}",
            subtitle: $step->function,
            meta: [
                'Path' => $step->path,
                'File' => $step->file,
                'Input' => $step->input,
                'Change' => $step->change,
                'Output' => $step->output,
            ],
        );
    }

    private function commitNode(Commit $commit): TraceNode
    {
        return new TraceNode(
            key: "commit:{$commit->id}",
            layer: 'evidence',
            type: 'Commit',
            id: $commit->id,
            title: $commit->subject,
            status: substr($commit->hash, 0, 8),
            subtitle: $commit->author,
            meta: [
                'Committed at' => $commit->committed_at->toDateTimeString(),
                'Body' => $commit->body,
            ],
        );
    }

    private function rawValue(Model $model, string $attribute): string
    {
        $value = $model->getRawOriginal($attribute);

        return is_scalar($value) ? (string) $value : '—';
    }
}
