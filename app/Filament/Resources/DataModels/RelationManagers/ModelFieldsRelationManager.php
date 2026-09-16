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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                Select::make('requirements')
                    ->label('支持需求')
                    ->relationship('requirements', 'title')
                    ->multiple()
                    ->preload(),
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
                TextColumn::make('name')
                    ->label('字段')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('类型'),
                IconColumn::make('nullable')
                    ->boolean()
                    ->label('可空'),
                TextColumn::make('default_value')
                    ->label('默认值'),
                TextColumn::make('constraint')
                    ->label('约束')
                    ->wrap(),
                TextColumn::make('description')
                    ->label('说明')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                TextColumn::make('requirements.title')
                    ->label('支持需求')
                    ->badge()
                    ->listWithLineBreaks(),
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
