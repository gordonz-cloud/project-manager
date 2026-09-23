<?php

namespace App\Filament\Resources\Scenarios\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\Tests\TestResource;
use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TestsRelationManager extends RelationManager
{
    use UsesResourceForm;

    protected static string $relationship = 'tests';

    protected static function relatedResource(): string
    {
        return TestResource::class;
    }

    public function table(Table $table): Table
    {
        return TestResource::table($table)
            ->headerActions([
                CreateAction::make()->slideOver(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DissociateAction::make(),
            ])
            ->toolbarActions([
                DissociateBulkAction::make(),
            ]);
    }
}
