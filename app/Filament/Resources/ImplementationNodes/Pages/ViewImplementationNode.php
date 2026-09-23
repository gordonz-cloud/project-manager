<?php

namespace App\Filament\Resources\ImplementationNodes\Pages;

use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewImplementationNode extends ViewRecord
{
    protected static string $resource = ImplementationNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
