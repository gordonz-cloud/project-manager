<?php

namespace App\Filament\Resources\Commits\Schemas;

use App\Services\ImplementationNodes\ImplementationNodeSelection;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

class CommitForm
{
    public static function configure(Schema $schema): Schema
    {
        $implementationNodes = resolve(ImplementationNodeSelection::class);

        return $schema
            ->components([
                Select::make('feature_id')
                    ->label('功能')
                    ->relationship(name: 'feature', titleAttribute: 'title')
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('implementation_node_id')
                    ->label('实现节点')
                    ->relationship(
                        name: 'implementationNode',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $implementationNodes
                            ->constrainToFeature($query, $get('feature_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->rule(fn (Get $get): Exists => $implementationNodes->existsInFeatureRule($get('feature_id'))),
            ]);
    }
}
