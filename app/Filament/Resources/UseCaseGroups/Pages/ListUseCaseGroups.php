<?php

namespace App\Filament\Resources\UseCaseGroups\Pages;

use App\Filament\Resources\UseCaseGroups\UseCaseGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUseCaseGroups extends ListRecords
{
    protected static string $resource = UseCaseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
