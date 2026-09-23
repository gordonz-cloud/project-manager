<?php

namespace App\Filament\Resources\ModuleSpecs\Pages;

use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModuleSpec extends EditRecord
{
    protected static string $resource = ModuleSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
