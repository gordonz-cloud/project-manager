<?php

namespace App\Filament\Resources\Scenarios\Pages;

use App\Filament\Resources\Scenarios\ScenarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScenarios extends ListRecords
{
    protected static string $resource = ScenarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
