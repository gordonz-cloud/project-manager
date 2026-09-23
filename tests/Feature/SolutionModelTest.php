<?php

use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Enums\NodeRunMode;
use App\Enums\NodeRunStatus;
use App\Enums\RunEventType;
use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\ModuleSpec;
use App\Models\NodeRun;
use App\Models\Project;
use App\Models\RunEvent;
use App\Models\Scenario;
use App\Models\Test as TestModel;
use App\Models\UseCase;
use App\Models\WorkflowRun;
use LogicException;

test('module spec and use case hierarchy connect the executable behavior chain', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create([
        'status' => UseCaseStatus::Ready,
    ]);
    $scenario = Scenario::factory()->create([
        'project_id' => $project->id,
        'use_case_id' => $useCase->id,
        'type' => ScenarioType::Happy,
        'priority' => ScenarioPriority::High,
        'status' => ScenarioStatus::Ready,
    ]);
    expect($spec->useCases()->first()->is($useCase))->toBeTrue()
        ->and($useCase->scenarios()->first()->is($scenario))->toBeTrue()
        ->and($useCase->status)->toBe(UseCaseStatus::Ready)
        ->and($scenario->type)->toBe(ScenarioType::Happy);
});

test('implementation graph connects scenarios to nodes and evidence', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $scenario = Scenario::factory()->create([
        'project_id' => $project->id,
        'use_case_id' => $useCase->id,
    ]);
    $first = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'kind' => ImplementationNodeKind::Code,
        'state' => ImplementationNodeState::Accepted,
    ]);
    $second = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'parent_id' => $first->id,
        'kind' => ImplementationNodeKind::Test,
    ]);
    $edge = ImplementationNodeEdge::factory()->create([
        'project_id' => $project->id,
        'from_node_id' => $first->id,
        'to_node_id' => $second->id,
        'kind' => ImplementationNodeEdgeKind::Forward,
    ]);
    $flowStep = FlowStep::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'implementation_node_id' => $first->id,
    ]);
    $test = TestModel::factory()->create([
        'project_id' => $project->id,
        'scenario_id' => $scenario->id,
    ]);
    $commit = Commit::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'implementation_node_id' => $second->id,
    ]);

    $scenario->implementationNodes()->attach($first);

    expect($feature->useCase()->first()->is($useCase))->toBeTrue()
        ->and($feature->implementationNodes()->count())->toBe(2)
        ->and($first->children()->first()->is($second))->toBeTrue()
        ->and($first->outgoingEdges()->first()->is($edge))->toBeTrue()
        ->and($second->incomingEdges()->first()->is($edge))->toBeTrue()
        ->and($edge->fromNode()->first()->is($first))->toBeTrue()
        ->and($edge->toNode()->first()->is($second))->toBeTrue()
        ->and($first->scenarios()->first()->is($scenario))->toBeTrue()
        ->and($first->flowSteps()->first()->is($flowStep))->toBeTrue()
        ->and($scenario->tests()->first()->is($test))->toBeTrue()
        ->and($second->commits()->first()->is($commit))->toBeTrue();
});

test('runtime keeps focus, node state, and append-only events', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $node = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $run = WorkflowRun::factory()->forUseCase($useCase)->create([
        'feature_id' => $feature->id,
        'status' => WorkflowRunStatus::Running,
    ]);
    $nodeRun = NodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'implementation_node_id' => $node->id,
        'mode' => NodeRunMode::Sticky,
        'status' => NodeRunStatus::Talking,
    ]);

    $run->update(['focus_node_run_id' => $nodeRun->id]);

    $entered = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
        'node_run_id' => $nodeRun->id,
        'event_type' => RunEventType::Entered,
    ]);
    $message = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
        'node_run_id' => $nodeRun->id,
        'event_type' => RunEventType::MessageAdded,
        'payload' => ['role' => 'user', 'content' => '继续这个节点'],
    ]);

    expect($run->focusNodeRun()->first()->is($nodeRun))->toBeTrue()
        ->and($nodeRun->workflowRun()->first()->is($run))->toBeTrue()
        ->and($nodeRun->implementationNode()->first()->is($node))->toBeTrue()
        ->and($nodeRun->mode)->toBe(NodeRunMode::Sticky)
        ->and($nodeRun->status)->toBe(NodeRunStatus::Talking)
        ->and($run->events()->count())->toBe(2)
        ->and($message->payload)->toBe(['role' => 'user', 'content' => '继续这个节点'])
        ->and($entered->created_at)->not->toBeNull();

    expect(fn () => $message->update(['payload' => ['role' => 'user']]))
        ->toThrow(LogicException::class);

    expect(fn () => $message->delete())
        ->toThrow(LogicException::class);

    expect(fn () => $nodeRun->delete())
        ->toThrow(LogicException::class);

    expect(fn () => $run->delete())
        ->toThrow(LogicException::class);
});

test('an implementation node cannot become its own ancestor', function () {
    $project = Project::factory()->create();
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    $root = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $child = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'parent_id' => $root->id,
    ]);

    expect(ImplementationNode::wouldCycle($root->id, $child->id))->toBeTrue();

    expect(fn () => $root->update(['parent_id' => $child->id]))
        ->toThrow(LogicException::class);

    expect(fn () => $root->update(['parent_id' => $root->id]))
        ->toThrow(LogicException::class);
});

test('use cases span several modules and features stay under their use case', function () {
    $project = Project::factory()->create();
    $billing = ModuleSpec::factory()->forProject($project)->create();
    $shipping = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($billing->module)->create();
    $useCase->modules()->attach($shipping->module);
    $feature = Feature::factory()->forUseCase($useCase)->create(['module_id' => $shipping->module_id]);

    expect($billing->useCases()->first()->is($useCase))->toBeTrue()
        ->and($shipping->module->useCases()->first()->is($useCase))->toBeTrue()
        ->and($useCase->modules()->count())->toBe(2)
        ->and($feature->module_id)->toBe($shipping->module_id)
        ->and($feature->project_id)->toBe($project->id);

    $outsider = ModuleSpec::factory()->forProject($project)->create();

    expect(fn () => Feature::factory()->forUseCase($useCase)->create(['module_id' => $outsider->module_id]))
        ->toThrow(LogicException::class);
});

test('a workflow run stays inside its use case', function () {
    $project = Project::factory()->create();
    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $otherUseCase = UseCase::factory()->forModule($spec->module)->create();
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $otherFeature = Feature::factory()->forUseCase($otherUseCase)->create();
    $otherNode = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $otherFeature->id,
    ]);

    $derived = WorkflowRun::factory()->create([
        'use_case_id' => null,
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);

    expect($derived->use_case_id)->toBe($useCase->id);

    expect(fn () => WorkflowRun::factory()->forUseCase($useCase)->create(['feature_id' => $otherFeature->id]))
        ->toThrow(LogicException::class, 'workflow use case');

    $run = WorkflowRun::factory()->forUseCase($useCase)->create();

    expect(fn () => NodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'implementation_node_id' => $otherNode->id,
    ]))->toThrow(LogicException::class, 'workflow use case');
});
