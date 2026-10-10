<?php

namespace App\Filament\Resources\Tests\Tables;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('requirements:id,number'))
            ->defaultGroup('module')
            ->groups([
                Group::make('module')
                    ->label('模块')
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('number')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('动作')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('expected')
                    ->label('预期')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('priority')
                    ->label('优先级')
                    ->badge(),
                TextColumn::make('auto')
                    ->label('自动化')
                    ->badge(),
                TextColumn::make('location')
                    ->label('测试文件')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('last_result')
                    ->label('最近结果')
                    ->badge(),
                TextColumn::make('requirements.number')
                    ->label('规则')
                    ->formatStateUsing(fn (int $state): string => "R{$state}")
                    ->badge()
                    ->color('gray'),
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
                    ->options(TestStatus::class),
                SelectFilter::make('last_result')
                    ->label('最近结果')
                    ->options(TestLastResult::class),
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
