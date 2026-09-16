<?php

namespace App\Filament\Resources\FlowSteps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FlowStepsTable
{
    /**
     * Shape columns (input/output) keep leading whitespace and line breaks:
     * escape first, then turn \n into <br>, so nothing but the intended
     * markup is rendered.
     */
    public static function formatShape(?string $state): string
    {
        return nl2br(e((string) $state));
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderBy('path')->orderBy('order'))
            ->defaultGroup('feature.title')
            ->groups([
                Group::make('feature.title')
                    ->label('功能')
                    ->collapsible(),
            ])
            ->columns([
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('路径')
                    ->searchable(),
                TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('step')
                    ->label('步骤')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('文件 · 方法')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('input')
                    ->label('手上拿到的数据')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (?string $state): string => self::formatShape($state))
                    ->html()
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: pre-wrap'])
                    // The shapes are read on a feature's own page, hop by hop;
                    // the cross-feature list is for finding a step, not reading it.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('change')
                    ->label('做了什么，交给谁')
                    ->wrap()
                    // The shapes are read on a feature's own page, hop by hop;
                    // the cross-feature list is for finding a step, not reading it.
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('output')
                    ->label('输出（不同于下一跳输入时才填）')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (?string $state): string => self::formatShape($state))
                    ->html()
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: pre-wrap'])
                    // The shapes are read on a feature's own page, hop by hop;
                    // the cross-feature list is for finding a step, not reading it.
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
                SelectFilter::make('module')
                    ->label('模块')
                    ->relationship('feature.requirement.modules', 'name')
                    ->preload(),
                SelectFilter::make('feature')
                    ->label('功能')
                    ->relationship('feature', 'title')
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
