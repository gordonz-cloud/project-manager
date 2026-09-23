<?php

namespace App\Filament\Resources\RequestReplies\Tables;

use App\Enums\FeatureTrigger;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequestRepliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['useCase', 'module'])
                ->withCount(['features', 'implementationNodes', 'outgoingEdges']))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('entry')
                    ->label('路径 / 命令 / Job')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('title')
                    ->label('入口')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('trigger')
                    ->label('触发方式')
                    ->badge(),
                TextColumn::make('useCase.goal')
                    ->label('Use case')
                    ->wrap(),
                TextColumn::make('module.name')
                    ->label('模块')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('outgoing_edges_count')
                    ->label('下游')
                    ->numeric(),
                TextColumn::make('implementation_nodes_count')
                    ->label('调用节点')
                    ->numeric(),
                TextColumn::make('features_count')
                    ->label('功能')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('trigger')
                    ->label('触发方式')
                    ->options(FeatureTrigger::class),
                SelectFilter::make('use_case')
                    ->label('Use case')
                    ->relationship('useCase', 'goal')
                    ->searchable()
                    ->preload(),
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
