<?php

use App\Enums\FeatureStatus;
use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Enums\NavigationGroup;
use App\Enums\NodeRunMode;
use App\Enums\NodeRunStatus;
use App\Enums\RunEventType;
use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Filament\Pages\WorkbenchGraph;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\NodeRun;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RunEvent;
use App\Models\Scenario;
use App\Models\Test as TestModel;
use App\Models\UseCase;
use App\Models\User;
use App\Models\WorkflowRun;
use Filament\Facades\Filament;
use Livewire\Livewire;

function workbenchGraphContext(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    $module = Module::factory()->create([
        'project_id' => $project->id,
        'name' => 'Trace module',
    ]);
    $spec = ModuleSpec::factory()->create([
        'project_id' => $project->id,
        'module_id' => $module->id,
        'status' => 'active',
        'summary' => 'Trace spec',
    ]);
    $useCase = UseCase::factory()->forModule($module)->create([
        'status' => UseCaseStatus::Ready,
        'goal' => 'Trace goal',
    ]);
    $scenario = Scenario::factory()->create([
        'project_id' => $project->id,
        'use_case_id' => $useCase->id,
        'name' => 'Trace scenario',
        'type' => ScenarioType::Happy,
        'priority' => ScenarioPriority::Normal,
        'status' => ScenarioStatus::Ready,
    ]);
    $feature = Feature::factory()->forUseCase($useCase)->create([
        'title' => 'Trace feature',
        'status' => FeatureStatus::Todo,
    ]);
    $node = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'kind' => ImplementationNodeKind::Code,
        'state' => ImplementationNodeState::Accepted,
        'title' => 'Trace node',
    ]);
    $secondNode = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'kind' => ImplementationNodeKind::Test,
        'state' => ImplementationNodeState::Proposed,
        'title' => 'Trace verification node',
    ]);
    ImplementationNodeEdge::factory()->create([
        'project_id' => $project->id,
        'from_node_id' => $node->id,
        'to_node_id' => $secondNode->id,
        'kind' => ImplementationNodeEdgeKind::Back,
    ]);
    $test = TestModel::factory()->create([
        'project_id' => $project->id,
        'scenario_id' => $scenario->id,
        'title' => 'Trace test',
        'status' => TestStatus::Valid,
        'last_result' => TestLastResult::Passed,
    ]);
    $feature->tests()->attach($test);
    $commit = Commit::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'implementation_node_id' => $node->id,
        'subject' => 'Trace commit',
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
    $event = RunEvent::factory()->create([
        'workflow_run_id' => $run->id,
        'node_run_id' => $nodeRun->id,
        'event_type' => RunEventType::Entered,
    ]);

    return compact(
        'project',
        'module',
        'spec',
        'useCase',
        'scenario',
        'feature',
        'node',
        'secondNode',
        'test',
        'commit',
        'run',
        'nodeRun',
        'event',
    );
}

test('workbench graph page uses the expected navigation contract', function () {
    expect(WorkbenchGraph::getNavigationLabel())->toBe('关系工作台')
        ->and(WorkbenchGraph::getNavigationGroup())->toBe(NavigationGroup::Scope)
        ->and(WorkbenchGraph::getNavigationSort())->toBe(0);
});

test('workbench graph renders the spec tree and detail pane', function () {
    workbenchGraphContext();

    Livewire::test(WorkbenchGraph::class)
        ->assertSee('关系工作台')
        ->assertSee('数据库关系树')
        ->assertSee('数据库关系树与详情')
        ->assertSee('Trace module')
        ->assertSee('Trace spec')
        ->assertSee('module_use_cases')
        ->assertSee('字段详情')
        ->assertSee('状态与转换')
        ->assertDontSee('全局关系图')
        ->assertDontSee('选中节点 Inspector')
        ->assertSeeHtml('xl:grid-cols-2');
});

test('workbench graph expands tree groups and selects nodes', function () {
    $records = workbenchGraphContext();

    Livewire::test(WorkbenchGraph::class)
        ->assertSet('focusMode', false)
        ->call('toggleTreeGroup', 'use_cases')
        ->call('selectNode', "scenario:{$records['scenario']->id}")
        ->assertSet('selectedKey', "scenario:{$records['scenario']->id}")
        ->assertSet('focusMode', true)
        ->assertSet('expandedTreeGroups.use_cases', true)
        ->call('setFocusDepth', 1)
        ->assertSet('focusDepth', 1)
        ->set('search', 'Trace scenario')
        ->assertSee('Trace scenario');
});

test('workbench graph shows state machine states and transitions', function () {
    $records = workbenchGraphContext();

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "feature:{$records['feature']->id}")
        ->assertSee('状态与转换')
        ->assertSee('当前：待做')
        ->assertSee('开发中')
        ->assertSee('开始开发')
        ->assertSee('验收失败');
});

test('workbench graph exposes use cases and evidence in the tree', function () {
    $records = workbenchGraphContext();

    Livewire::test(WorkbenchGraph::class)
        ->call('toggleTreeGroup', 'use_cases')
        ->call('toggleTreeUseCase', $records['useCase']->id)
        ->assertSee($records['scenario']->name)
        ->assertSeeHtml('wire:click="selectNode(\'feature:'.$records['feature']->id.'\')"')
        ->call('toggleTreeFeature', $records['feature']->id)
        ->assertSee($records['node']->title)
        ->assertSee($records['test']->title)
        ->assertSee($records['commit']->title)
        ->call('selectNode', "feature:{$records['feature']->id}")
        ->assertSet('selectedKey', "feature:{$records['feature']->id}")
        ->assertSee($records['feature']->title);
});

test('workbench graph shows unassigned legacy features under the spec', function () {
    $records = workbenchGraphContext();
    $requirement = Requirement::factory()->create([
        'project_id' => $records['project']->id,
        'title' => 'Legacy source requirement',
    ]);
    $requirement->modules()->attach($records['module']);
    $feature = Feature::factory()->create([
        'project_id' => $records['project']->id,
        'requirement_id' => $requirement->id,
        'title' => 'Feature linked test',
        'status' => FeatureStatus::Done,
    ]);
    $test = TestModel::factory()->create([
        'project_id' => $records['project']->id,
        'scenario_id' => null,
        'title' => 'Feature only evidence',
        'status' => TestStatus::Valid,
        'last_result' => TestLastResult::Passed,
    ]);
    $feature->tests()->attach($test);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectSpec', $records['spec']->id)
        ->assertSee('Unassigned Features')
        ->call('toggleTreeGroup', 'unassigned_features')
        ->call('toggleTreeFeature', $feature->id)
        ->assertSee('Feature only evidence');
});

test('workbench graph switches project and module scopes without crossing tenants', function () {
    $records = workbenchGraphContext();
    $otherProject = Project::factory()->create();
    $otherSpec = ModuleSpec::factory()->forProject($otherProject)->create();
    $otherModule = Module::factory()->create([
        'project_id' => $otherProject->id,
        'name' => 'Other tenant module',
    ]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectProjectScope')
        ->assertSet('scopeType', 'project')
        ->assertSet('focusMode', false)
        ->call('startModuleScope')
        ->assertSet('scopeType', 'module')
        ->call('selectModuleScope', $records['module']->id)
        ->assertSet('selectedKey', "module:{$records['module']->id}");

    Livewire::test(WorkbenchGraph::class)
        ->set('moduleSpecId', $otherSpec->id)
        ->assertSet('moduleSpecId', null)
        ->assertSet('selectedKey', null);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectSpec', $otherSpec->id)
        ->assertNotFound();

    Livewire::test(WorkbenchGraph::class)
        ->call('selectModuleScope', $otherModule->id)
        ->assertNotFound();
});

test('workbench graph escapes tenant content in the graph', function () {
    $records = workbenchGraphContext();
    $records['spec']->update(['summary' => "<script>alert('xss')</script>"]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectSpec', $records['spec']->id)
        ->assertSeeHtml('&lt;script&gt;')
        ->assertDontSeeHtml("<script>alert('xss')</script>");
});
