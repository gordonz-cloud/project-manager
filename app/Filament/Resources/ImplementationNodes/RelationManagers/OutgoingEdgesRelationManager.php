<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use App\Enums\ImplementationNodeEdgeKind;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OutgoingEdgesRelationManager extends RelationManager
{
    protected static string $relationship = 'outgoingEdges';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('to_node_id')
                ->label('目标节点')
                ->relationship('toNode', 'title')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('kind')
                ->label('类型')
                ->options(ImplementationNodeEdgeKind::class)
                ->required(),
            Textarea::make('condition')
                ->label('条件')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('toNode.title')->label('目标节点')->searchable()->wrap(),
                TextColumn::make('kind')->label('类型')->badge(),
                TextColumn::make('condition')->label('条件')->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
