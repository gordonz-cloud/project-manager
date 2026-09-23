<?php

namespace App\Filament\Resources\ImplementationNodes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NodeRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'nodeRuns';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('workflowRun.id')->label('Run')->numeric(),
                TextColumn::make('mode')->label('模式')->badge(),
                TextColumn::make('status')->label('状态')->badge(),
                TextColumn::make('started_at')->label('开始')->dateTime()->placeholder('—'),
                TextColumn::make('finished_at')->label('结束')->dateTime()->placeholder('—'),
            ]);
    }
}
