<?php

namespace App\Filament\Resources\Tests\Tables;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('测试')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('测试位置')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('last_result')
                    ->label('最近结果')
                    ->badge()
                    ->color(fn (TestLastResult $state): string => match ($state) {
                        TestLastResult::Passed => 'success',
                        TestLastResult::Failed => 'danger',
                        TestLastResult::Blocked => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('features.title')
                    ->label('功能'),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
