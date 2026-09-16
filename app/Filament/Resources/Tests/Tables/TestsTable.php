<?php

namespace App\Filament\Resources\Tests\Tables;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextInputColumn::make('title')
                    ->label('测试')
                    ->searchable(),
                TextInputColumn::make('location')
                    ->label('测试位置')
                    ->searchable(),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(TestStatus::class),
                SelectColumn::make('last_result')
                    ->label('最近结果')
                    ->options(TestLastResult::class),
                TextColumn::make('features.title')
                    ->label('功能')
                    ->badge()
                    ->listWithLineBreaks(),
                TextColumn::make('notion_url')
                    ->label('Notion URL')
                    ->toggleable(isToggledHiddenByDefault: true),
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
