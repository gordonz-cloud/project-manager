<?php

namespace App\Filament\Resources\WorkflowRuns\RelationManagers;

use App\Enums\RunEventType;
use App\Models\WorkflowRun;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
        return $schema->components([
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
                TextColumn::make('payload')->label('Payload')->formatStateUsing(fn (?array $state): string => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ]);
    }
}
