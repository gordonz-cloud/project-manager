<?php

namespace App\Filament\Resources\Features\Tables;

use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeaturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->striped()
            ->columns([
                TextColumn::make('number')
                    ->label('Feature ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('功能')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('triggers')
                    ->label('触发方式')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureTrigger::from($state)->getLabel()),
                TextColumn::make('entry')
                    ->label('入口'),
                TextColumn::make('requirement.title')
                    ->label('需求')
                    ->badge()
                    ->searchable()
                    ->url(fn ($record) => $record->requirement_id
                        ? route('filament.admin.resources.requirements.edit', ['tenant' => $record->project->slug, 'record' => $record->requirement_id])
                        : null),
                TextColumn::make('commit_range')
                    ->label('Commit Range'),
                TextColumn::make('latest_commit')
                    ->label('Latest Commit'),
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
                SelectFilter::make('requirement')
                    ->label('需求')
                    ->relationship('requirement', 'title'),
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
