<?php

namespace App\Filament\Resources\Requirements;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Requirements\Pages\CreateRequirement;
use App\Filament\Resources\Requirements\Pages\EditRequirement;
use App\Filament\Resources\Requirements\Pages\ListRequirements;
use App\Filament\Resources\Requirements\Schemas\RequirementForm;
use App\Filament\Resources\Requirements\Tables\RequirementsTable;
use App\Models\Requirement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequirementResource extends Resource
{
    protected static ?string $navigationLabel = 'Requirements';

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Requirement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Requirements;
    }

    protected static ?string $modelLabel = '需求';

    protected static ?string $pluralModelLabel = '需求';

    public static function form(Schema $schema): Schema
    {
        return RequirementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequirementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequirements::route('/'),
            'create' => CreateRequirement::route('/create'),
            'edit' => EditRequirement::route('/{record}/edit'),
        ];
    }
}
