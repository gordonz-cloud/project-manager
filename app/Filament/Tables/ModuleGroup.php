<?php

namespace App\Filament\Tables;

use App\Models\Requirement;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Grouping by module for anything that reaches modules through a
 * requirement. Filament cannot group on a many-to-many by name — it sorts the
 * query by the grouped relation and refuses BelongsToMany — so the group key
 * is the requirement's first module by name, and the query is ordered by the
 * same subquery so rows arrive already clustered. A row with no requirement
 * lands under its own heading rather than vanishing.
 */
class ModuleGroup
{
    public const string NO_MODULE = '（无模块）';

    /**
     * @param  ?string  $requirementRelation  how a row reaches its requirement; null when the row is one
     */
    public static function make(?string $requirementRelation = null): Group
    {
        $firstModuleName = function (Model $record) use ($requirementRelation): string {
            $requirement = $requirementRelation === null ? $record : $record->getRelationValue($requirementRelation);

            return $requirement instanceof Requirement
                ? $requirement->modules->sortBy('name')->first()->name ?? self::NO_MODULE
                : self::NO_MODULE;
        };

        // Built from literals only, so the query builder can see no user
        // input reaches the raw clause.
        $requirementId = $requirementRelation === null ? 'requirements.id' : 'features.requirement_id';
        $orderSql = '(select min(modules.name) from module_requirement
            join modules on modules.id = module_requirement.module_id
            where module_requirement.requirement_id = '.$requirementId.')';

        return Group::make('module')
            ->label('模块')
            ->getKeyFromRecordUsing($firstModuleName)
            ->getTitleFromRecordUsing($firstModuleName)
            ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                ->orderBy(DB::raw($orderSql), $direction === 'desc' ? 'desc' : 'asc'))
            ->scopeQueryByKeyUsing(fn (Builder $query, string $key): Builder => $key === self::NO_MODULE
                ? $query->whereRaw("{$orderSql} is null")
                : $query->whereRaw("{$orderSql} = ?", [$key]))
            ->collapsible();
    }
}
