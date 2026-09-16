<?php

namespace App\Filament\Resources\FlowSteps\Schemas;

use App\Models\FlowStep;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live()
                    ->required(),
                TextInput::make('step')
                    ->label('步骤')
                    ->required(),
                TextInput::make('path')
                    ->label('路径')
                    ->datalist(fn (Get $get): array => FlowStep::query()
                        ->where('feature_id', $get('feature_id'))
                        ->distinct()
                        ->pluck('path')
                        ->all())
                    ->required(),
                TextInput::make('order')
                    ->label('#')
                    ->numeric()
                    ->required(),
                TextInput::make('location')
                    ->label('文件 · 方法')
                    ->extraInputAttributes(['class' => 'font-mono']),
                Textarea::make('input')
                    ->label('手上拿到的数据')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14),
                Textarea::make('change')
                    ->label('做了什么，交给谁'),
                Textarea::make('output')
                    ->label('输出（不同于下一跳输入时才填）')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14),
            ]);
    }
}
