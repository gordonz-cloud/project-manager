<?php

namespace App\Filament\Resources\DataModels\Tables;

use App\Enums\DataModelStatus;
use App\Models\DataModel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DataModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('features.requirement.modules'))
            ->striped()
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
                TextColumn::make('design_gap')
                    ->label('设计差异')
                    ->wrap(),
                TextColumn::make('ruling')
                    ->label('拍板')
                    ->wrap(),
                TextColumn::make('modules')
                    ->label('模块')
                    ->state(fn (DataModel $record) => $record->derivedModules()->pluck('name'))
                    ->badge()
                    ->listWithLineBreaks(),
                TextColumn::make('features.title')
                    ->label('功能')
                    ->badge()
                    ->listWithLineBreaks(),
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
                    ->relationship('features.requirement.modules', 'name'),
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
