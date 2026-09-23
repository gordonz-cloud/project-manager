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
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $project_id
 * @property int $use_case_id
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
#[Fillable(['use_case_id', 'name', 'type', 'given', 'when', 'then', 'coverage_dimension', 'equivalence_class', 'boundary', 'priority', 'status'])]
class Scenario extends Model
{
    /** @use HasFactory<ScenarioFactory> */
    use BelongsToProject, HasFactory;

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
     * The path this scenario walks through the flow graph, in order.
     *
     * @return HasMany<ScenarioStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ScenarioStep::class)->orderBy('position');
    }

    /**
     * Rewrites the whole path: positions are renumbered from 1 in the given order.
     *
     * @param  list<int>  $requestReplyIds
     */
    public function replaceSteps(array $requestReplyIds): void
    {
        DB::transaction(function () use ($requestReplyIds): void {
            $this->steps()->delete();

            foreach ($requestReplyIds as $index => $requestReplyId) {
                $this->steps()->create(['position' => $index + 1, 'request_reply_id' => $requestReplyId]);
            }
        });
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
