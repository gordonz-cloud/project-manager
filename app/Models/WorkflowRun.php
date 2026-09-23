<?php

namespace App\Models;

use App\Enums\WorkflowRunStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\WorkflowRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $requirement_id
 * @property int|null $use_case_id
 * @property int|null $feature_id
 * @property string|null $graph_version
 * @property WorkflowRunStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requirement_id', 'use_case_id', 'feature_id', 'graph_version', 'status', 'started_at', 'finished_at'])]
class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $run): void {
            if ($run->use_case_id !== null) {
                $useCase = UseCase::withoutGlobalScopes()->find($run->use_case_id);

                if ($useCase === null) {
                    throw new LogicException('A workflow use case must exist.');
                }

                if (blank($run->project_id)) {
                    $run->project_id = $useCase->project_id;
                } elseif ((int) $run->project_id !== $useCase->project_id) {
                    throw new LogicException('A workflow use case must belong to the same project.');
                }
            }

            if ($run->feature_id !== null) {
                $feature = Feature::withoutGlobalScopes()->find($run->feature_id);

                if ($feature === null) {
                    throw new LogicException('A workflow feature must exist.');
                }

                if (blank($run->use_case_id)) {
                    $run->use_case_id = $feature->use_case_id;
                } elseif ($feature->use_case_id !== $run->use_case_id) {
                    throw new LogicException('A workflow feature must belong to the workflow use case.');
                }

                $projectId = $run->getRawOriginal('project_id');

                if ($projectId === null) {
                    $run->project_id = $feature->project_id;
                } elseif ((int) $projectId !== $feature->project_id) {
                    throw new LogicException('A workflow feature must belong to the same project.');
                }
            }

            if (
                $run->exists
                && $run->isDirty(['requirement_id', 'use_case_id', 'feature_id'])
                && $run->events()->exists()
            ) {
                throw new LogicException('A workflow with run history cannot change its use case, requirement, or feature.');
            }
        });

        static::deleting(function (self $run): void {
            if ($run->events()->exists()) {
                throw new LogicException('Workflow runs with events are append-only.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * @return BelongsTo<UseCase, $this>
     */
    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    /**
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * @return HasMany<RunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RunEvent::class);
    }
}
