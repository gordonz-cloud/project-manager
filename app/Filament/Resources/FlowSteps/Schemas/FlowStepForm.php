<?php

namespace App\Filament\Resources\FlowSteps\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FlowStepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('feature_id')
                    ->label('功能')
                    ->relationship('feature', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('step')
                    ->label('步骤')
                    ->required(),
                TextInput::make('path')
                    ->label('路径')
                    ->required(),
                TextInput::make('order')
                    ->label('顺序')
                    ->numeric()
                    ->required(),
                TextInput::make('location')
                    ->label('位置'),
                Textarea::make('input')
                    ->label('输入'),
                Textarea::make('change')
                    ->label('变化'),
                Textarea::make('output')
                    ->label('输出'),
            ]);
    }
}
