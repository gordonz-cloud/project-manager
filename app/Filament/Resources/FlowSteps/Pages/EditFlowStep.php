<?php

namespace App\Filament\Resources\FlowSteps\Pages;

use App\Filament\Resources\FlowSteps\FlowStepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFlowStep extends EditRecord
{
    protected static string $resource = FlowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
