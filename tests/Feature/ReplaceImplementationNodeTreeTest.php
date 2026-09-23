<?php

use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\Project;
use App\Models\RequestReply;
use App\Models\UseCase;
use App\Models\UseCaseGroup;

/**
 * @param  array<string, mixed>  $spec
 */
function callTreeFile(array $spec): string
{
    $file = (string) tempnam(sys_get_temp_dir(), 'tree');
    file_put_contents($file, json_encode($spec));

    return $file;
}

function treeNode(string $title, array $extra = []): array
{
    return ['title' => $title, 'file' => 'app/X.php', 'function' => 'run', 'input' => 'in', 'change' => '', 'output' => 'out', ...$extra];
}

beforeEach(function () {
    $this->project = Project::factory()->create(['slug' => 'sg']);
    $this->useCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $this->project->id])]);
    $this->requestReply = RequestReply::factory()->create(['use_case_id' => $this->useCase->id, 'method' => 'POST', 'entry' => '/login']);
    $this->old = ImplementationNode::factory()->create(['project_id' => $this->project->id, 'feature_id' => null, 'request_reply_id' => $this->requestReply->id, 'kind' => ImplementationNodeKind::Function]);
});

it('replaces the whole call tree with nodes and edges', function () {
    $other = Module::factory()->create(['project_id' => $this->project->id, 'name' => 'Billing']);
    $design = ImplementationNode::factory()->create(['project_id' => $this->project->id, 'feature_id' => null, 'request_reply_id' => $this->requestReply->id, 'kind' => ImplementationNodeKind::Design]);

    $this->artisan('implementation-nodes:replace-tree', ['file' => callTreeFile([
        'project' => 'sg', 'entry' => 'POST /login',
        'tree' => [treeNode('入口', ['children' => [
            treeNode('扣款', ['module' => 'Billing']),
            treeNode('报错', ['edge' => 'on_failure', 'condition' => '余额不足']),
        ]])],
    ])])->expectsOutputToContain('3 nodes now')->assertSuccessful();

    $nodes = ImplementationNode::query()->where('request_reply_id', $this->requestReply->id)->where('kind', ImplementationNodeKind::Function)->get()->keyBy('title');
    $failure = ImplementationNodeEdge::query()->where('to_node_id', $nodes['报错']->id)->sole();

    expect($nodes->keys()->all())->toBe(['入口', '扣款', '报错'])
        ->and(ImplementationNode::query()->find($this->old->id))->toBeNull()
        ->and(ImplementationNode::query()->find($design->id))->not->toBeNull()
        ->and($nodes['入口']->module_id)->toBe($this->requestReply->module_id)
        ->and($nodes['扣款']->module_id)->toBe($other->id)
        ->and($failure->from_node_id)->toBe($nodes['入口']->id)
        ->and($failure->kind)->toBe(ImplementationNodeEdgeKind::OnFailure)
        ->and($failure->condition)->toBe('余额不足')
        ->and(ImplementationNodeEdge::query()->where('to_node_id', $nodes['扣款']->id)->sole()->kind)->toBe(ImplementationNodeEdgeKind::Calls);
});

it('rejects bad input and writes nothing', function (array $spec, string $message) {
    $this->artisan('implementation-nodes:replace-tree', ['file' => callTreeFile($spec)])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(ImplementationNode::query()->find($this->old->id))->not->toBeNull();
})->with([
    'missing output' => [['project' => 'sg', 'entry' => 'POST /login', 'tree' => [treeNode('a', ['children' => [treeNode('b', ['output' => ''])]])]], 'tree[0].children[0] is missing output'],
    'missing input' => [['project' => 'sg', 'entry' => 'POST /login', 'tree' => [array_diff_key(treeNode('a'), ['input' => 1])]], 'tree[0] is missing input'],
    'unknown module' => [['project' => 'sg', 'entry' => 'POST /login', 'tree' => [treeNode('a', ['module' => 'Nope'])]], 'unknown module Nope'],
    'unknown project' => [['project' => 'zz', 'entry' => 'POST /login', 'tree' => []], 'No entry "POST /login" in project zz'],
    'unknown entry' => [['project' => 'sg', 'entry' => 'GET /login', 'tree' => []], 'No entry "GET /login" in project sg'],
]);

it('asks for the use case when the entry is in several', function () {
    $otherUseCase = UseCase::factory()->create(['use_case_group_id' => UseCaseGroup::factory()->state(['project_id' => $this->project->id])]);
    $other = RequestReply::factory()->create(['use_case_id' => $otherUseCase->id, 'method' => 'POST', 'entry' => '/login']);

    $this->artisan('implementation-nodes:replace-tree', ['file' => callTreeFile(['project' => 'sg', 'entry' => 'POST /login', 'tree' => []])])
        ->expectsOutputToContain('add "use_case"')
        ->assertFailed();

    $this->artisan('implementation-nodes:replace-tree', ['file' => callTreeFile(['project' => 'sg', 'use_case' => $otherUseCase->id, 'entry' => 'POST /login', 'tree' => [treeNode('a')]])])
        ->assertSuccessful();

    expect($other->implementationNodes()->count())->toBe(1)
        ->and(ImplementationNode::query()->find($this->old->id))->not->toBeNull();
});
