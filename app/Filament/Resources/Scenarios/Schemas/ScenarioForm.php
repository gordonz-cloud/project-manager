<?php

namespace App\Filament\Resources\Scenarios\Schemas;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                    ->live()
                    ->required(),
                Select::make('end_node_id')
                    ->label('终点节点')
                    ->helperText('场景走的路径：入口根节点 → 这个节点。')
                    ->relationship(
                        'endNode',
                        'title',
                        fn (Builder $query, Get $get): Builder => $query->whereHas(
                            'feature',
                            fn (Builder $feature): Builder => $feature->where('use_case_id', $get('use_case_id')),
                        ),
                    )
                    ->searchable()
                    ->preload(),
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
