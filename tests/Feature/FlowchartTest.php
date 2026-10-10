<?php

use App\Enums\RequirementKind;
use App\Models\Commit;
use App\Models\Flowchart;
use App\Models\Project;
use App\Models\Requirement;
use App\Support\FlowchartMermaid;

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

test('a valid chart saves under its rule\'s project [T14]', function () {
    $flowchart = Flowchart::factory()->create();

    expect($flowchart->project_id)->toBe($flowchart->requirement->project_id)
        ->and($flowchart->requirement->flowchart->is($flowchart))->toBeTrue();
});

/**
 * Rule 1 of project "fc" (repo: the fixture), worked on by a commit unless $planned, with a chart from $nodes and $edges.
 *
 * @param  list<array<string, string>>  $nodes
 * @param  list<array<string, string>>  $edges
 * @return array{Flowchart, Requirement}
 */
function checkedRuleChart(array $nodes, array $edges, bool $planned = false): array
{
    $project = Project::factory()->create(['slug' => 'fc', 'repo_path' => base_path('tests/Fixtures/repo')]);
    $rule = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Rule, 'title' => 'Orders keep a total']);

    if (! $planned) {
        $rule->commits()->attach(Commit::factory()->create(['project_id' => $project->id]));
    }

    return [Flowchart::factory()->create(['requirement_id' => $rule->id, 'chart' => ['nodes' => $nodes, 'edges' => $edges]]), $rule];
}

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

test('flowcharts:save gives a rule its flowchart and refuses a non-rule [T180]', function () {
    $project = Project::factory()->create(['slug' => 'fc']);
    $rule = Requirement::factory()->create(['project_id' => $project->id, 'number' => 5, 'kind' => RequirementKind::Rule]);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 6, 'kind' => RequirementKind::Group]);
    $file = tempnam(sys_get_temp_dir(), 'flowchart');
    $chart = fn (string $label) => ['nodes' => [['id' => 'a', 'label' => $label, 'shape' => 'start'], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], 'edges' => [['from' => 'a', 'to' => 'z']]];
    $write = fn (array $owner, string $label) => file_put_contents($file, json_encode(['project' => 'fc', ...$owner, 'chart' => $chart($label), 'pseudocode' => '1. rule']));

    $write(['requirement' => 5], 'A');
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('Requirement 5 flowchart saved')->assertSuccessful();
    $write(['requirement' => 5], 'A2');
    $this->artisan('flowcharts:save', ['file' => $file])->assertSuccessful();
    $write(['requirement' => 6], 'G');
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('Only a rule')->assertFailed();
    $write(['requirement' => 5], 'bad');
    file_put_contents($file, json_encode(['project' => 'fc', 'requirement' => 5, 'chart' => ['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start']], 'edges' => [['from' => 'a', 'to' => 'zz']]]]));
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('must connect existing nodes')->assertFailed();
    $write([], 'B');
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('Expected {project, requirement, chart')->assertFailed();

    expect($rule->flowchart()->sole()->chart['nodes'][0]['label'])->toBe('A2')
        ->and($rule->flowchart->project_id)->toBe($project->id)
        ->and(Flowchart::withoutGlobalScopes()->count())->toBe(1);

    unlink($file);
});

test('flowcharts:check verifies files and functions in the repo [T16]', function (array $node, string $expected, bool $passes) {
    checkedRuleChart([['id' => 'a', 'label' => 'A', 'shape' => 'start', ...$node], ['id' => 'z', 'label' => 'Z', 'shape' => 'end']], [['from' => 'a', 'to' => 'z']]);

    $result = $this->artisan('flowcharts:check', ['project-slug' => 'fc', '--requirement' => 1])->expectsOutputToContain($expected);
    $passes ? $result->assertSuccessful() : $result->assertFailed();
})->with([
    'found' => [['file' => 'app/OrderController.php', 'function' => 'store'], '1 个节点核对通过', true],
    'described function skipped' => [['file' => 'app/OrderController.php', 'function' => 'saved 钩子'], '1 个节点核对通过', true],
    'no file skipped' => [['function' => 'anything'], '0 个节点核对通过', true],
    'missing function' => [['file' => 'app/OrderController.php', 'function' => 'destroy'], '规则 1 Orders keep a total：a A app/OrderController.php destroy — 函数不存在', false],
    'missing file' => [['file' => 'app/Gone.php', 'function' => 'store'], '规则 1 Orders keep a total：a A app/Gone.php store — 文件不存在', false],
]);

test('flowcharts:check records which nodes are stale, empty when all are found [T109]', function () {
    [$flowchart] = checkedRuleChart([
        ['id' => 'a', 'label' => 'A', 'shape' => 'start', 'file' => 'app/OrderController.php', 'function' => 'store'],
        ['id' => 'b', 'label' => 'B', 'shape' => 'step', 'file' => 'app/Gone.php', 'function' => 'store'],
        ['id' => 'z', 'label' => 'Z', 'shape' => 'end'],
    ], [['from' => 'a', 'to' => 'b'], ['from' => 'b', 'to' => 'z']]);

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

test('flowcharts:check skips a rule no commit has worked on yet, checks it once one has [T110]', function () {
    [$flowchart, $rule] = checkedRuleChart([
        ['id' => 'a', 'label' => 'A', 'shape' => 'start', 'file' => 'app/Gone.php', 'function' => 'store'],
        ['id' => 'z', 'label' => 'Z', 'shape' => 'end'],
    ], [['from' => 'a', 'to' => 'z']], planned: true);

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])
        ->expectsOutputToContain('规则 1 Orders keep a total：计划中，跳过')
        ->assertSuccessful();

    expect($flowchart->fresh()->stale_nodes)->toBe([]);

    $rule->commits()->attach(Commit::factory()->create(['project_id' => $rule->project_id]));

    $this->artisan('flowcharts:check', ['project-slug' => 'fc'])->assertFailed();

    expect($flowchart->fresh()->stale_nodes)->toBe(['a']);
});
