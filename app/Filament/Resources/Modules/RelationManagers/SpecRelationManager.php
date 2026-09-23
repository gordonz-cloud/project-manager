<?php

namespace App\Filament\Resources\Modules\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SpecRelationManager extends RelationManager
{
    use UsesResourceForm;

    protected static string $relationship = 'spec';

    protected static function relatedResource(): string
    {
        return ModuleSpecResource::class;
    }

    public function table(Table $table): Table
    {
        return ModuleSpecResource::table($table)->headerActions([
            CreateAction::make()->slideOver(),
        ]);
    }
}
