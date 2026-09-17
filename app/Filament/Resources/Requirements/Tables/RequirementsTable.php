<?php

namespace App\Filament\Resources\Requirements\Tables;

use App\Enums\RequirementStatus;
use App\Filament\Tables\ModuleGroup;
use App\Models\Project;
use App\Models\Requirement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('需求 ID')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('buildOrder')
                    ->label('顺序')
                    ->state(function (Requirement $record): int {
                        $tenant = Filament::getTenant();
                        $order = $tenant instanceof Project ? Requirement::inBuildOrder($tenant) : collect();
                        $position = $order->search(fn (Requirement $requirement) => $requirement->id === $record->id);

                        return (is_int($position) ? $position : -1) + 1;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $tenant = Filament::getTenant();
                        $orderedIds = $tenant instanceof Project ? Requirement::inBuildOrder($tenant)->pluck('id') : collect();

                        $sql = '';
                        $bindings = [];

                        foreach ($orderedIds as $position => $id) {
                            $sql .= 'when ? then ? ';
                            $bindings[] = $id;
                            $bindings[] = $position;
                        }

                        $sql .= $direction === 'desc' ? 'end desc' : 'end asc';

                        return $query->orderByRaw("case requirements.id {$sql}", $bindings);
                    }),
                TextColumn::make('title')
                    ->label('需求')
                    ->searchable(),
                TextColumn::make('acceptance')
                    ->label('验收标准')
                    // One line per requirement; the whole text is a hover away.
                    ->limit(40)
                    ->tooltip(fn (TextColumn $column): ?string => mb_strlen((string) $column->getState()) > 40
                        ? (string) $column->getState()
                        : null),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('version')
                    ->label('版本'),
                TextColumn::make('dependsOn')
                    ->label('依赖')
                    ->state(fn (Requirement $record): array => $record->dependsOn
                        ->map(fn (Requirement $requirement): string => "{$requirement->id} · {$requirement->title}")
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
                TextColumn::make('modules.name')
                    ->label('模块')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['modules', 'dependsOn']))
            ->defaultSort('buildOrder')
            ->defaultGroup('module')
            ->groups([
                ModuleGroup::make(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class),
                SelectFilter::make('version')
                    ->label('版本')
                    ->options(fn (): array => Requirement::query()
                        ->whereNotNull('version')
                        ->distinct()
                        ->pluck('version', 'version')
                        ->all()),
                Filter::make('incomplete')
                    ->label('未完成')
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', RequirementStatus::Done)),
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
