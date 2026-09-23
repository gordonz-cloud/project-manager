<?php

namespace App\Filament\Resources\Requirements\RelationManagers;

use App\Enums\FeatureLayer;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('功能')
                    ->required()
                    ->maxLength(255),
                CheckboxList::make('layers')
                    ->label('层')
                    ->options(FeatureLayer::class),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Feature ID')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('功能')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('layers')
                    ->label('层')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeatureLayer::labelFor($state)),
                TextColumn::make('triggers')
                    ->label('触发方式')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('entry')
                    ->label('功能')
                    ->fontFamily(FontFamily::Mono)
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
