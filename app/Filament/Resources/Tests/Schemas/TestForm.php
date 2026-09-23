<?php

namespace App\Filament\Resources\Tests\Schemas;

use App\Enums\TestLastResult;
use App\Enums\TestStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('测试')
                    ->required(),
                TextInput::make('location')
                    ->label('测试位置'),
                Select::make('status')
                    ->label('状态')
                    ->options(TestStatus::class)
                    ->required(),
                Select::make('last_result')
                    ->label('最近结果')
                    ->options(TestLastResult::class)
                    ->required(),
                Select::make('scenario_id')
                    ->label('Scenario')
                    ->relationship('scenario', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('features')
                    ->label('入口')
                    ->multiple()
                    ->relationship('features', 'title')
                    ->preload(),
            ]);
    }
}
