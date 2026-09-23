<?php

namespace App\Filament\Resources\ImplementationNodes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImplementationNodeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('feature.title')->label('功能'),
                        TextEntry::make('parent.title')->label('父节点')->placeholder('—'),
                        TextEntry::make('kind')->label('类型')->badge(),
                        TextEntry::make('title')->label('节点')->columnSpanFull(),
                        TextEntry::make('state')->label('状态')->badge(),
                        TextEntry::make('contract')->label('交付契约')->columnSpanFull(),
                        TextEntry::make('evidence_required')->label('所需证据')->columnSpanFull(),
                    ]),
            ]);
    }
}
