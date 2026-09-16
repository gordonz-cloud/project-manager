<?php

namespace App\Filament\Resources\ModelFields\Pages;

use App\Filament\Resources\ModelFields\ModelFieldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModelField extends EditRecord
{
    protected static string $resource = ModelFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
