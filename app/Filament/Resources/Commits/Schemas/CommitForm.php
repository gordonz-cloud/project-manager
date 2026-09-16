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
                Select::make('feature_id')
                    ->label('功能')
                    ->relationship(name: 'feature', titleAttribute: 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
