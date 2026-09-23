<?php

namespace App\Filament\Resources\WorkflowRuns\RelationManagers;

use App\Enums\NodeRunMode;
use App\Enums\NodeRunStatus;
use App\Models\NodeRun;
use App\Models\WorkflowRun;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use LogicException;

class NodeRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'nodeRuns';

    public function getOwnerRecord(): WorkflowRun
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof WorkflowRun) {
            throw new LogicException('Node runs must belong to a workflow run.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        $run = $this->getOwnerRecord();

        return $schema->components([
            Select::make('implementation_node_id')
                ->label('实现节点')
                ->relationship(
                    'implementationNode',
                    'title',
                    fn (Builder $query): Builder => $query
                        ->where('project_id', $run->project_id)
                        ->when(
                            $run->feature_id,
                            fn (Builder $query): Builder => $query->where('feature_id', $run->feature_id),
                            fn (Builder $query): Builder => $query->whereHas(
                                'feature',
                                fn (Builder $query): Builder => $query->where('use_case_id', $run->use_case_id),
                            ),
                        ),
                )
                ->searchable()
                ->preload()
                ->disabled(fn (?NodeRun $record): bool => $record?->events()->exists() ?? false)
                ->rule(function () use ($run): Exists {
                    $rule = Rule::exists('implementation_nodes', 'id')
                        ->where('project_id', $run->project_id);

                    if ($run->feature_id !== null) {
                        $rule->where('feature_id', $run->feature_id);
                    } else {
                        $rule->using(fn (Builder $query): Builder => $query->whereHas(
                            'feature',
                            fn (Builder $query): Builder => $query->where('use_case_id', $run->use_case_id),
                        ));
                    }

                    return $rule;
                })
                ->required(),
            Select::make('mode')
                ->label('模式')
                ->options(NodeRunMode::class)
                ->disabled(fn (?NodeRun $record): bool => $record?->events()->exists() ?? false)
                ->required(),
            Select::make('status')
                ->label('状态')
                ->options(NodeRunStatus::class)
                ->required(),
            KeyValue::make('contract_snapshot')
                ->label('契约快照')
                ->disabled(fn (?NodeRun $record): bool => $record?->events()->exists() ?? false)
                ->required()
                ->columnSpanFull(),
            KeyValue::make('payload')
                ->label('Payload')
                ->columnSpanFull(),
            DateTimePicker::make('started_at')->label('开始时间'),
            DateTimePicker::make('finished_at')->label('结束时间'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('implementationNode.title')->label('节点')->searchable()->wrap(),
                TextColumn::make('mode')->label('模式')->badge(),
                TextColumn::make('status')->label('状态')->badge(),
                TextColumn::make('started_at')->label('开始')->dateTime()->placeholder('—'),
                TextColumn::make('finished_at')->label('结束')->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
            ]);
    }
}
