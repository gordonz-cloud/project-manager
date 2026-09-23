<?php

namespace App\Filament\Resources\WorkflowRuns\Pages;

use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowRuns extends ListRecords
{
    protected static string $resource = WorkflowRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
