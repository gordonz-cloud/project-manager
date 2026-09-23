<?php

namespace App\Filament\Resources\Scenarios\Schemas;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ScenarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('use_case_id')
                    ->label('Use case')
                    ->relationship('useCase', 'goal')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->actor.' · '.$record->goal)
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('场景')
                    ->required(),
                Select::make('type')
                    ->label('类型')
                    ->options(ScenarioType::class)
                    ->required(),
                Textarea::make('given')
                    ->label('Given')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('when')
                    ->label('When')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('then')
                    ->label('Then')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('coverage_dimension')
                    ->label('覆盖维度'),
                TextInput::make('equivalence_class')
                    ->label('等价类'),
                TextInput::make('boundary')
                    ->label('边界'),
                Select::make('priority')
                    ->label('优先级')
                    ->options(ScenarioPriority::class)
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(ScenarioStatus::class)
                    ->required(),
            ]);
    }
}
