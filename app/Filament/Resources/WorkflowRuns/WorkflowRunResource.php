<?php

namespace App\Filament\Resources\WorkflowRuns;

use App\Enums\NavigationGroup;
use App\Filament\Resources\WorkflowRuns\Pages\CreateWorkflowRun;
use App\Filament\Resources\WorkflowRuns\Pages\EditWorkflowRun;
use App\Filament\Resources\WorkflowRuns\Pages\ListWorkflowRuns;
use App\Filament\Resources\WorkflowRuns\Pages\ViewWorkflowRun;
use App\Filament\Resources\WorkflowRuns\RelationManagers\EventsRelationManager;
use App\Filament\Resources\WorkflowRuns\RelationManagers\NodeRunsRelationManager;
use App\Filament\Resources\WorkflowRuns\Schemas\WorkflowRunForm;
use App\Filament\Resources\WorkflowRuns\Schemas\WorkflowRunInfolist;
use App\Filament\Resources\WorkflowRuns\Tables\WorkflowRunsTable;
use App\Models\WorkflowRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkflowRunResource extends Resource
{
    protected static ?string $model = WorkflowRun::class;

    protected static ?string $navigationLabel = 'Runs';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Execution;
    }

    public static function form(Schema $schema): Schema
    {
        return WorkflowRunForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkflowRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NodeRunsRelationManager::class,
            EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowRuns::route('/'),
            'create' => CreateWorkflowRun::route('/create'),
            'view' => ViewWorkflowRun::route('/{record}'),
            'edit' => EditWorkflowRun::route('/{record}/edit'),
        ];
    }
}
