<?php

namespace App\Filament\Resources\DataModels\Schemas;

use App\Enums\DataModelStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DataModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Model')
                    ->required(),
                TextInput::make('table_name')
                    ->label('表名'),
                Select::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class)
                    ->required(),
                Textarea::make('description')
                    ->label('说明'),
                Textarea::make('business_purpose')
                    ->label('商业目的'),
                Select::make('features')
                    ->label('入口')
                    ->multiple()
                    ->relationship('features', 'title')
                    ->preload(),
            ]);
    }
}
