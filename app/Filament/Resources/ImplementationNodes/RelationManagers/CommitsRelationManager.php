<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use App\Filament\Resources\Commits\CommitResource;
use App\Models\ImplementationNode;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class CommitsRelationManager extends RelationManager
{
    protected static string $relationship = 'commits';

    public function getOwnerRecord(): ImplementationNode
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof ImplementationNode) {
            throw new LogicException('Commits must belong to an implementation node.');
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return CommitResource::table($table)
            ->headerActions([
                AssociateAction::make()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->where('feature_id', $owner->feature_id)),
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
