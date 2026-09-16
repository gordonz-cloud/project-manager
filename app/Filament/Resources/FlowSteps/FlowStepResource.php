<?php

namespace App\Filament\Resources\FlowSteps;

use App\Filament\Resources\FlowSteps\Pages\CreateFlowStep;
use App\Filament\Resources\FlowSteps\Pages\EditFlowStep;
use App\Filament\Resources\FlowSteps\Pages\ListFlowSteps;
use App\Filament\Resources\FlowSteps\Schemas\FlowStepForm;
use App\Filament\Resources\FlowSteps\Tables\FlowStepsTable;
use App\Models\FlowStep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FlowStepResource extends Resource
{
    protected static ?string $navigationLabel = 'Flow Steps';

    protected static ?int $navigationSort = 6;

    protected static ?string $model = FlowStep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = '数据流';

    public static function form(Schema $schema): Schema
    {
        return FlowStepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlowStepsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlowSteps::route('/'),
            'create' => CreateFlowStep::route('/create'),
            'edit' => EditFlowStep::route('/{record}/edit'),
        ];
    }
}
