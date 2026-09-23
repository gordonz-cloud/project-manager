<?php

use App\Enums\DataModelStatus;
use App\Enums\FeatureStatus;
use App\Enums\NavigationGroup;
use App\Enums\UseCaseStatus;
use App\Enums\WorkflowRunStatus;
use App\Filament\Pages\WorkbenchGraph;
use App\Models\DataModel;
use App\Models\Feature;
use App\Models\Flowchart;
use App\Models\ModelField;
use App\Models\Module;
use App\Models\ModuleSpec;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\UseCase;
use App\Models\UseCaseGroup;
use App\Models\UseCaseSpec;
use App\Models\User;
use App\Models\WorkflowRun;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * @return array{project: Project, group: UseCaseGroup, module: Module, moduleSpec: ModuleSpec, useCase: UseCase, useCaseSpec: UseCaseSpec, feature: Feature, requestReply: RequestReply, dataModel: DataModel, field: ModelField, run: WorkflowRun}
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
    $feature = Feature::factory()->forUseCase($useCase)->create([
        'title' => 'Trace feature',
        'status' => FeatureStatus::Done,
        'entry' => 'Feature entry text',
    ]);
    $requestReply = RequestReply::factory()->create(['use_case_id' => $useCase->id, 'method' => 'POST', 'entry' => '/trace', 'title' => 'Trace entry', 'module_id' => $module->id]);
    $feature->requestReplies()->attach($requestReply);
    $dataModel = DataModel::factory()->create(['project_id' => $project->id, 'name' => 'Trace data model', 'status' => DataModelStatus::Existing]);
    $field = ModelField::factory()->create(['project_id' => $project->id, 'data_model_id' => $dataModel->id, 'name' => 'trace_field']);
    $feature->dataModels()->attach($dataModel);
    $run = WorkflowRun::factory()->forUseCase($useCase)->create(['feature_id' => $feature->id, 'status' => WorkflowRunStatus::Running]);

    return compact('project', 'group', 'module', 'moduleSpec', 'useCase', 'useCaseSpec', 'feature', 'requestReply', 'dataModel', 'field', 'run');
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
        ->and($useCase->badge)->toBe('功能 1/1 · 1 Model')
        ->and($useCase->children[0]->key)->toBe("use_case_spec:{$records['useCaseSpec']->id}")
        ->and($folders->keys()->all())->toBe(['Use Case Spec', '模块', '功能', '执行记录'])
        ->and($folders['模块']->children[0]->key)->toBe("module:{$records['module']->id}")
        ->and($folders['功能']->children[0]->key)->toBe("feature:{$records['feature']->id}")
        ->and($folders['功能']->children[0]->badge)->toBe('Trace module')
        ->and($folders['功能']->children[0]->children[0]->key)->toBe("feature:{$records['feature']->id}#no-flowchart")
        ->and($folders['功能']->children[0]->children[1]->children[0]->key)->toBe("request_reply:{$records['requestReply']->id}")
        ->and($folders['功能']->children[0]->children[1]->children[0]->label)->toBe('① POST /trace');

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
        ->assertSee(['Trace module', 'Trace feature', 'trace_field'])
        ->assertDontSee(['module_use_cases', 'use_case_id', 'data_model_feature', '未建立']);
});

test('search filters the tree and opens the branches that match', function () {
    $records = workbenchContext();
    UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Unrelated goal']);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "use_case:{$records['useCase']->id}")
        ->assertSee('Unrelated goal')
        ->assertDontSee('Trace feature')
        ->set('search', 'Trace feature')
        ->assertSee('Trace feature')
        ->assertDontSee('Unrelated goal');
});

test('toggling a row opens its children', function () {
    $records = workbenchContext();
    $useCasePath = "use_case_group:{$records['group']->id}>use_case:{$records['useCase']->id}";

    Livewire::test(WorkbenchGraph::class)
        ->assertDontSee('Trace feature')
        ->call('toggleNode', $useCasePath)
        ->call('toggleNode', "{$useCasePath}>use_case:{$records['useCase']->id}#features")
        ->assertSee('Trace feature');
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

test('use cases within a group order by distinct data model count, ties by name', function () {
    $records = workbenchContext(); // 'Trace goal' has 1 model via its feature

    // Zero-model use case: no features at all.
    $zeroModels = UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Zero models']);

    // Two-model use case: two features, each with its own data model, no overlap.
    $twoModels = UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Two models']);
    $featureA = Feature::factory()->forUseCase($twoModels)->create();
    $featureB = Feature::factory()->forUseCase($twoModels)->create();
    $featureA->dataModels()->attach(DataModel::factory()->create(['project_id' => $records['project']->id]));
    $featureB->dataModels()->attach(DataModel::factory()->create(['project_id' => $records['project']->id]));

    // Tie with 'Trace goal' at one model, but two features sharing the SAME model
    // must still count once (distinct), and 'Aardvark' sorts before 'Trace goal'.
    $tiedShared = UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Aardvark tie']);
    $tiedFeatureA = Feature::factory()->forUseCase($tiedShared)->create();
    $tiedFeatureB = Feature::factory()->forUseCase($tiedShared)->create();
    $tiedFeatureA->dataModels()->attach($records['dataModel']);
    $tiedFeatureB->dataModels()->attach($records['dataModel']);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $useCases = collect($tree[0]->children);

    expect($useCases->pluck('label')->all())->toBe(['Zero models', 'Aardvark tie', 'Trace goal', 'Two models'])
        ->and($useCases->firstWhere('label', 'Zero models')->badge)->toEndWith('0 Model')
        ->and($useCases->firstWhere('label', 'Aardvark tie')->badge)->toEndWith('1 Model')
        ->and($useCases->firstWhere('label', 'Two models')->badge)->toEndWith('2 Model');
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

test('entries under features carry their use case number', function () {
    $records = workbenchContext();
    $login = $records['requestReply'];
    $entry = fn (string $path): RequestReply => RequestReply::factory()->create(['use_case_id' => $records['useCase']->id, 'method' => 'GET', 'entry' => $path]);
    $home = $entry('/home');
    $error = $entry('/error');
    $records['feature']->requestReplies()->attach([$home->id, $error->id]);

    $folders = collect(Livewire::test(WorkbenchGraph::class)->instance()->tree[0]->children[0]->children)->keyBy('label');
    $entries = collect($folders['功能']->children[0]->children[1]->children)->mapWithKeys(fn ($node): array => [$node->key => $node->label]);

    expect($entries->all())->toBe([
        "request_reply:{$login->id}" => '① POST /trace',
        "request_reply:{$home->id}" => '② GET /home',
        "request_reply:{$error->id}" => '③ GET /error',
    ]);
});

test('feature detail renders its flowchart as mermaid, then the pseudocode', function () {
    $records = workbenchContext();
    Flowchart::factory()->create([
        'feature_id' => $records['feature']->id,
        'chart' => ['nodes' => [['id' => 'a', 'label' => '收到请求', 'shape' => 'start'], ['id' => 'b', 'label' => '返回', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'b']]],
        'pseudocode' => '1. app/Http/TraceController.php::store — 收到请求',
    ]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "feature:{$records['feature']->id}")
        ->assertSeeHtml('data-flowchart')
        ->assertSeeHtml('flowchart TD')
        ->assertSeeHtmlInOrder(['data-flowchart', 'data-pseudocode', 'app/Http/TraceController.php::store']);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "request_reply:{$records['requestReply']->id}")
        ->assertDontSeeHtml('data-flowchart');
});

test('the flowchart has a fullscreen toggle button', function () {
    $records = workbenchContext();
    Flowchart::factory()->create([
        'feature_id' => $records['feature']->id,
        'chart' => ['nodes' => [['id' => 'a', 'label' => '收到请求', 'shape' => 'start'], ['id' => 'b', 'label' => '返回', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'b']]],
        'pseudocode' => '1. app/Http/TraceController.php::store — 收到请求',
    ]);

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "feature:{$records['feature']->id}")
        ->assertSeeHtml('data-flowchart-fullscreen-toggle');
});

test('the feature has a flowchart leaf that renders the same chart when selected', function () {
    $records = workbenchContext();
    Flowchart::factory()->create([
        'feature_id' => $records['feature']->id,
        'chart' => ['nodes' => [['id' => 'a', 'label' => '收到请求', 'shape' => 'start'], ['id' => 'b', 'label' => '返回', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'b']]],
        'pseudocode' => '1. app/Http/TraceController.php::store — 收到请求',
    ]);

    $feature = collect(Livewire::test(WorkbenchGraph::class)->instance()->tree[0]->children[0]->children)
        ->keyBy('label')['功能']->children[0];

    expect($feature->children[0]->key)->toBe("flowchart:{$records['feature']->id}")
        ->and($feature->children[0]->label)->toBe('流程图');

    Livewire::test(WorkbenchGraph::class)
        ->call('selectNode', "flowchart:{$records['feature']->id}")
        ->assertSeeHtml('data-flowchart')
        ->assertSeeHtmlInOrder(['data-flowchart', 'data-pseudocode', 'app/Http/TraceController.php::store']);
});

test('the feature shows a grey "无流程图" leaf when it has no flowchart', function () {
    $records = workbenchContext();

    $feature = collect(Livewire::test(WorkbenchGraph::class)->instance()->tree[0]->children[0]->children)
        ->keyBy('label')['功能']->children[0];

    expect($feature->children[0]->key)->toBe("feature:{$records['feature']->id}#no-flowchart")
        ->and($feature->children[0]->label)->toBe('无流程图')
        ->and($feature->children[0]->isFolder)->toBeTrue();
});

test('a use case is green only when all its features and their data models are finished', function () {
    $records = workbenchContext(); // 'Trace goal': 1 Done feature, 1 Existing model → complete

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $traceGoal = collect($tree[0]->children)->firstWhere('label', 'Trace goal');

    expect($traceGoal->tone)->toBe('success');

    // Add an unfinished feature to the same use case: turns amber.
    Feature::factory()->forUseCase($records['useCase'])->create(['status' => FeatureStatus::Todo]);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $traceGoal = collect($tree[0]->children)->firstWhere('label', 'Trace goal');

    expect($traceGoal->tone)->toBe('warning')
        ->and($traceGoal->badge)->toBe('功能 1/2 · 1 Model');
});

test('a use case turns amber when a used data model is still 计划中, and shows how many need building', function () {
    $records = workbenchContext();
    $planned = DataModel::factory()->create(['project_id' => $records['project']->id, 'status' => DataModelStatus::Planned]);
    $records['feature']->dataModels()->attach($planned);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $traceGoal = collect($tree[0]->children)->firstWhere('label', 'Trace goal');

    expect($traceGoal->tone)->toBe('warning')
        ->and($traceGoal->badge)->toBe('功能 1/1 · Model 1 待建 · 2 Model');
});

test('作废 features are excluded from the total and never block green', function () {
    $records = workbenchContext();
    Feature::factory()->forUseCase($records['useCase'])->create(['status' => FeatureStatus::Void]);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $traceGoal = collect($tree[0]->children)->firstWhere('label', 'Trace goal');

    expect($traceGoal->tone)->toBe('success')
        ->and($traceGoal->badge)->toBe('功能 1/1 · 1 Model');
});

test('a group rolls up the progress of every use case inside it', function () {
    $records = workbenchContext(); // group has 'Trace goal': 功能 1/1, 1 Model, complete
    $unfinished = UseCase::factory()->create(['use_case_group_id' => $records['group']->id, 'goal' => 'Unfinished goal']);
    Feature::factory()->forUseCase($unfinished)->create(['status' => FeatureStatus::Todo]);

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $group = $tree[0];

    expect($group->key)->toBe("use_case_group:{$records['group']->id}")
        ->and($group->tone)->toBe('warning')
        ->and($group->badge)->toBe('2 Use Case · 功能 1/2');
});

test('feature and data model rows show a status pill colored by meaning', function () {
    $records = workbenchContext();

    $tree = Livewire::test(WorkbenchGraph::class)->instance()->tree;
    $useCase = $tree[0]->children[0];
    $folders = collect($useCase->children)->keyBy('label');
    $feature = $folders['功能']->children[0];

    expect($feature->statusBadge)->toBe('完成')
        ->and($feature->tone)->toBe('success');

    $dataModel = $folders['模块']->children[0]->children[1]->children[0];

    expect($dataModel->statusBadge)->toBe('现有')
        ->and($dataModel->tone)->toBe('success');
});
