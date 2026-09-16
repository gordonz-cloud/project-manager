<?php

namespace App\Filament\Resources\Requirements\Tables;

use App\Enums\RequirementStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextInputColumn::make('title')
                    ->label('需求')
                    ->searchable(),
                TextColumn::make('acceptance')
                    ->label('验收标准')
                    ->wrap(),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class),
                TextColumn::make('modules.name')
                    ->label('模块')
                    ->badge()
                    ->listWithLineBreaks(),
                TextColumn::make('notion_url')
                    ->label('Notion URL')
                    ->searchable()
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
            ->groups([
                Group::make('modules.name')
                    ->label('模块'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class),
                Filter::make('incomplete')
                    ->label('未完成')
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', RequirementStatus::Done)),
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
