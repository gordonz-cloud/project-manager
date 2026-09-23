<?php

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

test('the model refuses a malformed chart', function (array $chart, string $message) {
    expect(fn () => Flowchart::factory()->create(['chart' => $chart]))
        ->toThrow(LogicException::class, $message);
})->with([
    'duplicate id' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step'], ['id' => 'a', 'label' => 'B', 'shape' => 'step']], 'edges' => []], 'duplicate node id a'],
    'dangling edge' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step']], 'edges' => [['from' => 'a', 'to' => 'b']]], 'edge 0 must connect existing nodes'],
    'unknown shape' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'circle']], 'edges' => []], 'node a shape'],
    'unknown kind' => [['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step']], 'edges' => [['from' => 'a', 'to' => 'a', 'kind' => 'maybe']]], 'edge 0 kind'],
    'no nodes' => [['edges' => []], 'expected'],
]);

test('a valid chart saves under its feature project', function () {
    $flowchart = Flowchart::factory()->create();

    expect($flowchart->project_id)->toBe($flowchart->feature->project_id)
        ->and($flowchart->feature->flowchart->is($flowchart))->toBeTrue();
});

test('mermaid maps shapes, renumbers ids and escapes labels', function () {
    $mermaid = FlowchartMermaid::fromFlowchart(chartOf(['nodes' => [
        ['id' => 'end', 'label' => 'Start "here"', 'shape' => 'start'],
        ['id' => 'b', 'label' => 'ok? <yes>', 'shape' => 'decision', 'file' => 'app/A.php', 'function' => 'store'],
        ['id' => 'c', 'label' => 'read #1', 'shape' => 'io'],
        ['id' => 'd', 'label' => 'step', 'shape' => 'step'],
    ], 'edges' => [['from' => 'end', 'to' => 'b']]]));

    expect($mermaid)->toBe(implode("\n", [
        'flowchart TD',
        '    n0(["Start #quot;here#quot;"])',
        '    n1{"ok? #lt;yes#gt;<br>app/A.php::store"}',
        '    n2[/"read #35;1"/]',
        '    n3["step"]',
        '    n0 --> n1',
    ]));
});

test('mermaid styles only failure edges red', function () {
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

test('flowcharts:save upserts a feature flowchart and rejects a bad chart', function () {
    $project = Project::factory()->create(['slug' => 'fc']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 7]);
    $file = tempnam(sys_get_temp_dir(), 'flowchart');
    $write = fn (array $chart) => file_put_contents($file, json_encode(['project' => 'fc', 'feature' => 7, 'chart' => $chart, 'pseudocode' => '1. go']));

    $write(['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start']], 'edges' => []]);
    $this->artisan('flowcharts:save', ['file' => $file])->assertSuccessful();
    $write(['nodes' => [['id' => 'a', 'label' => 'A2', 'shape' => 'start']], 'edges' => []]);
    $this->artisan('flowcharts:save', ['file' => $file])->assertSuccessful();
    $write(['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start']], 'edges' => [['from' => 'a', 'to' => 'zz']]]);
    $this->artisan('flowcharts:save', ['file' => $file])->expectsOutputToContain('must connect existing nodes')->assertFailed();

    expect($feature->flowchart()->sole()->chart['nodes'][0]['label'])->toBe('A2')
        ->and($feature->flowchart->pseudocode)->toBe('1. go');

    unlink($file);
});

test('flowcharts:check verifies files and functions in the repo', function (array $node, string $expected, bool $passes) {
    $project = Project::factory()->create(['slug' => 'fc', 'repo_path' => base_path('tests/Fixtures/repo')]);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 1]);
    Flowchart::factory()->create(['feature_id' => $feature->id, 'chart' => ['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'step', ...$node]], 'edges' => []]]);

    $result = $this->artisan('flowcharts:check', ['project-slug' => 'fc', '--feature' => 1])->expectsOutputToContain($expected);
    $passes ? $result->assertSuccessful() : $result->assertFailed();
})->with([
    'found' => [['file' => 'app/OrderController.php', 'function' => 'store'], '1 个节点核对通过', true],
    'described function skipped' => [['file' => 'app/OrderController.php', 'function' => 'saved 钩子'], '1 个节点核对通过', true],
    'no file skipped' => [['function' => 'anything'], '0 个节点核对通过', true],
    'missing function' => [['file' => 'app/OrderController.php', 'function' => 'destroy'], '函数不存在', false],
    'missing file' => [['file' => 'app/Gone.php', 'function' => 'store'], '文件不存在', false],
]);

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

test('the feature form edits the flowchart as JSON and refuses a malformed chart', function () {
    $user = User::factory()->create();
    $feature = Feature::factory()->forUseCase(UseCase::factory()->create())->create();
    $feature->project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($feature->project);
    $chart = ['nodes' => [['id' => 'a', 'label' => 'A', 'shape' => 'start']], 'edges' => []];

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
