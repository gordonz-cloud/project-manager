<?php

namespace App\Filament\Resources\ModelFields\Schemas;

use App\Enums\DataModelStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ModelFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('data_model_id')
                    ->label('Model')
                    ->relationship('dataModel', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('字段')
                    ->required(),
                TextInput::make('type')
                    ->label('类型'),
                Toggle::make('nullable')
                    ->label('可空'),
                TextInput::make('default_value')
                    ->label('默认值'),
                TextInput::make('constraint')
                    ->label('约束'),
                Textarea::make('description')
                    ->label('说明'),
                Select::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class)
                    ->required(),
            ]);
    }
}
