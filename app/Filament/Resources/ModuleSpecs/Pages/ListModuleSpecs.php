<?php

namespace App\Filament\Resources\ModuleSpecs\Pages;

use App\Filament\Resources\ModuleSpecs\ModuleSpecResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModuleSpecs extends ListRecords
{
    protected static string $resource = ModuleSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
