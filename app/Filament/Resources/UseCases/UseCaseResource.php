<?php

namespace App\Filament\Resources\UseCases;

use App\Enums\NavigationGroup;
use App\Filament\Resources\UseCases\Pages\CreateUseCase;
use App\Filament\Resources\UseCases\Pages\EditUseCase;
use App\Filament\Resources\UseCases\Pages\ListUseCases;
use App\Filament\Resources\UseCases\RelationManagers\FeaturesRelationManager;
use App\Filament\Resources\UseCases\RelationManagers\ScenariosRelationManager;
use App\Filament\Resources\UseCases\Schemas\UseCaseForm;
use App\Filament\Resources\UseCases\Tables\UseCasesTable;
use App\Models\UseCase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UseCaseResource extends Resource
{
    protected static ?string $model = UseCase::class;

    protected static ?string $navigationLabel = 'Use Cases';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Behavior;
    }

    public static function form(Schema $schema): Schema
    {
        return UseCaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UseCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ScenariosRelationManager::class,
            FeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUseCases::route('/'),
            'create' => CreateUseCase::route('/create'),
            'edit' => EditUseCase::route('/{record}/edit'),
        ];
    }
}
