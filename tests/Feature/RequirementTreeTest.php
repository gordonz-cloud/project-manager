<?php

use App\Data\Requirements\DeliveryStatus;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\FeatureStatus;
use App\Enums\RequirementDecider;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Enums\TestLastResult;
use App\Filament\Pages\RequirementTree;
use App\Models\Commit;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Models\User;
use App\Services\Requirements\RequirementTreeService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;

function saveRequirements(array $spec, array $options = []): PendingCommand
{
    $file = tempnam(sys_get_temp_dir(), 'requirements-save');
    file_put_contents($file, json_encode($spec));

    return test()->artisan('requirements:save', ['file' => $file, ...$options]);
}

function requirementNumbered(Project $project, int $number): Requirement
{
    return Requirement::where('project_id', $project->id)->where('number', $number)->firstOrFail();
}

/**
 * @return array<int, RequirementTreeNode> keyed by requirement number, every depth
 */
function flatRequirementTree(array $nodes): array
{
    $flat = [];
    foreach ($nodes as $node) {
        $flat[$node->requirement->number] = $node;
        $flat += flatRequirementTree($node->children);
    }

    return $flat;
}

it('creates goals, sub-goals and rules with server numbers, links features and tests, and records a revision each [T40]', function () {
    $project = Project::factory()->create(['slug' => 'rq']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 5]);
    $test = Test::factory()->create(['project_id' => $project->id, 'number' => 3]);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 7]);

    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'goal', 'kind' => '目标', 'title' => '公会成员按等级拿货', 'status' => '已定'],
        ['ref' => 'sub', 'parent_ref' => 'goal', 'kind' => '子目标', 'title' => 'Direct 商品分级可见', 'status' => '已定'],
        ['parent_ref' => 'sub', 'kind' => '规则', 'title' => '没到 Direct 等级能看不能加购', 'rationale' => '保护渠道价', 'source' => 'spec.md v1.0',
            'status' => '已定', 'decided_by' => 'Gordon', 'decided_at' => '2026-10-02', 'features' => [5], 'tests' => [3]],
    ]])->expectsOutputToContain('goal → #8')->expectsOutputToContain('row 2 → #10')->expectsOutputToContain('3 created')->assertSuccessful();

    $rule = requirementNumbered($project, 10);

    expect($rule->parent->number)->toBe(9)
        ->and($rule->parent->parent->number)->toBe(8)
        ->and($rule->kind)->toBe(RequirementKind::Rule)
        ->and($rule->decided_at->toDateString())->toBe('2026-10-02')
        ->and($rule->linkedFeatures->pluck('id')->all())->toBe([$feature->id])
        ->and($rule->tests->pluck('id')->all())->toBe([$test->id])
        ->and($rule->revisions)->toHaveCount(1)
        ->and($rule->revisions->first()->only(['old_status', 'new_status', 'new_statement', 'source']))
        ->toBe(['old_status' => null, 'new_status' => '已定', 'new_statement' => '没到 Direct 等级能看不能加购', 'source' => 'spec.md v1.0']);

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 10, 'rationale' => '保护渠道价和 Direct 会员权益', 'features' => []]]], ['--json' => true])
        ->expectsOutput('{"created":0,"updated":1,"assigned":[],"voided":[]}')->assertSuccessful();

    expect($rule->fresh()->rationale)->toBe('保护渠道价和 Direct 会员权益')
        ->and($rule->linkedFeatures()->count())->toBe(0)
        ->and($rule->revisions()->count())->toBe(1);
});

it('rejects a bad payload and writes nothing [T41]', function (array $nodes, string $message) {
    $project = Project::factory()->create(['slug' => 'rq']);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided]);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'kind' => RequirementKind::Rule, 'status' => RequirementStatus::Proposed]);

    saveRequirements(['project' => 'rq', 'nodes' => $nodes])->expectsOutputToContain($message)->assertFailed();

    expect(Requirement::count())->toBe(2)->and(DB::table('requirement_revisions')->count())->toBe(2);
})->with([
    'unknown number' => [[['number' => 9, 'title' => 'x']], '#9: no such requirement'],
    'new node without kind' => [[['title' => 'x']], 'needs a title and a kind'],
    'goal under a goal' => [[['parent' => 1, 'kind' => '目标', 'title' => 'x']], 'a 目标 must be a root'],
    'rule at the root' => [[['kind' => '规则', 'title' => 'x']], 'a 规则 must sit under 目标 or 子目标'],
    'rule under a rule' => [[['parent' => 2, 'kind' => '规则', 'title' => 'x']], 'must sit under'],
    'cycle' => [[['ref' => 'a', 'parent_ref' => 'b', 'kind' => '子目标', 'title' => 'a'], ['ref' => 'b', 'parent_ref' => 'a', 'kind' => '子目标', 'title' => 'b']], 'loops'],
    'unknown feature' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'features' => [42]]], 'feature 42 does not exist'],
    'unknown test' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'tests' => [42]]], 'test 42 does not exist'],
    'bad status' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'status' => '完成']], 'status "完成"'],
    'supersede a proposal' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'supersedes' => 2]], 'can only supersede a 已定 rule'],
    'decided node supersedes' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'status' => '已定', 'supersedes' => 1, 'reason' => 'r']], 'only a 提议 can supersede'],
    'conflict without target' => [[['parent' => 1, 'kind' => '规则', 'title' => 'x', 'status' => '冲突']], 'must name the rule it supersedes'],
]);

it('needs a reason to change a decided rule and keeps the old statement in its history [T42]', function () {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => '运费满 50 包邮', 'status' => '已定'],
    ]])->assertSuccessful();

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 2, 'title' => '运费满 80 包邮']]])
        ->expectsOutputToContain('#2: changing a 已定 rule needs a reason')->assertFailed();

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 2, 'title' => '运费满 80 包邮', 'reason' => '毛利不够', 'source' => 'Gordon 拍板 2026-10-06', 'decided_by' => 'Gordon']]])
        ->assertSuccessful();

    $latest = requirementNumbered($project, 2)->revisions->first();

    expect($latest->only(['old_statement', 'new_statement', 'reason', 'source', 'decided_by']))->toBe([
        'old_statement' => '运费满 50 包邮', 'new_statement' => '运费满 80 包邮', 'reason' => '毛利不够', 'source' => 'Gordon 拍板 2026-10-06', 'decided_by' => 'Gordon',
    ]);
});

it('stores a proposal that supersedes a decided rule as a conflict and voids the old rule once decided [T43]', function () {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => '旧规则', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => '新规则', 'status' => '提议', 'supersedes' => 2, 'source' => 'S6 spec v2'],
    ]])->assertSuccessful();

    expect(requirementNumbered($project, 3)->status)->toBe(RequirementStatus::Conflict)
        ->and(requirementNumbered($project, 3)->supersedes->number)->toBe(2);

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 3, 'status' => '已定']]])
        ->expectsOutputToContain('needs a reason')->assertFailed();

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 3, 'status' => '已定', 'reason' => '老板新文档', 'source' => 'Gordon 拍板 2026-10-06']]])
        ->expectsOutputToContain('#2 → 作废')->assertSuccessful();

    $old = requirementNumbered($project, 2);

    expect($old->status)->toBe(RequirementStatus::Void)
        ->and($old->revisions->first()->only(['old_status', 'new_status', 'reason']))
        ->toBe(['old_status' => '已定', 'new_status' => '作废', 'reason' => '被 #3 取代（Gordon 拍板 2026-10-06）：老板新文档'])
        ->and(requirementNumbered($project, 3)->revisions->first()->only(['old_status', 'new_status']))
        ->toBe(['old_status' => '冲突', 'new_status' => '已定']);
});

it('records who must decide a proposal and rejects it elsewhere [T49]', function () {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => '待定：选哪个', 'decider' => '老板'],
    ]])->assertSuccessful();

    expect(requirementNumbered($project, 2)->decider)->toBe(RequirementDecider::Boss);

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 2, 'decider' => '老王']]])->expectsOutputToContain('#2: decider "老王" is not one of Gordon/老板')->assertFailed();
    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 1, 'decider' => 'Gordon']]])->expectsOutputToContain('#1: decider only applies to a 提议 or 冲突')->assertFailed();
    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 2, 'status' => '已定', 'decider' => 'Gordon']]])->expectsOutputToContain('#2: decider only applies')->assertFailed();

    expect(requirementNumbered($project, 2)->decider)->toBe(RequirementDecider::Boss);
});

it('clears who must decide once the node is decided or voided [T50]', function (string $status) {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => 'R', 'decider' => 'Gordon'],
    ]])->assertSuccessful();

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 2, 'status' => $status, 'decided_by' => 'Gordon']]])->assertSuccessful();

    expect(requirementNumbered($project, 2)->only(['decider', 'decided_by']))->toBe(['decider' => null, 'decided_by' => 'Gordon']);
})->with(['已定', '作废']);

it('derives delivery from linked features and tests and rolls it up through decided children [T44]', function () {
    $project = Project::factory()->create();
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided]);
    $rule = fn (int $number, RequirementStatus $status = RequirementStatus::Decided): Requirement => Requirement::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => $status,
    ]);
    $feature = fn (FeatureStatus $status): Feature => Feature::factory()->create(['project_id' => $project->id, 'status' => $status]);
    $test = fn (TestLastResult $result): Test => Test::factory()->create(['project_id' => $project->id, 'last_result' => $result]);

    $rule(2)->tests()->attach([$test(TestLastResult::Passed)->id, $test(TestLastResult::Passed)->id]);
    $rule(3)->linkedFeatures()->attach($feature(FeatureStatus::Done));
    $rule(4)->linkedFeatures()->attach([$feature(FeatureStatus::Done)->id, $feature(FeatureStatus::Todo)->id]);
    $rule(5);
    $rule(6)->tests()->attach($test(TestLastResult::Failed));
    $rule(7, RequirementStatus::Proposed)->linkedFeatures()->attach($feature(FeatureStatus::Todo));
    $rule(8, RequirementStatus::Conflict);

    $nodes = flatRequirementTree(app(RequirementTreeService::class)->tree($project));

    expect(array_map(fn (RequirementTreeNode $node): string => $node->delivery->value, array_intersect_key($nodes, array_flip([2, 3, 4, 5, 6]))))
        ->toBe([2 => '已验证', 3 => '已实现', 4 => '实现中', 5 => '未实现', 6 => '验证失败'])
        ->and($nodes[1]->delivery)->toBe(DeliveryStatus::Failed)
        ->and($nodes[1]->rollup->delivered)->toEqualCanonicalizing(['已验证' => 1, '已实现' => 1, '实现中' => 1, '未实现' => 1, '验证失败' => 1])
        ->and($nodes[1]->rollup->verifiedPercent())->toBe(20)
        ->and([$nodes[1]->rollup->proposed, $nodes[1]->rollup->conflicts])->toBe([1, 1]);

    expect(DeliveryStatus::combined([DeliveryStatus::Verified, DeliveryStatus::Built]))->toBe(DeliveryStatus::Built)
        ->and(DeliveryStatus::combined([DeliveryStatus::NotBuilt, DeliveryStatus::Verified]))->toBe(DeliveryStatus::InProgress)
        ->and(DeliveryStatus::combined([]))->toBe(DeliveryStatus::NotBuilt);
});

it('keeps features.requirement_id mirrored into the many-to-many links [T48]', function () {
    $project = Project::factory()->create();
    [$first, $second] = Requirement::factory()->count(2)->create(['project_id' => $project->id]);

    $feature = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $first->id]);
    expect($first->linkedFeatures->pluck('id')->all())->toBe([$feature->id]);

    $feature->update(['requirement_id' => $second->id]);
    expect($first->linkedFeatures()->count())->toBe(0)
        ->and($second->linkedFeatures->pluck('id')->all())->toBe([$feature->id]);
});

it('migrates the flat list: maps statuses, numbers per project and copies feature links [T48]', function () {
    $migration = require base_path('database/migrations/2026_10_06_140038_turn_requirements_into_requirement_tree.php');
    $migration->down();

    $project = Project::factory()->create();
    $insert = fn (string $status): int => DB::table('requirements')->insertGetId(['project_id' => $project->id, 'title' => $status, 'status' => $status]);
    $ids = array_map($insert, ['完成', '进行中', '待做', '不确定', '暂缓', '作废']);
    $feature = Feature::factory()->create(['project_id' => $project->id]);
    DB::table('features')->where('id', $feature->id)->update(['requirement_id' => $ids[0]]);

    $migration->up();

    expect(DB::table('requirements')->orderBy('id')->pluck('status')->all())->toBe(['已定', '已定', '已定', '提议', '提议', '作废'])
        ->and(DB::table('requirements')->orderBy('id')->pluck('number')->all())->toBe([1, 2, 3, 4, 5, 6])
        ->and(DB::table('requirements')->where('id', $ids[4])->value('rationale'))->toBe('原状态：暂缓')
        ->and(DB::table('feature_requirement')->get()->map(fn ($row): array => (array) $row)->all())->toBe([['feature_id' => $feature->id, 'requirement_id' => $ids[0]]])
        ->and(Schema::hasTable('requirement_revisions'))->toBeTrue();
});

function requirementTreePage(): Project
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($project);

    return $project;
}

it('shows the overview tree with rollups and the unfiled legacy list [T44]', function () {
    $project = requirementTreePage();
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided, 'title' => 'Members buy by tier']);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => RequirementStatus::Conflict, 'title' => 'Tier rule']);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'status' => RequirementStatus::Decided, 'title' => 'Old flat requirement']);

    Livewire::test(RequirementTree::class)
        ->assertOk()
        ->assertSeeInOrder(['Members buy by tier', '⚠ 1 冲突', 'Tier rule', '未归类（1'])
        ->assertDontSee('Old flat requirement')
        ->toggle('showUnfiled')
        ->assertSee('Old flat requirement');
});

it('lists what waits on a decision with the rule it would replace and what it touches [T45]', function () {
    $project = requirementTreePage();
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided, 'title' => 'Checkout goal']);
    $old = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => RequirementStatus::Decided, 'title' => 'Free shipping over 50']);
    $old->linkedFeatures()->attach(Feature::factory()->create(['project_id' => $project->id, 'number' => 9, 'title' => 'Shipping calculator']));
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => RequirementStatus::Conflict,
        'supersedes_id' => $old->id, 'title' => 'Free shipping over 80', 'source' => 'boss doc v2', 'rationale' => 'margin']);
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 4, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => RequirementStatus::Decided, 'title' => 'Settled rule']);

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSeeInOrder(['待拍板', '（1）'])
        ->assertSeeInOrder(['Checkout goal', '现行规则 #2', 'Free shipping over 50', 'Free shipping over 80', 'boss doc v2', '为什么：margin', '会影响', 'F9 Shipping calculator'])
        ->assertDontSee('Settled rule');
});

it('groups recent revisions and commits by rule, newest first [T46]', function () {
    $project = requirementTreePage();
    $quiet = Requirement::factory()->create(['project_id' => $project->id, 'title' => 'Quiet rule']);
    $busy = Requirement::factory()->create(['project_id' => $project->id, 'title' => 'Busy rule']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'requirement_id' => $busy->id]);
    Commit::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'subject' => 'Ship the busy rule', 'committed_at' => now()->addDay()]);

    Livewire::withQueryParams(['tab' => 'changes'])->test(RequirementTree::class)
        ->assertSeeInOrder(['Busy rule', 'Ship the busy rule', 'Quiet rule', '新建 → ']);
});

it('shows a selected requirement with why, source, history, features, tests and commits [T47]', function () {
    $project = requirementTreePage();
    $requirement = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided,
        'title' => 'Members buy by tier', 'rationale' => 'protect channel price', 'source' => 'S5 spec v1.0', 'decided_by' => 'Gordon', 'decided_at' => '2026-10-02']);
    $feature = Feature::factory()->create(['project_id' => $project->id, 'number' => 4, 'title' => 'Tier gate', 'status' => FeatureStatus::Done]);
    $requirement->linkedFeatures()->attach($feature);
    $requirement->tests()->attach(Test::factory()->create(['project_id' => $project->id, 'number' => 12, 'last_result' => TestLastResult::Passed]));
    Commit::factory()->create(['project_id' => $project->id, 'feature_id' => $feature->id, 'subject' => 'Add tier gate']);

    Livewire::withQueryParams(['selectedNumber' => 1])->test(RequirementTree::class)
        ->assertSeeInOrder(['Members buy by tier', '已验证', 'protect channel price', 'S5 spec v1.0', 'Gordon · 2026-10-02', 'F4 Tier gate · 完成', 'T12 · 通过', '新建 → 已定', 'Add tier gate']);
});

it('groups pending decisions by who must decide [T51]', function () {
    $project = requirementTreePage();
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided, 'title' => 'G']);
    $pending = fn (int $number, string $title, RequirementStatus $status = RequirementStatus::Proposed, ?RequirementDecider $decider = null) => Requirement::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule, 'status' => $status, 'decider' => $decider,
        'title' => $title, 'supersedes_id' => $status === RequirementStatus::Conflict ? $goal->id : null,
    ]);
    $pending(2, 'Boss proposal', decider: RequirementDecider::Boss);
    $pending(3, '待定：boss question', decider: RequirementDecider::Boss);
    $pending(4, 'Boss conflict', RequirementStatus::Conflict, RequirementDecider::Boss);
    $pending(5, 'Gordon proposal', decider: RequirementDecider::Gordon);
    $pending(6, 'Nobody yet');

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSeeInOrder(['等 Gordon 拍板 1', '等老板拍板 3'])
        ->assertSeeInOrder(['等 Gordon 拍板 (1)', 'Gordon proposal', '等老板拍板 (3)', 'Boss conflict', '待定：boss question', 'Boss proposal', '未指定 (1)', 'Nobody yet']);
});

it('puts groups under sub-goals and rules under groups [T52]', function (array $nodes, ?string $message) {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['ref' => 's', 'parent_ref' => 'g', 'kind' => '子目标', 'title' => 'S', 'status' => '已定'],
        ['ref' => 'x', 'parent_ref' => 's', 'kind' => '分组', 'title' => 'X', 'status' => '已定'],
        ['parent_ref' => 'x', 'kind' => '规则', 'title' => 'R', 'status' => '已定'],
    ]])->assertSuccessful();

    expect(requirementNumbered($project, 4)->parent->kind)->toBe(RequirementKind::Group);

    $command = saveRequirements(['project' => 'rq', 'nodes' => $nodes]);
    $message === null ? $command->assertSuccessful() : $command->expectsOutputToContain($message)->assertFailed();
})->with([
    'group under a goal' => [[['parent' => 1, 'kind' => '分组', 'title' => 'x']], 'a 分组 must sit under 子目标'],
    'group under a group' => [[['parent' => 3, 'kind' => '分组', 'title' => 'x']], 'a 分组 must sit under 子目标'],
    'group at the root' => [[['kind' => '分组', 'title' => 'x']], 'a 分组 must sit under 子目标'],
    'rule still under a sub-goal' => [[['parent' => 2, 'kind' => '规则', 'title' => 'x']], null],
]);

it('records dependencies with depends_on and same-batch refs, replacing the set only when passed [T53]', function () {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['ref' => 'a', 'parent_ref' => 'g', 'kind' => '规则', 'title' => 'A'],
        ['ref' => 'b', 'parent_ref' => 'g', 'kind' => '规则', 'title' => 'B', 'depends_on_refs' => ['a'], 'position' => 3],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => 'C', 'depends_on' => [2], 'depends_on_refs' => ['b']],
    ]])->assertSuccessful();

    $dependsOn = fn (int $number): array => requirementNumbered($project, $number)->dependsOn()->orderBy('number')->pluck('number')->all();

    expect($dependsOn(3))->toBe([2])->and($dependsOn(4))->toBe([2, 3])
        ->and(requirementNumbered($project, 3)->position)->toBe(3);

    saveRequirements(['project' => 'rq', 'nodes' => [['number' => 4, 'title' => 'C2'], ['number' => 3, 'depends_on' => []]]])->assertSuccessful();

    expect($dependsOn(4))->toBe([2, 3])->and($dependsOn(3))->toBe([]);
});

it('rejects a dependency on itself, on nothing, or one that closes a loop [T54]', function (array $nodes, string $message) {
    $project = Project::factory()->create(['slug' => 'rq']);
    saveRequirements(['project' => 'rq', 'nodes' => [
        ['ref' => 'g', 'kind' => '目标', 'title' => 'G', 'status' => '已定'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => 'A'],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => 'B', 'depends_on' => [2]],
        ['parent_ref' => 'g', 'kind' => '规则', 'title' => 'C', 'depends_on' => [3]],
    ]])->assertSuccessful();

    saveRequirements(['project' => 'rq', 'nodes' => $nodes])->expectsOutputToContain($message)->assertFailed();

    expect(DB::table('requirement_dependencies')->count())->toBe(2)->and(Requirement::count())->toBe(4);
})->with([
    'itself' => [[['number' => 2, 'depends_on' => [2]]], '#2: a node cannot depend on itself'],
    'missing number' => [[['number' => 2, 'depends_on' => [99]]], '#2: depends on #99, which does not exist'],
    'missing ref' => [[['number' => 2, 'depends_on_refs' => ['nope']]], 'depends_on_refs "nope" matches no ref'],
    'loop through stored dependencies' => [[['number' => 2, 'depends_on' => [4]]], 'dependencies loop: #3 → #2 → #4 → #3'],
    'loop inside the batch' => [[['ref' => 'x', 'parent' => 1, 'kind' => '规则', 'title' => 'X', 'depends_on_refs' => ['y']], ['ref' => 'y', 'parent' => 1, 'kind' => '规则', 'title' => 'Y', 'depends_on_refs' => ['x']]], 'dependencies loop'],
    'not a list' => [[['number' => 2, 'depends_on' => 'A']], 'depends_on lists of integers'],
]);

it('orders every level depended-on first, then by position and number, lifting dependencies to siblings [T55]', function () {
    $project = Project::factory()->create();
    $node = fn (int $number, ?Requirement $parent, RequirementKind $kind, ?int $position = null): Requirement => Requirement::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $parent?->id, 'kind' => $kind, 'status' => RequirementStatus::Decided, 'position' => $position,
    ]);
    $goal = $node(1, null, RequirementKind::Goal);
    $sub = $node(2, $goal, RequirementKind::SubGoal);
    $x = $node(3, $sub, RequirementKind::Group);
    $y = $node(4, $sub, RequirementKind::Group);
    $z = $node(5, $sub, RequirementKind::Group, position: 1);
    $x1 = $node(6, $x, RequirementKind::Rule);
    $x2 = $node(7, $x, RequirementKind::Rule);
    $x3 = $node(8, $x, RequirementKind::Rule, position: 0);
    $y1 = $node(9, $y, RequirementKind::Rule);
    $z1 = $node(10, $z, RequirementKind::Rule);
    $otherGoal = $node(11, null, RequirementKind::Goal);
    $otherRule = $node(12, $otherGoal, RequirementKind::Rule);

    $x1->dependsOn()->attach($x2);       // direct: 7 before 6
    $x1->dependsOn()->attach($y1);       // lifted: group 4 before group 3
    $y1->dependsOn()->attach($x3);       // lifted back: 3 before 4 — closes a loop, skipped
    $z1->dependsOn()->attach($otherRule); // lifted to roots: goal 11 before goal 1

    $children = fn (array $nodes): array => array_map(fn (RequirementTreeNode $node): int => $node->requirement->number, $nodes);
    $tree = app(RequirementTreeService::class)->tree($project);
    $flat = flatRequirementTree($tree);

    expect($children($tree))->toBe([11, 1])
        ->and($children($flat[2]->children))->toBe([5, 4, 3])
        ->and($children($flat[3]->children))->toBe([8, 7, 6]);
});

it('shows groups in the tree, dependencies in the detail and groups in the pending path [T56]', function () {
    $project = requirementTreePage();
    $node = fn (int $number, ?Requirement $parent, RequirementKind $kind, string $title, RequirementStatus $status = RequirementStatus::Decided): Requirement => Requirement::factory()->create([
        'project_id' => $project->id, 'number' => $number, 'parent_id' => $parent?->id, 'kind' => $kind, 'status' => $status, 'title' => $title,
    ]);
    $goal = $node(1, null, RequirementKind::Goal, 'Members buy by tier');
    $sub = $node(2, $goal, RequirementKind::SubGoal, 'Direct visibility');
    $group = $node(3, $sub, RequirementKind::Group, 'Cart rules');
    $later = $node(4, $group, RequirementKind::Rule, 'Add to cart needs tier');
    $first = $node(5, $group, RequirementKind::Rule, 'Tier is known at login', RequirementStatus::Proposed);
    $later->dependsOn()->attach($first);

    Livewire::test(RequirementTree::class, ['expanded' => [$sub->id => true, $group->id => true]])
        ->assertSeeInOrder(['Members buy by tier', '子目标', 'Direct visibility', '分组', 'Cart rules', 'Tier is known at login', 'Add to cart needs tier'])
        ->assertDontSee('依赖');

    Livewire::withQueryParams(['selectedNumber' => 4])->test(RequirementTree::class)
        ->assertSeeInOrder(['Add to cart needs tier', '依赖', '#5 Tier is known at login']);

    Livewire::withQueryParams(['selectedNumber' => 5])->test(RequirementTree::class)
        ->assertSeeInOrder(['被依赖', '#4 Add to cart needs tier']);

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSeeInOrder(['Members buy by tier', '›', 'Direct visibility', '›', 'Cart rules', 'Tier is known at login']);
});
