<?php

namespace App\Filament\Resources\Features\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class FlowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'flowSteps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('step')
                    ->label('步骤')
                    ->required(),
                TextInput::make('location')
                    ->label('位置'),
                TextInput::make('input')
                    ->label('输入'),
                TextInput::make('change')
                    ->label('变化'),
                TextInput::make('output')
                    ->label('输出'),
                TextInput::make('path')
                    ->label('路径')
                    ->required(),
                TextInput::make('order')
                    ->label('顺序')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('step')
            ->defaultSort('order')
            ->groups([
                Group::make('path')->label('路径'),
            ])
            ->columns([
                TextColumn::make('path')
                    ->label('路径'),
                TextColumn::make('order')
                    ->label('顺序')
                    ->sortable(),
                TextColumn::make('step')
                    ->label('步骤'),
                TextColumn::make('location')
                    ->label('位置'),
                TextColumn::make('input')
                    ->label('输入'),
                TextColumn::make('change')
                    ->label('变化'),
                TextColumn::make('output')
                    ->label('输出'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
