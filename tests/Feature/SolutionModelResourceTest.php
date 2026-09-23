<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\ImplementationNodes\Pages\EditImplementationNode;
use App\Filament\Resources\ImplementationNodes\Pages\ListImplementationNodes;
use App\Filament\Resources\ImplementationNodes\RelationManagers\FlowStepsRelationManager;
use App\Filament\Resources\ImplementationNodes\RelationManagers\OutgoingEdgesRelationManager;
use App\Filament\Resources\Modules\Pages\CreateModule;
use App\Filament\Resources\ModuleSpecs\Pages\CreateModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\EditModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\ListModuleSpecs;
use App\Filament\Resources\ModuleSpecs\RelationManagers\UseCasesRelationManager;
use App\Filament\Resources\Scenarios\Pages\EditScenario;
use App\Filament\Resources\Scenarios\Pages\ListScenarios;
use App\Filament\Resources\Scenarios\RelationManagers\ImplementationNodesRelationManager;
use App\Filament\Resources\UseCaseGroups\Pages\CreateUseCaseGroup;
use App\Filament\Resources\UseCaseGroups\Pages\ListUseCaseGroups;
use App\Filament\Resources\UseCases\Pages\EditUseCase;
use App\Filament\Resources\UseCases\Pages\ListUseCases;
use App\Filament\Resources\UseCases\RelationManagers\FeaturesRelationManager as FeatureUseCaseRelationManager;
use App\Filament\Resources\WorkflowRuns\Pages\EditWorkflowRun;
use App\Filament\Resources\WorkflowRuns\Pages\ListWorkflowRuns;
use App\Filament\Resources\WorkflowRuns\RelationManagers\EventsRelationManager;
use App\Filament\Resources\WorkflowRuns\RelationManagers\NodeRunsRelationManager;
use App\Models\Feature;
use App\Models\FlowStep;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\NodeRun;
use App\Models\Project;
use App\Models\RunEvent;
use App\Models\Scenario;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\User;
use App\Models\WorkflowRun;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

function solutionModelContext(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    $spec = ModuleSpec::factory()->forProject($project)->create();
    $useCase = UseCase::factory()->forModule($spec->module)->create();
    $scenario = Scenario::factory()->create([
        'project_id' => $project->id,
        'use_case_id' => $useCase->id,
    ]);
    $feature = Feature::factory()->forUseCase($useCase)->create();
    $node = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $target = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
    ]);
    $edge = ImplementationNodeEdge::factory()->create([
        'project_id' => $project->id,
        'from_node_id' => $node->id,
        'to_node_id' => $target->id,
    ]);
    $run = WorkflowRun::factory()->forUseCase($useCase)->create([
        'feature_id' => $feature->id,
    ]);
    $nodeRun = NodeRun::factory()->create([
        'workflow_run_id' => $run->id,
        'implementation_node_id' => $node->id,
    ]);
    $event = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
        'node_run_id' => $nodeRun->id,
    ]);

    return compact(
        'project',
        'spec',
        'useCase',
        'scenario',
        'feature',
        'node',
        'target',
        'edge',
        'run',
        'nodeRun',
        'event',
    );
}

test('solution model resources list their tenant records', function () {
    $records = solutionModelContext();

    Livewire::test(ListModuleSpecs::class)->assertCanSeeTableRecords([$records['spec']]);
    Livewire::test(ListUseCases::class)->assertCanSeeTableRecords([$records['useCase']]);
    Livewire::test(ListScenarios::class)->assertCanSeeTableRecords([$records['scenario']]);
    Livewire::test(ListImplementationNodes::class)->assertCanSeeTableRecords([$records['node']]);
    Livewire::test(ListWorkflowRuns::class)->assertCanSeeTableRecords([$records['run']]);
});

test('a module spec can be created for a module once', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    $module = Module::factory()->create([
        'project_id' => $project->id,
        'name' => 'Spec module',
    ]);

    auth()->login($user);
    Filament::setTenant($project);

    Livewire::test(CreateModuleSpec::class)
        ->fillForm([
            'module_id' => $module->id,
            'version' => 1,
            'status' => 'active',
            'summary' => 'Complete module spec',
            'content' => 'This spec covers the module.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ModuleSpec::query()->where('module_id', $module->id)->count())->toBe(1);
});

test('creating a module requires its spec content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    Livewire::test(CreateModule::class)
        ->fillForm(['name' => 'Orders'])
        ->call('create')
        ->assertHasFormErrors(['spec.content' => 'required']);

    Livewire::test(CreateModule::class)
        ->fillForm(['name' => 'Orders', 'spec.content' => 'Owns the order lifecycle.'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Module::query()->where('name', 'Orders')->sole()->spec->content)
        ->toBe('Owns the order lifecycle.');
});

test('solution model relation managers render the linked records', function () {
    $records = solutionModelContext();
    $records['scenario']->implementationNodes()->attach($records['node']);

    Livewire::test(UseCasesRelationManager::class, [
        'ownerRecord' => $records['spec'],
        'pageClass' => EditModuleSpec::class,
    ])->assertCanSeeTableRecords([$records['useCase']]);

    Livewire::test(ImplementationNodesRelationManager::class, [
        'ownerRecord' => $records['scenario'],
        'pageClass' => EditScenario::class,
    ])->assertCanSeeTableRecords([$records['node']]);

    Livewire::test(OutgoingEdgesRelationManager::class, [
        'ownerRecord' => $records['node'],
        'pageClass' => EditImplementationNode::class,
    ])->assertCanSeeTableRecords([$records['edge']]);

    Livewire::test(NodeRunsRelationManager::class, [
        'ownerRecord' => $records['run'],
        'pageClass' => EditWorkflowRun::class,
    ])->assertCanSeeTableRecords([$records['nodeRun']]);

    Livewire::test(EventsRelationManager::class, [
        'ownerRecord' => $records['run'],
        'pageClass' => EditWorkflowRun::class,
    ])->assertCanSeeTableRecords([$records['event']]);
});

test('runtime relation managers only offer records from the owning run and feature', function () {
    $records = solutionModelContext();
    $otherUseCase = UseCase::factory()->forModule($records['spec']->module)->create();
    $otherFeature = Feature::factory()->forUseCase($otherUseCase)->create();
    $otherNode = ImplementationNode::factory()->create([
        'project_id' => $records['project']->id,
        'feature_id' => $otherFeature->id,
    ]);
    $otherRun = WorkflowRun::factory()->forUseCase($otherUseCase)->create([
        'feature_id' => $otherFeature->id,
    ]);
    $otherNodeRun = NodeRun::factory()->create([
        'workflow_run_id' => $otherRun->id,
        'implementation_node_id' => $otherNode->id,
    ]);

    Livewire::test(EventsRelationManager::class, [
        'ownerRecord' => $records['run'],
        'pageClass' => EditWorkflowRun::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'node_run_id' => $otherNodeRun->id,
            'event_type' => 'entered',
        ])
        ->assertHasFormErrors(['node_run_id']);

    Livewire::test(NodeRunsRelationManager::class, [
        'ownerRecord' => $records['run'],
        'pageClass' => EditWorkflowRun::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'implementation_node_id' => $otherNode->id,
            'mode' => 'oneshot',
            'status' => 'pending',
            'contract_snapshot' => ['contract' => 'test'],
        ])
        ->assertHasFormErrors(['implementation_node_id']);
});

test('owner-derived relation managers create records with the owner context', function () {
    $records = solutionModelContext();

    Livewire::test(UseCasesRelationManager::class, [
        'ownerRecord' => $records['spec'],
        'pageClass' => EditModuleSpec::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'use_case_group_id' => $records['useCase']->use_case_group_id,
            'actor' => '客户',
            'goal' => 'Owner context use case',
            'success_outcome' => 'Use case is created',
            'status' => 'draft',
            'spec' => ['content' => '客户下单后跨模块流转。'],
        ])
        ->assertHasNoFormErrors();

    $useCase = UseCase::query()->where('goal', 'Owner context use case')->sole();

    expect($useCase->modules()->pluck('modules.id')->all())->toBe([$records['spec']->module_id])
        ->and($useCase->spec?->content)->toBe('客户下单后跨模块流转。');

    Livewire::test(FeatureUseCaseRelationManager::class, [
        'ownerRecord' => $records['useCase'],
        'pageClass' => EditUseCase::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'title' => 'Owner context feature',
            'status' => FeatureStatus::Todo->value,
            'layers' => ['后端'],
            'triggers' => ['HTTP'],
            'entry' => 'owner-context',
        ])
        ->assertHasNoFormErrors();

    $feature = Feature::query()->where('title', 'Owner context feature')->sole();

    expect($feature->use_case_id)->toBe($records['useCase']->id);

    Livewire::test(FlowStepsRelationManager::class, [
        'ownerRecord' => $records['node'],
        'pageClass' => EditImplementationNode::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'step' => 'Owner context step',
            'path' => '主路径',
            'order' => 1,
            'output' => 'ok',
        ])
        ->assertHasNoFormErrors();

    $flowStep = FlowStep::query()->where('step', 'Owner context step')->sole();

    expect($flowStep->feature_id)->toBe($records['feature']->id)
        ->and($flowStep->implementation_node_id)->toBe($records['node']->id);
});

test('use case groups are listed per project and can be created', function () {
    $records = solutionModelContext();
    $otherGroup = UseCaseGroup::factory()->create(['name' => 'Other tenant group']);

    Livewire::test(ListUseCaseGroups::class)
        ->assertCanSeeTableRecords([$records['useCase']->group])
        ->assertCanNotSeeTableRecords([$otherGroup]);

    Livewire::test(CreateUseCaseGroup::class)
        ->fillForm(['name' => 'Checkout', 'sort_order' => 3])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(UseCaseGroup::query()->where('name', 'Checkout')->value('project_id'))->toBe($records['project']->id);
});
