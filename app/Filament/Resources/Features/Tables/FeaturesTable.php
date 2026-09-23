<?php

namespace App\Filament\Resources\Features\Tables;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use App\Filament\Resources\Features\FeatureResource;
use App\Models\Feature;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeaturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('number')
            ->recordUrl(fn (Feature $record): string => FeatureResource::getUrl('view', ['record' => $record]))
            ->recordAction(null)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['module'])
                ->withCount('commits'))
            ->groups([
                Group::make('module.name')
                    ->label('模块')
                    ->collapsible(),
                Group::make('requirement.title')
                    ->label('需求')
                    ->collapsible(),
                Group::make('useCase.goal')
                    ->label('Use case')
                    ->collapsible(),
            ])
            ->defaultGroup('module.name')
            ->columns([
                TextColumn::make('number')
                    ->label('Feature ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('入口')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('layers')
                    ->label('层')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureLayer::labelFor($state)),
                TextColumn::make('triggers')
                    ->label('触发方式')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureTrigger::labelFor($state)),
                TextColumn::make('entry')
                    ->label('入口'),
                TextColumn::make('useCase.goal')
                    ->label('Use case')
                    ->badge()
                    ->color('gray')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('module.name')
                    ->label('模块')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('commits_count')
                    ->label('Commits')
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
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(FeatureStatus::class),
                SelectFilter::make('module')
                    ->label('模块')
                    ->relationship('module', 'name')
                    ->preload(),
                SelectFilter::make('use_case')
                    ->label('Use case')
                    ->relationship('useCase', 'goal')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('layers')
                    ->label('层')
                    ->options(FeatureLayer::class)
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->whereJsonContains('layers', $data['value'])
                        : $query),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
            ])
            ->toolbarActions([]);
    }
}
