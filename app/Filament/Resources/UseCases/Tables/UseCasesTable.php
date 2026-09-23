<?php

namespace App\Filament\Resources\UseCases\Tables;

use App\Enums\UseCaseStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UseCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['modules', 'group'])
                ->withCount(['features']))
            ->columns([
                TextColumn::make('group.name')
                    ->label('分组')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('modules.name')
                    ->label('模块')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('actor')
                    ->label('角色')
                    ->searchable(),
                TextColumn::make('goal')
                    ->label('目标')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('features_count')
                    ->label('功能')
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(UseCaseStatus::class),
                SelectFilter::make('modules')
                    ->label('模块')
                    ->relationship('modules', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('use_case_group_id')
                    ->label('分组')
                    ->relationship('group', 'name')
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
