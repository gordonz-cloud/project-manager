<?php

namespace App\Filament\Resources\DataModels\Pages;

use App\Filament\Resources\DataModels\DataModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDataModels extends ListRecords
{
    protected static string $resource = DataModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
