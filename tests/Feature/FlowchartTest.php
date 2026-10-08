<?php

use App\Enums\FeatureStatus;
use App\Filament\Resources\Features\Pages\EditFeature;
use App\Models\Feature;
use App\Models\Flowchart;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\UseCase;
use App\Models\User;
use App\Support\FlowchartMermaid;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $chart
 */
function chartOf(array $chart): Flowchart
{
    return new Flowchart(['chart' => $chart]);
}

test('the model refuses a malformed chart [T15]', function (array $chart, string $message) {
    expect(fn () => Flowchart::factory()->create(['chart' => $chart]))
        ->toThrow(LogicException::class, $message);
})->with([
    'duplicate id' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step'], ['id' => 'a', 'label' => 'B', 'shape' => 'step']], 'edges' => []], 'duplicate node id a'],
    'dangling edge' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step']], 'edges' => [['from' => 'a', 'to' => 'b']]], 'edge 0 must connect existing nodes'],
    'unknown shape' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'circle']], 'edges' => []], 'node a shape'],
    'unknown kind' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step']], 'edges' => [['from' => 'a', 'to' => 'a', 'kind' => 'maybe']]], 'edge 0 kind'],
    'no nodes' => [['edges' => []], 'expected'],
]);

test('a valid chart saves under its feature project [T14]', function () {
    $flowchart = Flowchart::factory()->create();

    expect($flowchart->project_id)->toBe($flowchart->feature->project_id)
        ->and($flowchart->feature->flowchart->is($flowchart))->toBeTrue();
});

test('mermaid maps shapes, renumbers ids and escapes labels [T120]', function () {
    $mermaid = FlowchartMermaid::fromFlowchart(chartOf(['nodes' => [
        ['id' => 'end', 'label' => 'Start "here"', 'shape' => 'start'],
        ['id' => 'b', 'label' => 'ok? <yes>', 'shape' => 'decision', 'file' => 'app/A.php', 'function' => 'store'],
        ['id' => 'c', 'label' => 'read #1', 'shape' => 'io'],
        ['id' => 'd', 'label' => 'step', 'shape' => 'step'],
    ], 'edges' => [['from' => 'end', 'to' => 'b']]]));

    expect($mermaid)->toBe(implode("\n", [
        'flowchart TD',
        '    n0(["Start #quot;here#quot;"])',
        '    n1{"A::store<br/>ok? #lt;yes#gt;"}',
        '    n2[/"read #35;1"/]',
        '    n3["step"]',
        '    n0 --> n1',
    ]))->not->toContain('app/A.php');
});

test('mermaid node text shows a two-line code ref for file and/or function, label only otherwise [T120]', function (array $node, string $expected) {
    $mermaid = FlowchartMermaid::fromFlowchart(chartOf(['nodes' => [
        ['id' => 'a', 'label' => '下单', 'shape' => 'step', ...$node],
    ], 'edges' => []]));

    expect($mermaid)->toBe("flowchart TD\n    n0[\"{$expected}\"]");
})->with([
    'class file + function' => [['file' => 'app/Models/Membership.php', 'function' => 'makePrimaryFor'], 'Membership::makePrimaryFor<br/>下单'],
    'tsx file + component function' => [['file' => 'resources/js/pages/member/orders.tsx', 'function' => 'Orders'], 'orders::Orders<br/>下单'],
    'file only' => [['file' => 'app/Models/Membership.php'], 'Membership<br/>下单'],
    'function only' => [['function' => 'makePrimaryFor'], 'makePrimaryFor<br/>下单'],
    'neither' => [[], '下单'],
]);

test('mermaid tooltips carry file::function per node id [T120]', function () {
    expect(FlowchartMermaid::tooltips(chartOf(['nodes' => [
        ['id' => 'a', 'label' => 'A', 'shape' => 'start'],
        ['id' => 'b', 'label' => 'B', 'shape' => 'step', 'file' => 'app/A.php', 'function' => 'store'],
    ], 'edges' => []])))->toBe(['n1' => 'app/A.php::store']);
});

/**
 * start → decision → (是) end / (否) end, the smallest chart every drawing rule accepts.
 *
 * @return array{nodes: list<array<string, string>>, edges: list<array<string, string>>}
 */
function ruleChart(): array
{
    return [
        'nodes' => [
            ['id' => 's', 'label' => 'GET /member/orders', 'shape' => 'start'],
            ['id' => 'd', 'label' => '有当前公会？', 'shape' => 'decision'],
            ['id' => 'ok', 'label' => '返回订单列表页', 'shape' => 'end'],
            ['id' => 'no', 'label' => '返回 422', 'shape' => 'end'],
        ],
        'edges' => [
            ['from' => 's', 'to' => 'd'],
            ['from' => 'd', 'to' => 'ok', 'label' => '是'],
            ['from' => 'd', 'to' => 'no', 'label' => '否', 'kind' => 'failure'],
        ],
    ];
}

test('the model accepts a chart that follows the drawing rules [T14]', function () {
    $long = ruleChart();
    $long['nodes'][0]['label'] = str_repeat('入', 40);
    $long['nodes'][2]['label'] = str_repeat('字', 40);
    $parallel = ruleChart();
    $parallel['nodes'][1]['shape'] = 'step';
    $parallel['edges'][1]['label'] = '同时';
    $parallel['edges'][2]['label'] = '同时';

    expect(Flowchart::chartError(ruleChart()))->toBeNull()
        ->and(Flowchart::chartError($long))->toBeNull()
        ->and(Flowchart::chartError($parallel))->toBeNull();
});

test('the model accepts two start nodes converging into shared logic [T14]', function () {
    $chart = ruleChart();
    $chart['nodes'][] = ['id' => 's2', 'label' => 'POST /member/orders', 'shape' => 'start'];
    $chart['edges'][] = ['from' => 's2', 'to' => 'd'];

    expect(Flowchart::chartError($chart))->toBeNull();
});

test('the model refuses a chart that breaks a drawing rule [T15]', function (Closure $break, string $message) {
    $chart = ruleChart();
    $break($chart);

    expect(fn () => Flowchart::factory()->create(['chart' => $chart]))
        ->toThrow(LogicException::class, $message);
})->with([
    'no start' => [function (array &$c) {
        $c['nodes'][0]['shape'] = 'step';
    }, '至少要有一个 start 节点'],
    'no end' => [function (array &$c) {
        $c['nodes'][2]['shape'] = 'step';
        $c['nodes'][3]['shape'] = 'step';
    }, '至少要有一个 end 节点'],
    'unlabelled fork from a step' => [function (array &$c) {
        $c['nodes'][1]['shape'] = 'step';
        unset($c['edges'][2]['label']);
    }, '节点 d 分叉的每条出边都要带 label'],
    'unlabelled decision edge' => [function (array &$c) {
        unset($c['edges'][2]['label']);
    }, '节点 d 分叉的每条出边都要带 label'],
    'unreachable node' => [function (array &$c) {
        $c['edges'][] = ['from' => 'ok', 'to' => 's'];
        array_splice($c['edges'], 0, 1);
    }, '从 start 走不到'],
    'unreachable from either of two starts' => [function (array &$c) {
        $c['nodes'][] = ['id' => 's2', 'label' => 'POST /member/orders', 'shape' => 'start'];
        $c['nodes'][] = ['id' => 'orphan', 'label' => '孤立节点', 'shape' => 'step'];
        $c['edges'][] = ['from' => 'orphan', 'to' => 'ok'];
    }, '从 start 走不到'],
]);

test('mermaid renders multiple start nodes converging into shared logic [T120]', function () {
    $mermaid = FlowchartMermaid::fromFlowchart(chartOf(['nodes' => [
        ['id' => 's1', 'label' => 'GET /orders', 'shape' => 'start'],
        ['id' => 's2', 'label' => 'POST /orders', 'shape' => 'start'],
        ['id' => 'd', 'label' => '处理', 'shape' => 'step'],
    ], 'edges' => [
        ['from' => 's1', 'to' => 'd'],
        ['from' => 's2', 'to' => 'd'],
    ]]));

    expect($mermaid)->toBe(implode("\n", [
        'flowchart TD',
        '    n0(["GET /orders"])',
        '    n1(["POST /orders"])',
        '    n2["处理"]',
        '    n0 --> n2',
        '    n1 --> n2',
    ]));
});

test('mermaid styles only failure edges red [T120]', function () {
    $mermaid = FlowchartMermaid::fromFlowchart(chartOf(['nodes' => [
        ['id' => 'a', 'label' => 'A', 'shape' => 'step'],
        ['id' => 'b', 'label' => 'B', 'shape' => 'step'],
        ['id' => 'c', 'label' => 'C', 'shape' => 'end'],
    ], 'edges' => [
        ['from' => 'a', 'to' => 'b', 'kind' => 'next'],
        ['from' => 'a', 'to' => 'c', 'kind' => 'failure', 'label' => '密码错误'],
    ]]));

    expect($mermaid)->toContain('n0 -->|"密码错误"| n2')
        ->toEndWith('linkStyle 1 stroke:#dc2626,color:#dc2626');
});

test('flowcharts:save upserts a feature flowchart and rejects a bad chart [T14]', function () {
    $project = Project::factory()->create(['slug' => 'fc']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 7]);
    $file = tempnam(sys_get_temp_dir(), 'flowchart');
    $write = fn (array $chart) => file_put_contents($file, json_encode(['project' => 'fc', 'feature' => 7, 'chart' => $chart, 'pseudocode' => '1. go']));

    $write(['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start'], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'z']]]);
    $this->artisan('flowcharts:save', ['file' => $file])->assertSuccessful();
    $write(['nodes' => [['id' => 'a', 'label' => 'A2', 'shape' => 'start'], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'z']]]);
    $this->artisan('flowcharts:save', ['file' => $file])->assertSuccessful();
    $write(['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start']], 'edges' => [['from' => 'a', 'to' => 'zz']]]);
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('must connect existing nodes')->assertFailed();

    expect($feature->flowchart()->sole()->chart['nodes'][0]['label'])->toBe('A2')
        ->and($feature->flowchart->pseudocode)->toBe('1. go');

    unlink($file);
});

test('flowcharts:check verifies files and functions in the repo [T16]', function (array $node, string $expected, bool $passes) {
    $project = Project::factory()->create(['slug' => 'fc', 'repo_path' => base_path('tests/Fixtures/repo')]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1, 'status' => FeatureStatus::Done]);
    Flowchart::factory()->create(['feature_id' => $feature->id, 'chart' => ['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start', ...$node], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'z']]]]);

    $result = $this->artisan('flowcharts:check', ['project-slug' => 'fc', '--feature' => 1])->expectsOutputToContain($expected);
    $passes ? $result->assertSuccessful() : $result->assertFailed();
})->with([
    'found' => [['file' => 'app/OrderController.php', 'function' => 'store'], '1 个节点核对通过', true],
    'described function skipped' => [['file' => 'app/OrderController.php', 'function' => 'saved 钩子'], '1 个节点核对通过', true],
    'no file skipped' => [['function' => 'anything'], '0 个节点核对通过', true],
    'missing function' => [['file' => 'app/OrderController.php', 'function' => 'destroy'], '函数不存在', false],
    'missing file' => [['file' => 'app/Gone.php', 'function' => 'store'], '文件不存在', false],
]);

test('flowcharts:check records which nodes are stale, empty when all are found [T109]', function () {
    $project = Project::factory()->create(['slug' => 'fc', 'repo_path' => base_path('tests/Fixtures/repo')]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1, 'status' => FeatureStatus::Done]);
    $flowchart = Flowchart::factory()->create(['feature_id' => $feature->id, 'chart' => [
        'nodes' => [
            ['id' => 'a', 'label' => 'A', 'shape' => 'start', 'file' => 'app/OrderController.php', 'function' => 'store'],
            ['id' => 'b', 'label' => 'B', 'shape' => 'step', 'file' => 'app/Gone.php', 'function' => 'store'],
            ['id' => 'z', 'label' => 'Z', 'shape' => 'end'],
        ],
        'edges' => [['from' => 'a', 'to' => 'b'], ['from' => 'b', 'to' => 'z']],
    ]]);

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])->assertFailed();

    expect($flowchart->fresh()->stale_nodes)->toBe(['b'])
        ->and($flowchart->fresh()->stale_checked_at)->not->toBeNull();

    $flowchart->update(['chart' => [
        'nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start', 'file' => 'app/OrderController.php', 'function' => 'store'], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']],
        'edges' => [['from' => 'a', 'to' => 'z']],
    ]]);

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])->assertSuccessful();

    expect($flowchart->fresh()->stale_nodes)->toBe([]);
});

test('flowcharts:check skips a planned feature\'s missing files, checks it once done [T110]', function () {
    $project = Project::factory()->create(['slug' => 'fc', 'repo_path' => base_path('tests/Fixtures/repo')]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1, 'status' => FeatureStatus::Todo]);
    $flowchart = Flowchart::factory()->create(['feature_id' => $feature->id, 'chart' => [
        'nodes' => [
            ['id' => 'a', 'label' => 'A', 'shape' => 'start', 'file' => 'app/Gone.php', 'function' => 'store'],
            ['id' => 'z', 'label' => 'Z', 'shape' => 'end'],
        ],
        'edges' => [['from' => 'a', 'to' => 'z']],
    ]]);

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])
        ->expectsOutputToContain('计划中，跳过')
        ->assertSuccessful();

    expect($flowchart->fresh()->stale_nodes)->toBe([]);

    $feature->update(['status' => FeatureStatus::Done]);

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])->assertFailed();

    expect($flowchart->fresh()->stale_nodes)->toBe(['a']);
});

test('the migration turns a call tree into a flowchart with failure branch and pseudocode', function () {
    Schema::create('implementation_nodes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('feature_id')->nullable();
        $table->foreignId('request_reply_id')->nullable();
        $table->string('kind');
        $table->string('title');
        $table->string('file')->nullable();
        $table->string('function')->nullable();
        $table->text('input')->nullable();
        $table->text('change')->nullable();
        $table->text('output')->nullable();
    });
    Schema::create('implementation_node_edges', function (Blueprint $table) {
        $table->id();
        $table->foreignId('from_node_id');
        $table->foreignId('to_node_id');
        $table->string('kind');
        $table->text('condition')->nullable();
    });
    $entry = RequestReply::factory()->create();
    $first = Feature::factory()->create(['project_id' => $entry->project_id]);
    $second = Feature::factory()->create(['project_id' => $entry->project_id]);
    $first->requestReplies()->attach($entry);
    $second->requestReplies()->attach($entry);
    $node = fn (string $title, ?string $file, string $kind = 'function'): int => DB::table('implementation_nodes')->insertGetId([
        'request_reply_id' => $entry->id, 'kind' => $kind, 'title' => $title, 'file' => $file, 'function' => $file === null ? null : 'run',
        'input' => "{$title} in", 'change' => "{$title} change", 'output' => "{$title} out",
    ]);
    $route = $node('路由', 'routes/web.php');
    $login = $node('登录', 'app/Login.php');
    $reject = $node('拒绝', 'app/Reject.php');
    $node('设计稿', null, 'code');
    DB::table('implementation_node_edges')->insert([
        ['from_node_id' => $route, 'to_node_id' => $login, 'kind' => 'calls', 'condition' => null],
        ['from_node_id' => $login, 'to_node_id' => $reject, 'kind' => 'on_failure', 'condition' => '密码错误'],
    ]);

    (require base_path('database/migrations/2026_09_23_033827_move_call_trees_into_flowcharts.php'))->convertCallTrees();

    $flowchart = $first->flowchart()->sole();

    expect($second->flowchart()->exists())->toBeTrue()
        ->and($flowchart->chart['nodes'])->toBe([
            ['id' => "n{$route}", 'label' => '路由', 'shape' => 'start', 'file' => 'routes/web.php', 'function' => 'run'],
            ['id' => "n{$login}", 'label' => '登录', 'shape' => 'step', 'file' => 'app/Login.php', 'function' => 'run'],
            ['id' => "n{$reject}", 'label' => '拒绝', 'shape' => 'step', 'file' => 'app/Reject.php', 'function' => 'run'],
        ])
        ->and($flowchart->chart['edges'])->toBe([
            ['from' => "n{$route}", 'to' => "n{$login}", 'kind' => 'next'],
            ['from' => "n{$login}", 'to' => "n{$reject}", 'kind' => 'failure', 'label' => '密码错误'],
        ])
        ->and($flowchart->pseudocode)->toBe(implode("\n", [
            '1. routes/web.php::run — 路由 change (路由 in → 路由 out)',
            '2. app/Login.php::run — 登录 change (登录 in → 登录 out)',
            '   若 密码错误：3. app/Reject.php::run — 拒绝 change (拒绝 in → 拒绝 out)',
        ]));
});

test('the feature form edits the flowchart as JSON and refuses a malformed chart [T108]', function () {
    $user = User::factory()->create();
    $feature = Feature::factory()->forUseCase(UseCase::factory()->create())->create();
    $feature->project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($feature->project);
    $chart = ['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start'], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'z']]];

    Livewire::test(EditFeature::class, ['record' => $feature->getRouteKey()])
        ->fillForm(['flowchart.chart' => json_encode(['nodes' => [], 'edges' => [['from' => 'a', 'to' => 'b']]])])
        ->call('save')
        ->assertHasFormErrors(['flowchart.chart']);

    Livewire::test(EditFeature::class, ['record' => $feature->getRouteKey()])
        ->fillForm(['flowchart.chart' => json_encode($chart), 'flowchart.pseudocode' => '1. A'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($feature->flowchart()->sole()->chart)->toBe($chart);
});
