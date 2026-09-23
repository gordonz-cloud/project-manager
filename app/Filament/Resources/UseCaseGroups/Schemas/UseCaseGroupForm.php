<?php

namespace App\Filament\Resources\UseCaseGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UseCaseGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('名称')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
