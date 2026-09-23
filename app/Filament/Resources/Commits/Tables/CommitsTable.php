<?php

namespace App\Filament\Resources\Commits\Tables;

use App\Filament\Resources\Features\FeatureResource;
use App\Models\Commit;
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
                TextColumn::make('feature.title')
                    ->label('功能')
                    ->badge()
                    ->color('gray')
                    ->url(fn (Commit $record): ?string => $record->feature_id
                        ? FeatureResource::getUrl('view', ['record' => $record->feature_id])
                        : null),
                TextColumn::make('implementationNode.title')
                    ->label('实现节点')
                    ->badge()
                    ->color('gray')
                    ->wrap(),
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
                    ->label('未挂功能')
                    ->query(fn (Builder $query): Builder => $query->whereNull('feature_id')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('改挂功能')
                    ->slideOver(),
            ]);
    }
}
