<?php

namespace App\Filament\Resources\ModelFields\Tables;

use App\Enums\DataModelStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ModelFieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->defaultGroup('dataModel.name')
            ->striped()
            ->groups([
                Group::make('dataModel.name')
                    ->label('Model'),
            ])
            ->columns([
                TextColumn::make('number')
                    ->label('Field ID')
                    ->sortable(),
                TextColumn::make('dataModel.name')
                    ->label('Model')
                    ->searchable()
                    // The group header already says it; the column is for
                    // whoever turns grouping off.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('字段')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('类型'),
                IconColumn::make('nullable')
                    ->boolean()
                    ->label('可空'),
                TextColumn::make('default_value')
                    ->label('默认值'),
                TextColumn::make('constraint')
                    ->label('约束')
                    ->wrap(),
                TextColumn::make('description')
                    ->label('说明')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
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
