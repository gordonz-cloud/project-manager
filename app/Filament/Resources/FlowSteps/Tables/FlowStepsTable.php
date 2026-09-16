<?php

namespace App\Filament\Resources\FlowSteps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FlowStepsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderBy('path')->orderBy('order'))
            ->defaultGroup('feature.title')
            ->groups([
                Group::make('feature.title')
                    ->label('功能'),
            ])
            ->columns([
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('路径')
                    ->searchable(),
                TextColumn::make('order')
                    ->label('顺序')
                    ->sortable(),
                TextColumn::make('step')
                    ->label('步骤')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('位置'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
