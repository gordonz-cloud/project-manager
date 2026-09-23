<?php

namespace App\Filament\Resources\WorkflowRuns\Pages;

use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkflowRun extends ViewRecord
{
    protected static string $resource = WorkflowRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
