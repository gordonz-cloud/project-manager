<?php

namespace App\Filament\Resources\DataModels\Schemas;

use App\Enums\DataModelStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DataModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Model')
                    ->required(),
                TextInput::make('table_name')
                    ->label('表名'),
                Select::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class)
                    ->required(),
                Textarea::make('description')
                    ->label('说明'),
                Textarea::make('business_purpose')
                    ->label('商业目的'),
                Textarea::make('design_gap')
                    ->label('设计差异'),
                Textarea::make('ruling')
                    ->label('拍板'),
                Select::make('modules')
                    ->label('模块')
                    ->multiple()
                    ->relationship(
                        'modules',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('project_id', Filament::getTenant()?->getKey()),
                    )
                    ->preload(),
                Select::make('features')
                    ->label('功能')
                    ->multiple()
                    ->relationship(
                        'features',
                        'title',
                        modifyQueryUsing: fn ($query) => $query->where('project_id', Filament::getTenant()?->getKey()),
                    )
                    ->preload(),
                TextInput::make('notion_url')
                    ->label('Notion')
                    ->url(),
            ]);
    }
}
