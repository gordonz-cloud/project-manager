<?php

namespace App\Filament\Pages;

use App\Data\Workbench\WorkbenchTreeNode;
use App\Enums\NavigationGroup;
use App\Filament\Support\WorkbenchGraphPresenter;
use App\Models\Project;
use App\Services\Workbench\WorkbenchGraphService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use LogicException;

/**
 * @property-read Project $project
 * @property-read list<WorkbenchTreeNode> $tree
 * @property-read list<WorkbenchTreeNode> $visibleTree
 * @property-read Model|null $selectedRecord
 */
class WorkbenchGraph extends Page
{
    protected string $view = 'filament.pages.workbench-graph';

    protected static ?string $slug = 'workbench';

    protected static ?string $navigationLabel = '关系工作台';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    #[Url]
    public ?string $selectedKey = null;

    public string $search = '';

    /** @var array<string, bool> keyed by the ">"-joined keys from the root down to the row */
    public array $expanded = [];

    private ?WorkbenchGraphService $workbenchGraphService = null;

    private ?WorkbenchGraphPresenter $workbenchGraphPresenter = null;

    public function boot(
        WorkbenchGraphService $workbenchGraphService,
        WorkbenchGraphPresenter $workbenchGraphPresenter,
    ): void {
        $this->workbenchGraphService = $workbenchGraphService;
        $this->workbenchGraphPresenter = $workbenchGraphPresenter;
    }

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public function getTitle(): string
    {
        return '关系工作台';
    }

    public function mount(): void
    {
        if ($this->selectedKey === null || $this->selectedRecord === null) {
            $this->selectedKey = $this->tree[0]->children[0]->key ?? null;
        }

        $this->expandPathTo($this->selectedKey);
    }

    public function selectNode(string $key): void
    {
        abort_if($this->workbenchGraphService()->record($this->project, $key) === null, 404);

        $this->selectedKey = $key;
        $this->expandPathTo($key);
        unset($this->selectedRecord);
    }

    public function toggleNode(string $path): void
    {
        $this->expanded[$path] = ! ($this->expanded[$path] ?? false);
    }

    public function editAction(): Action
    {
        return EditAction::make('edit')
            ->label('编辑')
            ->slideOver()
            ->visible(fn (): bool => $this->editTarget() !== null)
            ->record(fn (): ?Model => $this->editTarget()['record'] ?? null)
            ->schema(function (Schema $schema): Schema {
                $target = $this->editTarget() ?? throw new LogicException('Nothing to edit.');

                return $target['resource']::form($schema);
            })
            ->after(fn () => $this->resetTree());
    }

    #[Computed]
    public function project(): Project
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Project, 404);

        return $tenant;
    }

    /**
     * @return list<WorkbenchTreeNode>
     */
    #[Computed]
    public function tree(): array
    {
        return $this->workbenchGraphPresenter()->tree($this->project);
    }

    /**
     * @return list<WorkbenchTreeNode>
     */
    #[Computed]
    public function visibleTree(): array
    {
        $needle = mb_strtolower(trim($this->search));

        return $needle === '' ? $this->tree : WorkbenchTreeNode::matchingAll($this->tree, $needle);
    }

    #[Computed]
    public function selectedRecord(): ?Model
    {
        return $this->selectedKey === null
            ? null
            : $this->workbenchGraphService()->record($this->project, $this->selectedKey);
    }

    public function isSearching(): bool
    {
        return trim($this->search) !== '';
    }

    public function presenter(): WorkbenchGraphPresenter
    {
        return $this->workbenchGraphPresenter();
    }

    /**
     * @return array{record: Model, resource: class-string<FilamentResource>}|null
     */
    private function editTarget(): ?array
    {
        $record = $this->selectedRecord;

        return $record === null ? null : $this->workbenchGraphPresenter()->editTarget($record);
    }

    private function expandPathTo(?string $key): void
    {
        $path = ($key === null ? null : WorkbenchTreeNode::pathTo($this->tree, $key)) ?? [];

        for ($depth = 1; $depth < count($path); $depth++) {
            $this->expanded[implode('>', array_slice($path, 0, $depth))] = true;
        }
    }

    private function resetTree(): void
    {
        unset($this->tree, $this->visibleTree, $this->selectedRecord);
    }

    private function workbenchGraphService(): WorkbenchGraphService
    {
        return $this->workbenchGraphService
            ?? throw new LogicException('Workbench graph service has not been booted.');
    }

    private function workbenchGraphPresenter(): WorkbenchGraphPresenter
    {
        return $this->workbenchGraphPresenter
            ?? throw new LogicException('Workbench graph presenter has not been booted.');
    }
}
