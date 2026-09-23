<?php

namespace App\Filament\Resources\FlowSteps\Schemas;

use App\Models\FlowStep;
use App\Services\ImplementationNodes\ImplementationNodeSelection;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

class FlowStepForm
{
    public static function configure(Schema $schema): Schema
    {
        $implementationNodes = resolve(ImplementationNodeSelection::class);

        return $schema
            ->components([
                Select::make('feature_id')
                    ->label('功能')
                    ->relationship('feature', 'title')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Select::make('implementation_node_id')
                    ->label('实现节点')
                    ->relationship(
                        'implementationNode',
                        'title',
                        fn (Builder $query, Get $get): Builder => $implementationNodes
                            ->constrainToFeature($query, $get('feature_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->rule(fn (Get $get): Exists => $implementationNodes->existsInFeatureRule($get('feature_id'))),
                TextInput::make('step')
                    ->label('步骤')
                    ->required(),
                TextInput::make('path')
                    ->label('路径')
                    ->datalist(fn (Get $get): array => FlowStep::query()
                        ->forFeature($get('feature_id'))
                        ->distinct()
                        ->pluck('path')
                        ->all())
                    ->required(),
                TextInput::make('order')
                    ->label('#')
                    ->numeric()
                    ->required(),
                TextInput::make('file')
                    ->label('文件')
                    ->datalist(fn (Get $get): array => FlowStep::query()
                        ->forFeature($get('feature_id'))
                        ->whereNotNull('file')
                        ->distinct()
                        ->pluck('file')
                        ->all())
                    ->extraInputAttributes(['class' => 'font-mono']),
                TextInput::make('function')
                    ->label('函数')
                    ->extraInputAttributes(['class' => 'font-mono']),
                Textarea::make('input')
                    ->label('参数')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14),
                Textarea::make('change')
                    ->label('做了什么'),
                Textarea::make('output')
                    ->label('返回值')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14)
                    ->required(),
            ]);
    }

    public static function configureForNode(Schema $schema, int $featureId): Schema
    {
        return $schema
            ->components([
                Hidden::make('feature_id')
                    ->default($featureId),
                TextInput::make('step')
                    ->label('步骤')
                    ->required(),
                TextInput::make('path')
                    ->label('路径')
                    ->required(),
                TextInput::make('order')
                    ->label('#')
                    ->numeric()
                    ->required(),
                TextInput::make('file')
                    ->label('文件')
                    ->extraInputAttributes(['class' => 'font-mono']),
                TextInput::make('function')
                    ->label('函数')
                    ->extraInputAttributes(['class' => 'font-mono']),
                Textarea::make('input')
                    ->label('参数')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14),
                Textarea::make('change')
                    ->label('做了什么'),
                Textarea::make('output')
                    ->label('返回值')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14)
                    ->required(),
            ]);
    }
}
