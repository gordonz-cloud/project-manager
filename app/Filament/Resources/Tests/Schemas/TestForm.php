<?php

namespace App\Filament\Resources\Tests\Schemas;

use App\Enums\TestAuto;
use App\Enums\TestLastResult;
use App\Enums\TestPriority;
use App\Enums\TestStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('动作')
                    ->required(),
                Textarea::make('expected')
                    ->label('预期'),
                TextInput::make('module')
                    ->label('模块'),
                Select::make('priority')
                    ->label('优先级')
                    ->options(TestPriority::class),
                Select::make('auto')
                    ->label('自动化')
                    ->options(TestAuto::class),
                TextInput::make('location')
                    ->label('测试文件'),
                TextInput::make('test_name')
                    ->label('测试名'),
                Textarea::make('notes')
                    ->label('备注'),
                Select::make('status')
                    ->label('状态')
                    ->options(TestStatus::class)
                    ->required(),
                Select::make('last_result')
                    ->label('最近结果')
                    ->options(TestLastResult::class)
                    ->required(),
                Select::make('requirements')
                    ->label('规则')
                    ->multiple()
                    ->relationship('requirements', 'title')
                    ->searchable(),
            ]);
    }
}
