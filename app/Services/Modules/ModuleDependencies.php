<?php

namespace App\Services\Modules;

use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ModuleDependencies
{
    /**
     * @param  Builder<Module>  $query
     * @return Builder<Module>
     */
    public function constrainCandidates(Builder $query, ?Module $record): Builder
    {
        if ($record === null) {
            return $query;
        }

        return $query->whereKeyNot($record->id);
    }

    /**
     * @param  array<int, mixed>  $dependsOnIds
     * @return array<int, mixed>
     */
    public function validate(?Module $record, array $dependsOnIds): array
    {
        if ($record === null) {
            return $dependsOnIds;
        }

        foreach ($dependsOnIds as $dependsOnId) {
            $dependsOnId = (int) $dependsOnId;

            if (Module::wouldCycle($record->id, $dependsOnId)) {
                $dependsOnName = Module::find($dependsOnId)?->name;

                throw ValidationException::withMessages([
                    'dependsOn' => "{$dependsOnName} 已经（直接或间接）依赖 {$record->name}，不能反过来",
                ]);
            }
        }

        return $dependsOnIds;
    }
}
