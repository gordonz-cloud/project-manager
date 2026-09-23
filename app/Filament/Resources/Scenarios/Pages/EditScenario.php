<?php

namespace App\Filament\Resources\Scenarios\Pages;

use App\Filament\Resources\Scenarios\ScenarioResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScenario extends EditRecord
{
    protected static string $resource = ScenarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
