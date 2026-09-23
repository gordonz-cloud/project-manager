<?php

namespace App\Filament\Resources\UseCases\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\Scenarios\ScenarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ScenariosRelationManager extends RelationManager
{
    use UsesResourceForm;

    protected static string $relationship = 'scenarios';

    protected static function relatedResource(): string
    {
        return ScenarioResource::class;
    }

    public function table(Table $table): Table
    {
        return ScenarioResource::table($table)->headerActions([
            CreateAction::make()->slideOver(),
        ]);
    }
}
