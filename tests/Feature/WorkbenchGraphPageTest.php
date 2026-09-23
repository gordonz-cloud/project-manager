<?php

use App\Enums\FeatureStatus;
use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Enums\NavigationGroup;
use App\Enums\RequestReplyEdgeKind;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Filament\Pages\WorkbenchGraph;
use App\Filament\Support\WorkbenchGraphPresenter;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\RequestReplyEdge;
use App\Models\Scenario;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\UseCaseSpec;
use App\Models\User;
use App\Models\WorkflowRun;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * @return array{project: Project, group: UseCaseGroup, module: Module, moduleSpec: ModuleSpec, useCase: UseCase, useCaseSpec: UseCaseSpec, scenario: Scenario, feature: Feature, requestReply: RequestReply, node: ImplementationNode, dataModel: DataModel, field: ModelField, run: WorkflowRun}
 */
function workbenchContext(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);

    auth()->login($user);
    Filament::setTenant($project);

    $group = UseCaseGroup::factory()->create(['project_id' => $project->id, 'name' => 'Checkout group', 'sort_order' => 1]);
    $module = Module::factory()->create(['project_id' => $project->id, 'name' => 'Trace module']);
    $moduleSpec = ModuleSpec::factory()->create([
        'project_id' => $project->id,
        'module_id' => $module->id,
        'status' => 'active',
        'content' => "## Module heading\n\nModule body",
    ]);
    $useCase = UseCase::factory()->forModule($module)->create([
        'use_case_group_id' => $group->id,
        'status' => UseCaseStatus::Ready,
        'goal' => 'Trace goal',
    ]);
    $useCaseSpec = UseCaseSpec::factory()->create(['use_case_id' => $useCase->id, 'content' => 'Use case flow']);
    $scenario = Scenario::factory()->create(['project_id' => $project->id, 'use_case_id' => $useCase->id, 'name' => 'Trace scenario']);
    $feature = Feature::factory()->forUseCase($useCase)->create([
        'title' => 'Trace feature',
        'status' => FeatureStatus::Done,
        'entry' => 'Feature entry text',
    ]);
    $requestReply = RequestReply::factory()->create(['use_case_id' => $useCase->id, 'method' => 'POST', 'entry' => '/trace', 'title' => 'Trace entry', 'module_id' => $module->id]);
    $feature->requestReplies()->attach($requestReply);
    $node = ImplementationNode::factory()->create([
        'project_id' => $project->id,
        'feature_id' => $feature->id,
        'kind' => ImplementationNodeKind::Code,
        'state' => ImplementationNodeState::Accepted,
        'title' => 'Trace node',
    ]);
    $dataModel = DataModel::factory()->create(['project_id' => $project->id, 'name' => 'Trace data model']);
    $field = ModelField::factory()->create(['project_id' => $project->id, 'data_model_id' => $dataModel->id, 'name' => 'trace_field']);
    $feature->dataModels()->attach($dataModel);
    $run = WorkflowRun::factory()->forUseCase($useCase)->create(['feature_id' => $feature->id, 'status' => WorkflowRunStatus::Running]);

    return compact('project', 'group', 'module', 'moduleSpec', 'useCase', 'useCaseSpec', 'scenario', 'feature', 'requestReply', 'node', 'dataModel', 'field', 'run');
}

test('workbench graph page uses the expected navigation contract', function () {
    expect(WorkbenchGraph::getNavigationLabel())->toBe('关系工作台')
        ->and(WorkbenchGraph::getNavigationGroup())->toBe(NavigationGroup::Scope)
        ->and(WorkbenchGraph::getNavigationSort())->toBe(0);
});

test('the tree starts at use case groups, then use cases, then their modules and features', function () {
    $records = workbenchContext();

    $page = Livewire::test(WorkbenchGraph::class)
        ->assertSet('selectedKey', "use_case:{$records['useCase']->id}");
    $tree = $page->instance()->tree;
    $useCase = $tree[0]->children[0];
    $folders = collect($useCase->children)->keyBy('label');

    expect($tree[0]->key)->toBe("use_case_group:{$records['group']->id}")
        ->and($useCase->key)->toBe("use_case:{$records['useCase']->id}")
        ->and($useCase->badge)->toBe('1/1')
        ->and($useCase->children[0]->key)->toBe("use_case_spec:{$records['useCaseSpec']->id}")
        ->and($folders->keys()->all())->toBe(['Use Case Spec', '模块', '场景', '功能', '执行记录'])
        ->and($folders['模块']->children[0]->key)->toBe("module:{$records['module']->id}")
        ->and($folders['功能']->children[0]->key)->toBe("feature:{$records['feature']->id}")
        ->and($folders['功能']->children[0]->badge)->toBe('Trace module')
        ->and($folders['功能']->children[0]->children[0]->children[0]->key)->toBe("request_reply:{$records['requestReply']->id}")
        ->and($folders['功能']->children[0]->children[0]->children[0]->label)->toBe('① POST /trace');

    $module = $folders['模块']->children[0];
    expect(collect($module->children)->pluck('key')->all())->toBe([
        "module_spec:{$records['moduleSpec']->id}",
        "module:{$records['module']->id}#data_models",
    ])->and($module->children[1]->children[0]->children[0]->key)->toBe("model_field:{$records['field']->id}");

    $page->assertSeeInOrder(['Checkout group', 'Trace goal']);
});

test('the tree shows business names only, never table or column names', function () {
    workbenchContext();

    Livewire::test(WorkbenchGraph::class)
        ->set('search', 'trace')
        ->assertSee(['Trace module', 'Trace feature', 'trace_field', 'Trace scenario'])
        ->assertDontSee(['module_use_cases', 'use_case_id', 'data_model_feature', '未建立']);
});

test('search filters the tree and opens the branches that match', function () {
    $records = workbenchContext();
    UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Unrelated goal']);

    Livewire::test(WorkbenchGraph::class)
        ->assertSee('Unrelated goal')
        ->assertDontSee('Trace scenario')
        ->set('search', 'Trace scenario')
        ->assertSee('Trace scenario')
        ->assertDontSee('Unrelated goal');
});

test('toggling a row opens its children', function () {
    $records = workbenchContext();
    $useCasePath = "use_case_group:{$records['group']->id}>use_case:{$records['useCase']->id}";

    Livewire::test(WorkbenchGraph::class)
        ->assertDontSee('Trace scenario')
        ->call('toggleNode', $useCasePath)
        ->call('toggleNode', "{$useCasePath}>use_case:{$records['useCase']->id}#scenarios")
        ->assertSee('Trace scenario');
});

test('the detail panel shows one rendered text block and no relation lists', function () {
    $records = workbenchContext();

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "module_spec:{$records['moduleSpec']->id}")
        ->assertSeeHtml('<h2>Module heading</h2>')
        ->assertSee('编辑')
        ->assertDontSee(['上游', '下游', '字段详情', '打开原始记录'])
        ->call('selectNode', "feature:{$records['feature']->id}")
        ->assertSee('Feature entry text');
});

test('the selected node survives a reload through the url and opens its branch', function () {
    $records = workbenchContext();
    $featureKey = "feature:{$records['feature']->id}";

    Livewire::withQueryParams(['selectedKey' => $featureKey])
        ->test(WorkbenchGraph::class)
        ->assertSet('selectedKey', $featureKey)
        ->assertSee('Trace feature')
        ->assertSee('Feature entry text');
});

test('features without a use case sit in their own bucket', function () {
    $records = workbenchContext();
    Feature::factory()->create(['project_id' => $records['project']->id, 'use_case_id' => null, 'title' => 'Loose feature']);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;

    expect(end($tree)->label)->toBe('未归入 Use Case')
        ->and(end($tree)->children[0]->label)->toBe('Loose feature');
});

test('records from another project cannot be selected', function () {
    $records = workbenchContext();
    $otherFeature = Feature::factory()->create(['project_id' => Project::factory()->create()->id]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "feature:{$otherFeature->id}")
        ->assertNotFound();

    Livewire::withQueryParams(['selectedKey' => "feature:{$otherFeature->id}"])
        ->test(WorkbenchGraph::class)
        ->assertSet('selectedKey', "use_case:{$records['useCase']->id}");
});

test('the edit slide-over saves the selected record', function () {
    $records = workbenchContext();

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "use_case_group:{$records['group']->id}")
        ->callAction('edit', data: ['name' => 'Renamed group'])
        ->assertHasNoActionErrors()
        ->assertSee('Renamed group');

    expect($records['group']->fresh()->name)->toBe('Renamed group');
});

test('markdown in specs is escaped', function () {
    $records = workbenchContext();
    $records['useCaseSpec']->update(['content' => "<script>alert('xss')</script>"]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "use_case_spec:{$records['useCaseSpec']->id}")
        ->assertDontSeeHtml("<script>alert('xss')</script>");
});

test('entries under features carry the use case number that scenario paths use', function () {
    $records = workbenchContext();
    $login = $records['requestReply'];
    $entry = fn (string $path): RequestReply => RequestReply::factory()->create(['use_case_id' => $records['useCase']->id, 'method' => 'GET', 'entry' => $path]);
    $home = $entry('/home');
    $error = $entry('/error');
    $records['feature']->requestReplies()->attach([$home->id, $error->id]);
    RequestReplyEdge::factory()->create(['from_request_reply_id' => $login->id, 'to_request_reply_id' => $error->id, 'kind' => RequestReplyEdgeKind::OnFailure]);
    $records['scenario']->replaceSteps([$login->id, $error->id, $login->id, $home->id]);

    $folders = collect(Livewire::test(WorkbenchGraph::class)->instance()->tree[0]->children[0]->children)->keyBy('label');
    $entries = collect($folders['功能']->children[0]->children[0]->children)->mapWithKeys(fn ($node): array => [$node->key => $node->label]);

    expect($entries->all())->toBe([
        "request_reply:{$login->id}" => '① POST /trace',
        "request_reply:{$home->id}" => '② GET /home',
        "request_reply:{$error->id}" => '③ GET /error',
    ])->and($folders['场景']->children[0]->label)->toBe('Trace scenario  ①✗→③→①→②');
});

test('the tree stops at entries: no call-tree nodes anywhere', function () {
    $records = workbenchContext();
    ImplementationNode::factory()->create(['project_id' => $records['project']->id, 'feature_id' => null, 'request_reply_id' => $records['requestReply']->id, 'title' => 'Entry node']);

    $keys = [];
    $walk = function (array $nodes) use (&$walk, &$keys): void {
        foreach ($nodes as $node) {
            $keys[] = $node->key;
            $walk($node->children);
        }
    };
    $walk(Livewire::test(WorkbenchGraph::class)->instance()->tree);

    expect(collect($keys)->filter(fn (string $key): bool => str_contains($key, 'implementation_node') || str_contains($key, 'call_tree'))->all())->toBe([]);
});

test('entry detail lists its call tree as ordered steps with failure rows marked, and a cycle ends', function () {
    $records = workbenchContext();
    $entry = $records['requestReply'];
    $node = fn (string $title, ?string $file = null, ?string $function = null): ImplementationNode => ImplementationNode::factory()->create([
        'project_id' => $records['project']->id, 'feature_id' => null, 'request_reply_id' => $entry->id,
        'title' => $title, 'file' => $file, 'function' => $function, 'input' => "{$title} in", 'change' => "{$title} change", 'output' => "{$title} out",
    ]);
    $edge = fn (ImplementationNode $from, ImplementationNode $to, ImplementationNodeEdgeKind $kind, ?string $condition = null) => ImplementationNodeEdge::factory()->create([
        'project_id' => $records['project']->id, 'from_node_id' => $from->id, 'to_node_id' => $to->id, 'kind' => $kind, 'condition' => $condition,
    ]);
    $save = $node('保存', 'app/Services/Save.php', 'store');
    $controller = $node('路由/控制器', 'app/Http/LoginController.php', 'login');
    $reject = $node('拒绝', 'app/Http/Reject.php', 'deny');
    $edge($controller, $reject, ImplementationNodeEdgeKind::OnFailure, '密码错误');
    $edge($controller, $save, ImplementationNodeEdgeKind::Calls);
    $edge($save, $controller, ImplementationNodeEdgeKind::Calls);

    $steps = app(WorkbenchGraphPresenter::class)->callSteps($entry);

    expect(array_map(fn (array $step): array => [$step['node']->id, $step['depth'], $step['failureCondition']], $steps))->toBe([
        [$save->id, 0, null],
        [$controller->id, 0, null],
        [$reject->id, 1, '密码错误'],
    ]);

    $html = Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "request_reply:{$entry->id}")
        ->html();

    expect($html)->toMatch('#app/Services/Save.php::store.*app/Http/LoginController.php::login.*路由/控制器.*text-red-600.*✗ 密码错误.*app/Http/Reject.php::deny.*拒绝 in#su');
});
