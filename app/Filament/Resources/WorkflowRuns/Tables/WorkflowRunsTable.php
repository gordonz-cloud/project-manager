<?php

namespace App\Filament\Resources\WorkflowRuns\Tables;

use App\Enums\WorkflowRunStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkflowRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['useCase', 'feature'])
                ->withCount(['events']))
            ->columns([
                TextColumn::make('id')
                    ->label('Run')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('useCase.goal')->wrap()
                    ->label('Use Case')
                    ->searchable(),
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('events_count')
                    ->label('事件')
                    ->numeric(),
                TextColumn::make('started_at')
                    ->label('开始')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('finished_at')
                    ->label('结束')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(WorkflowRunStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
