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

function saveTests(array $spec, array $options = []): PendingCommand
{
    $file = tempnam(sys_get_temp_dir(), 'tests-save');
    file_put_contents($file, json_encode($spec));

    return test()->artisan('tests:save', ['file' => $file, ...$options]);
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

it('refuses a parent that would make a cycle or lives in another project [T18]', function () {
    $root = Test::factory()->create(['number' => 1]);
    $child = Test::factory()->create(['project_id' => $root->project_id, 'number' => 2, 'parent_id' => $root->id]);

    expect(fn () => $root->update(['parent_id' => $child->id]))->toThrow(LogicException::class, 'loops');

    $stranger = Test::factory()->create();
    expect(fn () => $child->update(['parent_id' => $stranger->id]))->toThrow(LogicException::class, 'same project');
});

it('assigns numbers to new nodes, links them by parent_ref, updates by number, and syncs feature tags [T17]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 5]);

    saveTests(['project' => 'tt', 'nodes' => [
        ['ref' => 'cart', 'action' => 'Open cart', 'expected' => 'Cart shows', 'priority' => 'P0', 'auto' => 'yes', 'result' => '通过', 'features' => [5]],
        ['parent_ref' => 'cart', 'action' => 'Pay', 'result' => '未跑'],
    ]])->expectsOutputToContain('cart → #1')->expectsOutputToContain('row 1 → #2')->expectsOutputToContain('2 created')->assertSuccessful();

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

it('never reuses a number across saves and prints the mapping as JSON [T17]', function () {
    $project = Project::factory()->create(['slug' => 'tt']);
    Test::factory()->create(['project_id' => $project->id, 'number' => 7]);

    saveTests(['project' => 'tt', 'nodes' => [['ref' => 'a', 'action' => 'A']]], ['--json' => true])
        ->expectsOutput('{"created":1,"updated":0,"assigned":{"a":8}}')->assertSuccessful();
    saveTests(['project' => 'tt', 'nodes' => [['ref' => 'b', 'action' => 'B']]])->expectsOutputToContain('b → #9')->assertSuccessful();

    expect(Test::where('project_id', $project->id)->orderBy('number')->pluck('number')->all())->toBe([7, 8, 9]);
});

it('rejects a bad tree and writes nothing [T18]', function (array $nodes, string $message) {
    Project::factory()->create(['slug' => 'tt']);

    saveTests(['project' => 'tt', 'nodes' => $nodes])->expectsOutputToContain($message)->assertFailed();

    expect(Test::count())->toBe(0);
})->with([
    'unknown number' => [[['action' => 'a'], ['number' => 1, 'action' => 'b']], '#1: no such test'],
    'unknown parent_ref' => [[['parent_ref' => 'x', 'action' => 'a']], 'parent_ref "x" matches no ref'],
    'missing parent' => [[['parent' => 9, 'action' => 'a']], 'parent #9 does not exist'],
    'cycle' => [[['ref' => 'a', 'parent_ref' => 'b', 'action' => 'a'], ['ref' => 'b', 'parent_ref' => 'a', 'action' => 'b']], 'loops'],
    'unknown feature' => [[['action' => 'a', 'features' => [42]]], 'feature 42 does not exist'],
    'bad enum' => [[['action' => 'a', 'result' => 'PASS']], 'result "PASS"'],
]);

it('derives blocked below a failed node without storing it, and rolls up subtree counts [T20]', function () {
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
        ->and((array) $nodes[1]->rollup)->toBe(['passed' => 1, 'failed' => 1, 'blocked' => 2, 'gaps' => 4, 'fakeGreen' => 1]);
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

it('renders the tree, filters to matches with their ancestor path, and lists uncovered features [T19]', function () {
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
        ->assertSee('未分类')
        ->assertDontSee('Root step')
        ->assertSeeInOrder(['没有被任何测试覆盖的功能', 'Lonely feature'])
        ->call('toggleArea', '未分类')
        ->assertSee('Root step')
        ->assertDontSee('Middle step')
        ->call('setFilter', 'failed')
        ->assertSeeInOrder(['Root step', 'Middle step', 'Broken leaf'])
        ->assertDontSee('Green sibling')
        ->call('selectNode', 1)
        ->assertSee('F1')
        ->call('selectNode', 3)
        ->assertSeeInOrder(['Root step', 'Middle step', 'Broken leaf']);
});

it('groups nodes by business area as real subtrees: other-area ancestors muted once, shared chains merged, counts area-only [T123]', function () {
    $project = Project::factory()->create();
    $make = fn (int $number, ?Test $parent, ?string $module, TestLastResult $result = TestLastResult::Passed, ?string $location = 'tests/X.php'): Test => Test::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $parent?->id, 'module' => $module,
        'title' => "step {$number}", 'last_result' => $result, 'auto' => TestAuto::Yes, 'location' => $location,
    ]);

    $register = $make(1, null, '账户');
    $login = $make(2, $register, '账户');
    $openCheckout = $make(3, $login, '结账');
    $pay = $make(4, $openCheckout, '结账');
    $make(5, $pay, '结账', TestLastResult::Failed);
    $make(6, $pay, '结账', TestLastResult::NotRun, null);
    $make(7, $pay, '退货');
    $make(8, $login, '结账');
    $make(9, null, '');

    $areas = collect(app(TestTreeService::class)->areas($project))->keyBy('name');
    $checkout = $areas['结账'];

    $numbers = fn (array $nodes): array => array_map(fn (TestTreeNode $node): int => $node->test->number, $nodes);
    $muted = fn (array $nodes): array => array_keys(array_filter(flatTree($nodes), fn (TestTreeNode $node): bool => $node->muted));

    expect($areas->keys()->all())->toBe(['结账', '未分类', '账户', '退货'])
        ->and((array) $checkout->rollup)->toBe(['passed' => 3, 'failed' => 1, 'blocked' => 0, 'gaps' => 1, 'fakeGreen' => 0])
        ->and($numbers($checkout->nodes))->toBe([1])
        ->and($numbers($checkout->nodes[0]->children))->toBe([2])
        ->and($numbers($checkout->nodes[0]->children[0]->children))->toBe([3, 8])
        ->and($muted($checkout->nodes))->toBe([1, 2])
        ->and((array) $checkout->nodes[0]->rollup)->toBe((array) $checkout->rollup)
        ->and(array_keys(flatTree($checkout->nodes)))->not->toContain(7)
        ->and(array_keys(flatTree($areas['退货']->nodes)))->toBe([1, 2, 3, 4, 7])
        ->and($muted($areas['退货']->nodes))->toBe([1, 2, 3, 4])
        ->and((array) $areas['退货']->rollup)->toBe(['passed' => 1, 'failed' => 0, 'blocked' => 0, 'gaps' => 0, 'fakeGreen' => 0])
        ->and(array_keys(flatTree($areas['账户']->nodes)))->toBe([1, 2])
        ->and($muted($areas['账户']->nodes))->toBe([]);
});

it('renders other-area ancestors as muted, open, clickable rows without breadcrumb lines [T123]', function () {
    $project = testTreePage();
    $login = Test::factory()->create(['project_id' => $project->id, 'number' => 1, 'module' => '账户', 'title' => 'Log in']);
    $open = Test::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $login->id, 'module' => '结账', 'title' => 'Open cart']);
    Test::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $open->id, 'module' => '结账', 'title' => 'Pay deep']);

    Livewire::test(TestTree::class)
        ->call('toggleArea', '结账')
        ->assertSeeHtml('data-muted')
        ->assertSeeInOrder(['Log in', 'Open cart'])
        ->assertDontSee('Pay deep')
        ->assertDontSee('前置')
        ->call('selectNode', 1)
        ->assertSeeHtml('data-test-marker>[T1]<');
});

it('keeps number and expected out of rows and shows the expected field in the detail [T124]', function () {
    $project = testTreePage();
    $root = Test::factory()->create(['project_id' => $project->id, 'number' => 41, 'module' => '结账', 'title' => 'Pay now', 'expected' => 'Order row created', 'last_result' => TestLastResult::Failed]);
    Test::factory()->create(['project_id' => $project->id, 'number' => 42, 'parent_id' => $root->id, 'module' => '结账', 'title' => 'Refund', 'expected' => 'Money back', 'last_result' => TestLastResult::Passed]);

    Livewire::test(TestTree::class)
        ->call('toggleArea', '结账')
        ->call('toggleNode', $root->id)
        ->assertSee('Refund')
        ->assertDontSee('Money back')
        ->assertDontSee('#42')
        ->call('selectNode', 42)
        ->assertSeeHtmlInOrder(['data-test-expected>Money back<', 'data-test-marker>[T42]<']);
});
