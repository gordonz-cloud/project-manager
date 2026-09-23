<?php

namespace App\Filament\Resources\RequestReplies\Pages;

use App\Filament\Resources\RequestReplies\RequestReplyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequestReply extends EditRecord
{
    protected static string $resource = RequestReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
