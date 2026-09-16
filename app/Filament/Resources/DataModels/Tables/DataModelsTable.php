<?php

namespace App\Filament\Resources\DataModels\Tables;

use App\Enums\DataModelStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DataModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('modules.name')
                    ->label('模块'),
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
                SelectFilter::make('modules')
                    ->label('模块')
                    ->relationship(
                        'modules',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('project_id', Filament::getTenant()?->getKey()),
                    ),
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
