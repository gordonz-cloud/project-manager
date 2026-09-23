<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use App\Models\Concerns\BelongsToProject;
use App\Models\Concerns\HasProjectSequence;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property int $number
 * @property FeatureStatus $status
 * @property array<int, string>|null $triggers
 * @property string|null $entry
 * @property int|null $requirement_id
 * @property int|null $use_case_id
 * @property int|null $module_id
 * @property array<int, string>|null $layers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'number', 'status', 'triggers', 'entry', 'requirement_id', 'use_case_id', 'module_id', 'layers'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use BelongsToProject, HasFactory, HasProjectSequence;

    protected static function booted(): void
    {
        static::saving(function (self $feature): void {
            if ($feature->use_case_id === null) {
                return;
            }

            $useCase = UseCase::withoutGlobalScopes()->find($feature->use_case_id);

            if ($useCase === null) {
                throw new LogicException('A feature use case must exist.');
            }

            $projectId = $feature->project_id;

            if (blank($projectId)) {
                $feature->project_id = $useCase->project_id;
            } elseif ((int) $projectId !== $useCase->project_id) {
                throw new LogicException('A feature use case must belong to the same project.');
            }

            $feature->requirement_id = $useCase->requirement_id;

            if ($feature->module_id === null) {
                $moduleIds = $useCase->modules()->pluck('modules.id');

                if ($moduleIds->count() === 1) {
                    $feature->module_id = $moduleIds->first();
                }
            } elseif (! $useCase->modules()->whereKey($feature->module_id)->exists()) {
                throw new LogicException('A feature module must participate in the use case.');
            }
        });

        static::deleting(function (self $feature): void {
            if (WorkflowRun::withoutGlobalScopes()->where('feature_id', $feature->id)->exists()) {
                throw new LogicException('A feature with workflow history cannot be deleted.');
            }

            $nodeIds = $feature->implementationNodes()->pluck('id')->all();

            if ($feature->implementationNodes()->whereHas('nodeRuns')->exists()) {
                throw new LogicException('A feature with run history cannot be deleted.');
            }

        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
            'triggers' => 'array',
            'layers' => 'array',
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
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsToMany<DataModel, $this>
     */
    public function dataModels(): BelongsToMany
    {
        return $this->belongsToMany(DataModel::class);
    }

    /**
     * @return HasMany<FlowStep, $this>
     */
    public function flowSteps(): HasMany
    {
        return $this->hasMany(FlowStep::class);
    }

    /**
     * @return BelongsToMany<Test, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class);
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /**
     * @return HasMany<ImplementationNode, $this>
     */
    public function implementationNodes(): HasMany
    {
        return $this->hasMany(ImplementationNode::class);
    }

    public function hasRunHistory(): bool
    {
        return WorkflowRun::withoutGlobalScopes()->where('feature_id', $this->id)->exists()
            || $this->implementationNodes()->whereHas('nodeRuns')->exists();
    }
}
