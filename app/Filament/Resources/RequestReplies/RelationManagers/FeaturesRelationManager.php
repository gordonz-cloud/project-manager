<?php

namespace App\Filament\Resources\RequestReplies\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The work items that added or changed this entry.
 */
class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    protected static ?string $title = '功能';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('number')
                    ->label('#'),
                TextColumn::make('title')
                    ->label('功能')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
            ])
            ->headerActions([
                AttachAction::make()->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
