<?php

namespace App\Filament\Resources\Modules\Schemas;

use App\Models\Module;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ModuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('名字')
                    ->required(),
                Select::make('dependsOn')
                    ->label('依赖的模块')
                    ->relationship(
                        'dependsOn',
                        'name',
                        fn (Builder $query, ?Module $record) => $record ? $query->whereKeyNot($record->id) : $query,
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->dehydrateStateUsing(function (array $state, ?Module $record): array {
                        if ($record) {
                            foreach ($state as $dependsOnId) {
                                $dependsOnId = (int) $dependsOnId;

                                if (Module::wouldCycle($record->id, $dependsOnId)) {
                                    $dependsOnName = Module::find($dependsOnId)?->name;

                                    throw ValidationException::withMessages([
                                        'dependsOn' => "{$dependsOnName} 已经（直接或间接）依赖 {$record->name}，不能反过来",
                                    ]);
                                }
                            }
                        }

                        return $state;
                    }),
            ]);
    }
}
