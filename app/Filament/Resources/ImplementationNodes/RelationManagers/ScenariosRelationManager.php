<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\Scenarios\ScenarioResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
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
        return ScenarioResource::table($table)
            ->headerActions([
                AttachAction::make(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
