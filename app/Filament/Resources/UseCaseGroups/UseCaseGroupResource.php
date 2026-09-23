<?php

namespace App\Filament\Resources\UseCaseGroups;

use App\Enums\NavigationGroup;
use App\Filament\Resources\UseCaseGroups\Pages\CreateUseCaseGroup;
use App\Filament\Resources\UseCaseGroups\Pages\EditUseCaseGroup;
use App\Filament\Resources\UseCaseGroups\Pages\ListUseCaseGroups;
use App\Filament\Resources\UseCaseGroups\Schemas\UseCaseGroupForm;
use App\Filament\Resources\UseCaseGroups\Tables\UseCaseGroupsTable;
use App\Models\UseCaseGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UseCaseGroupResource extends Resource
{
    protected static ?string $model = UseCaseGroup::class;

    protected static ?string $navigationLabel = 'Use Case Groups';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Behavior;
    }

    public static function form(Schema $schema): Schema
    {
        return UseCaseGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UseCaseGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUseCaseGroups::route('/'),
            'create' => CreateUseCaseGroup::route('/create'),
            'edit' => EditUseCaseGroup::route('/{record}/edit'),
        ];
    }
}
