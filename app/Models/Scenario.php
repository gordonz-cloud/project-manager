<?php

namespace App\Models;

use App\Enums\ScenarioPriority;
use App\Enums\ScenarioStatus;
use App\Enums\ScenarioType;
use App\Models\Concerns\BelongsToProject;
use Database\Factories\ScenarioFactory;
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
 * @property int $use_case_id
 * @property int|null $end_node_id
 * @property string $name
 * @property ScenarioType $type
 * @property string $given
 * @property string $when
 * @property string $then
 * @property string|null $coverage_dimension
 * @property string|null $equivalence_class
 * @property string|null $boundary
 * @property ScenarioPriority $priority
 * @property ScenarioStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['use_case_id', 'end_node_id', 'name', 'type', 'given', 'when', 'then', 'coverage_dimension', 'equivalence_class', 'boundary', 'priority', 'status'])]
class Scenario extends Model
{
    /** @use HasFactory<ScenarioFactory> */
    use BelongsToProject, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $scenario): void {
            if ($scenario->end_node_id === null) {
                return;
            }

            $endsInOwnUseCase = ImplementationNode::withoutGlobalScopes()
                ->whereKey($scenario->end_node_id)
                ->whereHas('feature', fn ($feature) => $feature->where('use_case_id', $scenario->use_case_id))
                ->exists();

            if (! $endsInOwnUseCase) {
                throw new LogicException('A scenario end node must belong to an entry of the same use case.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ScenarioType::class,
            'priority' => ScenarioPriority::class,
            'status' => ScenarioStatus::class,
        ];
    }

    /**
     * @return BelongsTo<UseCase, $this>
     */
    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    /**
     * @return BelongsTo<ImplementationNode, $this>
     */
    public function endNode(): BelongsTo
    {
        return $this->belongsTo(ImplementationNode::class, 'end_node_id');
    }

    /**
     * @return BelongsToMany<ImplementationNode, $this>
     */
    public function implementationNodes(): BelongsToMany
    {
        return $this->belongsToMany(ImplementationNode::class, 'scenario_implementation_nodes');
    }

    /**
     * @return HasMany<Test, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }
}
