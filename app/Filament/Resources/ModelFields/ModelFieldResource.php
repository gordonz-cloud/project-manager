<?php

namespace App\Filament\Resources\ModelFields;

use App\Filament\Resources\ModelFields\Pages\CreateModelField;
use App\Filament\Resources\ModelFields\Pages\EditModelField;
use App\Filament\Resources\ModelFields\Pages\ListModelFields;
use App\Filament\Resources\ModelFields\Schemas\ModelFieldForm;
use App\Filament\Resources\ModelFields\Tables\ModelFieldsTable;
use App\Models\ModelField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModelFieldResource extends Resource
{
    protected static ?string $model = ModelField::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Model Field';

    protected static ?string $modelLabel = 'Model Field';

    public static function form(Schema $schema): Schema
    {
        return ModelFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModelFieldsTable::configure($table);
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
            'index' => ListModelFields::route('/'),
            'create' => CreateModelField::route('/create'),
            'edit' => EditModelField::route('/{record}/edit'),
        ];
    }
}
