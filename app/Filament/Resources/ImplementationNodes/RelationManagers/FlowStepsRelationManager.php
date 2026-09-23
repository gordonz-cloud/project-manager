<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use App\Filament\Resources\FlowSteps\FlowStepResource;
use App\Filament\Resources\FlowSteps\Schemas\FlowStepForm;
use App\Models\ImplementationNode;
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

class FlowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'flowSteps';

    public function getOwnerRecord(): ImplementationNode
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof ImplementationNode) {
            throw new LogicException('Flow steps must belong to an implementation node.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        return FlowStepForm::configureForNode($schema, $this->getOwnerRecord()->feature_id);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return FlowStepResource::table($table)
            ->headerActions([
                CreateAction::make()->slideOver(),
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
