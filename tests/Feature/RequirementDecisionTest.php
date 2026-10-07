<?php

use App\Data\Requirements\DeliveryStatus;
use App\Data\Requirements\RequirementDecision;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\FeatureStatus;
use App\Enums\RequirementDecider;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Filament\Pages\RequirementTree;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\User;
use App\Services\Requirements\RequirementDecisions;
use App\Services\Requirements\RequirementTreeService;
use Filament\Facades\Filament;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;

function saveDecisionNodes(array $nodes): PendingCommand
{
    $file = tempnam(sys_get_temp_dir(), 'requirements-decision');
    file_put_contents($file, json_encode(['project' => 'dq', 'nodes' => $nodes]));

    return test()->artisan('requirements:save', ['file' => $file]);
}

/**
 * @return array{Project, User, Requirement} the project, its signed-in user and a decided goal to hang rules under
 */
function decisionDesk(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create(['slug' => 'dq']);
    $project->users()->attach($user);
    auth()->login($user);
    Filament::setTenant($project);
    $goal = Requirement::factory()->create(['project_id' => $project->id, 'number' => 1, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided, 'title' => 'Checkout']);

    return [$project, $user, $goal];
}

function decisionRule(Requirement $goal, int $number, RequirementStatus $status, array $attributes = []): Requirement
{
    return Requirement::factory()->create([
        'project_id' => $goal->project_id, 'number' => $number, 'parent_id' => $goal->id, 'kind' => RequirementKind::Rule,
        'status' => $status, 'title' => "Rule {$number}", 'source' => 'spec v1', ...$attributes,
    ]);
}

function shippingDecision(): array
{
    return [
        'now' => 'Free shipping over 50, because the old carrier was cheap',
        'change' => 'Free shipping over 80',
        'difference' => 'Orders between 50 and 80 pay shipping',
        'risk' => 'Fewer small orders',
        'impact' => 'Shipping calculator',
        'options' => [
            ['key' => 'A', 'label' => 'Raise to 80', 'outcome' => 'accept', 'consequence' => 'Old rule voided', 'result_title' => 'Free shipping over 80 from November', 'recommended' => true],
            ['key' => 'B', 'label' => 'Keep 50', 'outcome' => 'keep_current', 'consequence' => 'Nothing changes'],
            ['key' => 'C', 'label' => 'Raise to 65', 'outcome' => 'custom', 'consequence' => 'Middle ground', 'result_title' => 'Free shipping over 65'],
        ],
    ];
}

it('stores a decision with the node and rejects one of the wrong shape [T58]', function (array $decision, ?string $message) {
    [$project, , $goal] = decisionDesk();

    $command = saveDecisionNodes([['parent' => 1, 'kind' => '规则', 'title' => 'Free shipping over 80', 'decider' => 'Gordon', 'decision' => $decision]]);

    if ($message === null) {
        $command->assertSuccessful()->run();
        $stored = Requirement::where('project_id', $project->id)->where('title', 'Free shipping over 80')->sole()->decision;

        expect($stored->now)->toBe('Free shipping over 50, because the old carrier was cheap')
            ->and($stored->recommended()->key)->toBe('A')
            ->and($stored->option('C')->resultTitle)->toBe('Free shipping over 65');

        return;
    }

    $command->expectsOutputToContain($message)->assertFailed()->run();
    expect(Requirement::where('project_id', $project->id)->count())->toBe(1);
})->with([
    'a full decision' => [shippingDecision(), null],
    'not an object' => [['just text'], 'decision must be an object'],
    'no change' => [['now' => 'x', 'options' => shippingDecision()['options']], 'decision.change is required'],
    'one option' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0]]], 'at least two options'],
    'unknown outcome' => [[...shippingDecision(), 'options' => [['key' => 'A', 'label' => 'a', 'outcome' => 'maybe', 'consequence' => 'c'], shippingDecision()['options'][1]]], 'outcome must be one of'],
    'custom without its wording' => [[...shippingDecision(), 'options' => [['key' => 'A', 'label' => 'a', 'outcome' => 'custom', 'consequence' => 'c'], shippingDecision()['options'][1]]], 'a custom outcome needs result_title'],
    'two recommended' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'recommended' => true]]], 'at most one option recommended'],
    'duplicate keys' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'key' => 'A']]], 'keys must be unique'],
    'reserved key' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'key' => 'skip']]], 'key cannot be'],
    'unknown key' => [[...shippingDecision(), 'colour' => 'red'], 'unknown key "colour"'],
]);

it('shows one status per node and rolls them up as 待决策 / 待做 / 进行中 / 完成 [T59]', function () {
    [$project, , $goal] = decisionDesk();
    $built = Feature::factory()->create(['project_id' => $project->id, 'status' => FeatureStatus::Done]);
    $building = Feature::factory()->create(['project_id' => $project->id, 'status' => FeatureStatus::InDevelopment]);
    decisionRule($goal, 2, RequirementStatus::Proposed, ['decider' => RequirementDecider::Boss]);
    decisionRule($goal, 3, RequirementStatus::Decided);
    decisionRule($goal, 4, RequirementStatus::Decided)->linkedFeatures()->attach($building);
    decisionRule($goal, 5, RequirementStatus::Decided)->linkedFeatures()->attach($built);
    decisionRule($goal, 6, RequirementStatus::Void);

    $tree = app(RequirementTreeService::class)->tree($project);
    $progress = collect(RequirementTreeNode::flattened($tree))->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect($progress)->toBe([1 => RequirementProgress::InProgress, 2 => RequirementProgress::Pending, 3 => RequirementProgress::Todo, 4 => RequirementProgress::InProgress, 5 => RequirementProgress::Done, 6 => RequirementProgress::Dropped])
        ->and(RequirementProgress::of(RequirementStatus::Decided, DeliveryStatus::Failed))->toBe(RequirementProgress::InProgress)
        ->and(RequirementProgress::of(RequirementStatus::Decided, DeliveryStatus::Verified))->toBe(RequirementProgress::Done)
        ->and(RequirementTreeNode::total($tree)->progressCounts())->toBe(['待决策' => 1, '待做' => 1, '进行中' => 1, '完成' => 1]);

    Livewire::test(RequirementTree::class)
        ->assertSee('title="1 待决策 · 1 待做 · 1 进行中 · 1 完成"', false)
        ->assertSeeHtmlInOrder(['1<span class="hidden @xl:inline"> 待决策</span>', '1<span class="hidden @xl:inline"> 完成</span>'])
        ->assertSeeInOrder(['Rule 2', '待决策 · 等老板', 'Rule 3', '待做', 'Rule 4', '进行中', 'Rule 5', '完成', 'Rule 6', '放弃']);
});

it('keeps a pick as a draft that survives reopening the page and counts it [T60]', function () {
    [$project, $user, $goal] = decisionDesk();
    $rule = decisionRule($goal, 2, RequirementStatus::Proposed, ['decision' => shippingDecision()]);
    decisionRule($goal, 3, RequirementStatus::Proposed);

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('choose', $rule->id, 'A')
        ->call('choose', $rule->id, 'Z')
        ->assertNotified('#2 没有选项 Z。');

    expect(RequirementDecisionDraft::sole()->only(['requirement_id', 'user_id', 'choice']))->toBe(['requirement_id' => $rule->id, 'user_id' => $user->id, 'choice' => 'A']);

    Livewire::withQueryParams(['tab' => 'pending', 'selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeHtmlInOrder(['data-drafted="yes"', 'Rule 2', 'data-drafted="no"', 'Rule 3', 'data-picked', 'A. Raise to 80', 'B. Keep 50', '已选 1 / 2'])
        ->call('writeCustom', $rule->id, 'Free shipping over 70');

    expect(RequirementDecisionDraft::sole()->only(['choice', 'custom_text']))->toBe(['choice' => 'custom', 'custom_text' => 'Free shipping over 70']);

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('writeCustom', $rule->id, '  ');

    expect(RequirementDecisionDraft::count())->toBe(0);
});

it('applies every kind of answer in one confirmed batch, with history, and clears the drafts [T61]', function () {
    [$project, $user, $goal] = decisionDesk();
    $old = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Free shipping over 50']);
    $conflict = decisionRule($goal, 3, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping over 80', 'decision' => shippingDecision()]);
    $kept = decisionRule($goal, 4, RequirementStatus::Proposed, ['decision' => shippingDecision()]);
    $rejected = decisionRule($goal, 5, RequirementStatus::Proposed);
    $custom = decisionRule($goal, 6, RequirementStatus::Proposed);
    $forwarded = decisionRule($goal, 7, RequirementStatus::Proposed, ['decider' => RequirementDecider::Gordon]);
    $skipped = decisionRule($goal, 8, RequirementStatus::Proposed);
    $decisions = app(RequirementDecisions::class);
    $decisions->choose($conflict, $user, 'A');
    $decisions->choose($kept, $user, 'B');
    $decisions->choose($rejected, $user, 'B');
    $decisions->choose($custom, $user, 'custom', 'Write it my way');
    $decisions->choose($forwarded, $user, 'ask_boss');
    $decisions->choose($skipped, $user, 'skip');
    $today = now()->toDateString();

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('confirmAllDrafts')
        ->assertNotified('定了 4 条，转老板 1 条，先不定 1 条');

    expect($conflict->fresh()->only(['status', 'title', 'source', 'decided_by', 'decider']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Free shipping over 80 from November', 'source' => "spec v1；Gordon 拍板 {$today}：选 A Raise to 80", 'decided_by' => 'Gordon', 'decider' => null])
        ->and($conflict->fresh()->decided_at->toDateString())->toBe($today)
        ->and($conflict->revisions()->first()->only(['old_status', 'new_status', 'new_statement', 'reason']))->toBe(['old_status' => '冲突', 'new_status' => '已定', 'new_statement' => 'Free shipping over 80 from November', 'reason' => 'Raise to 80：Old rule voided'])
        ->and($old->fresh()->status)->toBe(RequirementStatus::Void)
        ->and($old->revisions()->first()->reason)->toStartWith('被 #3 取代')
        ->and($kept->fresh()->status)->toBe(RequirementStatus::Void)
        ->and($kept->revisions()->first()->reason)->toBe('Keep 50：Nothing changes')
        ->and($rejected->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Void, 'title' => 'Rule 5'])
        ->and($rejected->revisions()->first()->reason)->toBe('不要：这条作废')
        ->and($custom->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Write it my way'])
        ->and($custom->revisions()->first()->reason)->toBe('Gordon 自己写')
        ->and($forwarded->fresh()->only(['status', 'decider']))->toBe(['status' => RequirementStatus::Proposed, 'decider' => RequirementDecider::Boss])
        ->and($skipped->fresh()->status)->toBe(RequirementStatus::Proposed)
        ->and(RequirementDecisionDraft::count())->toBe(0);

    Livewire::withQueryParams(['tab' => 'todo'])->test(RequirementTree::class)
        ->assertSeeInOrder(['Free shipping over 80 from November', 'Write it my way'])
        ->assertDontSee('Rule 8');
});

it('changes nothing when one answer in the batch no longer fits [T61]', function () {
    [, $user, $goal] = decisionDesk();
    $accepted = decisionRule($goal, 2, RequirementStatus::Proposed);
    $broken = decisionRule($goal, 3, RequirementStatus::Proposed, ['decision' => shippingDecision()]);
    $decisions = app(RequirementDecisions::class);
    $decisions->choose($accepted, $user, 'A');
    $decisions->choose($broken, $user, 'C');
    $broken->update(['decision' => [...shippingDecision(), 'options' => array_slice(shippingDecision()['options'], 0, 2)]]);

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('confirmAllDrafts')
        ->assertNotified('没有确认，什么都没改');

    expect($accepted->fresh()->status)->toBe(RequirementStatus::Proposed)
        ->and(RequirementDecisionDraft::count())->toBe(2);
});

it('lists every question waiting on the boss as plain text, with what we recommend [T62]', function () {
    [$project, $user, $goal] = decisionDesk();
    decisionRule($goal, 2, RequirementStatus::Proposed, ['decider' => RequirementDecider::Boss, 'title' => 'Free shipping over 80', 'decision' => shippingDecision()]);
    $forwarded = decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Gift wrap for members']);
    decisionRule($goal, 4, RequirementStatus::Proposed, ['decider' => RequirementDecider::Gordon, 'title' => 'Not for the boss']);
    app(RequirementDecisions::class)->choose($forwarded, $user, 'ask_boss');
    app(RequirementDecisions::class)->confirm($project, $user, collect([$forwarded]));

    $text = app(RequirementDecisions::class)->bossQuestions($project);

    expect($text)->toContain('共 2 条')
        ->toContain(implode("\n", [
            '1. Free shipping over 80（编号 2）',
            '   现在：Free shipping over 50, because the old carrier was cheap',
            '   要改成：Free shipping over 80',
            '   差别：Orders between 50 and 80 pay shipping',
            '   风险：Fewer small orders',
            '   选项：',
            '     A. Raise to 80 —— Old rule voided',
            '     B. Keep 50 —— Nothing changes',
            '     C. Raise to 65 —— Middle ground',
            '   我们推荐：A. Raise to 80',
        ]))
        ->toContain("2. Gift wrap for members（编号 3）\n   现在：还没有这条规则")
        ->toContain('   我们推荐：没有倾向，请老板定')
        ->not->toContain('Not for the boss');

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('toggleBossQuestions')
        ->assertSee('Gift wrap for members');
});

it('records the boss answer through the same cards, credited to the boss [T63]', function () {
    [, , $goal] = decisionDesk();
    $rule = decisionRule($goal, 2, RequirementStatus::Proposed, ['decider' => RequirementDecider::Boss, 'decision' => shippingDecision()]);

    Livewire::withQueryParams(['tab' => 'pending', 'waitingOn' => 'boss', 'selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSee('老板的答复')
        ->assertDontSee('问老板')
        ->call('choose', $rule->id, 'A')
        ->call('confirmAllDrafts');

    $today = now()->toDateString();

    expect($rule->fresh()->only(['status', 'decided_by', 'source']))->toBe(['status' => RequirementStatus::Decided, 'decided_by' => '老板', 'source' => "spec v1；老板拍板 {$today}（经 Gordon 转）：选 A Raise to 80"])
        ->and($rule->revisions()->first()->decided_by)->toBe('老板');
});

it('lists decided rules not built yet in dependency order [T64]', function () {
    [$project, , $goal] = decisionDesk();
    $later = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Charge the card']);
    $first = decisionRule($goal, 3, RequirementStatus::Decided, ['title' => 'Validate the card']);
    $later->dependsOn()->attach($first);
    decisionRule($goal, 4, RequirementStatus::Decided, ['title' => 'Send receipt'])->linkedFeatures()->attach(Feature::factory()->create(['project_id' => $project->id, 'status' => FeatureStatus::InDevelopment]));
    decisionRule($goal, 5, RequirementStatus::Decided, ['title' => 'Already shipped'])->linkedFeatures()->attach(Feature::factory()->create(['project_id' => $project->id, 'status' => FeatureStatus::Done]));
    decisionRule($goal, 6, RequirementStatus::Proposed, ['title' => 'Still a proposal']);

    Livewire::withQueryParams(['tab' => 'todo'])->test(RequirementTree::class)
        ->assertSeeInOrder(['待做（3）', 'Validate the card', 'Charge the card', '进行中', 'Send receipt'])
        ->assertDontSee('Already shipped')
        ->assertDontSee('Still a proposal');
});

it('lists pending nodes by goal and shows one panel where each piece appears once [T65]', function () {
    [$project, , $goal] = decisionDesk();
    $old = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Free shipping over 50', 'decided_by' => '老板', 'decided_at' => '2026-10-05']);
    $old->linkedFeatures()->attach(Feature::factory()->create(['project_id' => $project->id, 'number' => 9, 'title' => 'Shipping calculator']));
    decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Gift wrap for members', 'rationale' => 'see GiftWrapService::apply']);
    decisionRule($goal, 4, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping over 80', 'decision' => shippingDecision(), 'rationale' => 'Free shipping over 50, because the old carrier was cheap']);
    decisionRule($goal, 5, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping for everyone']);

    $html = Livewire::withQueryParams(['tab' => 'pending', 'selectedNumber' => 4])->test(RequirementTree::class)
        ->assertSeeInOrder(['Checkout', '改规则', 'Free shipping over 80', '改规则', 'Free shipping for everyone', 'Gift wrap for members'])
        ->assertSeeInOrder([
            'Checkout', '#4', 'Free shipping over 80', '待决策 · 等我',
            '需要你决定', '现在', 'Free shipping over 50, because the old carrier was cheap', '现行规则：Free shipping over 50', '要改成', 'Free shipping over 80',
            '差别', 'Orders between 50 and 80 pay shipping', '风险', 'Fewer small orders',
            'A. Raise to 80', '★推荐', 'Old rule voided', '（定了以后：Free shipping over 80 from November）', 'B. Keep 50', 'C. Raise to 65',
            '✎ 自己写', '问老板', '先不定', '背景（与上面重复，点开看）', '来源：spec v1', '做到哪了', 'F9 · Shipping calculator', '历史',
        ])
        ->html();

    foreach (['需要你决定', '背景（与上面重复', '做到哪了', '>历史<', 'data-panel-header'] as $section) {
        expect(substr_count($html, $section))->toBe(1, $section);
    }

    expect($html)->not->toContain('>为什么<')->not->toContain('protect')->not->toContain('会动到')->not->toContain('背景和出处')->not->toContain('已选 A');

    Livewire::withQueryParams(['tab' => 'todo', 'selectedNumber' => 3])->test(RequirementTree::class)
        ->assertSeeInOrder(['Gift wrap for members', '需要你决定', '还没有这条规则', 'A. 同意', 'B. 不要', '为什么', 'see GiftWrapService::apply']);

    Livewire::withQueryParams(['tab' => 'todo', 'selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['Free shipping over 50', '老板拍板 2026-10-05'])
        ->assertDontSee('需要你决定');
});

it('never asks to decide a 分组: no card, no count, not on the boss list, and its row sums up its rules [T66]', function () {
    [$project, , $goal] = decisionDesk();
    $sub = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided, 'title' => 'Shipping']);
    $pendingGroup = Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Proposed, 'decider' => RequirementDecider::Boss, 'title' => 'Pending group']);
    $builtGroup = Requirement::factory()->create(['project_id' => $project->id, 'number' => 4, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Proposed, 'title' => 'Built group']);
    decisionRule($pendingGroup, 5, RequirementStatus::Proposed, ['title' => 'Rule for me']);
    decisionRule($pendingGroup, 6, RequirementStatus::Proposed, ['title' => 'Rule for the boss', 'decider' => RequirementDecider::Boss]);
    decisionRule($builtGroup, 7, RequirementStatus::Decided, ['title' => 'Done rule'])->linkedFeatures()->attach(Feature::factory()->create(['project_id' => $project->id, 'status' => FeatureStatus::Done]));

    $tree = app(RequirementTreeService::class)->tree($project);
    $progress = collect(RequirementTreeNode::flattened($tree))->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect(app(RequirementTreeService::class)->awaitingDecision($project, $tree)->pluck('number')->all())->toBe([5, 6])
        ->and(RequirementTreeNode::total($tree)->pending())->toBe(2)
        ->and($progress[3])->toBe(RequirementProgress::Pending)
        ->and($progress[4])->toBe(RequirementProgress::Done)
        ->and(app(RequirementDecisions::class)->bossQuestions($project))->toContain('共 1 条')->not->toContain('Pending group');

    Livewire::withQueryParams(['selectedNumber' => 3])->test(RequirementTree::class)
        ->assertSeeInOrder(['等我 1', '等老板 1', '待决策（2）'])
        ->assertSeeInOrder(['Pending group', '含 2 待决策']);

    Livewire::withQueryParams(['tab' => 'pending', 'waitingOn' => 'all'])->test(RequirementTree::class)
        ->assertSee(['Rule for me', 'Rule for the boss'])
        ->assertDontSee('Built group');
});

it('saves a new 分组 as 已定 and refuses to make any 分组 a 提议 or 冲突 [T67]', function () {
    [$project, , $goal] = decisionDesk();
    Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided]);

    saveDecisionNodes([['parent' => 2, 'kind' => '分组', 'title' => 'Filed rules']])->assertSuccessful()->run();
    $group = Requirement::where('project_id', $project->id)->where('title', 'Filed rules')->sole();

    expect($group->status)->toBe(RequirementStatus::Decided);

    saveDecisionNodes([['parent' => 2, 'kind' => '分组', 'title' => 'Proposed group', 'status' => '提议']])->expectsOutputToContain('分组只是归类，不需要拍板')->assertFailed()->run();
    saveDecisionNodes([['number' => $group->number, 'status' => '提议', 'reason' => 'x']])->expectsOutputToContain('分组只是归类，不需要拍板')->assertFailed()->run();

    expect($group->fresh()->status)->toBe(RequirementStatus::Decided)
        ->and(Requirement::where('project_id', $project->id)->where('title', 'Proposed group')->exists())->toBeFalse();
});

it('decides a pending node right from the 全貌 detail and confirms every draft from the bottom bar [T68]', function () {
    [, $user, $goal] = decisionDesk();
    $rule = decisionRule($goal, 2, RequirementStatus::Proposed, ['title' => 'Free shipping over 80', 'decision' => shippingDecision(), 'rationale' => 'margin']);
    $bossRule = decisionRule($goal, 3, RequirementStatus::Proposed, ['decider' => RequirementDecider::Boss, 'title' => 'Boss rule']);
    decisionRule($goal, 4, RequirementStatus::Decided, ['title' => 'Settled rule']);
    app(RequirementDecisions::class)->choose($bossRule, $user, 'A');

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['需要你决定', '现在', 'Free shipping over 50, because the old carrier was cheap', '要改成', 'A. Raise to 80', '★推荐', '✎ 自己写', '问老板', '先不定', '为什么', 'margin', '历史'])
        ->assertSeeInOrder(['data-decision-bar', '已选 1 / 2', '确认这一批'])
        ->call('choose', $rule->id, 'A');

    expect(RequirementDecisionDraft::where('requirement_id', $rule->id)->value('choice'))->toBe('A');

    Livewire::withQueryParams(['selectedNumber' => 4])->test(RequirementTree::class)
        ->assertDontSee('需要你决定')
        ->assertSee('已选 2 / 2')
        ->call('confirmAllDrafts')
        ->assertNotified('定了 2 条，转老板 0 条，先不定 0 条');

    expect($rule->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Free shipping over 80 from November'])
        ->and($bossRule->fresh()->decided_by)->toBe('老板')
        ->and(RequirementDecisionDraft::count())->toBe(0);
});

it('warns about a title over 60 characters but saves it [T69]', function () {
    [$project] = decisionDesk();

    saveDecisionNodes([['parent' => 1, 'kind' => '规则', 'title' => str_repeat('长', 61)]])
        ->expectsOutputToContain('标题 61 字，超过 60 字；标题 ≤40 字，细节写进 decision.change。')
        ->assertSuccessful()->run();

    expect(Requirement::where('project_id', $project->id)->where('title', str_repeat('长', 61))->exists())->toBeTrue();
});

it('folds a rationale that retells 现在: two shared runs of 8+ characters, or a rule number both cite [T70]', function () {
    $decision = RequirementDecision::fromArray([...shippingDecision(), 'now' => '访客只看到价格区间，这是 Gordon 2026-10-02 定的（#151），理由是百分比能反推会员价']);

    expect($decision->retells('现状（Gordon 2026-10-02）：当初理由是百分比能反推会员价'))->toBeTrue()
        ->and($decision->retells('老板要求按 #151 改'))->toBeTrue()
        ->and($decision->retells('老板要求按 #15 改'))->toBeFalse()
        ->and($decision->retells('Gordon 2026-10-02 说的，与此无关'))->toBeFalse()
        ->and($decision->retells('margin'))->toBeFalse();
});
