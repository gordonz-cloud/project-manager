<?php

namespace App\Filament\Resources\Features\Tables;

use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
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
    private const string FIRST_MODULE_SQL = '(select min(modules.name) from module_requirement
        join modules on modules.id = module_requirement.module_id
        where module_requirement.requirement_id = features.requirement_id)';

    public static function configure(Table $table): Table
    {
        // A feature reaches its module through its requirement, and a
        // requirement can sit in several modules, so this is not a column the
        // table can group on by name. The first module by name stands for the
        // row; two requirements in the whole dataset span more than one.
        $firstModuleName = fn (Feature $feature): string => $feature->requirement?->modules
            ->sortBy('name')->first()->name ?? '（无模块）';

        return $table
            ->defaultSort('number')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('requirement.modules'))
            ->groups([
                Group::make('module')
                    ->label('模块')
                    ->getKeyFromRecordUsing($firstModuleName)
                    ->getTitleFromRecordUsing($firstModuleName)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        $direction === 'desc' ? self::FIRST_MODULE_SQL.' desc' : self::FIRST_MODULE_SQL.' asc'
                    ))
                    ->collapsible(),
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
