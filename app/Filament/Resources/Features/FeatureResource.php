<?php

namespace App\Filament\Resources\Features;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Features\Pages\CreateFeature;
use App\Filament\Resources\Features\Pages\EditFeature;
use App\Filament\Resources\Features\Pages\ListFeatures;
use App\Filament\Resources\Features\Pages\ViewFeature;
use App\Filament\Resources\Features\RelationManagers\CommitsRelationManager;
use App\Filament\Resources\Features\RelationManagers\DataModelsRelationManager;
use App\Filament\Resources\Features\RelationManagers\FlowStepsRelationManager;
use App\Filament\Resources\Features\RelationManagers\ImplementationNodesRelationManager;
use App\Filament\Resources\Features\RelationManagers\TestsRelationManager;
use App\Filament\Resources\Features\Schemas\FeatureForm;
use App\Filament\Resources\Features\Tables\FeaturesTable;
use App\Models\Feature;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FeatureResource extends Resource
{
    protected static ?string $navigationLabel = 'Features';

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Feature::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Delivery;
    }

    protected static ?string $modelLabel = '功能';

    protected static ?string $pluralModelLabel = '功能';

    public static function form(Schema $schema): Schema
    {
        return FeatureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeaturesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ImplementationNodesRelationManager::class,
            FlowStepsRelationManager::class,
            TestsRelationManager::class,
            DataModelsRelationManager::class,
            CommitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatures::route('/'),
            'create' => CreateFeature::route('/create'),
            'view' => ViewFeature::route('/{record}'),
            'edit' => EditFeature::route('/{record}/edit'),
        ];
    }
}
