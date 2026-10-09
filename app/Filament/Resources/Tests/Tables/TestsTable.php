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
        $firstFeatureTitleSql = '(select features.title from feature_test
            join features on features.id = feature_test.feature_id
            where feature_test.test_id = tests.id
            order by features.number limit 1)';

        return $table
            ->stackedOnMobile()
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
                    ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $key === '（未挂功能）'
                        ? $query->whereRaw("{$firstFeatureTitleSql} is null")
                        : $query->whereRaw("{$firstFeatureTitleSql} = ?", [$key]))
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
                TextColumn::make('features.title')
                    ->label('功能')
                    ->badge()
                    ->color('gray')
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
                SelectFilter::make('module')
                    ->label('模块')
                    ->relationship('features.requirement.modules', 'name')
                    ->preload(),
                SelectFilter::make('feature')
                    ->label('功能')
                    ->relationship('features', 'title')
                    ->searchable()
                    ->preload(),
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
