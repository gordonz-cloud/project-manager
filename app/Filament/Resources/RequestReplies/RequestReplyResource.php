<?php

namespace App\Filament\Resources\RequestReplies;

use App\Enums\NavigationGroup;
use App\Filament\Resources\RequestReplies\Pages\CreateRequestReply;
use App\Filament\Resources\RequestReplies\Pages\EditRequestReply;
use App\Filament\Resources\RequestReplies\Pages\ListRequestReplies;
use App\Filament\Resources\RequestReplies\RelationManagers\FeaturesRelationManager;
use App\Filament\Resources\RequestReplies\Schemas\RequestReplyForm;
use App\Filament\Resources\RequestReplies\Tables\RequestRepliesTable;
use App\Models\RequestReply;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RequestReplyResource extends Resource
{
    protected static ?string $model = RequestReply::class;

    protected static ?string $navigationLabel = 'Request Replies';

    protected static ?string $modelLabel = '入口';

    protected static ?string $pluralModelLabel = '入口';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Behavior;
    }

    public static function form(Schema $schema): Schema
    {
        return RequestReplyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestRepliesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequestReplies::route('/'),
            'create' => CreateRequestReply::route('/create'),
            'edit' => EditRequestReply::route('/{record}/edit'),
        ];
    }
}
