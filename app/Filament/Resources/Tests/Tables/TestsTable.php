<?php

namespace App\Filament\Resources\Tests\Tables;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use App\Models\Test;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TestsTable
{
    public static function configure(Table $table): Table
    {
        // A test can cover several features, so Filament cannot group on the
        // relation itself (it sorts by it and refuses BelongsToMany). The
        // lowest-numbered feature stands for the row, and the query is
        // ordered by that same subquery so rows arrive already clustered.
        $firstFeature = fn (Test $test): string => $test->features->sortBy('number')->first()->title ?? '（未挂功能）';

        return $table
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('features'))
            ->defaultGroup('feature')
            ->groups([
                Group::make('feature')
                    ->label('功能')
                    ->getKeyFromRecordUsing($firstFeature)
                    ->getTitleFromRecordUsing($firstFeature)
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy(
                        DB::raw('(select min(features.number) from feature_test
                            join features on features.id = feature_test.feature_id
                            where feature_test.test_id = tests.id)'),
                        $direction === 'desc' ? 'desc' : 'asc',
                    ))
                    ->collapsible(),
            ])
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
                    ->badge(),
                TextColumn::make('features.title')
                    ->label('功能')
                    ->badge()
                    ->listWithLineBreaks(),
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
