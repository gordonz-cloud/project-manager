<?php

namespace App\Filament\Resources\Features\RelationManagers;

use App\Filament\Resources\FlowSteps\Tables\FlowStepsTable;
use App\Models\FlowStep;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
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
                TextInput::make('file')
                    ->label('文件')
                    ->datalist(fn (): array => FlowStep::query()
                        ->where('feature_id', $this->getOwnerRecord()->getKey())
                        ->whereNotNull('file')
                        ->distinct()
                        ->pluck('file')
                        ->all())
                    ->extraInputAttributes(['class' => 'font-mono']),
                TextInput::make('function')
                    ->label('函数')
                    ->extraInputAttributes(['class' => 'font-mono']),
                Textarea::make('input')
                    ->label('参数')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14),
                Textarea::make('change')
                    ->label('做了什么'),
                Textarea::make('output')
                    ->label('返回值')
                    ->placeholder('写成 Log::debug 会打出来的样子：字段名、类型、示例值、可空')
                    ->extraInputAttributes(['class' => 'font-mono'])
                    ->rows(14)
                    ->required(),
                TextInput::make('path')
                    ->label('路径')
                    ->datalist(fn (): array => FlowStep::query()
                        ->where('feature_id', $this->getOwnerRecord()->getKey())
                        ->distinct()
                        ->pluck('path')
                        ->all())
                    ->required(),
                TextInput::make('order')
                    ->label('#')
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
                Group::make('path')->label('路径')->collapsible(),
            ])
            ->columns([
                TextColumn::make('path')
                    ->label('路径'),
                TextColumn::make('order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('step')
                    ->label('步骤'),
                TextColumn::make('file')
                    ->label('文件')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('function')
                    ->label('函数')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('input')
                    ->label('参数')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (?string $state): string => FlowStepsTable::formatShape($state))
                    ->html()
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: pre-wrap']),
                TextColumn::make('change')
                    ->label('做了什么')
                    ->wrap(),
                TextColumn::make('output')
                    ->label('返回值')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (?string $state): string => FlowStepsTable::formatShape($state))
                    ->html()
                    ->wrap()
                    ->extraAttributes(['style' => 'white-space: pre-wrap']),
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
