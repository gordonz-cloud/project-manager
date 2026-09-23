<?php

namespace App\Filament\Resources\WorkflowRuns\Schemas;

use App\Enums\WorkflowRunStatus;
use App\Models\WorkflowRun;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class WorkflowRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('use_case_id')
                    ->label('Use Case')
                    ->relationship('useCase', 'goal')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabled(fn (?WorkflowRun $record): bool => $record !== null
                        && ($record->nodeRuns()->exists() || $record->events()->exists())),
                Select::make('feature_id')
                    ->label('入口')
                    ->relationship(
                        'feature',
                        'title',
                        fn (Builder $query, Get $get): Builder => $query->where('use_case_id', $get('use_case_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?WorkflowRun $record): bool => $record !== null
                        && ($record->nodeRuns()->exists() || $record->events()->exists()))
                    ->rule(fn (Get $get): Exists => Rule::exists('features', 'id')
                        ->where('use_case_id', $get('use_case_id'))),
                TextInput::make('graph_version')
                    ->label('图版本'),
                Select::make('focus_node_run_id')
                    ->label('聚焦 NodeRun')
                    ->relationship(
                        'focusNodeRun',
                        'id',
                        fn (Builder $query, ?WorkflowRun $record): Builder => $record === null
                            ? $query->whereRaw('1 = 0')
                            : $query->where('workflow_run_id', $record->id),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "#{$record->id} · {$record->implementationNode->title}")
                    ->searchable()
                    ->preload()
                    ->rule(fn (?WorkflowRun $record): Exists => $record === null
                        ? Rule::exists('node_runs', 'id')->where('id', -1)
                        : Rule::exists('node_runs', 'id')->where('workflow_run_id', $record->id)),
                Select::make('status')
                    ->label('状态')
                    ->options(WorkflowRunStatus::class)
                    ->required(),
                DateTimePicker::make('started_at')
                    ->label('开始时间'),
                DateTimePicker::make('finished_at')
                    ->label('结束时间'),
            ]);
    }
}
