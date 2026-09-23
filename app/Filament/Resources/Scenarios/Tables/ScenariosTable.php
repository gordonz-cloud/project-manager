<?php

namespace App\Filament\Resources\Scenarios\Tables;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScenariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('useCase.requirement')
                ->withCount(['implementationNodes', 'tests']))
            ->columns([
                TextColumn::make('name')
                    ->label('场景')
                    ->searchable(),
                TextColumn::make('useCase.goal')
                    ->label('Use case')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('类型')
                    ->badge(),
                TextColumn::make('priority')
                    ->label('优先级')
                    ->badge(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('implementation_nodes_count')
                    ->label('节点')
                    ->numeric(),
                TextColumn::make('tests_count')
                    ->label('测试')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options(ScenarioType::class),
                SelectFilter::make('priority')
                    ->label('优先级')
                    ->options(ScenarioPriority::class),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(ScenarioStatus::class),
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
