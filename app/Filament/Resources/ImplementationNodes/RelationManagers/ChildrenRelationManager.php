<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use App\Filament\Resources\ImplementationNodes\Schemas\ImplementationNodeForm;
use App\Models\ImplementationNode;
use App\Services\ImplementationNodes\ImplementationNodeSelection;
use Filament\Actions\AssociateAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public function getOwnerRecord(): ImplementationNode
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof ImplementationNode) {
            throw new LogicException('Children must belong to an implementation node.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return ImplementationNodeForm::configureForChild($schema, $this->getOwnerRecord()->feature_id);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();
        $implementationNodes = resolve(ImplementationNodeSelection::class);

        return ImplementationNodeResource::table($table)
            ->headerActions([
                CreateAction::make()->slideOver(),
                AssociateAction::make()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $implementationNodes
                        ->constrainToAssociableChild($query, $owner)),
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
