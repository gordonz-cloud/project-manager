<?php

namespace App\Filament\Resources\DataModels\Pages;

use App\Filament\Resources\DataModels\DataModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDataModel extends CreateRecord
{
    protected static string $resource = DataModelResource::class;
}
