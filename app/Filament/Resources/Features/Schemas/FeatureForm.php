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
                Select::make('layer')
                    ->label('层')
                    ->options(FeatureLayer::class),
                TextInput::make('version')
                    ->label('版本'),
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
                TextInput::make('commit_range')
                    ->label('Commit Range'),
                TextInput::make('latest_commit')
                    ->label('Latest Commit'),
            ]);
    }
}
