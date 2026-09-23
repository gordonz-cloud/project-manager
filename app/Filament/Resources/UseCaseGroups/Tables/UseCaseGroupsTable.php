<?php

namespace App\Filament\Resources\UseCaseGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UseCaseGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('use_cases_count')
                    ->counts('useCases')
                    ->label('Use Cases')
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
