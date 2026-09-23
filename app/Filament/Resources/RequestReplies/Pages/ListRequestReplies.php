<?php

namespace App\Filament\Resources\RequestReplies\Pages;

use App\Filament\Resources\RequestReplies\RequestReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequestReplies extends ListRecords
{
    protected static string $resource = RequestReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
