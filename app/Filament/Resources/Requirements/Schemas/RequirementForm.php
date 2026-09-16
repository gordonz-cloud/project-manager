<?php

namespace App\Filament\Resources\Requirements\Schemas;

use App\Enums\RequirementStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RequirementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('需求')
                    ->required(),
                Textarea::make('acceptance')
                    ->label('验收标准')
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('状态')
                    ->options(RequirementStatus::class)
                    ->required(),
                Select::make('modules')
                    ->label('模块')
                    ->relationship(name: 'modules', titleAttribute: 'name')
                    ->multiple()
                    ->preload(),
                Select::make('modelFields')
                    ->label('Model Field')
                    ->relationship(name: 'modelFields', titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->dataModel->name.'.'.$record->name)
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                TextInput::make('notion_url')
                    ->label('Notion URL')
                    ->url(),
            ]);
    }
}
