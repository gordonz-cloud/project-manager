<?php

namespace App\Filament\Resources\Features\Schemas;

use App\Enums\FeatureLayer;
use App\Enums\FeatureStatus;
use App\Enums\FeatureTrigger;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FeatureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('功能')
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(FeatureStatus::class)
                    ->required(),
                CheckboxList::make('layers')
                    ->label('层')
                    ->options(FeatureLayer::class),
                CheckboxList::make('triggers')
                    ->label('触发方式')
                    ->options(FeatureTrigger::class),
                TextInput::make('entry')
                    ->label('入口'),
                Select::make('requirement_id')
                    ->label('需求')
                    ->relationship(name: 'requirement', titleAttribute: 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
