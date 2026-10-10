<?php

use App\Data\Requirements\DeliveryStatus;
use App\Data\Requirements\RequirementDecision;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementRollup;
use App\Data\Requirements\RequirementTreeNode;
use App\Data\Requirements\TimelineEntry;
use App\Enums\RequirementKind;
use App\Enums\RequirementStatus;
use App\Enums\TestLastResult;
use App\Filament\Pages\RequirementTree;
use App\Models\Commit;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\Test;
use App\Models\User;
use App\Services\Requirements\RequirementDecisions;
use App\Services\Requirements\RequirementTimelines;
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

    $command = saveDecisionNodes([['parent' => 1, 'kind' => '规则', 'title' => 'Free shipping over 80', 'decision' => $decision]]);

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
    'no options' => [[...shippingDecision(), 'options' => []], 'at least one option'],
    'unknown outcome' => [[...shippingDecision(), 'options' => [['key' => 'A', 'label' => 'a', 'outcome' => 'maybe', 'consequence' => 'c'], shippingDecision()['options'][1]]], 'outcome must be one of'],
    'custom without its wording' => [[...shippingDecision(), 'options' => [['key' => 'A', 'label' => 'a', 'outcome' => 'custom', 'consequence' => 'c'], shippingDecision()['options'][1]]], 'a custom outcome needs result_title'],
    'two recommended' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'recommended' => true]]], 'at most one option recommended'],
    'duplicate keys' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'key' => 'A']]], 'keys must be unique'],
    'reserved key' => [[...shippingDecision(), 'options' => [shippingDecision()['options'][0], [...shippingDecision()['options'][1], 'key' => 'later']]], 'key cannot be'],
    'unknown key' => [[...shippingDecision(), 'colour' => 'red'], 'unknown key "colour"'],
]);

it('shows one status per node and rolls them up as 待决策 / 待做 / 完成 [T59]', function () {
    [$project, , $goal] = decisionDesk();
    decisionRule($goal, 2, RequirementStatus::Proposed);
    decisionRule($goal, 3, RequirementStatus::Decided);
    decisionRule($goal, 4, RequirementStatus::Decided)->commits()->attach(Commit::factory()->create(['project_id' => $project->id]));
    decisionRule($goal, 5, RequirementStatus::Decided, ['accepted_at' => now()]);
    decisionRule($goal, 6, RequirementStatus::Void);

    $tree = app(RequirementTreeService::class)->tree($project);
    $progress = collect(RequirementTreeNode::flattened($tree))->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect($progress)->toBe([1 => RequirementProgress::Pending, 2 => RequirementProgress::Pending, 3 => RequirementProgress::Todo, 4 => RequirementProgress::Todo, 5 => RequirementProgress::Done, 6 => RequirementProgress::Dropped])
        ->and(RequirementProgress::of(RequirementStatus::Decided, DeliveryStatus::Failed))->toBe(RequirementProgress::Todo)
        ->and(RequirementProgress::of(RequirementStatus::Decided, DeliveryStatus::InProgress))->toBe(RequirementProgress::Todo)
        ->and(RequirementProgress::of(RequirementStatus::Decided, DeliveryStatus::Verified))->toBe(RequirementProgress::Done)
        ->and(RequirementTreeNode::total($tree)->progressCounts())->toBe(['待决策' => 1, '待做' => 2, '待验收' => 0, '完成' => 1]);

    Livewire::test(RequirementTree::class)
        ->assertSee('title="1 待决策 · 2 待做 · 1 完成"', false)
        ->assertDontSee('进行中')
        ->assertSeeHtmlInOrder(['1<span class="hidden @xl:inline"> 待决策</span>', '1<span class="hidden @xl:inline"> 完成</span>'])
        ->assertSeeInOrder(['Checkout', '待决策（1）', 'Rule 2', '待决策', 'Rule 3', '待做', 'Rule 4', '待做', 'Rule 5', '完成', 'Rule 6', '放弃']);
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
        ->assertSeeHtmlInOrder(['data-drafted="yes"', 'Rule 2', 'data-drafted="no"', 'Rule 3', 'data-picked', 'Raise to 80', 'Keep 50', '以后做', '已选 1 / 2'])
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
    $skipped = decisionRule($goal, 8, RequirementStatus::Proposed);
    $decisions = app(RequirementDecisions::class);
    $decisions->choose($conflict, $user, 'A');
    $decisions->choose($kept, $user, 'B');
    $decisions->choose($rejected, $user, 'keep');
    $decisions->choose($custom, $user, 'custom', 'Write it my way');
    $today = now()->toDateString();

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->call('confirmAllDrafts')
        ->assertNotified('定下 2 条 · 作废 2 条 · 定下的规则从待做开始（原来挂的功能和测试记为受影响）');

    expect($conflict->fresh()->only(['status', 'title', 'source', 'decided_by']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Free shipping over 80 from November', 'source' => "spec v1；Gordon 拍板 {$today}：Raise to 80", 'decided_by' => 'Gordon'])
        ->and($conflict->fresh()->decided_at->toDateString())->toBe($today)
        ->and($conflict->revisions()->first()->only(['old_status', 'new_status', 'new_statement', 'reason']))->toBe(['old_status' => '冲突', 'new_status' => '已定', 'new_statement' => 'Free shipping over 80 from November', 'reason' => 'Raise to 80：Old rule voided'])
        ->and($old->fresh()->status)->toBe(RequirementStatus::Void)
        ->and($old->revisions()->first()->reason)->toStartWith('被 #3 取代')
        ->and($kept->fresh()->status)->toBe(RequirementStatus::Void)
        ->and($kept->revisions()->first()->reason)->toBe('Keep 50：Nothing changes')
        ->and($rejected->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Void, 'title' => 'Rule 5'])
        ->and($rejected->revisions()->first()->reason)->toBe('保持现在：不改，照现在的做')
        ->and($custom->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Rule 6：Write it my way'])
        ->and($custom->revisions()->first()->reason)->toBe('Gordon 自己写：Write it my way')
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

it('lists decided rules not built yet in dependency order [T64]', function () {
    [$project, , $goal] = decisionDesk();
    $later = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Charge the card']);
    $first = decisionRule($goal, 3, RequirementStatus::Decided, ['title' => 'Validate the card']);
    $later->dependsOn()->attach($first);
    decisionRule($goal, 4, RequirementStatus::Decided, ['title' => 'Send receipt'])->commits()->attach(Commit::factory()->create(['project_id' => $project->id]));
    decisionRule($goal, 5, RequirementStatus::Decided, ['title' => 'Already shipped', 'accepted_at' => now()]);
    decisionRule($goal, 6, RequirementStatus::Proposed, ['title' => 'Still a proposal']);

    Livewire::withQueryParams(['tab' => 'todo'])->test(RequirementTree::class)
        ->assertSeeInOrder(['待做（3）', 'Validate the card', 'Charge the card', 'Send receipt'])
        ->assertDontSee('Already shipped')
        ->assertDontSee('Still a proposal');
});

it('shows pending nodes in tree order and shows only what the decision needs, each piece once [T65]', function () {
    [$project, , $goal] = decisionDesk();
    $old = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Free shipping over 50', 'decided_by' => '老板', 'decided_at' => '2026-10-05', 'source' => '老板文档 docs/product/S7-cart/spec.md v0.9 §3.7（Haorui，2026-09-01）']);
    decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Gift wrap for members', 'rationale' => 'see GiftWrapService::apply']);
    decisionRule($goal, 4, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping over 80', 'rationale' => 'margin', 'decision' => [
        ...shippingDecision(), 'difference' => 'Should orders between 50 and 80 pay shipping?', 'risk' => 'Fewer small orders than #2 promised',
    ]]);
    decisionRule($goal, 5, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping for everyone']);

    $html = Livewire::withQueryParams(['tab' => 'pending', 'selectedNumber' => 4])->test(RequirementTree::class)
        ->assertSeeInOrder(['Checkout', 'Gift wrap for members', '改规则', 'Free shipping over 80', '改规则', 'Free shipping for everyone'])
        ->assertSeeInOrder([
            'Checkout', 'Free shipping over 80', '待决策',
            '要定的事：', 'Should orders between 50 and 80 pay shipping?',
            '现在', 'Free shipping over 50, because the old carrier was cheap', '出处：老板文档 S7-cart spec v0.9 2026-09-01',
            '新要求', 'Free shipping over 80', '出处：spec v1',
            '注意：', 'Fewer small orders than',
            'Raise to 80', '：Old rule voided', '★推荐', 'Keep 50', 'Raise to 65', '以后做', '：这期不做，将来再看', '✎ 自己写',
            '更早的说法（1）', '2026-10-05', '老板拍板', '现在生效', 'Free shipping over 50', '历史',
        ])
        ->html();

    foreach (['要定的事', '注意：', '★推荐', '更早的说法', '>历史<', 'data-panel-header'] as $section) {
        expect(substr_count($html, $section))->toBe(1, $section);
    }

    $panel = substr($html, (int) strpos($html, 'data-panel='), (int) strpos($html, 'data-decision-bar') - (int) strpos($html, 'data-panel='));

    expect($panel)->not->toMatch('/#\d|\b[FT]\d+ ·/')
        ->toContain('<span class="underline decoration-dotted" title="Free shipping over 50">「Free shipping over 5…」</span>')
        ->not->toContain('margin')
        ->not->toContain('Shipping calculator');

    foreach (['为什么', '差别', '风险', '你的意见', '问老板', '>先不定<', '老板的问题', '做到哪了', '依赖', '当时写的理由', '来源：', '定了以后', '等我'] as $gone) {
        expect($html)->not->toContain($gone);
    }

    Livewire::withQueryParams(['tab' => 'todo', 'selectedNumber' => 3])->test(RequirementTree::class)
        ->assertSeeInOrder(['Gift wrap for members', '要定的事：', '要不要加这条规则？', '还没有这条规则', '出处：没有文档，是代码现状', '同意', '保持现在', '以后做'])
        ->assertDontSee('GiftWrapService');

    Livewire::withQueryParams(['tab' => 'todo', 'selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSee('出处：老板文档 S7-cart spec v0.9 2026-09-01')
        ->assertDontSee('要定的事');
});

it('takes where 现在 comes from off the replaced rule, else now_source, else says it is just the code [T168]', function () {
    [, , $goal] = decisionDesk();
    $sourced = decisionRule($goal, 2, RequirementStatus::Decided, ['source' => '老板文档 docs/product/S3-home/spec.md v1.1 §2.1（Haorui，2026-10-06）']);
    $unsourced = decisionRule($goal, 3, RequirementStatus::Decided, ['source' => null]);
    $withNowSource = [...shippingDecision(), 'now_source' => '会员页文档 v1.2 2026-10-01'];
    $cases = [
        decisionRule($goal, 4, RequirementStatus::Conflict, ['supersedes_id' => $sourced->id, 'decision' => $withNowSource]),
        decisionRule($goal, 5, RequirementStatus::Conflict, ['supersedes_id' => $unsourced->id, 'decision' => $withNowSource]),
        decisionRule($goal, 6, RequirementStatus::Proposed, ['decision' => $withNowSource]),
        decisionRule($goal, 7, RequirementStatus::Proposed, ['source' => '现状（代码 app/Models/User.php:967-970 canAccessPanel）']),
    ];

    expect(array_map(fn (Requirement $requirement): ?string => $requirement->fresh()->nowSource(), $cases))->toBe(['老板文档 S3-home spec v1.1 2026-10-06', '会员页文档 v1.2 2026-10-01', '会员页文档 v1.2 2026-10-01', null])
        ->and(Requirement::briefSource('老板文档 docs/product/S8-my-account/spec.md v1.4 §3.12、验收 16、docs/product/S8-my-account/code-changes.md 第 12 项（Haorui，2026-10-07）'))->toBe('老板文档 S8-my-account spec v1.4、S8-my-account code-changes 2026-10-07')
        ->and(Requirement::briefSource('老板文档 docs/product/S2-organizer-entry/spec.md v1.1 Q3、C1（Haorui，2026-10-05）'))->toBe('老板文档 S2-organizer-entry spec v1.1 2026-10-05')
        ->and(Requirement::briefSource('.ai/rules/app.md §会员钱包从账本现算'))->toBe('项目规则 会员钱包从账本现算')
        ->and(Requirement::briefSource('S5 spec v1.0'))->toBe('S5 spec v1.0')
        ->and(Requirement::briefSource(null))->toBeNull();

    Livewire::withQueryParams(['selectedNumber' => 7])->test(RequirementTree::class)
        ->assertSeeInOrder(['现在', '出处：没有文档，是代码现状', '新要求', '出处：代码现状']);
});

it('never asks to decide a 分组: no card, no count, and its row sums up its rules [T66]', function () {
    [$project, , $goal] = decisionDesk();
    $sub = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided, 'title' => 'Shipping']);
    $pendingGroup = Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Proposed, 'title' => 'Pending group']);
    $builtGroup = Requirement::factory()->create(['project_id' => $project->id, 'number' => 4, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Proposed, 'title' => 'Built group']);
    decisionRule($pendingGroup, 5, RequirementStatus::Proposed, ['title' => 'Rule for me']);
    decisionRule($pendingGroup, 6, RequirementStatus::Proposed, ['title' => 'Another rule']);
    decisionRule($builtGroup, 7, RequirementStatus::Decided, ['title' => 'Done rule', 'accepted_at' => now()]);

    $tree = app(RequirementTreeService::class)->tree($project);
    $progress = collect(RequirementTreeNode::flattened($tree))->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect(app(RequirementTreeService::class)->awaitingDecision($project, $tree)->pluck('number')->all())->toBe([5, 6])
        ->and(RequirementTreeNode::total($tree)->pending())->toBe(2)
        ->and($progress[3])->toBe(RequirementProgress::Pending)
        ->and($progress[4])->toBe(RequirementProgress::Done);

    Livewire::withQueryParams(['selectedNumber' => 3])->test(RequirementTree::class)
        ->assertSeeInOrder(['Pending group', '待决策（2）'])
        ->assertDontSee('要定的事');

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSee('Rule for me')
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
    $other = decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Other rule']);
    decisionRule($goal, 4, RequirementStatus::Decided, ['title' => 'Settled rule']);
    app(RequirementDecisions::class)->choose($other, $user, 'A');

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['要定的事：', 'Orders between 50 and 80 pay shipping', '现在', 'Free shipping over 50, because the old carrier was cheap', '新要求', 'Raise to 80', '★推荐', '✎ 自己写', '历史'])
        ->assertSeeInOrder(['data-decision-bar', '已选 1 / 2', '确认这一批'])
        ->call('choose', $rule->id, 'A');

    expect(RequirementDecisionDraft::where('requirement_id', $rule->id)->value('choice'))->toBe('A');

    Livewire::withQueryParams(['selectedNumber' => 4])->test(RequirementTree::class)
        ->assertDontSee('要定的事')
        ->assertSee('已选 2 / 2')
        ->call('confirmAllDrafts')
        ->assertNotified('定下 2 条 · 定下的规则从待做开始（原来挂的功能和测试记为受影响）');

    expect($rule->fresh()->only(['status', 'title']))->toBe(['status' => RequirementStatus::Decided, 'title' => 'Free shipping over 80 from November'])
        ->and($other->fresh()->decided_by)->toBe('Gordon')
        ->and(RequirementDecisionDraft::count())->toBe(0);
});

it('warns about a title over 60 characters but saves it [T69]', function () {
    [$project] = decisionDesk();

    saveDecisionNodes([['parent' => 1, 'kind' => '规则', 'title' => str_repeat('长', 61)]])
        ->expectsOutputToContain('标题 61 字，超过 60 字；标题 ≤40 字，细节写进 decision.change。')
        ->assertSuccessful()->run();

    expect(Requirement::where('project_id', $project->id)->where('title', str_repeat('长', 61))->exists())->toBeTrue();
});

it('says what the batch will do before it is confirmed [T71]', function () {
    [, $user, $goal] = decisionDesk();
    $mine = decisionRule($goal, 2, RequirementStatus::Proposed, ['title' => 'Mine', 'decision' => shippingDecision()]);
    $kept = decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Kept', 'decision' => shippingDecision()]);
    $later = decisionRule($goal, 4, RequirementStatus::Proposed, ['title' => 'Later', 'decision' => shippingDecision()]);
    $decisions = app(RequirementDecisions::class);
    $decisions->choose($mine, $user, 'A');
    $decisions->choose($kept, $user, 'B');
    $decisions->choose($later, $user, 'later');

    Livewire::test(RequirementTree::class)
        ->assertSeeInOrder(['已选 3 / 3', '这批会：定下 1 条 · 作废 1 条 · 以后做 1 条'])
        ->assertSeeHtml('「Mine」 Raise to 80'.PHP_EOL.'「Kept」 Keep 50'.PHP_EOL.'「Later」 以后做');

    RequirementDecisionDraft::where('requirement_id', $later->id)->update(['choice' => 'Z']);

    expect(fn () => $decisions->confirm($goal->project, $user, collect([$mine, $kept, $later])))->toThrow(InvalidArgumentException::class, '#4 没有选项 Z')
        ->and($mine->fresh()->status)->toBe(RequirementStatus::Proposed);
});

it('keeps the selected node in view on every tab, opening its ancestors in the tree [T72]', function () {
    [$project, $user, $goal] = decisionDesk();
    $sub = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided, 'title' => 'Shipping']);
    $group = Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Decided, 'title' => 'Thresholds']);
    $pending = decisionRule($group, 4, RequirementStatus::Proposed, ['title' => 'Deep pending rule']);
    $todo = decisionRule($group, 5, RequirementStatus::Decided, ['title' => 'Deep todo rule']);

    $page = Livewire::withQueryParams(['tab' => 'pending', 'selectedNumber' => 4])->test(RequirementTree::class);

    expect($page->html())->toMatch('/data-requirement-number="4"\s+data-selected/');

    $page->call('setTab', 'overview')
        ->assertSet('expanded', [$group->id => true, $sub->id => true, $goal->id => true])
        ->assertDispatched('reveal-selected');

    expect(Livewire::withQueryParams(['selectedNumber' => 4])->test(RequirementTree::class)->assertSeeInOrder(['Thresholds', 'Deep pending rule'])->html())->toMatch('/data-requirement-number="4"\s+data-selected/')
        ->and(Livewire::withQueryParams(['tab' => 'todo', 'selectedNumber' => 5])->test(RequirementTree::class)->html())->toMatch('/data-requirement-number="5"\s+data-selected/')
        ->and(Livewire::withQueryParams(['tab' => 'changes', 'selectedNumber' => 5])->test(RequirementTree::class)->html())->toMatch('/data-selected\s+class="rounded-md border p-3 border-primary-500/');
});

it('shows 待决策, 待做 and 以后做 as the 全貌 tree cut down to their nodes and the ancestors above them, opened [T173]', function () {
    [$project, , $goal] = decisionDesk();
    $sub = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided, 'title' => 'Shipping']);
    $group = Requirement::factory()->create(['project_id' => $project->id, 'number' => 3, 'parent_id' => $sub->id, 'kind' => RequirementKind::Group, 'status' => RequirementStatus::Decided, 'title' => 'Thresholds']);
    decisionRule($group, 4, RequirementStatus::Proposed, ['title' => 'Deep pending rule']);
    decisionRule($group, 5, RequirementStatus::Decided, ['title' => 'Deep todo rule']);
    decisionRule($group, 6, RequirementStatus::Later, ['title' => 'Deep later rule']);
    $payments = Requirement::factory()->create(['project_id' => $project->id, 'number' => 7, 'kind' => RequirementKind::Goal, 'status' => RequirementStatus::Decided, 'title' => 'Payments']);
    decisionRule($payments, 8, RequirementStatus::Decided, ['title' => 'Refund rule']);

    Livewire::test(RequirementTree::class)->assertDontSee('Deep pending rule');

    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSeeInOrder(['Checkout', 'Shipping', 'Thresholds', 'Deep pending rule'])
        ->assertSeeHtml('data-requirement-number="4"')
        ->assertDontSee(['Deep todo rule', 'Deep later rule', 'Payments', 'Refund rule']);

    Livewire::withQueryParams(['tab' => 'todo'])->test(RequirementTree::class)
        ->assertSeeInOrder(['已定还没做完的，树里从上往下按依赖顺序做。', 'Checkout', 'Shipping', 'Thresholds', 'Deep todo rule', 'Payments', 'Refund rule'])
        ->assertDontSee(['Deep pending rule', 'Deep later rule']);

    Livewire::withQueryParams(['tab' => 'later'])->test(RequirementTree::class)
        ->assertSeeInOrder(['Checkout', 'Shipping', 'Thresholds', 'Deep later rule'])
        ->assertDontSee(['Deep pending rule', 'Deep todo rule', 'Payments', 'Refund rule']);
});

it('marks each 待决策 node in the tree as picked or not, keeps 改规则, and drops it from the tree once confirmed [T174]', function () {
    [, $user, $goal] = decisionDesk();
    $old = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Free shipping over 50']);
    $conflict = decisionRule($goal, 3, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Free shipping over 80', 'decision' => shippingDecision()]);
    decisionRule($goal, 4, RequirementStatus::Proposed, ['title' => 'Gift wrap']);
    app(RequirementDecisions::class)->choose($conflict, $user, 'A');

    $page = Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)
        ->assertSeeHtmlInOrder(['Checkout', 'data-drafted="yes"', '改规则', 'Free shipping over 80', 'data-drafted="no"', 'Gift wrap', '已选 1 / 2']);

    expect(substr_count($page->html(), 'data-drafted='))->toBe(2);

    $page->call('confirmAllDrafts')
        ->assertDontSeeHtml('data-requirement-number="3"')
        ->assertSeeHtml('data-requirement-number="4"');
});

it('sums a parent up from everything under it, open decisions included [T73]', function (array $delivered, int $pending, ?RequirementProgress $expected) {
    expect((new RequirementRollup($delivered, $pending))->progress())->toBe($expected);
})->with([
    'all built' => [['已验证' => 2, '已实现' => 1], 0, RequirementProgress::Done],
    'built and a decision still open' => [['已验证' => 3], 1, RequirementProgress::Pending],
    'built and not built' => [['已验证' => 1, '未实现' => 1], 0, RequirementProgress::Todo],
    'built and being built' => [['已验证' => 1, '实现中' => 1], 0, RequirementProgress::Todo],
    'nothing built, all ready' => [['未实现' => 2], 0, RequirementProgress::Todo],
    'all open decisions' => [[], 2, RequirementProgress::Pending],
    'ready and open, nothing built' => [['未实现' => 1], 1, RequirementProgress::Pending],
    'failed' => [['验证失败' => 1, '已验证' => 1], 0, RequirementProgress::Todo],
    'nothing counted' => [[], 0, null],
]);

it('shows a sub-goal with a built rule and an open one as 待决策, not done [T73]', function () {
    [$project, , $goal] = decisionDesk();
    $sub = Requirement::factory()->create(['project_id' => $project->id, 'number' => 2, 'parent_id' => $goal->id, 'kind' => RequirementKind::SubGoal, 'status' => RequirementStatus::Decided, 'title' => 'Members only']);
    decisionRule($sub, 3, RequirementStatus::Decided, ['accepted_at' => now()]);
    decisionRule($sub, 4, RequirementStatus::Proposed);
    decisionRule($sub, 5, RequirementStatus::Void);
    decisionRule($sub, 6, RequirementStatus::Decided, ['title' => 'Failing rule'])->tests()->attach(Test::factory()->create(['project_id' => $project->id, 'last_result' => TestLastResult::Failed]));

    Livewire::withQueryParams(['selectedNumber' => 6])->test(RequirementTree::class)
        ->assertSeeInOrder(['Members only', '待决策（1）', '✗ 1', 'Failing rule', '待做', '✗ 验证失败']);

    $progress = collect(RequirementTreeNode::flattened(app(RequirementTreeService::class)->tree($project)))
        ->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect($progress[2])->toBe(RequirementProgress::Pending)
        ->and($progress[1])->toBe(RequirementProgress::Pending);
});

it('starts a newly decided rule from 待做, signed by Gordon, and shows the old rule as superseded [T74]', function () {
    [$project, $user, $goal] = decisionDesk();
    $old = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Visitors see a price range']);
    $conflict = decisionRule($goal, 3, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Visitors see the market price', 'decision' => [
        ...shippingDecision(),
        'options' => [['key' => 'A', 'label' => '按新要求改', 'outcome' => 'accept', 'consequence' => 'Old rule voided']],
    ]]);
    $conflict->forceFill(['accepted_at' => now()])->save();
    $conflict->tests()->attach(Test::factory()->create(['project_id' => $project->id, 'title' => 'Visitor sees no member price', 'last_result' => TestLastResult::Passed]));
    decisionRule($goal, 4, RequirementStatus::Void, ['title' => 'Dropped idea']);
    app(RequirementDecisions::class)->choose($conflict, $user, 'A');

    expect(app(RequirementDecisions::class)->preview($user, collect([$conflict]))->summary())->toContain('定下的规则从待做开始');

    app(RequirementDecisions::class)->confirm($project, $user, collect([$conflict]));
    $decided = $conflict->fresh();
    $today = now()->toDateString();

    expect($decided->only(['status', 'decided_by', 'source']))->toBe(['status' => RequirementStatus::Decided, 'decided_by' => 'Gordon', 'source' => "spec v1；Gordon 拍板 {$today}：按新要求改"])
        ->and($decided->decided_at->toDateString())->toBe($today)
        ->and($decided->accepted_at)->toBeNull()
        ->and($decided->tests()->count())->toBe(0)
        ->and($decided->decision->impact)->toBe('Shipping calculator；受影响（定下前挂着的）：测试「Visitor sees no member price」（通过）');

    $progress = collect(RequirementTreeNode::flattened(app(RequirementTreeService::class)->tree($project)))
        ->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect($progress[2])->toBe(RequirementProgress::Superseded)
        ->and($progress[3])->toBe(RequirementProgress::Todo)
        ->and($progress[4])->toBe(RequirementProgress::Dropped);

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['Visitors see a price range', '已被取代', '被', '「Visitors see the market price」', "取代（{$today}）"]);
});

it('stores a written timeline and rejects one of the wrong shape [T75]', function (array $timeline, ?string $message) {
    [$project] = decisionDesk();

    $command = saveDecisionNodes([['parent' => 1, 'kind' => '规则', 'title' => 'Timeline rule', 'timeline' => $timeline]]);

    if ($message === null) {
        $command->assertSuccessful()->run();

        expect(Requirement::where('project_id', $project->id)->where('title', 'Timeline rule')->sole()->timeline)->toEqual($timeline);

        return;
    }

    $command->expectsOutputToContain($message)->assertFailed()->run();
})->with([
    'a full timeline' => [[['date' => '2026-10-06', 'who' => '老板文档', 'where' => '首页文档', 'said' => 'Show the market price', 'current' => true], ['who' => '代码现状', 'said' => 'Shows a range', 'conflict_with' => '老板文档']], null],
    'not a list' => [['who' => '其他'], 'timeline must be a list'],
    'unknown who' => [[['date' => '2026-10-06', 'who' => '小王', 'said' => 'x']], 'who must be one of'],
    'no date outside the code' => [[['who' => '老板拍板', 'said' => 'x']], 'date is required (only 代码现状'],
    'bad date' => [[['date' => '10/06', 'who' => '其他', 'said' => 'x']], 'date must be YYYY-MM-DD'],
    'nothing said' => [[['date' => '2026-10-06', 'who' => '其他', 'said' => '']], 'said is required'],
    'two current' => [[['date' => '2026-10-06', 'who' => '其他', 'said' => 'a', 'current' => true], ['date' => '2026-10-07', 'who' => '其他', 'said' => 'b', 'current' => true]], 'at most one entry current'],
    'unknown key' => [[['date' => '2026-10-06', 'who' => '其他', 'said' => 'a', 'colour' => 'red']], 'unknown key "colour"'],
]);

it('pieces a timeline together from the replaced rule, dated sources and rewrites when none is written [T75]', function () {
    [$project, , $goal] = decisionDesk();
    $older = decisionRule($goal, 2, RequirementStatus::Void, ['title' => 'Show nothing to visitors', 'decided_by' => 'Gordon', 'decided_at' => '2026-09-01']);
    $old = decisionRule($goal, 3, RequirementStatus::Decided, ['title' => 'Visitors see a price range', 'decided_by' => 'Gordon', 'decided_at' => '2026-10-02', 'supersedes_id' => null]);
    $old->forceFill(['supersedes_id' => $older->id])->saveQuietly();
    $conflict = decisionRule($goal, 4, RequirementStatus::Conflict, ['supersedes_id' => $old->id, 'title' => 'Visitors see the market price',
        'source' => '老板文档 docs/product/S3-home/spec.md v1.1 §2.1（Haorui，2026-10-06）；老板拍板 2026-10-07（经 Gordon 转）：选 A Show it']);

    $entries = app(RequirementTimelines::class)->for($conflict->fresh())->entries;

    expect(array_map(fn (TimelineEntry $entry): array => [$entry->date, $entry->who, $entry->said, $entry->current], $entries))->toBe([
        ['2026-10-07', '老板拍板', '选 A Show it', false],
        ['2026-10-06', '老板文档', 'Visitors see the market price', false],
        ['2026-10-02', 'Gordon 拍板', 'Visitors see a price range', true],
        ['2026-09-01', '已被取代的旧规则', 'Show nothing to visitors', false],
    ]);

    $proposal = decisionRule($goal, 5, RequirementStatus::Proposed, ['title' => 'Gift wrap', 'source' => null]);
    $proposal->update(['title' => 'Gift wrap for members']);
    $proposal->update(['status' => RequirementStatus::Decided, 'decided_by' => '老板']);
    $entries = app(RequirementTimelines::class)->for($proposal->fresh('revisions'))->entries;

    expect(array_map(fn (TimelineEntry $entry): array => [$entry->who, $entry->said, $entry->current], $entries))->toBe([
        ['老板拍板', 'Gift wrap for members', true],
    ]);
});

it('folds the timeline under 更早的说法, newest first, with conflicts and code that disagrees flagged [T75]', function () {
    [, , $goal] = decisionDesk();
    $other = decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Other rule']);
    decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Stock left on cards', 'rationale' => 'old reason', 'timeline' => [
        ['date' => '2026-09-02', 'who' => 'Gordon 拍板', 'said' => 'Hide stock from visitors', 'current' => true],
        ['date' => '2026-10-06', 'who' => '老板文档', 'where' => '首页文档', 'said' => 'Show stock left to everyone', 'conflict_with' => 'the rule from #2'],
        ['who' => '代码现状', 'said' => 'Shows stock to members only', 'conflict_with' => '老板文档'],
    ]]);

    $html = Livewire::withQueryParams(['selectedNumber' => 3])->test(RequirementTree::class)
        ->assertSeeInOrder([
            '要定的事', '✎ 自己写',
            '更早的说法（3）',
            '2026-10-06', '老板文档', '首页文档', 'Show stock left to everyone', '⚠ 和「the rule from', '「Other rule」', '」冲突',
            '2026-09-02', '你拍板', '现在生效', 'Hide stock from visitors',
            '现在', '代码现状', 'Shows stock to members only',
            '⚠ 代码和现行说法不一致', '历史',
        ])
        ->html();

    expect(substr_count($html, '⚠ 和'))->toBe(1)
        ->and($html)->not->toContain('old reason');
});

it('words a rule decided in Gordon\'s own words as topic plus his words, unchanged [T164]', function (string $title, string $topic) {
    [$project, $user, $goal] = decisionDesk();
    $question = decisionRule($goal, 2, RequirementStatus::Conflict, ['title' => $title, 'supersedes_id' => decisionRule($goal, 3, RequirementStatus::Decided)->id]);

    expect($question->topic())->toBe($topic);

    app(RequirementDecisions::class)->choose($question, $user, 'custom', '只给会员，全站统一，详情页也要统一');
    app(RequirementDecisions::class)->confirm($project, $user, collect([$question]));

    expect($question->fresh()->title)->toBe("{$topic}：只给会员，全站统一，详情页也要统一")
        ->and($question->revisions()->first()->reason)->toBe('Gordon 自己写：只给会员，全站统一，详情页也要统一');
})->with([
    'conflict asking whether' => ['冲突：商品卡剩余件数给不给访客看？', '商品卡剩余件数'],
    'open question' => ['待定：Factory Direct 解锁看哪个公会等级?', 'Factory Direct 解锁看哪个公会等'],
    'long plain title' => ['首页公会专属区只给会员看，没货就隐藏，不放占位卡片', '首页公会专属区只给会员看，没货就'],
]);

it('saves a rule as 以后做, shows it grey on the tree and keeps it out of the counts, 待决策 and 待做 [T165]', function () {
    [$project, , $goal] = decisionDesk();
    decisionRule($goal, 2, RequirementStatus::Decided, ['title' => 'Built rule', 'accepted_at' => now()]);
    decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Guild page later']);

    saveDecisionNodes([['number' => 3, 'status' => '以后做', 'reason' => '区分以后做和放弃']])->assertSuccessful()->run();
    $tree = app(RequirementTreeService::class)->tree($project);

    $progress = collect(RequirementTreeNode::flattened($tree))->mapWithKeys(fn (RequirementTreeNode $node): array => [$node->requirement->number => $node->progress])->all();

    expect($progress)->toBe([1 => RequirementProgress::Done, 2 => RequirementProgress::Done, 3 => RequirementProgress::Later])
        ->and(RequirementTreeNode::total($tree)->progressCounts())->toBe(['待决策' => 0, '待做' => 0, '待验收' => 0, '完成' => 1]);

    Livewire::test(RequirementTree::class)->assertSeeInOrder(['Guild page later', 'data-progress="以后做"'], false)->assertDontSee('放弃');
    Livewire::withQueryParams(['tab' => 'pending'])->test(RequirementTree::class)->assertDontSee('Guild page later');
    Livewire::withQueryParams(['tab' => 'todo'])->test(RequirementTree::class)->assertDontSee('Guild page later');
});

it('says when to look again at a 以后做 rule and puts it back to 提议 on 现在要做了 [T166]', function () {
    [, , $goal] = decisionDesk();
    $rule = decisionRule($goal, 2, RequirementStatus::Later, ['title' => 'Guild page later', 'source' => 'Gordon 拍板 2026-10-08：等复购期再评估']);

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['什么时候再看：', '等复购期再评估', '现在要做了'])
        ->call('startNow')
        ->assertNotified('已改回提议，进待决策');

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)->assertSeeInOrder(['Guild page later', '待决策', '要定的事'])->assertDontSee('wire:click="startNow"', false);

    expect($rule->fresh()->status)->toBe(RequirementStatus::Proposed)
        ->and($rule->revisions()->first()->only(['old_status', 'new_status', 'reason']))->toBe(['old_status' => '以后做', 'new_status' => '提议', 'reason' => '现在要做了']);
});

it('records a rule picked for later as 以后做, not 作废 [T167]', function () {
    [, $user, $goal] = decisionDesk();
    $rule = decisionRule($goal, 2, RequirementStatus::Proposed, ['decision' => [...shippingDecision(), 'options' => [
        ...shippingDecision()['options'],
        ['key' => 'D', 'label' => 'Not this round', 'outcome' => 'later', 'consequence' => 'Look again after launch'],
    ]]]);
    app(RequirementDecisions::class)->choose($rule, $user, 'D');

    Livewire::test(RequirementTree::class)->call('confirmAllDrafts');

    expect($rule->fresh()->status)->toBe(RequirementStatus::Later)
        ->and($rule->revisions()->first()->reason)->toBe('Not this round：Look again after launch');
});

it('always offers 保持现在 and 以后做, once, and applies them as 作废 and 以后做 signed by Gordon [T169]', function () {
    [, , $goal] = decisionDesk();
    $onlyAccept = ['now' => 'Shows a range', 'change' => 'Show the market price', 'options' => [['key' => 'A', 'label' => '按新要求改', 'outcome' => 'accept', 'consequence' => '游客看到市场价']]];
    $kept = decisionRule($goal, 2, RequirementStatus::Proposed, ['title' => 'Kept', 'decision' => $onlyAccept]);
    $later = decisionRule($goal, 3, RequirementStatus::Proposed, ['title' => 'Later', 'decision' => $onlyAccept]);

    expect(array_map(fn ($option) => $option->label, $kept->decision->choices()))->toBe(['按新要求改', '保持现在', '以后做'])
        ->and(array_map(fn ($option) => $option->label, RequirementDecision::fromArray(shippingDecision())->choices()))->toBe(['Raise to 80', 'Keep 50', 'Raise to 65', '以后做']);

    Livewire::withQueryParams(['selectedNumber' => 2])->test(RequirementTree::class)
        ->assertSeeInOrder(['按新要求改', '保持现在', '：不改，照现在的做', '以后做', '：这期不做，将来再看'])
        ->call('choose', $kept->id, 'keep')
        ->call('choose', $later->id, 'later')
        ->call('confirmAllDrafts');

    expect($kept->fresh()->only(['status', 'decided_by']))->toBe(['status' => RequirementStatus::Void, 'decided_by' => 'Gordon'])
        ->and($kept->revisions()->first()->reason)->toBe('保持现在：不改，照现在的做')
        ->and($later->fresh()->status)->toBe(RequirementStatus::Later)
        ->and($later->revisions()->first()->reason)->toBe('以后做：这期不做，将来再看');
});
