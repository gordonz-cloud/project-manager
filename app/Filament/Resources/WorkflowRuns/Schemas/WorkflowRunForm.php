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
                        && $record->events()->exists()),
                Select::make('feature_id')
                    ->label('功能')
                    ->relationship(
                        'feature',
                        'title',
                        fn (Builder $query, Get $get): Builder => $query->where('use_case_id', $get('use_case_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (?WorkflowRun $record): bool => $record !== null
                        && $record->events()->exists())
                    ->rule(fn (Get $get): Exists => Rule::exists('features', 'id')
                        ->where('use_case_id', $get('use_case_id'))),
                TextInput::make('graph_version')
                    ->label('图版本'),
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
