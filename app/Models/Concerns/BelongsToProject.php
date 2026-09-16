<?php

namespace App\Models\Concerns;

use App\Models\Project;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current Filament tenant (Project).
 *
 * Filament's built-in tenancy already scopes queries made through a panel
 * Resource to the current tenant. This trait covers what that doesn't:
 * - a `project()` relation
 * - auto-filling `project_id` from the current tenant on create
 * - a global scope so direct Eloquent queries (outside a Resource) are
 *   still scoped to the current tenant whenever one is set
 */
trait BelongsToProject
{
    public static function bootBelongsToProject(): void
    {
        static::creating(function (self $model): void {
            if (! $model->project_id && Filament::getTenant() instanceof Project) {
                $model->project_id = Filament::getTenant()->getKey();
            }
        });

        static::addGlobalScope('project', function (Builder $query): void {
            if (Filament::getTenant() instanceof Project) {
                $query->where($query->getModel()->getTable().'.project_id', Filament::getTenant()->getKey());
            }
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
