<?php

namespace App\Filament\Resources\Features\Pages;

use App\Enums\FeatureLayer;
use App\Enums\FeatureTrigger;
use App\Filament\Resources\Features\FeatureResource;
use App\Models\Feature;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class ViewFeature extends ViewRecord
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->slideOver(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')
                            ->label('Feature ID'),
                        TextEntry::make('title')
                            ->label('功能'),
                        TextEntry::make('status')
                            ->label('状态')
                            ->badge(),
                        TextEntry::make('layers')
                            ->label('层')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => FeatureLayer::labelFor($state)),
                        TextEntry::make('triggers')
                            ->label('触发方式')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => FeatureTrigger::labelFor($state)),
                        TextEntry::make('entry')
                            ->label('入口')
                            ->fontFamily(FontFamily::Mono),
                        TextEntry::make('useCase.goal')
                            ->label('Use Case'),
                        TextEntry::make('module')
                            ->label('模块')
                            ->state(fn (Feature $record): array => array_filter([
                                $record->module?->name,
                            ]))
                            ->badge(),
                    ]),
            ]);
    }
}
