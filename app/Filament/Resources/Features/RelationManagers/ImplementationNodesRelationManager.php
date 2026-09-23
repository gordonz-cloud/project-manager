<?php

namespace App\Filament\Resources\Features\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ImplementationNodesRelationManager extends RelationManager
{
    use UsesResourceForm;

    protected static string $relationship = 'implementationNodes';

    protected static function relatedResource(): string
    {
        return ImplementationNodeResource::class;
    }

    public function table(Table $table): Table
    {
        return ImplementationNodeResource::table($table)
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
