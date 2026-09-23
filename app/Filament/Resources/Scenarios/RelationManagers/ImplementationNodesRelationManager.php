<?php

namespace App\Filament\Resources\Scenarios\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
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
