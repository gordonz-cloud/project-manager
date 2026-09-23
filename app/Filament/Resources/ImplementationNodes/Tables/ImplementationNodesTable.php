<?php

namespace App\Filament\Resources\ImplementationNodes\Tables;

use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImplementationNodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['feature', 'parent'])
                ->withCount(['children', 'scenarios', 'flowSteps', 'commits']))
            ->columns([
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label('父节点')
                    ->placeholder('—'),
                TextColumn::make('kind')
                    ->label('类型')
                    ->badge(),
                TextColumn::make('title')
                    ->label('节点')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('state')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('children_count')->label('子节点')->numeric(),
                TextColumn::make('scenarios_count')->label('场景')->numeric(),
                TextColumn::make('flow_steps_count')->label('Flow')->numeric(),
                TextColumn::make('commits_count')->label('Commits')->numeric(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('类型')
                    ->options(ImplementationNodeKind::class),
                SelectFilter::make('state')
                    ->label('状态')
                    ->options(ImplementationNodeState::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
