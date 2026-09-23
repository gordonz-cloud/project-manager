<?php

namespace App\Filament\Resources\ModuleSpecs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModuleSpecsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label('项目')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('module.name')
                    ->label('模块')
                    ->searchable(),
                TextColumn::make('version')
                    ->label('版本')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('use_cases_count')
                    ->counts('useCases')
                    ->label('Use Cases')
                    ->numeric(),
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
