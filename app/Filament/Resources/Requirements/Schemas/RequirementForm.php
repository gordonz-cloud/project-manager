<?php

namespace App\Filament\Resources\Requirements\Schemas;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class RequirementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('需求')
                    ->required(),
                Textarea::make('acceptance')
                    ->label('验收标准')
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class)
                    ->required(),
                TextInput::make('version')
                    ->label('版本'),
                Select::make('modules')
                    ->label('模块')
                    ->relationship(name: 'modules', titleAttribute: 'name')
                    ->multiple()
                    ->preload(),
                Select::make('dependsOn')
                    ->label('依赖的需求')
                    ->relationship(
                        'dependsOn',
                        'title',
                        fn (Builder $query, ?Requirement $record) => $record ? $query->whereKeyNot($record->id) : $query,
                    )
                    ->getOptionLabelFromRecordUsing(fn (Requirement $record): string => "{$record->id} · {$record->title}")
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->dehydrateStateUsing(function (array $state, ?Requirement $record): array {
                        if ($record) {
                            foreach ($state as $dependsOnId) {
                                $dependsOnId = (int) $dependsOnId;

                                if (Requirement::wouldCycle($record->id, $dependsOnId)) {
                                    $dependsOnTitle = Requirement::find($dependsOnId)?->title;

                                    throw ValidationException::withMessages([
                                        'dependsOn' => "{$dependsOnTitle} 已经（直接或间接）依赖 {$record->title}，不能反过来",
                                    ]);
                                }
                            }
                        }

                        return $state;
                    }),
            ]);
    }
}
