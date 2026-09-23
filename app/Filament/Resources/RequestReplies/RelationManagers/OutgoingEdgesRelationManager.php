<?php

namespace App\Filament\Resources\RequestReplies\RelationManagers;

use App\Enums\RequestReplyEdgeKind;
use App\Models\RequestReply;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the flow goes after this entry: the next entries of the same use case.
 */
class OutgoingEdgesRelationManager extends RelationManager
{
    protected static string $relationship = 'outgoingEdges';

    protected static ?string $title = '下游入口';

    public function form(Schema $schema): Schema
    {
        /** @var RequestReply $owner */
        $owner = $this->getOwnerRecord();

        return $schema
            ->components([
                Select::make('to_request_reply_id')
                    ->label('下一个入口')
                    ->relationship('to', 'title', fn (Builder $query): Builder => $query
                        ->where('use_case_id', $owner->use_case_id)
                        ->whereKeyNot($owner->id))
                    ->getOptionLabelFromRecordUsing(fn (RequestReply $record): string => "{$record->label()} · {$record->title}")
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('kind')
                    ->label('关系')
                    ->options(RequestReplyEdgeKind::class)
                    ->default(RequestReplyEdgeKind::Next)
                    ->required(),
                TextInput::make('condition')
                    ->label('条件'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('to'))
            ->columns([
                TextColumn::make('to.entry')
                    ->label('下一个入口')
                    ->formatStateUsing(fn ($record): string => $record->to->label())
                    ->wrap(),
                TextColumn::make('to.title')
                    ->label('入口')
                    ->wrap(),
                TextColumn::make('kind')
                    ->label('关系')
                    ->badge(),
                TextColumn::make('condition')
                    ->label('条件')
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }
}
