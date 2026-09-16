<?php

namespace App\Filament\Resources\DataModels\RelationManagers;

use App\Enums\DataModelStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ModelFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'modelFields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('字段')
                    ->required()
                    ->maxLength(255),
                TextInput::make('type')
                    ->label('类型'),
                Toggle::make('nullable')
                    ->label('可空'),
                TextInput::make('default_value')
                    ->label('默认值'),
                TextInput::make('constraint')
                    ->label('约束'),
                TextInput::make('description')
                    ->label('说明'),
                Select::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('number')
            ->striped()
            ->columns([
                TextColumn::make('number')
                    ->label('Field ID')
                    ->sortable(),
                TextInputColumn::make('name')
                    ->label('字段')
                    ->searchable(),
                TextInputColumn::make('type')
                    ->label('类型'),
                ToggleColumn::make('nullable')
                    ->label('可空'),
                TextInputColumn::make('default_value')
                    ->label('默认值'),
                TextInputColumn::make('constraint')
                    ->label('约束'),
                TextColumn::make('description')
                    ->label('说明')
                    ->wrap(),
                SelectColumn::make('status')
                    ->label('状态')
                    ->options(DataModelStatus::class),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->recordActions([
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
