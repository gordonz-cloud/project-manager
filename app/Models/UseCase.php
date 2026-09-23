<?php

namespace App\Models;

use App\Enums\UseCaseStatus;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\UseCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $requirement_id
 * @property int $use_case_group_id
 * @property string $actor
 * @property string $goal
 * @property string|null $trigger
 * @property string|null $precondition
 * @property string $success_outcome
 * @property string|null $failure_outcome
 * @property UseCaseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requirement_id', 'use_case_group_id', 'actor', 'goal', 'trigger', 'precondition', 'success_outcome', 'failure_outcome', 'status'])]
class UseCase extends Model
{
    /** @use HasFactory<UseCaseFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $useCase): void {
            if (blank($useCase->use_case_group_id)) {
                return;
            }

            $group = UseCaseGroup::withoutGlobalScopes()->find($useCase->use_case_group_id);

            if ($group === null) {
                throw new LogicException('A use case group must exist.');
            }

            if (blank($useCase->project_id)) {
                $useCase->project_id = $group->project_id;
            }

            if ((int) $useCase->project_id !== $group->project_id) {
                throw new LogicException('A use case group must belong to the same project.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UseCaseStatus::class,
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
     * @return BelongsTo<UseCaseGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UseCaseGroup::class, 'use_case_group_id');
    }

    /**
     * @return HasOne<UseCaseSpec, $this>
     */
    public function spec(): HasOne
    {
        return $this->hasOne(UseCaseSpec::class);
    }

    /**
     * @return BelongsToMany<Module, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_use_cases');
    }

    /**
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /**
     * @return HasMany<RequestReply, $this>
     */
    public function requestReplies(): HasMany
    {
        return $this->hasMany(RequestReply::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<WorkflowRun, $this>
     */
    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * Modules this use case touches: its request replies' modules.
     *
     * @return Collection<int, Module>
     */
    public function participatingModules(): Collection
    {
        return Module::withoutGlobalScopes()
            ->whereIn('id', RequestReply::withoutGlobalScopes()->where('use_case_id', $this->id)->whereNotNull('module_id')->select('module_id'))
            ->orderBy('name')
            ->get();
    }
}
