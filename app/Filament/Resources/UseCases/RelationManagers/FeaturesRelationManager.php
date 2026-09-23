<?php

namespace App\Filament\Resources\UseCases\RelationManagers;

use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Resources\Features\Schemas\FeatureForm;
use App\Models\UseCase;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use LogicException;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public function getOwnerRecord(): UseCase
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof UseCase) {
            throw new LogicException('Features must belong to a use case.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return FeatureForm::configureForUseCase($schema, $this->getOwnerRecord()->id);
    }

    public function table(Table $table): Table
    {
        return FeatureResource::table($table)->headerActions([
            CreateAction::make()->slideOver(),
        ]);
    }
}
