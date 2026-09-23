<?php

namespace App\Filament\Resources\WorkflowRuns\RelationManagers;

use App\Enums\RunEventType;
use App\Models\NodeRun;
use App\Models\WorkflowRun;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use LogicException;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    public function getOwnerRecord(): WorkflowRun
    {
        $record = parent::getOwnerRecord();

        if (! $record instanceof WorkflowRun) {
            throw new LogicException('Events must belong to a workflow run.');
        }

        return $record;
    }

    public function form(Schema $schema): Schema
    {
        $run = $this->getOwnerRecord();

        return $schema->components([
            Select::make('node_run_id')
                ->label('NodeRun')
                ->relationship(
                    'nodeRun',
                    'id',
                    fn (Builder $query): Builder => $query->where('workflow_run_id', $run->id),
                )
                ->getOptionLabelFromRecordUsing(fn (NodeRun $record): string => "#{$record->id} · {$record->implementationNode->title}")
                ->searchable()
                ->preload()
                ->rule(Rule::exists('node_runs', 'id')->where('workflow_run_id', $run->id)),
            Select::make('event_type')
                ->label('事件')
                ->options(RunEventType::class)
                ->required(),
            KeyValue::make('payload')
                ->label('Payload')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('时间')->dateTime(),
                TextColumn::make('event_type')->label('事件')->badge(),
                TextColumn::make('nodeRun.implementationNode.title')->label('节点')->placeholder('—')->wrap(),
                TextColumn::make('payload')->label('Payload')->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ]);
    }
}
