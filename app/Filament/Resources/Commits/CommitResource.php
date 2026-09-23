<?php

namespace App\Filament\Resources\Commits;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Commits\Pages\ListCommits;
use App\Filament\Resources\Commits\Schemas\CommitForm;
use App\Filament\Resources\Commits\Tables\CommitsTable;
use App\Models\Commit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommitResource extends Resource
{
    protected static ?string $navigationLabel = 'Commits';

    protected static ?int $navigationSort = 3;

    protected static ?string $model = Commit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    public static function getNavigationGroup(): NavigationGroup
    {
        return NavigationGroup::Evidence;
    }

    protected static ?string $modelLabel = 'Commit';

    protected static ?string $pluralModelLabel = 'Commits';

    public static function form(Schema $schema): Schema
    {
        return CommitForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommitsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommits::route('/'),
        ];
    }
}
