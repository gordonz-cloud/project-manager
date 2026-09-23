<?php

namespace App\Filament\Resources\WorkflowRuns\Pages;

use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowRun extends EditRecord
{
    protected static string $resource = WorkflowRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
