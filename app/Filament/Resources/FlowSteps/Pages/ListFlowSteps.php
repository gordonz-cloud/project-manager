<?php

namespace App\Filament\Resources\FlowSteps\Pages;

use App\Filament\Resources\FlowSteps\FlowStepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlowSteps extends ListRecords
{
    protected static string $resource = FlowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
