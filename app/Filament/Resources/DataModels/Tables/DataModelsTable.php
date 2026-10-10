<?php

namespace App\Filament\Resources\DataModels\Tables;

use App\Enums\DataModelStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DataModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->columns([
                TextColumn::make('name')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('table_name')
                    ->label('表名')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('description')
                    ->label('说明')
                    ->wrap(),
                TextColumn::make('business_purpose')
                    ->label('商业目的')
                    ->wrap(),
                TextColumn::make('model_fields_count')
                    ->label('字段数')
                    ->counts('modelFields')
                    ->sortable(),
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
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
