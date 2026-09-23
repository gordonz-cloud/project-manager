<?php

namespace App\Filament\Resources\UseCases\RelationManagers;

use App\Filament\Resources\Concerns\UsesResourceForm;
use App\Filament\Resources\RequestReplies\RequestReplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RequestRepliesRelationManager extends RelationManager
{
    use UsesResourceForm;

    protected static string $relationship = 'requestReplies';

    protected static ?string $title = '入口';

    protected static function relatedResource(): string
    {
        return RequestReplyResource::class;
    }

    public function table(Table $table): Table
    {
        return RequestReplyResource::table($table)->headerActions([
            CreateAction::make()->slideOver(),
        ]);
    }
}
