<?php

namespace App\Filament\Resources\ModuleSpecs\RelationManagers;

use App\Filament\Resources\UseCases\Schemas\UseCaseForm;
use App\Filament\Resources\UseCases\UseCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UseCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'useCases';

    public function form(Schema $schema): Schema
    {
        return UseCaseForm::configureInModule($schema);
    }

    public function table(Table $table): Table
    {
        return UseCaseResource::table($table)->headerActions([
            CreateAction::make()->slideOver(),
        ]);
    }
}
