<?php

namespace App\Filament\Pages;

use App\Data\Requirements\DecisionBatchResult;
use App\Data\Requirements\RequirementChangeGroup;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementRollup;
use App\Data\Requirements\RequirementTimeline;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\NavigationGroup;
use App\Enums\TestLastResult;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\Test;
use App\Models\User;
use App\Services\Requirements\RequirementDecisions;
use App\Services\Requirements\RequirementTimelines;
use App\Services\Requirements\RequirementTreeService;
use App\Support\RequirementMentions;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use LogicException;

/**
 * The why of the product: goals down to verifiable rules, what waits on a decision, and what changed lately.
 *
 * @property-read Project $project
 * @property-read list<RequirementTreeNode> $tree
 * @property-read Requirement|null $selectedRequirement
 * @property-read Collection<int, Requirement> $awaitingDecision
 * @property-read array<string, list<Requirement>> $pendingByGoal
 * @property-read EloquentCollection<int, RequirementDecisionDraft> $drafts
 * @property-read array<int, RequirementTreeNode> $nodesById
 */
class RequirementTree extends Page
{
    /** @var array<string, string> tab key => label */
    public const TABS = ['overview' => '全貌', 'pending' => '待决策', 'todo' => '待做', 'changes' => '最近变化'];

    protected string $view = 'filament.pages.requirement-tree';

    protected static ?string $slug = 'requirement-tree';

    protected static ?string $navigationLabel = '需求树';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public ?int $selectedNumber = null;

    /** @var array<int, bool> keyed by requirement id; roots start open */
    public array $expanded = [];

    public bool $showUnfiled = false;

    private ?RequirementTreeService $requirementTreeService = null;

    private ?RequirementDecisions $requirementDecisions = null;

    public function boot(RequirementTreeService $requirementTreeService, RequirementDecisions $requirementDecisions): void
    {
        $this->requirementTreeService = $requirementTreeService;
        $this->requirementDecisions = $requirementDecisions;
    }

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public function getTitle(): string
    {
        return '需求树';
    }

    public function mount(): void
    {
        $this->revealSelected();
    }

    /**
     * The selected node stays selected across tabs: the tab opens with it in view.
     */
    public function setTab(string $tab): void
    {
        $this->tab = isset(self::TABS[$tab]) ? $tab : 'overview';
        $this->revealSelected();
        $this->dispatch('reveal-selected');
    }

    public function choose(int $requirementId, string $choice, ?string $customText = null): void
    {
        $requirement = $this->awaitingDecision->firstWhere('id', $requirementId);

        if ($requirement === null) {
            return;
        }

        try {
            $this->requirementDecisions()->choose($requirement, $this->user(), $choice, $customText);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }

        unset($this->drafts, $this->batchPreview);
    }

    /**
     * A 自己写 box: text drafts it as Gordon's own wording, emptying it drops that draft.
     */
    public function writeCustom(int $requirementId, string $text): void
    {
        if (trim($text) !== '') {
            $this->choose($requirementId, RequirementDecisionDraft::CUSTOM, $text);
        } elseif ($this->drafts->get($requirementId)?->choice === RequirementDecisionDraft::CUSTOM) {
            $this->clearChoice($requirementId);
        }
    }

    public function clearChoice(int $requirementId): void
    {
        if ($requirement = $this->awaitingDecision->firstWhere('id', $requirementId)) {
            $this->requirementDecisions()->clear($requirement, $this->user());
        }

        unset($this->drafts, $this->batchPreview);
    }

    /**
     * Applies every draft of this user, wherever it was picked (全貌 or 待决策), then reads the page again.
     */
    public function confirmAllDrafts(): void
    {
        try {
            $result = $this->requirementDecisions()->confirm($this->project, $this->user(), $this->awaitingDecision);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('没有确认，什么都没改')->body($exception->getMessage())->danger()->send();

            return;
        }

        unset($this->tree, $this->awaitingDecision, $this->pendingByGoal, $this->drafts, $this->batchPreview, $this->nodesById, $this->total, $this->selectedRequirement);
        Notification::make()->title($result->summary())->success()->send();
    }

    /**
     * The selected 以后做 rule is wanted now: back to 提议, on the 待决策 list.
     */
    public function startNow(): void
    {
        $this->requirementDecisions()->startNow($this->project, $this->selectedRequirement ?? throw new LogicException('Nothing selected.'));
        unset($this->tree, $this->awaitingDecision, $this->pendingByGoal, $this->nodesById, $this->total, $this->selectedRequirement);
        Notification::make()->title('已改回提议，进待决策')->success()->send();
    }

    public function timelineOf(Requirement $requirement): RequirementTimeline
    {
        return app(RequirementTimelines::class)->for($requirement);
    }

    /**
     * Turns "#N" in text shown on the page into that rule's title.
     */
    #[Computed]
    public function mentions(): RequirementMentions
    {
        return new RequirementMentions(collect($this->nodesById)->mapWithKeys(fn (RequirementTreeNode $node): array => [(int) $node->requirement->number => $node->requirement->title])->all());
    }

    /**
     * What the bottom bar's 确认这一批 would do, shown before it is pressed.
     */
    #[Computed]
    public function batchPreview(): DecisionBatchResult
    {
        return $this->requirementDecisions()->preview($this->user(), $this->awaitingDecision);
    }

    public function selectNode(int $number): void
    {
        $this->selectedNumber = $number;
        unset($this->selectedRequirement);
        $this->revealSelected();
    }

    /**
     * Opens every ancestor of the selected node in the tree (and the unfiled list when it lives there), so its row exists
     * to scroll to.
     */
    private function revealSelected(): void
    {
        $node = collect($this->nodesById)->first(fn (RequirementTreeNode $node): bool => $node->requirement->number === $this->selectedNumber);

        for ($parentId = $node?->requirement->parent_id; $parentId !== null && isset($this->nodesById[$parentId]); $parentId = $this->nodesById[$parentId]->requirement->parent_id) {
            $this->expanded[$parentId] = true;
            $root = $this->nodesById[$parentId]->requirement;
        }

        if ($node !== null && ($root ?? $node->requirement)->kind === null) {
            $this->showUnfiled = true;
        }
    }

    public function toggleNode(int $id, bool $isOpen = false): void
    {
        $this->expanded[$id] = ! $isOpen;
    }

    #[Computed]
    public function project(): Project
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Project, 404);

        return $tenant;
    }

    /**
     * @return list<RequirementTreeNode>
     */
    #[Computed]
    public function tree(): array
    {
        return $this->requirementTreeService()->tree($this->project);
    }

    /**
     * Roots placed in the tree (have a kind), goals first.
     *
     * @return list<RequirementTreeNode>
     */
    public function placedRoots(): array
    {
        return array_values(array_filter($this->tree, fn (RequirementTreeNode $node): bool => $node->requirement->kind !== null));
    }

    /**
     * Roots not yet placed in the tree, e.g. the old flat requirement list.
     *
     * @return list<RequirementTreeNode>
     */
    public function unfiledRoots(): array
    {
        return array_values(array_filter($this->tree, fn (RequirementTreeNode $node): bool => $node->requirement->kind === null));
    }

    #[Computed]
    public function total(): RequirementRollup
    {
        return RequirementTreeNode::total($this->tree);
    }

    /**
     * @return Collection<int, Requirement>
     */
    #[Computed]
    public function awaitingDecision(): Collection
    {
        return $this->requirementTreeService()->awaitingDecision($this->project, $this->tree);
    }

    /**
     * The 待决策 list grouped by goal in tree order;
     * within a goal 冲突 first, then tree order.
     *
     * @return array<string, list<Requirement>> goal title => requirements
     */
    #[Computed]
    public function pendingByGoal(): array
    {
        $goalPosition = array_flip(array_keys($this->nodesById));
        $groups = [];

        foreach ($this->awaitingDecision as $requirement) {
            $goal = $this->goalOf($requirement);
            $groups[$goal->id] ??= ['goal' => $goal, 'items' => []];
            $groups[$goal->id]['items'][] = $requirement;
        }

        uasort($groups, fn (array $a, array $b): int => ($goalPosition[$a['goal']->id] ?? PHP_INT_MAX) <=> ($goalPosition[$b['goal']->id] ?? PHP_INT_MAX));

        return collect($groups)->mapWithKeys(fn (array $group): array => [$group['goal']->title => $group['items']])->all();
    }

    /**
     * This user's picks, keyed by requirement id.
     *
     * @return EloquentCollection<int, RequirementDecisionDraft>
     */
    #[Computed]
    public function drafts(): EloquentCollection
    {
        return RequirementDecisionDraft::query()
            ->where('user_id', $this->user()->id)
            ->whereIn('requirement_id', $this->awaitingDecision->pluck('id'))
            ->get()
            ->keyBy('requirement_id');
    }

    /**
     * Whether the selected node is one Gordon can decide right here (a 提议/冲突 that is not a 分组).
     */
    public function isDecidable(Requirement $requirement): bool
    {
        return $this->awaitingDecision->contains('id', $requirement->id);
    }

    /**
     * @return list<RequirementTreeNode>
     */
    #[Computed]
    public function todo(): array
    {
        return $this->requirementTreeService()->todo($this->tree);
    }

    /**
     * @return array<int, RequirementTreeNode>
     */
    #[Computed]
    public function nodesById(): array
    {
        return collect(RequirementTreeNode::flattened($this->tree))->keyBy(fn (RequirementTreeNode $node): int => $node->requirement->id)->all();
    }

    /**
     * The root above $requirement (itself when it is one), read from the tree already in memory.
     */
    private function goalOf(Requirement $requirement): Requirement
    {
        $goal = $requirement;

        while ($goal->parent_id !== null && isset($this->nodesById[$goal->parent_id])) {
            $goal = $this->nodesById[$goal->parent_id]->requirement;
        }

        return $goal;
    }

    /**
     * Titles from the root down to the parent, read from the tree already in memory.
     *
     * @return list<string>
     */
    public function pathOf(Requirement $requirement): array
    {
        $path = [];

        for ($id = $requirement->parent_id; $id !== null && isset($this->nodesById[$id]); $id = $this->nodesById[$id]->requirement->parent_id) {
            array_unshift($path, $this->nodesById[$id]->requirement->title);
        }

        return $path;
    }

    /**
     * 做到哪了 in one line: "2 个功能 · 5 个测试全过", "1 个测试：1 失败", or 还没做.
     */
    public function builtSummary(Requirement $requirement): string
    {
        $tests = $requirement->tests;
        $results = $tests->every(fn (Test $test): bool => $test->last_result === TestLastResult::Passed)
            ? '全过'
            : '：'.$tests->countBy(fn (Test $test): string => $test->last_result->value)->map(fn (int $count, string $result): string => "{$count} {$result}")->implode('，');

        return implode(' · ', array_filter([
            $requirement->linkedFeatures->isEmpty() ? null : "{$requirement->linkedFeatures->count()} 个功能",
            $tests->isEmpty() ? null : "{$tests->count()} 个测试{$results}",
        ])) ?: '还没做';
    }

    public function progressOf(Requirement $requirement): ?RequirementProgress
    {
        return $this->nodesById[$requirement->id]->progress ?? null;
    }

    /**
     * @return list<RequirementChangeGroup>
     */
    #[Computed]
    public function recentChanges(): array
    {
        return $this->requirementTreeService()->recentChanges($this->project);
    }

    #[Computed]
    public function selectedRequirement(): ?Requirement
    {
        return $this->selectedNumber === null ? null : Requirement::query()
            ->where('project_id', $this->project->id)
            ->where('number', $this->selectedNumber)
            ->with(['revisions', 'linkedFeatures', 'tests', 'supersedes', 'dependsOn', 'dependents'])
            ->first();
    }

    public function featureUrl(Feature $feature): string
    {
        return WorkbenchGraph::getUrl(['selectedKey' => "feature:{$feature->id}"]);
    }

    public function testUrl(Test $test): string
    {
        return TestTree::getUrl(['selectedNumber' => $test->number]);
    }

    private function requirementTreeService(): RequirementTreeService
    {
        return $this->requirementTreeService ?? throw new LogicException('Requirement tree service has not been booted.');
    }

    private function requirementDecisions(): RequirementDecisions
    {
        return $this->requirementDecisions ?? throw new LogicException('Requirement decisions have not been booted.');
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
