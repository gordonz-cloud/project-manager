<?php

namespace App\Filament\Resources\WorkflowRuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkflowRunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('useCase.goal')->label('Use Case')->placeholder('—'),
                        TextEntry::make('feature.title')->label('功能')->placeholder('—'),
                        TextEntry::make('status')->label('状态')->badge(),
                        TextEntry::make('graph_version')->label('图版本')->placeholder('—'),
                        TextEntry::make('focus_node_run_id')->label('聚焦 NodeRun')->placeholder('—'),
                        TextEntry::make('started_at')->label('开始时间')->dateTime()->placeholder('—'),
                        TextEntry::make('finished_at')->label('结束时间')->dateTime()->placeholder('—'),
                    ]),
            ]);
    }
}
