<?php

namespace App\Filament\Resources\DataModels;

use App\Enums\NavigationGroup;
use App\Filament\Resources\DataModels\Pages\CreateDataModel;
use App\Filament\Resources\DataModels\Pages\EditDataModel;
use App\Filament\Resources\DataModels\Pages\ListDataModels;
use App\Filament\Resources\DataModels\RelationManagers\ModelFieldsRelationManager;
use App\Filament\Resources\DataModels\Schemas\DataModelForm;
use App\Filament\Resources\DataModels\Tables\DataModelsTable;
use App\Models\DataModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DataModelResource extends Resource
{
    protected static ?string $navigationLabel = 'Models';

    protected static ?int $navigationSort = 2;

    protected static ?string $model = DataModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Solution;
    }

    protected static ?string $modelLabel = 'Model';

    public static function form(Schema $schema): Schema
    {
        return DataModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DataModelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ModelFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataModels::route('/'),
            'create' => CreateDataModel::route('/create'),
            'edit' => EditDataModel::route('/{record}/edit'),
        ];
    }
}
