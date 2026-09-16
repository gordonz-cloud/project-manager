<?php

namespace App\Models\Concerns;

/**
 * Auto-assigns an incrementing `number` scoped to the model's project,
 * unless a number is already set (e.g. during import).
 */
trait HasProjectSequence
{
    protected static function bootHasProjectSequence(): void
    {
        static::creating(function ($model) {
            if ($model->number !== null) {
                return;
            }

            $model->number = static::withoutGlobalScopes()
                ->where('project_id', $model->project_id)
                ->max('number') + 1;
        });
    }
}
