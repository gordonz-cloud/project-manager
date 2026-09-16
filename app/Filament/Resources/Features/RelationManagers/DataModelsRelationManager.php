<?php

namespace App\Filament\Resources\Features\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DataModelsRelationManager extends RelationManager
{
    protected static string $relationship = 'dataModels';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->label('模型')
                    ->searchable(),
                TextColumn::make('table_name')
                    ->label('表名'),
            ])
            ->headerActions([
                AttachAction::make(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
