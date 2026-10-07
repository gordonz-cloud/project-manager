<?php

namespace App\Filament\Pages;

use App\Data\Requirements\DecisionBatchResult;
use App\Data\Requirements\RequirementChangeGroup;
use App\Data\Requirements\RequirementProgress;
use App\Data\Requirements\RequirementRollup;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\NavigationGroup;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementDecisionDraft;
use App\Models\Test;
use App\Models\User;
use App\Services\Requirements\RequirementDecisions;
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

    /** @var array<string, string> 待决策 filter key => label */
    public const WAITING_ON = [Requirement::STAGE_MINE => '等我', Requirement::STAGE_TO_SEND => '待发老板', Requirement::STAGE_AWAITING_BOSS => '等老板回复'];

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

    #[Url]
    public string $waitingOn = Requirement::STAGE_MINE;

    public bool $showBossQuestions = false;

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

    public function setWaitingOn(string $waitingOn): void
    {
        $this->waitingOn = isset(self::WAITING_ON[$waitingOn]) ? $waitingOn : Requirement::STAGE_MINE;
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
     * A 自己写 box: text drafts $choice (custom, opinion:custom or boss:custom), emptying it drops that draft.
     */
    public function writeCustom(int $requirementId, string $text, string $choice = RequirementDecisionDraft::CUSTOM): void
    {
        if (trim($text) !== '') {
            $this->choose($requirementId, $choice, $text);
        } elseif ($this->drafts->get($requirementId)?->choice === $choice) {
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
     * Applies every draft of this user, wherever it was picked (全貌 or 待决策, any filter), then reads the page again.
     */
    public function confirmAllDrafts(): void
    {
        try {
            $result = $this->requirementDecisions()->confirm($this->project, $this->user(), $this->awaitingDecision);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('没有确认，什么都没改')->body($exception->getMessage())->danger()->send();

            return;
        }

        unset($this->tree, $this->awaitingDecision, $this->pendingByGoal, $this->drafts, $this->batchPreview, $this->bossQuestions, $this->nodesById, $this->total, $this->selectedRequirement);
        Notification::make()->title($result->summary())->success()->send();
    }

    /**
     * Gordon sent the list: those questions now wait for the boss's reply.
     */
    public function markSentToBoss(): void
    {
        $sent = $this->requirementDecisions()->markSentToBoss($this->project);
        unset($this->awaitingDecision, $this->pendingByGoal, $this->bossQuestions, $this->selectedRequirement);
        Notification::make()->title("已标记 {$sent} 条发给老板")->success()->send();
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

    public function toggleBossQuestions(): void
    {
        $this->showBossQuestions = ! $this->showBossQuestions;
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
     * The 待决策 list under the current filter (等我 includes nodes nobody assigned yet), grouped by goal in tree order;
     * within a goal 冲突 first, then tree order.
     *
     * @return array<string, list<Requirement>> goal title => requirements
     */
    #[Computed]
    public function pendingByGoal(): array
    {
        $goalPosition = array_flip(array_keys($this->nodesById));
        $groups = [];

        foreach ($this->awaitingDecision->filter(fn (Requirement $requirement): bool => $this->isWaitingOn($requirement, $this->waitingOn)) as $requirement) {
            $goal = $this->goalOf($requirement);
            $groups[$goal->id] ??= ['goal' => $goal, 'items' => []];
            $groups[$goal->id]['items'][] = $requirement;
        }

        uasort($groups, fn (array $a, array $b): int => ($goalPosition[$a['goal']->id] ?? PHP_INT_MAX) <=> ($goalPosition[$b['goal']->id] ?? PHP_INT_MAX));

        return collect($groups)->mapWithKeys(fn (array $group): array => [$group['goal']->title => $group['items']])->all();
    }

    public function waitingOnCount(string $waitingOn): int
    {
        return $this->awaitingDecision->filter(fn (Requirement $requirement): bool => $this->isWaitingOn($requirement, $waitingOn))->count();
    }

    private function isWaitingOn(Requirement $requirement, string $waitingOn): bool
    {
        return $requirement->decisionStage() === $waitingOn;
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
     * Whether the rationale mostly repeats the 现在 of the decision shown above it, so the panel folds it away.
     */
    public function repeatsDecision(Requirement $requirement): bool
    {
        return $requirement->decision !== null && $this->isDecidable($requirement) && $requirement->decision->retells((string) $requirement->rationale);
    }

    #[Computed]
    public function bossQuestions(): string
    {
        return $this->requirementDecisions()->bossQuestions($this->project);
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
