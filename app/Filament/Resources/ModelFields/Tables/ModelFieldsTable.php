<?php

namespace App\Filament\Resources\ModelFields\Tables;

use App\Enums\DataModelStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
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
                    ->searchable(),
                TextInputColumn::make('name')
                    ->label('字段')
                    ->searchable(),
                TextInputColumn::make('type')
                    ->label('类型'),
                ToggleColumn::make('nullable')
                    ->label('可空'),
                TextInputColumn::make('default_value')
                    ->label('默认值'),
                TextInputColumn::make('constraint')
                    ->label('约束'),
                TextColumn::make('description')
                    ->label('说明')
                    ->wrap(),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class),
                TextColumn::make('requirements.title')
                    ->label('支持需求')
                    ->badge()
                    ->listWithLineBreaks(),
                TextColumn::make('notion_url')
                    ->label('Notion URL')
                    ->toggleable(isToggledHiddenByDefault: true),
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
