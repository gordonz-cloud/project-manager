<?php

namespace App\Filament\Resources\UseCases\Pages;

use App\Filament\Resources\UseCases\UseCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUseCases extends ListRecords
{
    protected static string $resource = UseCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
