<?php

namespace App\Filament\Pages;

use App\Data\Tests\TestArea;
use App\Data\Tests\TestNodeState;
use App\Data\Tests\TestRollup;
use App\Data\Tests\TestTreeNode;
use App\Enums\NavigationGroup;
use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestPriority;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Test;
use App\Services\Tests\TestTreeService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use LogicException;

/**
 * The Test Matrix as a tree: root to leaf is one test case, a failed node blocks everything below it.
 *
 * @property-read Project $project
 * @property-read list<TestArea> $areas
 * @property-read list<TestArea> $visibleAreas
 * @property-read Test|null $selectedTest
 */
class TestTree extends Page
{
    /** @var array<string, string> filter key => label */
    public const FILTERS = ['failed' => '只看失败', 'p0' => '只看 P0', 'fakeGreen' => '只看假绿', 'gap' => '只看缺口'];

    protected string $view = 'filament.pages.test-tree';

    protected static ?string $slug = 'test-tree';

    protected static ?string $navigationLabel = '测试树';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    #[Url]
    public ?int $selectedNumber = null;

    #[Url]
    public string $filter = '';

    public string $search = '';

    /** @var array<int, bool> keyed by test id */
    public array $expanded = [];

    /** @var array<string, bool> keyed by area name */
    public array $expandedAreas = [];

    private ?TestTreeService $testTreeService = null;

    public function boot(TestTreeService $testTreeService): void
    {
        $this->testTreeService = $testTreeService;
    }

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public function getTitle(): string
    {
        return '测试树';
    }

    public function selectNode(int $number): void
    {
        $this->selectedNumber = $number;
        unset($this->selectedTest);
    }

    public function toggleNode(int $id, bool $isOpen = false): void
    {
        $this->expanded[$id] = ! $isOpen;
    }

    public function toggleArea(string $name): void
    {
        $this->expandedAreas[$name] = ! ($this->expandedAreas[$name] ?? false);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $this->filter === $filter || ! isset(self::FILTERS[$filter]) ? '' : $filter;
    }

    #[Computed]
    public function project(): Project
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Project, 404);

        return $tenant;
    }

    /**
     * @return list<TestArea>
     */
    #[Computed]
    public function areas(): array
    {
        return $this->testTreeService()->areas($this->project);
    }

    /**
     * @return list<TestArea>
     */
    #[Computed]
    public function visibleAreas(): array
    {
        $needle = mb_strtolower(trim($this->search));
        $areas = $this->areas;

        if (isset(self::FILTERS[$this->filter])) {
            $areas = array_filter(array_map(fn (TestArea $area): ?TestArea => $area->matching(fn (TestTreeNode $node): bool => $this->matchesFilter($node)), $areas));
        }

        if ($needle !== '') {
            $areas = array_filter(array_map(fn (TestArea $area): ?TestArea => $area->matching(fn (TestTreeNode $node): bool => str_contains(mb_strtolower(implode(' ', [
                '#'.$node->test->number, $node->test->title, $node->test->expected, $node->test->location, $node->test->test_name, $node->test->module,
            ])), $needle)), $areas));
        }

        return array_values($areas);
    }

    #[Computed]
    public function total(): TestRollup
    {
        return array_reduce($this->areas, fn (TestRollup $sum, TestArea $area): TestRollup => $sum->plus($area->rollup), new TestRollup);
    }

    #[Computed]
    public function selectedTest(): ?Test
    {
        return $this->selectedNumber === null ? null : Test::query()
            ->where('project_id', $this->project->id)
            ->where('number', $this->selectedNumber)
            ->with('features')
            ->first();
    }

    /**
     * The selected node's dot, derived like the tree does: any failed ancestor blocks it.
     */
    public function selectedState(): ?TestNodeState
    {
        $path = $this->selectedPath();
        $test = array_pop($path);

        return $test === null ? null : TestTreeNode::stateOf($test, collect($path)->contains(fn (Test $step): bool => $step->last_result === TestLastResult::Failed));
    }

    /**
     * Root first, ending with the selected node.
     *
     * @return list<Test>
     */
    public function selectedPath(): array
    {
        $path = [];

        for ($test = $this->selectedTest; $test !== null; $test = $test->parent) {
            array_unshift($path, $test);
        }

        return $path;
    }

    /**
     * @return Collection<int, Feature>
     */
    #[Computed]
    public function uncoveredFeatures(): Collection
    {
        return $this->testTreeService()->uncoveredFeatures($this->project);
    }

    public function isNarrowed(): bool
    {
        return trim($this->search) !== '' || isset(self::FILTERS[$this->filter]);
    }

    public function featureUrl(Feature $feature): string
    {
        return WorkbenchGraph::getUrl(['selectedKey' => "feature:{$feature->id}"]);
    }

    private function matchesFilter(TestTreeNode $node): bool
    {
        return match ($this->filter) {
            'failed' => $node->state === TestNodeState::Failed,
            'p0' => $node->test->priority === TestPriority::P0,
            'fakeGreen' => $node->test->auto === TestAuto::Wrong,
            'gap' => $node->test->isGap(),
            default => true,
        };
    }

    private function testTreeService(): TestTreeService
    {
        return $this->testTreeService ?? throw new LogicException('Test tree service has not been booted.');
    }
}
