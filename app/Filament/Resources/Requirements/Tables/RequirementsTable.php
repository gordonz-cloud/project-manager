<?php

namespace App\Filament\Resources\Requirements\Tables;

use App\Enums\RequirementStatus;
use App\Filament\Tables\ModuleGroup;
use App\Models\Requirement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('需求 ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('需求')
                    ->searchable(),
                TextColumn::make('acceptance')
                    ->label('验收标准')
                    // One line per requirement; the whole text is a hover away.
                    ->limit(40)
                    ->tooltip(fn (TextColumn $column): ?string => mb_strlen((string) $column->getState()) > 40
                        ? (string) $column->getState()
                        : null),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('version')
                    ->label('版本'),
                TextColumn::make('modules.name')
                    ->label('模块')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
                TextColumn::make('features.title')
                    ->label('功能')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('modules'))
            ->defaultGroup('module')
            ->groups([
                ModuleGroup::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class),
                SelectFilter::make('version')
                    ->label('版本')
                    ->options(fn (): array => Requirement::query()
                        ->whereNotNull('version')
                        ->distinct()
                        ->pluck('version', 'version')
                        ->all()),
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
