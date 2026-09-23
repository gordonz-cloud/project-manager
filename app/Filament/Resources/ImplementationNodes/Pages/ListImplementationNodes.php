<?php

namespace App\Filament\Resources\ImplementationNodes\Pages;

use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImplementationNodes extends ListRecords
{
    protected static string $resource = ImplementationNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
