<?php

namespace App\Filament\Resources\ImplementationNodes;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ImplementationNodes\Pages\CreateImplementationNode;
use App\Filament\Resources\ImplementationNodes\Pages\EditImplementationNode;
use App\Filament\Resources\ImplementationNodes\Pages\ListImplementationNodes;
use App\Filament\Resources\ImplementationNodes\Pages\ViewImplementationNode;
use App\Filament\Resources\ImplementationNodes\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\ImplementationNodes\RelationManagers\CommitsRelationManager;
use App\Filament\Resources\ImplementationNodes\RelationManagers\IncomingEdgesRelationManager;
use App\Filament\Resources\ImplementationNodes\RelationManagers\NodeRunsRelationManager;
use App\Filament\Resources\ImplementationNodes\RelationManagers\OutgoingEdgesRelationManager;
use App\Filament\Resources\ImplementationNodes\Schemas\ImplementationNodeForm;
use App\Filament\Resources\ImplementationNodes\Schemas\ImplementationNodeInfolist;
use App\Filament\Resources\ImplementationNodes\Tables\ImplementationNodesTable;
use App\Models\ImplementationNode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImplementationNodeResource extends Resource
{
    protected static ?string $model = ImplementationNode::class;

    protected static ?string $navigationLabel = 'Implementation Nodes';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Delivery;
    }

    public static function form(Schema $schema): Schema
    {
        return ImplementationNodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ImplementationNodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImplementationNodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChildrenRelationManager::class,
            CommitsRelationManager::class,
            NodeRunsRelationManager::class,
            OutgoingEdgesRelationManager::class,
            IncomingEdgesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImplementationNodes::route('/'),
            'create' => CreateImplementationNode::route('/create'),
            'view' => ViewImplementationNode::route('/{record}'),
            'edit' => EditImplementationNode::route('/{record}/edit'),
        ];
    }
}
