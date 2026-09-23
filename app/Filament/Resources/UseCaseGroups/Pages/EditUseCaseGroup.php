<?php

namespace App\Filament\Resources\UseCaseGroups\Pages;

use App\Filament\Resources\UseCaseGroups\UseCaseGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUseCaseGroup extends EditRecord
{
    protected static string $resource = UseCaseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
