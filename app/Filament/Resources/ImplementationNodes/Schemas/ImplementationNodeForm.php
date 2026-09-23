<?php

namespace App\Filament\Resources\ImplementationNodes\Schemas;

use App\Enums\ImplementationNodeKind;
use App\Enums\ImplementationNodeState;
use App\Models\ImplementationNode;
use App\Services\ImplementationNodes\ImplementationNodeSelection;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ImplementationNodeForm
{
    public static function configure(Schema $schema): Schema
    {
        $implementationNodes = resolve(ImplementationNodeSelection::class);

        return $schema
            ->components([
                Select::make('feature_id')
                    ->label('入口')
                    ->relationship('feature', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('parent_id')
                    ->label('父节点')
                    ->relationship(
                        'parent',
                        'title',
                        fn (Builder $query, ?ImplementationNode $record, Get $get): Builder => $implementationNodes
                            ->constrainToAvailableParent($query, $record, (int) $get('feature_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->rule(fn (Get $get, ?ImplementationNode $record): Closure => $implementationNodes->parentRule(
                        $record,
                        (int) $get('feature_id'),
                    )),
                Select::make('kind')
                    ->label('类型')
                    ->options(ImplementationNodeKind::class)
                    ->required(),
                TextInput::make('title')
                    ->label('节点')
                    ->required(),
                Select::make('module_id')
                    ->label('模块')
                    ->relationship('module', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('file')
                    ->label('文件'),
                TextInput::make('function')
                    ->label('函数'),
                Textarea::make('input')
                    ->label('输入'),
                Textarea::make('change')
                    ->label('变化'),
                Textarea::make('output')
                    ->label('输出'),
                Textarea::make('contract')
                    ->label('交付契约')
                    ->dehydrateStateUsing(fn (?string $state): string => $state ?? '')
                    ->columnSpanFull(),
                Select::make('state')
                    ->label('状态')
                    ->options(ImplementationNodeState::class)
                    ->required(),
                Textarea::make('evidence_required')
                    ->label('所需证据')
                    ->dehydrateStateUsing(fn (?string $state): string => $state ?? '')
                    ->columnSpanFull(),
            ]);
    }

    public static function configureForChild(Schema $schema, int $featureId): Schema
    {
        return $schema
            ->components([
                Hidden::make('feature_id')
                    ->default($featureId),
                Select::make('kind')
                    ->label('类型')
                    ->options(ImplementationNodeKind::class)
                    ->required(),
                TextInput::make('title')
                    ->label('节点')
                    ->required(),
                Select::make('module_id')
                    ->label('模块')
                    ->relationship('module', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('file')
                    ->label('文件'),
                TextInput::make('function')
                    ->label('函数'),
                Textarea::make('input')
                    ->label('输入'),
                Textarea::make('change')
                    ->label('变化'),
                Textarea::make('output')
                    ->label('输出'),
                Textarea::make('contract')
                    ->label('交付契约')
                    ->columnSpanFull(),
                Select::make('state')
                    ->label('状态')
                    ->options(ImplementationNodeState::class)
                    ->required(),
                Textarea::make('evidence_required')
                    ->label('所需证据')
                    ->columnSpanFull(),
            ]);
    }
}
