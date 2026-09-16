<?php

namespace App\Filament\Resources\Requirements\Pages;

use App\Filament\Resources\Requirements\RequirementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequirement extends EditRecord
{
    protected static string $resource = RequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
