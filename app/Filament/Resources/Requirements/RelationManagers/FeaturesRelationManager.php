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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
                Select::make('layer')
                    ->label('层')
                    ->options(FeatureLayer::class),
                TextInput::make('version')
                    ->label('版本'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->striped()
            ->columns([
                TextColumn::make('title')
                    ->label('功能')
                    ->searchable(),
                TextColumn::make('layer')
                    ->label('层')
                    ->badge(),
                TextColumn::make('version')
                    ->label('版本'),
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
