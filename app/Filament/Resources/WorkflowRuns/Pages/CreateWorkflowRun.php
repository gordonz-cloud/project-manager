<?php

namespace App\Filament\Resources\WorkflowRuns\Pages;

use App\Filament\Resources\WorkflowRuns\WorkflowRunResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowRun extends CreateRecord
{
    protected static string $resource = WorkflowRunResource::class;
}
