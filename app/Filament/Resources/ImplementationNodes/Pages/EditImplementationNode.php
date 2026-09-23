<?php

namespace App\Filament\Resources\ImplementationNodes\Pages;

use App\Filament\Resources\ImplementationNodes\ImplementationNodeResource;
use App\Models\ImplementationNode;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditImplementationNode extends EditRecord
{
    protected static string $resource = ImplementationNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (ImplementationNode $record): bool => ! $record->hasRunHistory()),
        ];
    }
}
