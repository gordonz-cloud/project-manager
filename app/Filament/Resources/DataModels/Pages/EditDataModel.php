<?php

namespace App\Filament\Resources\DataModels\Pages;

use App\Filament\Resources\DataModels\DataModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDataModel extends EditRecord
{
    protected static string $resource = DataModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
