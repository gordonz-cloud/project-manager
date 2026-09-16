<?php

namespace App\Filament\Resources\Features\Tables;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use App\Filament\Resources\Features\FeatureResource;
use App\Filament\Tables\ModuleGroup;
use App\Models\Feature;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
            ->defaultSort('number')
            ->recordUrl(fn (Feature $record): string => FeatureResource::getUrl('view', ['record' => $record]))
            ->recordAction(null)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('requirement.modules')->withCount('commits'))
            ->groups([
                ModuleGroup::make('requirement'),
                Group::make('requirement.title')
                    ->label('需求')
                    ->collapsible(),
            ])
            ->defaultGroup('module')
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
                TextColumn::make('layers')
                    ->label('层')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureLayer::from($state)->getLabel()),
                TextColumn::make('triggers')
                    ->label('触发方式')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureTrigger::from($state)->getLabel()),
                TextColumn::make('entry')
                    ->label('入口'),
                TextColumn::make('requirement.title')
                    ->label('需求')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->url(fn ($record) => $record->requirement_id
                        ? route('filament.admin.resources.requirements.edit', ['tenant' => $record->project->slug, 'record' => $record->requirement_id])
                        : null),
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
                    ->relationship('requirement.modules', 'name')
                    ->preload(),
                SelectFilter::make('requirement')
                    ->label('需求')
                    ->relationship('requirement', 'title')
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
