<?php

namespace App\Filament\Resources\Commits\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CommitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('requirements')
                    ->label('规则')
                    ->multiple()
                    ->relationship(name: 'requirements', titleAttribute: 'title')
                    ->searchable(),
            ]);
    }
}
