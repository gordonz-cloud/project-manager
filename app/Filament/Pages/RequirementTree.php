<?php

namespace App\Filament\Pages;

use App\Data\Requirements\DeliveryStatus;
use App\Data\Requirements\RequirementChangeGroup;
use App\Data\Requirements\RequirementRollup;
use App\Data\Requirements\RequirementTreeNode;
use App\Enums\NavigationGroup;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Test;
use App\Services\Requirements\RequirementTreeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use LogicException;

/**
 * The why of the product: goals down to verifiable rules, what waits on a decision, and what changed lately.
 *
 * @property-read Project $project
 * @property-read list<RequirementTreeNode> $tree
 * @property-read Requirement|null $selectedRequirement
 */
class RequirementTree extends Page
{
    /** @var array<string, string> tab key => label */
    public const TABS = ['overview' => '全貌', 'pending' => '待拍板', 'changes' => '最近变化'];

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

    public function boot(RequirementTreeService $requirementTreeService): void
    {
        $this->requirementTreeService = $requirementTreeService;
    }

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public function getTitle(): string
    {
        return '需求树';
    }

    public function setTab(string $tab): void
    {
        $this->tab = isset(self::TABS[$tab]) ? $tab : 'overview';
    }

    public function selectNode(int $number): void
    {
        $this->selectedNumber = $number;
        unset($this->selectedRequirement);
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
        return $this->requirementTreeService()->awaitingDecision($this->project);
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
            ->with(['revisions', 'linkedFeatures', 'tests', 'supersedes'])
            ->first();
    }

    public function deliveryOf(Requirement $requirement): ?DeliveryStatus
    {
        return $this->findNode($this->tree, $requirement->id)?->delivery;
    }

    public function featureUrl(Feature $feature): string
    {
        return WorkbenchGraph::getUrl(['selectedKey' => "feature:{$feature->id}"]);
    }

    public function testUrl(Test $test): string
    {
        return TestTree::getUrl(['selectedNumber' => $test->number]);
    }

    /**
     * @param  list<RequirementTreeNode>  $nodes
     */
    private function findNode(array $nodes, int $id): ?RequirementTreeNode
    {
        foreach ($nodes as $node) {
            if ($node->requirement->id === $id) {
                return $node;
            }

            if ($found = $this->findNode($node->children, $id)) {
                return $found;
            }
        }

        return null;
    }

    private function requirementTreeService(): RequirementTreeService
    {
        return $this->requirementTreeService ?? throw new LogicException('Requirement tree service has not been booted.');
    }
}
