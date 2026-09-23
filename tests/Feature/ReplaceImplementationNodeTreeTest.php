<?php

use App\Enums\ImplementationNodeEdgeKind;
use App\Enums\ImplementationNodeKind;
use App\Models\Feature;
use App\Models\ImplementationNode;
use App\Models\ImplementationNodeEdge;
use App\Models\Module;
use App\Models\Project;

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
    $this->feature = Feature::factory()->create(['project_id' => $this->project->id, 'number' => 14]);
    $this->old = ImplementationNode::factory()->create(['project_id' => $this->project->id, 'feature_id' => $this->feature->id, 'kind' => ImplementationNodeKind::Function]);
});

it('replaces the whole call tree with nodes and edges', function () {
    $other = Module::factory()->create(['project_id' => $this->project->id, 'name' => 'Billing']);
    $design = ImplementationNode::factory()->create(['project_id' => $this->project->id, 'feature_id' => $this->feature->id, 'kind' => ImplementationNodeKind::Design]);

    $this->artisan('implementation-nodes:replace-tree', ['file' => callTreeFile([
        'project' => 'sg', 'feature' => 14,
        'tree' => [treeNode('入口', ['children' => [
            treeNode('扣款', ['module' => 'Billing']),
            treeNode('报错', ['edge' => 'on_failure', 'condition' => '余额不足']),
        ]])],
    ])])->expectsOutputToContain('3 nodes now')->assertSuccessful();

    $nodes = ImplementationNode::query()->where('feature_id', $this->feature->id)->where('kind', ImplementationNodeKind::Function)->get()->keyBy('title');
    $failure = ImplementationNodeEdge::query()->where('to_node_id', $nodes['报错']->id)->sole();

    expect($nodes->keys()->all())->toBe(['入口', '扣款', '报错'])
        ->and(ImplementationNode::query()->find($this->old->id))->toBeNull()
        ->and(ImplementationNode::query()->find($design->id))->not->toBeNull()
        ->and($nodes['入口']->module_id)->toBe($this->feature->module_id)
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
    'missing output' => [['project' => 'sg', 'feature' => 14, 'tree' => [treeNode('a', ['children' => [treeNode('b', ['output' => ''])]])]], 'tree[0].children[0] is missing output'],
    'missing input' => [['project' => 'sg', 'feature' => 14, 'tree' => [array_diff_key(treeNode('a'), ['input' => 1])]], 'tree[0] is missing input'],
    'unknown module' => [['project' => 'sg', 'feature' => 14, 'tree' => [treeNode('a', ['module' => 'Nope'])]], 'unknown module Nope'],
    'unknown project' => [['project' => 'zz', 'feature' => 14, 'tree' => []], 'No feature 14 in project zz'],
    'unknown feature' => [['project' => 'sg', 'feature' => 99, 'tree' => []], 'No feature 99 in project sg'],
]);
