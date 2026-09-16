<?php

namespace App\Filament\Resources\FlowSteps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FlowStepsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderBy('path')->orderBy('order'))
            ->defaultGroup('feature.title')
            ->striped()
            ->groups([
                Group::make('feature.title')
                    ->label('功能')
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->searchable(),
                TextInputColumn::make('path')
                    ->label('路径')
                    ->searchable(),
                TextInputColumn::make('order')
                    ->label('顺序')
                    ->sortable(),
                TextInputColumn::make('step')
                    ->label('步骤')
                    ->searchable(),
                TextInputColumn::make('location')
                    ->label('位置'),
                TextColumn::make('input')
                    ->label('输入')
                    ->wrap(),
                TextColumn::make('change')
                    ->label('变化')
                    ->wrap(),
                TextColumn::make('output')
                    ->label('输出')
                    ->wrap(),
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
                //
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
