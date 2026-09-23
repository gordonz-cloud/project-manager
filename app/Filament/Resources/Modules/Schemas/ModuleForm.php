<?php

namespace App\Filament\Resources\Modules\Schemas;

use App\Models\Module;
use App\Services\Modules\ModuleDependencies;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ModuleForm
{
    public static function configure(Schema $schema): Schema
    {
        $dependencies = resolve(ModuleDependencies::class);

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
                        fn (Builder $query, ?Module $record): Builder => $dependencies
                            ->constrainCandidates($query, $record),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->dehydrateStateUsing(fn (array $state, ?Module $record): array => $dependencies
                        ->validate($record, $state)),
                Section::make('Module Spec')
                    ->relationship('spec')
                    ->schema([
                        Textarea::make('content')
                            ->label('模块事实')
                            ->rows(10)
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
