<?php

namespace App\Filament\Resources\ModelFields\Pages;

use App\Filament\Resources\ModelFields\ModelFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModelFields extends ListRecords
{
    protected static string $resource = ModelFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
