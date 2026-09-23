<?php

namespace App\Services\Requirements;

use App\Models\Requirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class RequirementDependencies
{
    /**
     * @param  Builder<Requirement>  $query
     * @return Builder<Requirement>
     */
    public function constrainCandidates(Builder $query, ?Requirement $record): Builder
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
    public function validate(?Requirement $record, array $dependsOnIds): array
    {
        if ($record === null) {
            return $dependsOnIds;
        }

        foreach ($dependsOnIds as $dependsOnId) {
            $dependsOnId = (int) $dependsOnId;

            if (Requirement::wouldCycle($record->id, $dependsOnId)) {
                $dependsOnTitle = Requirement::find($dependsOnId)?->title;

                throw ValidationException::withMessages([
                    'dependsOn' => "{$dependsOnTitle} 已经（直接或间接）依赖 {$record->title}，不能反过来",
                ]);
            }
        }

        return $dependsOnIds;
    }
}
