<?php

namespace App\Filament\Resources\UseCases\Pages;

use App\Filament\Resources\UseCases\UseCaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUseCase extends EditRecord
{
    protected static string $resource = UseCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
