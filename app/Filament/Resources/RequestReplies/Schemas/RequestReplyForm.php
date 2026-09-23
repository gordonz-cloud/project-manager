<?php

namespace App\Filament\Resources\RequestReplies\Schemas;

use App\Enums\FeatureTrigger;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RequestReplyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('use_case_id')
                    ->label('Use case')
                    ->relationship('useCase', 'goal')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->actor.' · '.$record->goal)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                TextInput::make('title')
                    ->label('入口')
                    ->required(),
                Select::make('trigger')
                    ->label('触发方式')
                    ->options(FeatureTrigger::class)
                    ->required(),
                Select::make('method')
                    ->label('Method')
                    ->options(array_combine($methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $methods)),
                TextInput::make('entry')
                    ->label('路径 / 命令 / Job')
                    ->required(),
                Select::make('module_id')
                    ->label('模块')
                    ->relationship('module', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('sort_order')
                    ->label('排序')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
