<?php

namespace App\Filament\Resources\ModuleSpecs;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ModuleSpecs\Pages\CreateModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\EditModuleSpec;
use App\Filament\Resources\ModuleSpecs\Pages\ListModuleSpecs;
use App\Filament\Resources\ModuleSpecs\RelationManagers\UseCasesRelationManager;
use App\Filament\Resources\ModuleSpecs\Schemas\ModuleSpecForm;
use App\Filament\Resources\ModuleSpecs\Tables\ModuleSpecsTable;
use App\Models\ModuleSpec;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModuleSpecResource extends Resource
{
    protected static ?string $navigationLabel = 'Module Specs';

    protected static ?int $navigationSort = 0;

    protected static ?string $model = ModuleSpec::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Module Spec';

    protected static ?string $pluralModelLabel = 'Module Specs';

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Scope;
    }

    public static function form(Schema $schema): Schema
    {
        return ModuleSpecForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModuleSpecsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UseCasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModuleSpecs::route('/'),
            'create' => CreateModuleSpec::route('/create'),
            'edit' => EditModuleSpec::route('/{record}/edit'),
        ];
    }
}
