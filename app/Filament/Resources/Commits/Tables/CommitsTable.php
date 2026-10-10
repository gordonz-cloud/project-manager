<?php

namespace App\Filament\Resources\Commits\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('committed_at', 'desc')
            ->columns([
                TextColumn::make('hash')
                    ->label('Hash')
                    ->formatStateUsing(fn (string $state): string => substr($state, 0, 8))
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('requirements.number')
                    ->label('规则')
                    ->formatStateUsing(fn (int $state): string => "R{$state}")
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('author')
                    ->label('作者'),
                TextColumn::make('committed_at')
                    ->label('提交时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('unassigned')
                    ->label('未挂规则')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('requirements')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('改挂规则')
                    ->slideOver(),
            ]);
    }
}
