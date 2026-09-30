<?php

use App\Data\Tests\TestNodeState;
use App\Data\Tests\TestTreeNode;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestPriority;
use App\Filament\Pages\TestTree;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Test;
use App\Models\User;
use App\Services\Tests\TestTreeService;
use Filament\Facades\Filament;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;

function saveTests(array $spec): PendingCommand
{
    $file = tempnam(sys_get_temp_dir(), 'tests-save');
    file_put_contents($file, json_encode($spec));

    return test()->artisan('tests:save', ['file' => $file]);
}

/**
 * @return array<int, TestTreeNode> keyed by test number, every depth
 */
function flatTree(array $nodes): array
{
    $flat = [];
    foreach ($nodes as $node) {
        $flat[$node->test->number] = $node;
        $flat += flatTree($node->children);
    }

    return $flat;
}

it('refuses a parent that would make a cycle or lives in another project', function () {
    $root = Test::factory()->create(['number' => 1]);
    $child = Test::factory()->create(['project_id' => $root->project_id, 'number' => 2, 'parent_id' => $root->id]);

    expect(fn () => $root->update(['parent_id' => $child->id]))->toThrow(LogicException::class, 'loops');

    $stranger = Test::factory()->create();
    expect(fn () => $child->update(['parent_id' => $stranger->id]))->toThrow(LogicException::class, 'same project');
});

it('upserts nodes by number, keeps untouched nodes, and syncs feature tags', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 5]);

    saveTests(['project' => 'tt', 'nodes' => [
        ['number' => 1, 'parent' => null, 'action' => 'Open cart', 'expected' => 'Cart shows', 'priority' => 'P0', 'auto' => 'yes', 'result' => '通过', 'features' => [5]],
        ['number' => 2, 'parent' => 1, 'action' => 'Pay', 'result' => '未跑'],
    ]])->expectsOutputToContain('2 created')->assertSuccessful();

    saveTests(['project' => 'tt', 'nodes' => [['number' => 2, 'result' => '失败']]])->expectsOutputToContain('1 updated')->assertSuccessful();

    $root = Test::where('project_id', $project->id)->where('number', 1)->firstOrFail();
    $child = Test::where('project_id', $project->id)->where('number', 2)->firstOrFail();

    expect($root->features->pluck('id')->all())->toBe([$feature->id])
        ->and($root->priority)->toBe(TestPriority::P0)
        ->and($child->last_result)->toBe(TestLastResult::Failed)
        ->and($child->title)->toBe('Pay')
        ->and($child->parent_id)->toBe($root->id);

    saveTests(['project' => 'tt', 'nodes' => [['number' => 1, 'features' => []]]])->assertSuccessful();
    expect($root->features()->count())->toBe(0);
});

it('rejects a bad tree and writes nothing', function (array $nodes, string $message) {
    Project::factory()->create(['slug' => 'tt']);

    saveTests(['project' => 'tt', 'nodes' => $nodes])->expectsOutputToContain($message)->assertFailed();

    expect(Test::count())->toBe(0);
})->with([
    'missing parent' => [[['number' => 1, 'parent' => 9, 'action' => 'a']], 'parent #9 does not exist'],
    'cycle' => [[['number' => 1, 'parent' => 2, 'action' => 'a'], ['number' => 2, 'parent' => 1, 'action' => 'b']], 'loops'],
    'unknown feature' => [[['number' => 1, 'action' => 'a', 'features' => [42]]], 'feature 42 does not exist'],
    'bad enum' => [[['number' => 1, 'action' => 'a', 'result' => 'PASS']], 'result "PASS"'],
]);

it('derives blocked below a failed node without storing it, and rolls up subtree counts', function () {
    $project = Project::factory()->create();
    $make = fn (int $number, ?Test $parent, TestLastResult $result, ?TestAuto $auto = TestAuto::Yes): Test => Test::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $parent?->id, 'last_result' => $result, 'auto' => $auto,
    ]);

    $root = $make(1, null, TestLastResult::Passed);
    $failed = $make(2, $root, TestLastResult::Failed);
    $child = $make(3, $failed, TestLastResult::Passed);
    $make(4, $child, TestLastResult::NotRun);
    $make(5, $root, TestLastResult::NotRun, TestAuto::No);
    $make(6, $root, TestLastResult::Passed, TestAuto::Wrong);

    $nodes = flatTree(app(TestTreeService::class)->tree($project));

    expect($nodes[3]->state)->toBe(TestNodeState::Blocked)
        ->and($nodes[4]->state)->toBe(TestNodeState::Blocked)
        ->and($nodes[2]->state)->toBe(TestNodeState::Failed)
        ->and($child->fresh()->last_result)->toBe(TestLastResult::Passed)
        ->and((array) $nodes[1]->rollup)->toBe(['passed' => 1, 'failed' => 1, 'blocked' => 2, 'manual' => 1, 'fakeGreen' => 1]);
});

function testTreePage(): Project
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($project);

    return $project;
}

it('renders the tree, filters to matches with their ancestor path, and lists uncovered features', function () {
    $project = testTreePage();
    $covered = Feature::factory()->create(['project_id' => $project->id, 'number' => 1, 'title' => 'Covered feature']);
    Feature::factory()->create(['project_id' => $project->id, 'number' => 2, 'title' => 'Lonely feature']);

    $root = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'title' => 'Root step', 'last_result' => TestLastResult::Passed]);
    $root->features()->attach($covered);
    $middle = Test::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $root->id, 'title' => 'Middle step', 'last_result' => TestLastResult::Passed]);
    Test::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $middle->id, 'title' => 'Broken leaf', 'last_result' => TestLastResult::Failed]);
    Test::factory()->create(['project_id' => $project->id, 'number' => 4, 'parent_id' => $root->id, 'title' => 'Green sibling', 'last_result' => TestLastResult::Passed]);

    Livewire::test(TestTree::class)
        ->assertOk()
        ->assertSee('Root step')
        ->assertSee('F1')
        ->assertDontSee('Middle step')
        ->assertSeeInOrder(['没有被任何测试覆盖的功能', 'Lonely feature'])
        ->call('setFilter', 'failed')
        ->assertSeeInOrder(['Root step', 'Middle step', 'Broken leaf'])
        ->assertDontSee('Green sibling')
        ->call('selectNode', 3)
        ->assertSeeInOrder(['#1 Root step', '#2 Middle step', '#3 Broken leaf']);
});
