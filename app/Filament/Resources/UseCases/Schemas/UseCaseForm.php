<?php

namespace App\Filament\Resources\UseCases\Schemas;

use App\Enums\UseCaseStatus;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UseCaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('modules')
                    ->label('模块')
                    ->relationship('modules', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                ...self::fields(),
            ]);
    }

    public static function configureInModule(Schema $schema): Schema
    {
        return $schema->components(self::fields());
    }

    /**
     * @return list<Field|Section>
     */
    private static function fields(): array
    {
        return [
            Select::make('use_case_group_id')
                ->label('分组')
                ->relationship('group', 'name')
                ->createOptionForm([
                    TextInput::make('name')->label('名称')->required(),
                ])
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('actor')
                ->label('角色')
                ->required(),
            Textarea::make('goal')
                ->label('目标')
                ->required()
                ->columnSpanFull(),
            Textarea::make('trigger')
                ->label('触发')
                ->columnSpanFull(),
            Textarea::make('precondition')
                ->label('前置条件')
                ->columnSpanFull(),
            Textarea::make('success_outcome')
                ->label('成功结果')
                ->required()
                ->columnSpanFull(),
            Textarea::make('failure_outcome')
                ->label('失败结果')
                ->columnSpanFull(),
            Select::make('status')
                ->label('状态')
                ->options(UseCaseStatus::class)
                ->required(),
            Section::make('Use Case Spec')
                ->relationship('spec')
                ->schema([
                    Textarea::make('content')
                        ->label('跨模块流程')
                        ->rows(10)
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }
}
