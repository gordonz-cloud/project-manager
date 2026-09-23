<?php

namespace App\Filament\Resources\Scenarios\Schemas;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use App\Models\RequestReply;
use App\Models\Scenario;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live()
                    ->required(),
                Repeater::make('steps')
                    ->label('路径')
                    ->helperText('场景按顺序经过的入口，同一入口可以出现多次。')
                    ->relationship()
                    ->simple(
                        Select::make('request_reply_id')
                            ->label('入口')
                            ->options(fn (Get $get): array => RequestReply::query()
                                ->where('use_case_id', $get('../../use_case_id'))
                                ->orderBy('sort_order')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (RequestReply $requestReply): array => [$requestReply->id => "{$requestReply->label()} · {$requestReply->title}"])
                                ->all())
                            ->required(),
                    )
                    ->reorderable()
                    ->saveRelationshipsUsing(fn (Repeater $component, Scenario $record) => $record->replaceSteps(
                        array_map(intval(...), array_column((array) $component->getState(), 'request_reply_id')),
                    ))
                    ->columnSpanFull(),
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
