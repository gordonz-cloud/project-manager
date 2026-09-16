<?php

namespace App\Filament\Resources\Modules\Tables;

use App\Models\Module;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('requirements')->with(['dependsOn', 'dependents']))
            ->defaultSort('buildOrder')
            ->columns([
                TextColumn::make('buildOrder')
                    ->label('顺序')
                    ->state(function (Module $record): int {
                        $tenant = Filament::getTenant();
                        $order = $tenant instanceof Project ? Module::inBuildOrder($tenant) : collect();
                        $position = $order->search(fn (Module $module) => $module->id === $record->id);

                        return (is_int($position) ? $position : -1) + 1;
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $tenant = Filament::getTenant();
                        $orderedIds = $tenant instanceof Project ? Module::inBuildOrder($tenant)->pluck('id') : collect();

                        $sql = '';
                        $bindings = [];

                        foreach ($orderedIds as $position => $id) {
                            $sql .= 'when ? then ? ';
                            $bindings[] = $id;
                            $bindings[] = $position;
                        }

                        $sql .= $direction === 'desc' ? 'end desc' : 'end asc';

                        return $query->orderByRaw("case modules.id {$sql}", $bindings);
                    }),
                TextColumn::make('name')
                    ->label('名字')
                    ->searchable(),
                TextColumn::make('dependsOn.name')
                    ->label('依赖')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
                TextColumn::make('dependents.name')
                    ->label('被依赖')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks(),
                TextColumn::make('requirements_count')
                    ->label('需求数')
                    ->sortable(),
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
